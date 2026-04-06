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

$job_key = trim(post_string('job_key'));

if ($job_key === '') {

    $respond(422, ['ok' => false, 'error' => 'Missing job key.']);
}

try {

    $user = Auth()->user();
    $user_id = (int) ($user['id'] ?? 0);

    $job = Async()->get_job($job_key, $user_id);
}
catch (Throwable $throwable) {

    $respond(404, ['ok' => false, 'error' => $throwable->getMessage()]);
}

$respond(200, [
    'ok' => true,
    'job' => [
        'job_key' => $job['job_key'],
        'status' => $job['status'],
        'progress_bars' => $job['progress_bars'],
        'download_url' => $job['download_url'],
        'error_message' => $job['error_message'],
        'warnings' => $job['warnings'],
    ],
]);
