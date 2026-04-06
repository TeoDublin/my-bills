<?php

require_once __DIR__ . '/../../../includes/constants.php';
require_once __DIR__ . '/../../../includes/class.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {

    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

Auth()->require_auth();

$action = trim(post_string('action'));

if ($action === '') {

    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid action.']);
    exit;
}

try {

    if ($action === 'create_bill') {

        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $name = trim(post_string('name'));
        $value_raw = str_replace(',', '.', trim(post_string('value')));
        $date = trim(post_string('date'));

        if ($group_id === false || $group_id === null || $group_id <= 0) {

            throw new InvalidArgumentException('Select a valid group.');
        }

        if ($name === '') {

            throw new InvalidArgumentException('Enter the bill name.');
        }

        if (!is_numeric($value_raw)) {

            throw new InvalidArgumentException('Enter a valid value.');
        }

        $value = round((float) $value_raw, 2);
        if ($value < 0) {

            throw new InvalidArgumentException('The value cannot be negative.');
        }

        if (!bills_is_valid_date($date)) {

            throw new InvalidArgumentException('Enter a valid date.');
        }

        $group_exists = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE id = " . (int) $group_id . "
            LIMIT 1
        ");

        if ($group_exists === []) {

            throw new InvalidArgumentException('The selected group does not exist.');
        }

        $bill_id = Insert([
            'id_group' => (int) $group_id,
            'name' => $name,
            'value' => number_format($value, 2, '.', ''),
            'date' => $date,
        ])->into('bills')->get();

        echo json_encode([
            'ok' => true,
            'message' => 'Bill created.',
            'bill_id' => (int) $bill_id,
            'groups' => bills_group_options_payload(),
        ]);
        exit;
    }

    if ($action === 'update_bill') {

        $bill_id = filter_input(INPUT_POST, 'bill_id', FILTER_VALIDATE_INT);
        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $name = trim(post_string('name'));
        $value_raw = str_replace(',', '.', trim(post_string('value')));
        $date = trim(post_string('date'));

        if ($bill_id === false || $bill_id === null || $bill_id <= 0) {

            throw new InvalidArgumentException('Invalid bill.');
        }

        if ($group_id === false || $group_id === null || $group_id <= 0) {

            throw new InvalidArgumentException('Select a valid group.');
        }

        if ($name === '') {

            throw new InvalidArgumentException('Enter the bill name.');
        }

        if (!is_numeric($value_raw)) {

            throw new InvalidArgumentException('Enter a valid value.');
        }

        $value = round((float) $value_raw, 2);
        if ($value < 0) {

            throw new InvalidArgumentException('The value cannot be negative.');
        }

        if (!bills_is_valid_date($date)) {

            throw new InvalidArgumentException('Enter a valid date.');
        }

        $bill_exists = SQL()->select("
            SELECT id
            FROM bills
            WHERE id = " . (int) $bill_id . "
            LIMIT 1
        ");

        if ($bill_exists === []) {

            throw new InvalidArgumentException('The selected bill does not exist.');
        }

        $group_exists = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE id = " . (int) $group_id . "
            LIMIT 1
        ");

        if ($group_exists === []) {

            throw new InvalidArgumentException('The selected group does not exist.');
        }

        Update('bills')
            ->set([
                'id_group' => (int) $group_id,
                'name' => $name,
                'value' => number_format($value, 2, '.', ''),
                'date' => $date,
            ])
            ->where('id = ' . (int) $bill_id);

        echo json_encode([
            'ok' => true,
            'message' => 'Bill updated.',
            'bill_id' => (int) $bill_id,
            'groups' => bills_group_options_payload(),
        ]);
        exit;
    }

    if ($action === 'create_group') {

        $name = trim(post_string('name'));

        if ($name === '') {

            throw new InvalidArgumentException('Enter the group name.');
        }

        $exists = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE LOWER(name) = LOWER('" . SQL()->escape($name) . "')
            LIMIT 1
        ");

        if ($exists !== []) {

            throw new InvalidArgumentException('A group with this name already exists.');
        }

        $group_id = Insert([
            'name' => $name,
        ])->into('bills_groups')->get();

        echo json_encode([
            'ok' => true,
            'message' => 'Group created.',
            'group_id' => (int) $group_id,
            'groups' => bills_group_options_payload(),
        ]);
        exit;
    }

    if ($action === 'rename_group') {

        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $name = trim(post_string('name'));

        if ($group_id === false || $group_id === null || $group_id <= 0) {

            throw new InvalidArgumentException('Select a valid group.');
        }

        if ($name === '') {

            throw new InvalidArgumentException('Enter the new group name.');
        }

        $exists = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE id = " . (int) $group_id . "
            LIMIT 1
        ");

        if ($exists === []) {

            throw new InvalidArgumentException('The selected group does not exist.');
        }

        $duplicate = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE LOWER(name) = LOWER('" . SQL()->escape($name) . "')
              AND id <> " . (int) $group_id . "
            LIMIT 1
        ");

        if ($duplicate !== []) {

            throw new InvalidArgumentException('A group with this name already exists.');
        }

        Update('bills_groups')
            ->set([
                'name' => $name,
            ])
            ->where('id = ' . (int) $group_id);

        echo json_encode([
            'ok' => true,
            'message' => 'Group updated.',
            'group_id' => (int) $group_id,
            'groups' => bills_group_options_payload(),
        ]);
        exit;
    }

    if ($action === 'delete_group') {

        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);

        if ($group_id === false || $group_id === null || $group_id <= 0) {

            throw new InvalidArgumentException('Select a valid group.');
        }

        $exists = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE id = " . (int) $group_id . "
            LIMIT 1
        ");

        if ($exists === []) {

            throw new InvalidArgumentException('The selected group does not exist.');
        }

        $related_bills = SQL()->select("
            SELECT COUNT(*) AS total
            FROM bills
            WHERE id_group = " . (int) $group_id . "
        ");

        if ((int) ($related_bills[0]['total'] ?? 0) > 0) {

            throw new InvalidArgumentException('You cannot delete the group because bills are linked to it.');
        }

        Delete()
            ->from('bills_groups')
            ->where('id = ' . (int) $group_id);

        $groups = bills_group_options_payload();

        echo json_encode([
            'ok' => true,
            'message' => 'Group deleted.',
            'groups' => $groups,
            'selected_group_id' => (int) ($groups[0]['id'] ?? 0),
        ]);
        exit;
    }

    if ($action === 'delete_bill') {

        $bill_id = filter_input(INPUT_POST, 'bill_id', FILTER_VALIDATE_INT);

        if ($bill_id === false || $bill_id === null || $bill_id <= 0) {

            throw new InvalidArgumentException('Invalid bill.');
        }

        $exists = SQL()->select("
            SELECT id
            FROM bills
            WHERE id = " . (int) $bill_id . "
            LIMIT 1
        ");

        if ($exists === []) {

            throw new InvalidArgumentException('The selected bill does not exist.');
        }

        Delete()
            ->from('bills')
            ->where('id = ' . (int) $bill_id);

        echo json_encode([
            'ok' => true,
            'message' => 'Bill deleted.',
            'groups' => bills_group_options_payload(),
        ]);
        exit;
    }

    if ($action === 'bulk_delete_bills') {

        $payload = bills_create_bulk_action_payload_from_request($_POST);
        $where = bills_build_where($payload['filters'], [
            'selected_ids' => $payload['selected_ids'],
        ]);

        $count_rows = SQL()->select("
            SELECT COUNT(*) AS total
            FROM bills
            WHERE " . $where . "
        ");
        $total = (int) ($count_rows[0]['total'] ?? 0);

        if ($total <= 0) {

            throw new InvalidArgumentException('No bills found to delete.');
        }

        SQL()->query("
            DELETE FROM bills
            WHERE " . $where . "
        ");

        echo json_encode([
            'ok' => true,
            'message' => $total === 1 ? '1 bill deleted.' : ($total . ' bills deleted.'),
            'deleted_count' => $total,
        ]);
        exit;
    }

    throw new InvalidArgumentException('Unsupported action.');
}
catch (InvalidArgumentException $exception) {

    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
    ]);
    exit;
}
catch (Throwable $exception) {

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Errore interno.',
    ]);
    exit;
}
