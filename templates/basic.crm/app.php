<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';

function app_pages(): array
{

    return [
        'history' => [
            'id' => 'history',
            'label' => 'History',
            'icon' => 'bill-check.svg',
            'endpoint' => url('pages/history/history.php'),
            'script' => asset('pages/history/history.js'),
            'style' => asset('pages/history/history.css'),
        ],
    ];

}

function app_default_page(): string
{

    $pages = app_pages();
    return array_key_first($pages);

}

function app_page(?string $page_id = null): array
{

    $pages = app_pages();
    $page_id ??= $_GET['page'] ?? app_default_page();

    return $pages[$page_id] ?? $pages[app_default_page()];

}

function app_frontend_config(): array
{

    $pages = app_pages();
    $current_theme = theme();

    return [
        'indexUrl' => url('index.php'),
        'logoutUrl' => url('templates/basic.crm/logout.php'),
        'themeUrl' => url('templates/basic.crm/theme.php'),
        'csrfToken' => csrf_token(),
        'menuCookieName' => 'menu',
        'menuIconOpen' => icon('minor.svg', 'primary', '30', '30'),
        'menuIconClosed' => icon('greater.svg', 'primary', '30', '30'),
        'currentTheme' => $current_theme,
        'logoLightUrl' => image('logo-light.svg'),
        'logoDarkUrl' => image('logo-dark.svg'),
        'pages' => $pages,
        'defaultPage' => app_default_page(),
        'currentPage' => app_page()['id'],
    ];

}
