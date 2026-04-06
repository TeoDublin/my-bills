<?php

require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Preference.php';

class Auth
{

    private ?array $currentUser = null;

    public function attempt(string $username, string $password): bool
    {

        $sql = SQL();
        $username = trim($username);

        if ($username === '' || $password === '') {

            return false;
        }

        $username_escaped = $sql->escape($username);
        $rows = $sql->select("
            SELECT id, username, password_hash, full_name, email
            FROM users
            WHERE username = '{$username_escaped}'

            LIMIT 1
        ");

        if (!$rows) {

            return false;
        }

        $user = $rows[0];

        if (!password_verify($password, $user['password_hash'])) {

            return false;
        }

        $token = bin2hex(random_bytes(32));
        $expires_at = $this->next_token_expiry();
        $expires_at_escaped = $sql->escape($expires_at->format('Y-m-d H:i:s'));
        $token_escaped = $sql->escape($token);
        $user_id = (int) $user['id'];

        $sql->query("
            UPDATE users
            SET token = '{$token_escaped}',
                token_expires_at = '{$expires_at_escaped}'
            WHERE id = {$user_id}
        ");

        $session = Session();
        $session->regenerate();
        $session->put('auth', [
            'user_id' => $user_id,
            'token' => $token,
        ]);
        $session->persist_until($expires_at);

        $preferences = Preference()->all_for_user($user_id);
        $theme = Preference()->theme_for_user($user_id, 'light');

        if (!headers_sent()) {

            setcookie('preferred_theme', $theme, [
                'expires' => $expires_at->getTimestamp(),
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        $this->currentUser = [
            'id' => $user_id,
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'token' => $token,
            'token_expires_at' => $expires_at->format('Y-m-d H:i:s'),
            'preferences' => $preferences,
            'theme' => $theme,
        ];

        return true;
    }

    public function user(): ?array
    {

        if ($this->currentUser !== null) {

            return $this->currentUser;
        }

        $session = Session();
        $auth = $session->get('auth');

        if (!is_array($auth) || empty($auth['user_id']) || empty($auth['token'])) {

            return null;
        }

        $sql = SQL();
        $user_id = (int) $auth['user_id'];
        $token_escaped = $sql->escape(string_value($auth['token'] ?? ''));

        $rows = $sql->select("
            SELECT id, username, full_name, email, token, token_expires_at
            FROM users
            WHERE 
                id = {$user_id}
                AND token = '{$token_escaped}'
            LIMIT 1
        ");

        if (!$rows) {

            $this->logout(false);
            return null;
        }

        $user = $rows[0];
        $expires_at = $user['token_expires_at'];

        if (empty($expires_at) || strtotime($expires_at) <= time()) {

            $this->logout(false);
            return null;
        }

        $user_id = (int) $user['id'];
        $fallback_theme = 'light';
        $preferences = Preference()->all_for_user($user_id);
        $theme = Preference()->theme_for_user($user_id, $fallback_theme);

        $this->currentUser = [
            'id' => $user_id,
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'token' => $user['token'],
            'token_expires_at' => $expires_at,
            'preferences' => $preferences,
            'theme' => $theme,
        ];

        return $this->currentUser;
    }

    public function check(): bool
    {

        return $this->user() !== null;

    }

    public function require_auth(): void
    {

        if (!$this->check()) {

            if (($this->is_ajax_request())) {

                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }

            header('Location: ' . url('index.php'));
            exit;
        }

    }

    public function logout(bool $revoke_token = true): void
    {

        $session = Session();
        $auth = $session->get('auth');

        if ($revoke_token && is_array($auth) && !empty($auth['user_id'])) {

            $user_id = (int) $auth['user_id'];
            SQL()->query("UPDATE users SET token = NULL, token_expires_at = NULL WHERE id = {$user_id}");

        }

        $session->destroy();
        $this->currentUser = null;
    }

    public function send_password_reset(string $username, string $email, string $reset_url): array
    {

        $sql = SQL();
        $username_escaped = $sql->escape(trim($username));
        $email_escaped = $sql->escape(trim($email));

        $rows = $sql->select("
            SELECT id, username, email, full_name
            FROM users
            WHERE username = '{$username_escaped}'

              AND email = '{$email_escaped}'
            LIMIT 1
        ");

        if (!$rows) {

            return [
                'ok' => false,
                'reason' => 'identity_mismatch',
            ];
        }

        $user = $rows[0];
        $user_id = (int) $user['id'];
        $raw_token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $raw_token);
        $expires_at = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('+1 hour');
        $token_hash_escaped = $sql->escape($token_hash);
        $expires_at_escaped = $sql->escape($expires_at->format('Y-m-d H:i:s'));

        $sql->query("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = {$user_id} AND used_at IS NULL");
        $sql->query("
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
            VALUES ({$user_id}, '{$token_hash_escaped}', '{$expires_at_escaped}', NOW())
        ");

        $full_reset_url = $reset_url . $raw_token;
        $subject = 'CRM-Core password reset';
        $message = '<html><body>';
        $message .= '<p>Hello ' . htmlspecialchars(string_value($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') . ',</p>';
        $message .= '<p>Use the link below to set a new password:</p>';
        $message .= '<p><a href="' . htmlspecialchars($full_reset_url, ENT_QUOTES, 'UTF-8') . '">Reset your password</a></p>';
        $message .= '<p>If the button does not open, use this URL:<br>' . htmlspecialchars($full_reset_url, ENT_QUOTES, 'UTF-8') . '</p>';
        $message .= '<p>This link expires on ' . htmlspecialchars($expires_at->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') . '.</p>';
        $message .= '</body></html>';
        $from_address = string_value(env_value('MAIL_FROM_ADDRESS', 'no-reply@my-bills.local'), 'no-reply@my-bills.local');
        $from_name = string_value(env_value('MAIL_FROM_NAME', PROJECT_TITLE), PROJECT_TITLE);

        $this->log_password_reset_link($user['email'], $full_reset_url);
        $sent = Mailer()->send($user['email'], $subject, $message, [
            'From' => $from_address,
            'Reply-To' => $from_address,
            'X-Mailer-Name' => $from_name,
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);

        if (!$sent) {

            return [
                'ok' => false,
                'reason' => 'transport_error',
                'detail' => Mailer()->transport_error(),
            ];
        }

        return [
            'ok' => true,
        ];
    }

    public function reset_password(string $raw_token, string $new_password): bool
    {

        $sql = SQL();
        $token_hash = $sql->escape(hash('sha256', trim($raw_token)));
        $rows = $sql->select("
            SELECT prt.id, prt.user_id
            FROM password_reset_tokens prt
            WHERE prt.token_hash = '{$token_hash}'

              AND prt.used_at IS NULL
              AND prt.expires_at > NOW()
            LIMIT 1
        ");

        if (!$rows) {

            return false;
        }

        $reset_row = $rows[0];
        $user_id = (int) $reset_row['user_id'];
        $password_hash = $sql->escape(password_hash($new_password, PASSWORD_DEFAULT));
        $token_id = (int) $reset_row['id'];

        $sql->query("
            UPDATE users
            SET password_hash = '{$password_hash}',
                token = NULL,
                token_expires_at = NULL
            WHERE id = {$user_id}
        ");

        $sql->query("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = {$token_id}");

        $current_user = $this->user();
        $current_user_id = (int) ($current_user['id'] ?? 0);

        if ($this->check() && $current_user_id === $user_id) {

            $this->logout(false);
        }

        return true;
    }

    public function theme_for_reset_token(?string $raw_token): string
    {

        if (!$raw_token) {

            return $this->guest_theme();
        }

        $sql = SQL();
        $token_hash = $sql->escape(hash('sha256', trim($raw_token)));
        $rows = $sql->select("
            SELECT prt.user_id
            FROM password_reset_tokens prt
            WHERE prt.token_hash = '{$token_hash}'

              AND prt.used_at IS NULL
              AND prt.expires_at > NOW()
            LIMIT 1
        ");

        if (!$rows) {

            return $this->guest_theme();
        }

        $user_id = (int) $rows[0]['user_id'];
        $fallback_theme = $this->guest_theme();

        return Preference()->theme_for_user($user_id, $fallback_theme);
    }

    public function guest_theme(?string $username = null): string
    {

        if ($username) {

            $sql = SQL();
            $username_escaped = $sql->escape(trim($username));
            $rows = $sql->select("SELECT id FROM users WHERE username = '{$username_escaped}' LIMIT 1");

            if ($rows) {

                $user_id = (int) $rows[0]['id'];
                $fallback_theme = 'light';

                return Preference()->theme_for_user($user_id, $fallback_theme);
            }

        }

        $cookie_theme = $_COOKIE['preferred_theme'] ?? 'light';
        return in_array($cookie_theme, ['light', 'dark'], true) ? $cookie_theme : 'light';
    }

    public function next_token_expiry(): DateTimeImmutable
    {

        $timezone = new DateTimeZone(APP_TIMEZONE);
        $now = new DateTimeImmutable('now', $timezone);
        $expiry = $now->setTime(6, 0);

        if ($now >= $expiry) {

            $expiry = $expiry->modify('+1 day');
        }

        return $expiry;

    }

    private function log_password_reset_link(string $email, string $link): void
    {

        $storage_path = BASE_PATH . '/storage';

        if (!is_dir($storage_path)) {

            mkdir($storage_path, 0775, true);
        }

        $line = '[' . date('Y-m-d H:i:s') . "] {$email} {$link}\n";

        file_put_contents($storage_path . '/password-reset-links.log', $line, FILE_APPEND);
    }

    private function is_ajax_request(): bool
    {

        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

    }

}
