<?php

require_once __DIR__ . '/../includes/constants.php';

class Mailer
{

    public function send(string $to, string $subject, string $message, array $headers = []): bool
    {

        $transport = $this->configured_transport();

        if ($transport === 'smtp') {

            return $this->send_via_smtp($to, $subject, $message, $headers);
        }

        if ($transport === 'mail') {

            return $this->send_via_native_mail($to, $subject, $message, $headers);
        }

        return false;

    }

    public function configured_transport(): string
    {

        $smtp_host = env_value('SMTP_HOST', '');
        if ($smtp_host !== '') {

            return 'smtp';
        }

        $sendmail_path = ini_get('sendmail_path') ?: '';
        if ($sendmail_path !== '') {

            $binary = strtok($sendmail_path, ' ');
            if ($binary && is_executable($binary)) {

                return 'mail';
            }
        }

        return 'none';

    }

    public function transport_error(): string
    {

        return match ($this->configured_transport()) {

            'smtp' => 'SMTP delivery failed.',
            'mail' => 'The native mail transport rejected the message.',
            default => 'No mail transport is configured. Install sendmail or configure SMTP_* environment variables.',
        };

    }

    private function send_via_native_mail(string $to, string $subject, string $message, array $headers): bool
    {

        return mail($to, $subject, $message, $this->format_headers($headers));

    }

    private function send_via_smtp(string $to, string $subject, string $message, array $headers): bool
    {

        $host = env_value('SMTP_HOST');
        $port = (int) env_value('SMTP_PORT', 587);
        $username = string_value(env_value('SMTP_USERNAME', ''));
        $password = string_value(env_value('SMTP_PASSWORD', ''));
        $encryption = strtolower(string_value(env_value('SMTP_ENCRYPTION', 'tls'), 'tls'));
        $timeout = 15;
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!$socket) {

            return false;
        }

        stream_set_timeout($socket, $timeout);

        if (!$this->smtp_expect($socket, [220])) {

            fclose($socket);
            return false;
        }

        $hostname = $_SERVER['SERVER_NAME'] ?? 'localhost';
        if (!$this->smtp_command($socket, "EHLO {$hostname}", [250])) {

            fclose($socket);
            return false;
        }

        if ($encryption === 'tls') {

            if (!$this->smtp_command($socket, 'STARTTLS', [220])) {

                fclose($socket);
                return false;
            }

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {

                fclose($socket);
                return false;
            }

            if (!$this->smtp_command($socket, "EHLO {$hostname}", [250])) {

                fclose($socket);
                return false;
            }
        }

        if ($username !== '') {

            if (
                !$this->smtp_command($socket, 'AUTH LOGIN', [334]) ||
                !$this->smtp_command($socket, base64_encode($username), [334]) ||
                !$this->smtp_command($socket, base64_encode($password), [235])
            ) {

                fclose($socket);
                return false;
            }
        }

        $from = $headers['From'] ?? env_value('MAIL_FROM_ADDRESS', 'no-reply@my-bills.local');

        if (
            !$this->smtp_command($socket, "MAIL FROM:<{$from}>", [250]) ||
            !$this->smtp_command($socket, "RCPT TO:<{$to}>", [250, 251]) ||
            !$this->smtp_command($socket, 'DATA', [354])
        ) {

            fclose($socket);
            return false;
        }

        $payload_headers = array_merge([
            'From' => $from,
            'To' => $to,
            'Subject' => $subject,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
        ], $headers);

        $payload = $this->format_headers($payload_headers) . "\r\n\r\n" . str_replace(["\r\n.\r\n", "\n.\n"], ["\r\n..\r\n", "\n..\n"], $message);

        fwrite($socket, $payload . "\r\n.\r\n");

        if (!$this->smtp_expect($socket, [250])) {

            fclose($socket);
            return false;
        }

        $this->smtp_command($socket, 'QUIT', [221]);
        fclose($socket);

        return true;
    }

    private function smtp_command($socket, string $command, array $expected_codes): bool
    {

        fwrite($socket, $command . "\r\n");
        return $this->smtp_expect($socket, $expected_codes);

    }

    private function smtp_expect($socket, array $expected_codes): bool
    {

        while (($line = fgets($socket, 515)) !== false) {

            $code = (int) substr($line, 0, 3);
            $separator = substr($line, 3, 1);

            if ($separator === ' ') {

                return in_array($code, $expected_codes, true);
            }
        }

        return false;

    }

    private function format_headers(array $headers): string
    {

        $lines = [];

        foreach ($headers as $name => $value) {

            $lines[] = "{$name}: {$value}";

        }

        return implode("\r\n", $lines);
    }
}
