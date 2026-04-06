<?php

require_once __DIR__ . '/../../../includes/constants.php';
require_once __DIR__ . '/../../../includes/class.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../../../jobs/montly_sync.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {

    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

Auth()->require_auth();
$user_id = auth_user_id_or_fail();

$action = trim(post_string('action'));

if ($action === '') {

    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid action.']);
    exit;
}

try {

    if ($action === 'create_montly_bill') {

        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $name = trim(post_string('name'));
        $value_raw = str_replace(',', '.', trim(post_string('value')));
        $day = filter_input(INPUT_POST, 'day', FILTER_VALIDATE_INT);
        $first_date = trim(post_string('first_date'));
        $last_date = trim(post_string('last_date'));

        if ($group_id === false || $group_id === null || $group_id <= 0) {

            throw new InvalidArgumentException('Select a valid group.');
        }

        if ($name === '') {

            throw new InvalidArgumentException('Enter the monthly bill name.');
        }

        if (!is_numeric($value_raw)) {

            throw new InvalidArgumentException('Enter a valid value.');
        }

        $value = round((float) $value_raw, 2);
        if ($value < 0) {

            throw new InvalidArgumentException('The value cannot be negative.');
        }

        if (!montly_day_is_valid($day)) {

            throw new InvalidArgumentException('Day must be between 1 and 31.');
        }

        if ($first_date !== '' && !montly_is_valid_date($first_date)) {

            throw new InvalidArgumentException('Enter a valid first date.');
        }

        if ($last_date !== '' && !montly_is_valid_date($last_date)) {

            throw new InvalidArgumentException('Enter a valid last date.');
        }

        $group_exists = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE id = " . (int) $group_id . "
              AND user_id = " . $user_id . "
            LIMIT 1
        ");

        if ($group_exists === []) {

            throw new InvalidArgumentException('The selected group does not exist.');
        }

        $row_id = Insert([
            'user_id' => $user_id,
            'id_group' => (int) $group_id,
            'name' => $name,
            'value' => number_format($value, 2, '.', ''),
            'day' => (int) $day,
            'first_date' => $first_date !== '' ? $first_date : null,
            'last_date' => $last_date !== '' ? $last_date : null,
        ])->into('montly_bills')->get();
        $sync_summary = montly_sync_run(date('Y-m-d'), $user_id);

        echo json_encode([
            'ok' => true,
            'message' => 'Monthly bill created.',
            'id' => (int) $row_id,
            'groups' => montly_group_options_payload(),
            'sync' => $sync_summary,
        ]);
        exit;
    }

    if ($action === 'update_montly_bill') {

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $name = trim(post_string('name'));
        $value_raw = str_replace(',', '.', trim(post_string('value')));
        $day = filter_input(INPUT_POST, 'day', FILTER_VALIDATE_INT);
        $first_date = trim(post_string('first_date'));
        $last_date = trim(post_string('last_date'));

        if ($id === false || $id === null || $id <= 0) {

            throw new InvalidArgumentException('Invalid monthly bill.');
        }

        if ($group_id === false || $group_id === null || $group_id <= 0) {

            throw new InvalidArgumentException('Select a valid group.');
        }

        if ($name === '') {

            throw new InvalidArgumentException('Enter the monthly bill name.');
        }

        if (!is_numeric($value_raw)) {

            throw new InvalidArgumentException('Enter a valid value.');
        }

        $value = round((float) $value_raw, 2);
        if ($value < 0) {

            throw new InvalidArgumentException('The value cannot be negative.');
        }

        if (!montly_day_is_valid($day)) {

            throw new InvalidArgumentException('Day must be between 1 and 31.');
        }

        if ($first_date !== '' && !montly_is_valid_date($first_date)) {

            throw new InvalidArgumentException('Enter a valid first date.');
        }

        if ($last_date !== '' && !montly_is_valid_date($last_date)) {

            throw new InvalidArgumentException('Enter a valid last date.');
        }

        $row_exists = SQL()->select("
            SELECT id
            FROM montly_bills
            WHERE id = " . (int) $id . "
              AND user_id = " . $user_id . "
            LIMIT 1
        ");

        if ($row_exists === []) {

            throw new InvalidArgumentException('The selected monthly bill does not exist.');
        }

        $group_exists = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE id = " . (int) $group_id . "
              AND user_id = " . $user_id . "
            LIMIT 1
        ");

        if ($group_exists === []) {

            throw new InvalidArgumentException('The selected group does not exist.');
        }

        Update('montly_bills')
            ->set([
                'id_group' => (int) $group_id,
                'name' => $name,
                'value' => number_format($value, 2, '.', ''),
                'day' => (int) $day,
                'first_date' => $first_date !== '' ? $first_date : null,
                'last_date' => $last_date !== '' ? $last_date : null,
            ])
            ->where('id = ' . (int) $id . ' AND user_id = ' . $user_id);
        $history_refresh = montly_sync_refresh_bill_history((int) $id, $user_id);
        $sync_summary = montly_sync_run(date('Y-m-d'), $user_id);

        echo json_encode([
            'ok' => true,
            'message' => 'Monthly bill updated.',
            'id' => (int) $id,
            'groups' => montly_group_options_payload(),
            'history_refresh' => $history_refresh,
            'sync' => $sync_summary,
        ]);
        exit;
    }

    if ($action === 'delete_montly_bill') {

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($id === false || $id === null || $id <= 0) {

            throw new InvalidArgumentException('Invalid monthly bill.');
        }

        $row_exists = SQL()->select("
            SELECT id
            FROM montly_bills
            WHERE id = " . (int) $id . "
              AND user_id = " . $user_id . "
            LIMIT 1
        ");

        if ($row_exists === []) {

            throw new InvalidArgumentException('The selected monthly bill does not exist.');
        }

        $deleted_history_rows = montly_sync_delete_generated_rows_for_bill_ids([(int) $id], $user_id);

        Delete()
            ->from('montly_bills')
            ->where('id = ' . (int) $id . ' AND user_id = ' . $user_id);

        echo json_encode([
            'ok' => true,
            'message' => 'Monthly bill deleted.',
            'id' => (int) $id,
            'groups' => montly_group_options_payload(),
            'deleted_history_rows' => $deleted_history_rows,
        ]);
        exit;
    }

    if ($action === 'bulk_delete_montly_bills') {

        $payload = montly_create_bulk_action_payload_from_request($_POST);
        $where = montly_build_where($payload['filters'], [
            'selected_ids' => $payload['selected_ids'],
        ]);

        $count_rows = SQL()->select("
            SELECT COUNT(*) AS total
            FROM montly_bills
            WHERE " . montly_build_where($payload['filters'], [
                'selected_ids' => $payload['selected_ids'],
                'user_id' => $user_id,
            ]) . "
        ");
        $total = (int) ($count_rows[0]['total'] ?? 0);

        if ($total <= 0) {

            throw new InvalidArgumentException('No monthly bills found to delete.');
        }

        $rows_to_delete = SQL()->select("
            SELECT id
            FROM montly_bills
            WHERE " . montly_build_where($payload['filters'], [
                'selected_ids' => $payload['selected_ids'],
                'user_id' => $user_id,
            ]) . "
        ");
        $montly_bill_ids = array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rows_to_delete
        ), static fn (int $id): bool => $id > 0));
        $deleted_history_rows = montly_sync_delete_generated_rows_for_bill_ids($montly_bill_ids, $user_id);

        SQL()->query("
            DELETE FROM montly_bills
            WHERE " . montly_build_where($payload['filters'], [
                'selected_ids' => $payload['selected_ids'],
                'user_id' => $user_id,
            ]) . "
        ");

        echo json_encode([
            'ok' => true,
            'message' => $total === 1 ? '1 monthly bill deleted.' : ($total . ' monthly bills deleted.'),
            'deleted_history_rows' => $deleted_history_rows,
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
              AND user_id = " . $user_id . "
            LIMIT 1
        ");

        if ($exists !== []) {

            throw new InvalidArgumentException('A group with this name already exists.');
        }

        $group_id = Insert([
            'user_id' => $user_id,
            'name' => $name,
        ])->into('bills_groups')->get();

        echo json_encode([
            'ok' => true,
            'message' => 'Group created.',
            'group_id' => (int) $group_id,
            'groups' => montly_group_options_payload(),
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
              AND user_id = " . $user_id . "
            LIMIT 1
        ");

        if ($exists === []) {

            throw new InvalidArgumentException('The selected group does not exist.');
        }

        $duplicate = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE LOWER(name) = LOWER('" . SQL()->escape($name) . "')
              AND user_id = " . $user_id . "
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
            ->where('id = ' . (int) $group_id . ' AND user_id = ' . $user_id);

        echo json_encode([
            'ok' => true,
            'message' => 'Group updated.',
            'group_id' => (int) $group_id,
            'groups' => montly_group_options_payload(),
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
              AND user_id = " . $user_id . "
            LIMIT 1
        ");

        if ($exists === []) {

            throw new InvalidArgumentException('The selected group does not exist.');
        }

        $related_rows = SQL()->select("
            SELECT COUNT(*) AS total
            FROM montly_bills
            WHERE id_group = " . (int) $group_id . "
              AND user_id = " . $user_id . "
        ");

        if ((int) ($related_rows[0]['total'] ?? 0) > 0) {

            throw new InvalidArgumentException('You cannot delete the group because monthly bills are linked to it.');
        }

        Delete()
            ->from('bills_groups')
            ->where('id = ' . (int) $group_id . ' AND user_id = ' . $user_id);

        $groups = montly_group_options_payload();

        echo json_encode([
            'ok' => true,
            'message' => 'Group deleted.',
            'groups' => $groups,
            'selected_group_id' => (int) ($groups[0]['id'] ?? 0),
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
        'error' => 'Internal error.',
    ]);
    exit;
}
