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
$user_id = auth_user_id_or_fail();

$action = trim(post_string('action'));

if ($action === '') {

    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid action.']);
    exit;
}

try {

    if ($action === 'create_extra_income') {

        $name = trim(post_string('name'));
        $value_raw = str_replace(',', '.', trim(post_string('value')));
        $date = trim(post_string('date'));

        if ($name === '') {

            throw new InvalidArgumentException('Enter the income name.');
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

        $income_id = Insert([
            'user_id' => $user_id,
            'name' => $name,
            'value' => number_format($value, 2, '.', ''),
            'date' => $date,
        ])->into('extra_incomes')->get();

        echo json_encode([
            'ok' => true,
            'message' => 'Income created.',
            'income_id' => (int) $income_id,
        ]);
        exit;
    }

    if ($action === 'update_extra_income') {

        $income_id = filter_input(INPUT_POST, 'income_id', FILTER_VALIDATE_INT);
        $name = trim(post_string('name'));
        $value_raw = str_replace(',', '.', trim(post_string('value')));
        $date = trim(post_string('date'));

        if ($income_id === false || $income_id === null || $income_id <= 0) {

            throw new InvalidArgumentException('Invalid income.');
        }

        if ($name === '') {

            throw new InvalidArgumentException('Enter the income name.');
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

        $exists = SQL()->select("
            SELECT id
            FROM extra_incomes
            WHERE id = " . (int) $income_id . "
              AND user_id = " . $user_id . "
            LIMIT 1
        ");

        if ($exists === []) {

            throw new InvalidArgumentException('The selected income does not exist.');
        }

        Update('extra_incomes')
            ->set([
                'name' => $name,
                'value' => number_format($value, 2, '.', ''),
                'date' => $date,
            ])
            ->where('id = ' . (int) $income_id . ' AND user_id = ' . $user_id);

        echo json_encode([
            'ok' => true,
            'message' => 'Income updated.',
            'income_id' => (int) $income_id,
        ]);
        exit;
    }

    if ($action === 'delete_extra_income') {

        $income_id = filter_input(INPUT_POST, 'income_id', FILTER_VALIDATE_INT);

        if ($income_id === false || $income_id === null || $income_id <= 0) {

            throw new InvalidArgumentException('Invalid income.');
        }

        $exists = SQL()->select("
            SELECT id
            FROM extra_incomes
            WHERE id = " . (int) $income_id . "
              AND user_id = " . $user_id . "
            LIMIT 1
        ");

        if ($exists === []) {

            throw new InvalidArgumentException('The selected income does not exist.');
        }

        Delete()
            ->from('extra_incomes')
            ->where('id = ' . (int) $income_id . ' AND user_id = ' . $user_id);

        echo json_encode([
            'ok' => true,
            'message' => 'Income deleted.',
        ]);
        exit;
    }

    if ($action === 'bulk_delete_extra_incomes') {

        $payload = extra_income_create_bulk_action_payload_from_request($_POST);
        $where = extra_income_build_where($payload['filters'], [
            'selected_ids' => $payload['selected_ids'],
            'user_id' => $user_id,
        ]);

        $count_rows = SQL()->select("
            SELECT COUNT(*) AS total
            FROM extra_incomes
            WHERE " . $where . "
        ");
        $total = (int) ($count_rows[0]['total'] ?? 0);

        if ($total <= 0) {

            throw new InvalidArgumentException('No incomes found to delete.');
        }

        SQL()->query("
            DELETE FROM extra_incomes
            WHERE " . $where . "
        ");

        echo json_encode([
            'ok' => true,
            'message' => $total === 1 ? '1 income deleted.' : ($total . ' incomes deleted.'),
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
        'error' => 'Internal error.',
    ]);
    exit;
}
