<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {

    Session()->flash('error', 'Invalid request.');
    redirect(url('index.php'));
}

$username = trim(post_string('username'));
$password = post_string('password');

Session()->flash('last_username', $username);

if (!Auth()->attempt($username, $password)) {

    Session()->flash('error', 'Invalid username or password.');
    redirect(url('index.php?username=' . urlencode($username)));
}

redirect(url('index.php'));
