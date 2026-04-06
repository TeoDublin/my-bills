<?php

require_once __DIR__ . '/../../../includes/constants.php';
require_once __DIR__ . '/../../../includes/class.php';
require_once __DIR__ . '/../../../includes/functions.php';

function income_is_valid_day($value): bool
{

    $day = filter_var($value, FILTER_VALIDATE_INT);

    return $day !== false && $day !== null && $day >= 1 && $day <= 31;

}

function income_current_entry(): array
{

    $user_id = auth_user_id();

    if ($user_id <= 0) {

        return [];
    }

    $rows = SQL()->select("
        SELECT id, user_id, value, day
        FROM incoming
        WHERE user_id = " . $user_id . "
        ORDER BY id ASC
        LIMIT 1
    ");

    return $rows[0] ?? [];

}
