<?php

require_once __DIR__ . '/../includes/constants.php';

class Session
{

    private static ?Session $instance = null;
    private static ?string $session_base_path = null;

    private function __construct()
    {

    }

    public static function get_instance(): Session
    {

        if (session_status() === PHP_SESSION_NONE) {

            self::start_session();
        }

        if (self::$instance === null) {

            self::$instance = new Session();
        }

        return self::$instance;

    }

    private static function start_session(): void
    {

        self::$session_base_path = BASE_PATH . '/storage/sessions';

        self::ensure_directory_writable(self::$session_base_path);

        $handler = new class implements SessionHandlerInterface {

            public function open(string $path, string $name): bool
            {

                return true;

            }

            public function close(): bool
            {

                return true;

            }

            public function read(string $id): string
            {

                $path = Session::resolve_session_file_path($id, true);

                if ($path === null || !is_file($path) || !is_readable($path)) {

                    return '';
                }

                $contents = file_get_contents($path);
                return is_string($contents) ? $contents : '';

            }

            public function write(string $id, string $data): bool
            {

                $path = Session::resolve_session_file_path($id, false);

                if ($path === null) {

                    return false;
                }

                $directory = dirname($path);
                if (!Session::ensure_directory_writable($directory)) {

                    return false;
                }

                $written = file_put_contents($path, $data, LOCK_EX) !== false;

                if (!$written) {

                    return false;
                }

                $existing_path = Session::find_existing_session_file_path($id);

                if ($existing_path !== null && $existing_path !== $path && is_file($existing_path)) {

                    @unlink($existing_path);
                }

                return true;

            }

            public function destroy(string $id): bool
            {

                $path = Session::resolve_session_file_path($id, true);

                if ($path !== null && is_file($path)) {

                    @unlink($path);
                }

                return true;

            }

            public function gc(int $max_lifetime): int|false
            {

                $base_path = Session::session_base_path();
                $files = glob($base_path . '/sess_*');
                $dated_files = glob($base_path . '/*/sess_*');
                $dated_owner_files = glob($base_path . '/*/*/sess_*');
                $files = array_merge($files ?: [], $dated_files ?: [], $dated_owner_files ?: []);
                $deleted = 0;
                $threshold = time() - $max_lifetime;

                foreach ($files as $file) {

                    if (!is_file($file)) {

                        continue;
                    }

                    if (filemtime($file) !== false && filemtime($file) < $threshold) {

                        if (@unlink($file)) {

                            $deleted++;
                        }
                    }
                }

                return $deleted;

            }

        };

        session_set_save_handler($handler, true);

        if (!headers_sent()) {

            session_name(PROJECT_NAME . '_session');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        session_start();

    }

    public static function session_base_path(): string
    {

        return self::$session_base_path ?? (BASE_PATH . '/storage/sessions');

    }

    public static function resolve_session_file_path(string $id, bool $prefer_existing): ?string
    {

        $base_path = self::session_base_path();
        $file_name = 'sess_' . $id;

        if ($prefer_existing) {

            $existing_path = self::find_existing_session_file_path($id);

            if ($existing_path !== null) {

                return $existing_path;
            }
        }

        return $base_path . '/' . date('Y-m-d') . '/' . self::current_session_owner_folder() . '/' . $file_name;

    }

    public static function find_existing_session_file_path(string $id): ?string
    {

        $base_path = self::session_base_path();
        $file_name = 'sess_' . $id;
        $legacy_path = $base_path . '/' . $file_name;

        if (is_file($legacy_path)) {

            return $legacy_path;
        }

        $matches = array_merge(
            glob($base_path . '/*/' . $file_name) ?: [],
            glob($base_path . '/*/*/' . $file_name) ?: []
        );

        if ($matches === []) {

            return null;
        }

        return $matches[0];

    }

    public static function ensure_directory_writable(string $path): bool
    {

        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {

            return false;
        }

        @chmod($path, 0777);

        return is_dir($path) && is_writable($path);

    }

    private static function current_session_owner_folder(): string
    {

        $user_id = (int) ($_SESSION['auth']['user_id'] ?? 0);

        if ($user_id > 0) {

            return strval($user_id);
        }

        return 'guest';

    }

    public function regenerate(): void
    {

        session_regenerate_id(true);

    }

    public function put(string $key, mixed $value): void
    {

        $_SESSION[$key] = $value;

    }

    public function get(string $key, mixed $default = null): mixed
    {

        return $_SESSION[$key] ?? $default;

    }

    public function has(string $key): bool
    {

        return array_key_exists($key, $_SESSION);

    }

    public function remove(string $key): void
    {

        unset($_SESSION[$key]);

    }

    public function flash(string $key, mixed $value): void
    {

        $_SESSION['_flash'][$key] = $value;

    }

    public function pull_flash(string $key, mixed $default = null): mixed
    {

        if (!isset($_SESSION['_flash'][$key])) {

            return $default;
        }

        $value = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);

        if (empty($_SESSION['_flash'])) {

            unset($_SESSION['_flash']);
        }

        return $value;

    }

    public function persist_until(DateTimeInterface $expires_at): void
    {

        if (headers_sent()) {

            return;
        }

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            session_id(),
            [
                'expires' => $expires_at->getTimestamp(),
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => $params['secure'] ?? false,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );

    }

    public function destroy(): void
    {

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE && ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => $params['secure'] ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {

            session_unset();
            session_destroy();
        }

        self::$instance = null;

    }

}
