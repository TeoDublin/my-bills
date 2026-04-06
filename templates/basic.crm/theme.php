<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {

    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

Auth()->require_auth();

$theme = trim(post_string('theme'));
if (!in_array($theme, ['light', 'dark'], true)) {

    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Tema non valido.']);
    exit;
}

$user = Auth()->user();
$user_id = (int) ($user['id'] ?? 0);

if ($user_id <= 0) {

    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

Preference()->set($user_id, 'theme', $theme, 'global');

if (!headers_sent()) {

    $expires_at = Auth()->next_token_expiry();
    setcookie('preferred_theme', $theme, [
        'expires' => $expires_at->getTimestamp(),
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

echo json_encode([
    'ok' => true,
    'theme' => $theme,
]);
