<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth()->require_auth();

$job_key = trim(get_string('job_key'));

if ($job_key === '') {

    http_response_code(404);
    exit;
}

try {

    $user = Auth()->user();
    $user_id = (int) ($user['id'] ?? 0);

    $job = Async()->get_job($job_key, $user_id);
}
catch (Throwable) {

    http_response_code(404);
    exit;
}

$download_path = string_value($job['download_path'] ?? '');

if (($job['status'] ?? '') !== 'completed' || $download_path === '' || !is_file($download_path)) {

    http_response_code(404);
    exit;
}

$download_name = string_value($job['download_name'] ?? ('download-' . $job_key . '.bin'));
$download_type = strtolower(string_value($job['download_type'] ?? 'bin'));
$content_type = match ($download_type) {
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'csv' => 'text/csv; charset=UTF-8',
    'txt' => 'text/plain; charset=UTF-8',
    default => 'application/octet-stream',
};

header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . addslashes($download_name) . '"');
header('Content-Length: ' . filesize($download_path));
readfile($download_path);
exit;
