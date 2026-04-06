<?php

require_once __DIR__ . '/includes/functions.php';

$auth_route = $_GET['auth'] ?? null;

if ($auth_route === 'reset-password') {

    $page_theme = Auth()->theme_for_reset_token($_GET['token'] ?? null);
    require_once __DIR__ . '/templates/reset-password/reset-password.php';
    exit;
}

if (Auth()->check()) {

    require_once __DIR__ . '/templates/basic.crm/basic.crm.php';
    exit;
}

$page_theme = Auth()->guest_theme($_GET['username'] ?? null);
require_once __DIR__ . '/templates/login/login.php';

