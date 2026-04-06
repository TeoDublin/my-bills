<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {

    Session()->flash('error', 'Invalid request.');
    redirect(url('index.php'));
}

$token = trim(post_string('token'));
$password = post_string('password');
$password_confirm = post_string('password_confirm');

if ($token === '') {

    Session()->flash('error', 'Missing reset token.');
    redirect(url('index.php'));
}

if ($password === '' || strlen($password) < 8) {

    Session()->flash('error', 'Password must be at least 8 characters long.');
    redirect(url('index.php?auth=reset-password&token=' . urlencode($token)));
}

if ($password !== $password_confirm) {

    Session()->flash('error', 'Passwords do not match.');
    redirect(url('index.php?auth=reset-password&token=' . urlencode($token)));
}

if (!Auth()->reset_password($token, $password)) {

    Session()->flash('error', 'Reset link is invalid or expired.');
    redirect(url('index.php?auth=reset-password&token=' . urlencode($token)));
}

Session()->flash('success', 'Password changed successfully. Please log in.');
redirect(url('index.php'));
