<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    Session()->flash('error', 'Invalid request.');
    redirect(url('index.php'));
}

$has_valid_csrf = verify_csrf($_POST['csrf_token'] ?? null);
$is_same_origin = is_same_origin_request();

if (!$has_valid_csrf && !$is_same_origin) {

    Session()->flash('error', 'Invalid request.');
    Session()->flash('forgot_panel_open', true);
    redirect(url('index.php'));
}

$username = trim(post_string('username'));
$email = trim(post_string('email'));

Session()->flash('last_username', $username);
Session()->flash('forgot_panel_open', true);

if ($username === '' || $email === '') {

    Session()->flash('error', 'Username and email are required.');
    redirect(url('index.php?username=' . urlencode($username)));
}

$result = Auth()->send_password_reset($username, $email, app_url('index.php?auth=reset-password&token='));

if (($result['ok'] ?? false) === true) {

    Session()->flash('success', 'Reset link sent successfully.');
    redirect(url('index.php?username=' . urlencode($username)));
}

if (($result['reason'] ?? '') === 'identity_mismatch') {

    Session()->flash('error', 'The provided username and email do not match.');
    redirect(url('index.php?username=' . urlencode($username)));
}

Session()->flash('error', $result['detail'] ?? 'Unable to send the reset email.');

redirect(url('index.php?username=' . urlencode($username)));
