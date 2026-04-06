<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';

function history_available_tabs(): array
{

    return [
        'historic' => [
            'id' => 'historic',
            'label' => 'Historic',
        ],
        'dashboard' => [
            'id' => 'dashboard',
            'label' => 'Dashboard',
        ],
    ];

}

function history_active_tab($value): string
{

    $tab = trim(string_value($value));
    $tabs = history_available_tabs();

    return array_key_exists($tab, $tabs) ? $tab : 'historic';

}
