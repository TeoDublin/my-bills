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

if ($action !== 'create_income') {

    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid action.']);
    exit;
}

try {

    $value_raw = str_replace(',', '.', trim(post_string('value')));
    $day = filter_input(INPUT_POST, 'day', FILTER_VALIDATE_INT);

    if (!is_numeric($value_raw)) {

        throw new InvalidArgumentException('Enter a valid income value.');
    }

    $value = round((float) $value_raw, 2);
    if ($value < 0) {

        throw new InvalidArgumentException('The income value cannot be negative.');
    }

    if (!income_is_valid_day($day)) {

        throw new InvalidArgumentException('Enter a valid income day.');
    }

    $existing_rows = SQL()->select("
        SELECT id
        FROM incoming
        ORDER BY id ASC
    ");

    $primary_id = (int) ($existing_rows[0]['id'] ?? 0);

    if ($primary_id > 0) {

        Update('incoming')
            ->set([
                'value' => number_format($value, 2, '.', ''),
                'day' => (int) $day,
            ])
            ->where('id = ' . $primary_id);

        if (count($existing_rows) > 1) {

            $extra_ids = array_values(array_filter(array_map(
                static fn (array $row): int => (int) ($row['id'] ?? 0),
                array_slice($existing_rows, 1)
            ), static fn (int $id): bool => $id > 0));

            if ($extra_ids !== []) {

                SQL()->query("
                    DELETE FROM incoming
                    WHERE id IN(" . implode(',', $extra_ids) . ")
                ");
            }
        }

        $income_id = $primary_id;
    } else {

        $income_id = Insert([
            'value' => number_format($value, 2, '.', ''),
            'day' => (int) $day,
        ])->into('incoming')->get();
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Income updated.',
        'income_id' => (int) $income_id,
    ]);
    exit;
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
