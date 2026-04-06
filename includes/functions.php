<?php 

    require_once __DIR__ . '/constants.php';
    require_once __DIR__ . '/env.php';
    require_once __DIR__ . '/class.php';
    load_env_file(root('.env'));
    date_default_timezone_set(APP_TIMEZONE);

    function root(string $path = ''): string {

        return BASE_PATH . '/' . ltrim($path, '/');

    }

    function url(string $path = ''): string {

        return BASE_URL . '/' . ltrim($path, '/');

    }

    function string_value(mixed $value, string $default = ''): string {

        if (is_string($value)) {

            return $value;
        }

        if ($value === null) {

            return $default;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {

            return strval($value);
        }

        if (is_object($value) && method_exists($value, '__toString')) {

            return strval($value);
        }

        return $default;

    }

    function array_string(array $source, string|int $key, string $default = ''): string {

        return string_value($source[$key] ?? null, $default);

    }

    function post_string(string $key, string $default = ''): string {

        return array_string($_POST, $key, $default);

    }

    function get_string(string $key, string $default = ''): string {

        return array_string($_GET, $key, $default);

    }

    function app_url(string $path = ''): string {

        $path = ltrim($path, '/');
        $configured_base = rtrim(string_value(env_value('APP_URL', '')), '/');

        if ($configured_base !== '') {

            return $configured_base . '/' . $path;
        }

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        if ($host === '') {

            return url($path);
        }

        $https = $_SERVER['HTTPS'] ?? '';
        $forwarded_proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $scheme = ($forwarded_proto === 'https' || (!empty($https) && $https !== 'off')) ? 'https' : 'http';

        return $scheme . '://' . $host . url($path);

    }

    function asset(string $path): string {

        $path = ltrim($path, '/');

        $file = root($path);
        $url  = url($path);

        if (file_exists($file)) {

            return $url . '?v=' . filemtime($file);
        }

        return $url;

    }

    function icon_apply_fill(string $svg, string $fill): string {

        $fill_replaced = false;
        $svg = preg_replace_callback(
            '/fill="([^"]*)"/',
            static function (array $matches) use ($fill, &$fill_replaced): string {

                $current_fill = strtolower(trim(array_string($matches, 1)));

                if ($current_fill === 'none' || $current_fill === 'transparent') {

                    return $matches[0];
                }

                $fill_replaced = true;

                return 'fill="' . $fill . '"';
            },
            $svg
        );

        if ($fill_replaced || !is_string($svg)) {

            return string_value($svg);
        }

        return preg_replace('/<svg\b/', '<svg fill="' . $fill . '"', $svg, 1) ?? $svg;

    }

    function icon(string $name,string $class='primary',string $width='16',string $height='16'): string {

        $svg = file_get_contents(root("assets/icons/{$name}"));
        $svg = str_replace('width="16"', 'width="'.$width.'"', $svg);
        $svg = str_replace('height="16"', 'height="'.$height.'"', $svg);

        if (preg_match('/class="([^"]*)"/', $svg, $matches)) {

            $classes = trim($matches[1] . ' crm-icon '.$class);
            $svg = preg_replace('/class="([^"]*)"/', 'class="' . $classes . '"', $svg, 1);
        } else {

            $svg = preg_replace('/<svg\b/', '<svg class="crm-icon"', $svg, 1);
        }

        return string_value($svg);
    }

    function image(string $name): string {

        return asset("/assets/images/{$name}");

    }

    function title(): string {

        return PROJECT_TITLE;

    }

    function theme(): string {

        return Auth()->check()
            ? Auth()->user()['theme']
            : Auth()->guest_theme($_GET['username'] ?? null);

    }

    function auth_user_id(): int {

        if (!Auth()->check()) {

            return 0;
        }

        return (int) (Auth()->user()['id'] ?? 0);

    }

    function auth_user_id_or_fail(): int {

        $user_id = auth_user_id();

        if ($user_id <= 0) {

            throw new RuntimeException('Invalid user.');
        }

        return $user_id;

    }

    function unformat_date(?string $date,string $fallback='-'):string{

        if(!$date)return $fallback;
        else{

            $date_value=new DateTime(date($date));
            return $date_value->format('d/m/Y');
        }

    }

    function format(String $date, String $format):String{

        $date_value=new DateTime(date($date));
        return $date_value->format($format);

    }

    function redirect(string $path): never {

        header('Location: ' . $path);
        exit;

    }

    function csrf_token(): string {

        $session = Session();
        $token = $session->get('csrf_token');

        if (!$token) {

            $token = bin2hex(random_bytes(32));
            $session->put('csrf_token', $token);
        }

        return $token;

    }

    function csrf_input(): string {

        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';

    }

    function verify_csrf(?string $token): bool {

        $stored_token = Session()->get('csrf_token');
        return is_string($token) && is_string($stored_token) && hash_equals($stored_token, $token);

    }

    function async_handler_path(string $handler): string {

        $normalized_handler = trim($handler);

        if ($normalized_handler === '') {

            throw new InvalidArgumentException('Missing async handler.');
        }

        if (str_contains($normalized_handler, "\0")) {

            throw new InvalidArgumentException('Invalid async handler.');
        }

        $normalized_handler = ltrim($normalized_handler, '/');

        if (!str_ends_with(strtolower($normalized_handler), '.php')) {

            $normalized_handler .= '.php';
        }

        $resolved_path = root($normalized_handler);
        $real_base_path = realpath(BASE_PATH);
        $real_handler_path = realpath($resolved_path);

        if (!is_string($real_base_path) || !is_string($real_handler_path) || !str_starts_with($real_handler_path, $real_base_path . DIRECTORY_SEPARATOR)) {

            throw new InvalidArgumentException('Async handler not found.');
        }

        return $real_handler_path;

    }

    function async_handler_definition(string $handler): array {

        $handler_path = async_handler_path($handler);
        $definition = require $handler_path;

        if (is_callable($definition)) {

            return [
                'title' => strtoupper(pathinfo($handler_path, PATHINFO_FILENAME)),
                'run' => $definition,
            ];
        }

        if (!is_array($definition) || !isset($definition['run']) || !is_callable($definition['run'])) {

            throw new RuntimeException('Async handler definition is invalid.');
        }

        $definition['title'] = trim(string_value($definition['title'] ?? '', strtoupper(pathinfo($handler_path, PATHINFO_FILENAME))));
        $definition['create_payload_from_request'] = is_callable($definition['create_payload_from_request'] ?? null)
            ? $definition['create_payload_from_request']
            : null;

        return $definition;

    }

    function is_same_origin_request(): bool {

        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($host === '') {

            return false;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {

            $origin_host = parse_url($origin, PHP_URL_HOST);
            return is_string($origin_host) && strcasecmp($origin_host, $host) === 0;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer !== '') {

            $referer_host = parse_url($referer, PHP_URL_HOST);
            return is_string($referer_host) && strcasecmp($referer_host, $host) === 0;
        }

        return false;

    }
