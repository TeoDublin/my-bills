<?php

require_once __DIR__ . '/../objects/ProgressBar.php';

class Async
{

    private ?string $current_job_key = null;
    private $db = null;

    public function start_job(int $user_id, string $handler, array $payload, ?string $title = null): array
    {

        $definition = async_handler_definition($handler);

        $job_key = bin2hex(random_bytes(16));
        $default_title = string_value($definition['title'] ?? '', $handler);
        $title = trim(string_value($title, $default_title));
        $payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $normalized_payload_json = $payload_json === false ? '{}' : $payload_json;

        $this->db()->query("
            INSERT INTO async_jobs (
                job_key,
                user_id,
                handler,
                title,
                status,
                payload_json,
                progress_bars_json,
                warnings_json
            ) VALUES (
                " . $this->quote($job_key) . ",
                " . (int) $user_id . ",
                " . $this->quote($handler) . ",
                " . $this->quote($title) . ",
                'queued',
                " . $this->quote($normalized_payload_json) . ",
                '[]',
                '[]'
            )
        ");

        $pid = $this->spawn_worker($job_key);
        $this->update_job_fields($job_key, [
            'pid' => $pid,
        ]);

        return $this->get_job($job_key, $user_id);

    }

    public function get_job(string $job_key, int $user_id): array
    {

        $job = $this->find_job($job_key);

        if ($job === null) {

            throw new RuntimeException('Async job not found.');
        }

        $job_user_id = (int) ($job['user_id'] ?? 0);

        if ($job_user_id !== $user_id) {

            throw new RuntimeException('Async job not found.');
        }

        $job = $this->cleanup_stale_job($job);

        return $this->normalize_job($job);

    }

    public function run_job(string $job_key): void
    {

        $job = $this->find_job($job_key);

        if ($job === null) {

            throw new RuntimeException('Async job not found.');
        }

        $handler = string_value($job['handler'] ?? '');

        $this->current_job_key = $job_key;
        $this->update_job_fields($job_key, [
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        try {

            $definition = async_handler_definition($handler);
            $runner = $definition['run'];

            $payload = $this->decode_json(string_value($job['payload_json'] ?? '[]'));
            $current_job = $this->find_job($job_key) ?? $job;
            $normalized_job = $this->normalize_job($current_job);

            $runner($payload, $normalized_job);

            $job = $this->find_job($job_key);

            if ($job === null) {

                throw new RuntimeException('Async job not found after execution.');
            }

            if (($job['status'] ?? '') === 'failed' || ($job['status'] ?? '') === 'completed') {

                return;
            }

            $download_path = string_value($job['download_path'] ?? '');

            $this->update_job_fields($job_key, [
                'status' => 'completed',
                'finished_at' => date('Y-m-d H:i:s'),
                'error_message' => null,
            ]);

            if ($download_path !== '' && !is_file($download_path)) {

                $this->fail_job($job_key, 'Export file was not generated.');
            }
        }
        catch (Throwable $throwable) {

            $this->fail_job($job_key, $throwable->getMessage());
        } finally {

            $this->current_job_key = null;
        }

    }

    public function progressbar(string $title, int $total): ProgressBar
    {

        if ($this->current_job_key === null) {

            throw new RuntimeException('No async job is active.');
        }

        $job = $this->find_job($this->current_job_key);

        if ($job === null) {

            throw new RuntimeException('Async job not found.');
        }

        $progress_bars = $this->decode_json(string_value($job['progress_bars_json'] ?? '[]'));
        $progress_bar_key = 'progress_bar_' . (count($progress_bars) + 1);
        $progress_bars[] = [
            'key' => $progress_bar_key,
            'title' => trim($title) !== '' ? $title : strtoupper(string_value($job['handler'] ?? 'EXPORT')),
            'total' => max(0, $total),
            'current' => 0,
            'percent' => $total > 0 ? 0 : 100,
            'warnings' => [],
            'status' => $total > 0 ? 'running' : 'completed',
        ];

        $this->update_job_fields($this->current_job_key, [
            'progress_bars_json' => json_encode($progress_bars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return new ProgressBar($this, $this->current_job_key, $progress_bar_key);

    }

    public function refresh_progress_bar(string $job_key, string $progress_bar_key, int $current): void
    {

        $job = $this->find_job_or_fail($job_key);
        $progress_bars = $this->decode_json(string_value($job['progress_bars_json'] ?? '[]'));

        foreach ($progress_bars as $index => $progress_bar) {

            if (($progress_bar['key'] ?? '') !== $progress_bar_key) {

                continue;
            }

            $progress_bar_total = (int) ($progress_bar['total'] ?? 0);
            $total = max(0, $progress_bar_total);
            $current = max(0, $current);

            $progress_bars[$index]['current'] = $current;
            $progress_bars[$index]['percent'] = $total > 0
                ? max(0, min(100, (int) floor(($current / $total) * 100)))
                : 100;
            $progress_bars[$index]['status'] = $total === 0 || $current >= $total ? 'completed' : 'running';
            break;
        }

        $this->update_job_fields($job_key, [
            'progress_bars_json' => json_encode($progress_bars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

    }

    public function append_progress_bar_warning(string $job_key, string $progress_bar_key, string $message): void
    {

        $job = $this->find_job_or_fail($job_key);
        $progress_bars = $this->decode_json(string_value($job['progress_bars_json'] ?? '[]'));
        $job_warnings = $this->decode_json(string_value($job['warnings_json'] ?? '[]'));
        $trimmed_message = trim($message);

        if ($trimmed_message === '') {

            return;
        }

        foreach ($progress_bars as $index => $progress_bar) {

            if (($progress_bar['key'] ?? '') !== $progress_bar_key) {

                continue;
            }

            $warnings = is_array($progress_bar['warnings'] ?? null) ? $progress_bar['warnings'] : [];
            $warnings[] = $trimmed_message;
            $progress_bars[$index]['warnings'] = $warnings;
            break;
        }

        $job_warnings[] = $trimmed_message;

        $this->update_job_fields($job_key, [
            'progress_bars_json' => json_encode($progress_bars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'warnings_json' => json_encode($job_warnings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

    }

    public function reserve_download(string $job_key, string $filename, string $filetype, ?string $progress_bar_key = null): string
    {

        $allowed_types = ['xlsx', 'txt', 'csv'];
        $normalized_type = strtolower(trim($filetype));

        if (!in_array($normalized_type, $allowed_types, true)) {

            throw new InvalidArgumentException('Unsupported download type.');
        }

        $download_name = trim($filename) !== '' ? trim($filename) : ('download-' . $job_key);
        $download_name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $download_name) ?? ('download-' . $job_key);
        $download_name = trim($download_name, '-.');
        $download_name = $download_name !== '' ? $download_name : ('download-' . $job_key);

        if (substr(strtolower($download_name), -1 * (strlen($normalized_type) + 1)) !== '.' . $normalized_type) {

            $download_name .= '.' . $normalized_type;
        }

        $output_dir = root('storage/async/files/' . date('Y-m-d'));
        $this->ensure_directory_is_writable($output_dir);

        $output_path = $output_dir . '/' . $job_key . '-' . $download_name;

        $fields = [
            'download_name' => $download_name,
            'download_type' => $normalized_type,
            'download_path' => $output_path,
        ];

        if ($progress_bar_key !== null) {

            $job = $this->find_job_or_fail($job_key);
            $progress_bars = $this->decode_json(string_value($job['progress_bars_json'] ?? '[]'));

            foreach ($progress_bars as $index => $progress_bar) {

                if (($progress_bar['key'] ?? '') !== $progress_bar_key) {

                    continue;
                }

                $progress_bars[$index]['status'] = 'running';
                break;
            }

            $fields['progress_bars_json'] = json_encode($progress_bars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->update_job_fields($job_key, $fields);

        return $output_path;

    }

    public function fail_job(string $job_key, string $message, ?string $progress_bar_key = null): void
    {

        $fields = [
            'status' => 'failed',
            'error_message' => trim($message) !== '' ? trim($message) : 'Async job failed.',
            'finished_at' => date('Y-m-d H:i:s'),
        ];

        if ($progress_bar_key !== null) {

            $job = $this->find_job_or_fail($job_key);
            $progress_bars = $this->decode_json(string_value($job['progress_bars_json'] ?? '[]'));

            foreach ($progress_bars as $index => $progress_bar) {

                if (($progress_bar['key'] ?? '') !== $progress_bar_key) {

                    continue;
                }

                $progress_bars[$index]['status'] = 'failed';
                break;
            }

            $fields['progress_bars_json'] = json_encode($progress_bars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->update_job_fields($job_key, $fields);

    }

    private function spawn_worker(string $job_key): int
    {

        $php_binary = $this->resolve_php_cli_binary();
        $worker_path = root('fragments/async/worker.php');
        $command = escapeshellarg($php_binary) . ' ' . escapeshellarg($worker_path) . ' ' . escapeshellarg($job_key) . ' > /dev/null 2>&1 & echo $!';

        $output = [];
        $exit_code = 0;
        exec($command, $output, $exit_code);

        if ($exit_code !== 0 || !isset($output[0]) || !ctype_digit(trim((string) $output[0]))) {

            throw new RuntimeException('Unable to start async worker.');
        }

        return (int) trim((string) $output[0]);

    }

    private function cleanup_stale_job(array $job): array
    {

        $status = string_value($job['status'] ?? '');

        if ($status !== 'running' && $status !== 'queued') {

            return $job;
        }

        $pid = (int) ($job['pid'] ?? 0);
        $job_key = string_value($job['job_key'] ?? '');

        if ($pid > 0 && $this->is_process_running($pid)) {

            return $job;
        }

        $this->update_job_fields($job_key, [
            'status' => 'failed',
            'error_message' => string_value(
                $job['error_message'] ?? '',
                $status === 'queued'
                    ? 'Async worker stopped before starting.'
                    : 'Async worker stopped unexpectedly.'
            ),
            'finished_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->find_job_or_fail($job_key);

    }

    private function is_process_running(int $pid): bool
    {

        if ($pid <= 0) {

            return false;
        }

        if (function_exists('posix_kill')) {

            return @posix_kill($pid, 0);
        }

        return is_dir('/proc/' . $pid);

    }

    private function resolve_php_cli_binary(): string
    {

        $candidates = [];

        if (defined('PHP_BINDIR') && is_string(PHP_BINDIR) && PHP_BINDIR !== '') {

            $candidates[] = rtrim(PHP_BINDIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php';
        }

        if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {

            $binary_name = strtolower(basename(PHP_BINARY));

            if (strpos($binary_name, 'fpm') === false && strpos($binary_name, 'apache') === false && strpos($binary_name, 'cgi') === false) {

                array_unshift($candidates, PHP_BINARY);
            }
        }

        $candidates[] = '/usr/bin/php';
        $candidates[] = 'php';

        foreach (array_values(array_unique($candidates)) as $candidate) {

            if ($candidate === 'php') {

                return $candidate;
            }

            if (is_file($candidate) && is_executable($candidate)) {

                return $candidate;
            }
        }

        return 'php';

    }

    private function normalize_job(array $job): array
    {

        $progress_bars = $this->decode_json(string_value($job['progress_bars_json'] ?? '[]'));
        $warnings = $this->decode_json(string_value($job['warnings_json'] ?? '[]'));
        $download_path = string_value($job['download_path'] ?? '');
        $download_url = null;

        if (($job['status'] ?? '') === 'completed' && $download_path !== '' && is_file($download_path)) {

            $download_url = url('fragments/async/download.php?job_key=' . urlencode(string_value($job['job_key'] ?? '')));
        }

        return [
            'job_key' => string_value($job['job_key'] ?? ''),
            'user_id' => (int) ($job['user_id'] ?? 0),
            'handler' => string_value($job['handler'] ?? ''),
            'title' => string_value($job['title'] ?? ''),
            'status' => string_value($job['status'] ?? 'queued'),
            'pid' => (int) ($job['pid'] ?? 0),
            'error_message' => string_value($job['error_message'] ?? ''),
            'progress_bars' => $progress_bars,
            'warnings' => $warnings,
            'download_name' => string_value($job['download_name'] ?? ''),
            'download_type' => string_value($job['download_type'] ?? ''),
            'download_path' => $download_path,
            'download_url' => $download_url,
            'created_at' => string_value($job['created_at'] ?? ''),
            'updated_at' => string_value($job['updated_at'] ?? ''),
            'started_at' => string_value($job['started_at'] ?? ''),
            'finished_at' => string_value($job['finished_at'] ?? ''),
        ];

    }

    private function ensure_directory_is_writable(string $path): void
    {

        if (!is_dir($path)) {

            mkdir($path, 0777, true);
        }

        @chmod($path, 0777);

    }

    private function find_job_or_fail(string $job_key): array
    {

        $job = $this->find_job($job_key);

        if ($job === null) {

            throw new RuntimeException('Async job not found.');
        }

        return $job;

    }

    private function find_job(string $job_key): ?array
    {

        $rows = $this->db()->select("
            SELECT *
            FROM async_jobs
            WHERE job_key = " . $this->quote($job_key) . "
            LIMIT 1
        ");

        return $rows[0] ?? null;

    }

    private function update_job_fields(string $job_key, array $fields): void
    {

        if ($job_key === '' || $fields === []) {

            return;
        }

        $assignments = [];

        foreach ($fields as $field => $value) {

            $assignments[] = '`' . $field . '` = ' . $this->value_to_sql($value);
        }

        $this->db()->query("
            UPDATE async_jobs
            SET " . implode(', ', $assignments) . "
            WHERE job_key = " . $this->quote($job_key) . "
            LIMIT 1
        ");

    }

    private function value_to_sql($value): string
    {

        if ($value === null) {

            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {

            return (string) $value;
        }

        return $this->quote(string_value($value));

    }

    private function quote(string $value): string
    {

        return "'" . $this->db()->escape($value) . "'";

    }

    private function decode_json(string $json): array
    {

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];

    }

    private function db(): Sql
    {

        if ($this->db === null) {

            $this->db = Sql();
        }

        return $this->db;

    }

}
