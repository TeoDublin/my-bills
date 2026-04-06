<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';

function montly_available_tabs(): array
{

    return [
        'bills' => [
            'id' => 'bills',
            'label' => 'Bills',
        ],
        'income' => [
            'id' => 'income',
            'label' => 'Income',
        ],
    ];

}

function montly_active_tab($value): string
{

    $tab = trim(string_value($value));
    $tabs = montly_available_tabs();

    return array_key_exists($tab, $tabs) ? $tab : 'bills';

}
