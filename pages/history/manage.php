<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';
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
    echo json_encode(['ok' => false, 'error' => 'Azione non valida.']);
    exit;
}

try {

    if ($action === 'create_bill') {

        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $name = trim(post_string('name'));
        $value_raw = str_replace(',', '.', trim(post_string('value')));
        $date = trim(post_string('date'));

        if ($group_id === false || $group_id === null || $group_id <= 0) {

            throw new InvalidArgumentException('Seleziona un gruppo valido.');
        }

        if ($name === '') {

            throw new InvalidArgumentException('Inserisci il nome del bill.');
        }

        if (!is_numeric($value_raw)) {

            throw new InvalidArgumentException('Inserisci un valore valido.');
        }

        $value = round((float) $value_raw, 2);
        if ($value < 0) {

            throw new InvalidArgumentException('Il valore non puo essere negativo.');
        }

        if (!bills_is_valid_date($date)) {

            throw new InvalidArgumentException('Inserisci una data valida.');
        }

        $group_exists = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE id = " . (int) $group_id . "
            LIMIT 1
        ");

        if ($group_exists === []) {

            throw new InvalidArgumentException('Il gruppo selezionato non esiste.');
        }

        $bill_id = Insert([
            'id_group' => (int) $group_id,
            'name' => $name,
            'value' => number_format($value, 2, '.', ''),
            'date' => $date,
        ])->into('bills')->get();

        echo json_encode([
            'ok' => true,
            'message' => 'Bill creato.',
            'bill_id' => (int) $bill_id,
            'groups' => bills_group_options_payload(),
        ]);
        exit;
    }

    if ($action === 'create_group') {

        $name = trim(post_string('name'));

        if ($name === '') {

            throw new InvalidArgumentException('Inserisci il nome del gruppo.');
        }

        $exists = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE LOWER(name) = LOWER('" . SQL()->escape($name) . "')
            LIMIT 1
        ");

        if ($exists !== []) {

            throw new InvalidArgumentException('Esiste gia un gruppo con questo nome.');
        }

        $group_id = Insert([
            'name' => $name,
        ])->into('bills_groups')->get();

        echo json_encode([
            'ok' => true,
            'message' => 'Gruppo creato.',
            'group_id' => (int) $group_id,
            'groups' => bills_group_options_payload(),
        ]);
        exit;
    }

    if ($action === 'rename_group') {

        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $name = trim(post_string('name'));

        if ($group_id === false || $group_id === null || $group_id <= 0) {

            throw new InvalidArgumentException('Seleziona un gruppo valido.');
        }

        if ($name === '') {

            throw new InvalidArgumentException('Inserisci il nuovo nome del gruppo.');
        }

        $exists = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE id = " . (int) $group_id . "
            LIMIT 1
        ");

        if ($exists === []) {

            throw new InvalidArgumentException('Il gruppo selezionato non esiste.');
        }

        $duplicate = SQL()->select("
            SELECT id
            FROM bills_groups
            WHERE LOWER(name) = LOWER('" . SQL()->escape($name) . "')
              AND id <> " . (int) $group_id . "
            LIMIT 1
        ");

        if ($duplicate !== []) {

            throw new InvalidArgumentException('Esiste gia un gruppo con questo nome.');
        }

        Update('bills_groups')
            ->set([
                'name' => $name,
            ])
            ->where('id = ' . (int) $group_id);

        echo json_encode([
            'ok' => true,
            'message' => 'Gruppo aggiornato.',
            'group_id' => (int) $group_id,
            'groups' => bills_group_options_payload(),
        ]);
        exit;
    }

    throw new InvalidArgumentException('Azione non supportata.');
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
