<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

$respond = static function (int $status_code, array $payload): never {

    http_response_code($status_code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {

    $respond(422, ['ok' => false, 'error' => 'Invalid request.']);
}

Auth()->require_auth();

try {

    $handler = trim(post_string('handler'));
    $definition = async_handler_definition($handler);
    $payload_factory = $definition['create_payload_from_request'] ?? null;
    $payload = is_callable($payload_factory)
        ? $payload_factory($_POST, $_FILES)
        : (json_decode(array_string($_POST, 'payload', '{}'), true) ?: []);
    $user = Auth()->user();
    $user_id = (int) ($user['id'] ?? 0);
    $job_payload = is_array($payload) ? $payload : [];
    $title = string_value($definition['title'] ?? '');

    $job = Async()->start_job($user_id, $handler, $job_payload, $title);
} 
catch (Throwable $throwable) {

    $respond($throwable instanceof InvalidArgumentException ? 422 : 500, ['ok' => false, 'error' => $throwable->getMessage()]);
}

$respond(200, [
    'ok' => true,
    'job' => [
        'job_key' => $job['job_key'],
        'status' => $job['status'],
        'progress_bars' => $job['progress_bars'],
        'download_url' => $job['download_url'],
        'error_message' => $job['error_message'],
    ],
]);
