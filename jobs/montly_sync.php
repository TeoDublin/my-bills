<?php

require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/class.php';
require_once __DIR__ . '/../includes/functions.php';

function montly_sync_day_in_month(int $year, int $month, int $day): DateTimeImmutable
{

    $last_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $normalized_day = min($day, $last_day);

    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $normalized_day));

}

function montly_sync_reference_period(DateTimeImmutable $today, int $income_day): array
{

    $current_anchor = montly_sync_day_in_month((int) $today->format('Y'), (int) $today->format('n'), $income_day);

    if ($today < $current_anchor) {

        $previous_month = $today->modify('first day of previous month');
        $start = montly_sync_day_in_month((int) $previous_month->format('Y'), (int) $previous_month->format('n'), $income_day);
    } else {

        $start = $current_anchor;
    }

    $next_month = $start->modify('first day of next month');
    $end = montly_sync_day_in_month((int) $next_month->format('Y'), (int) $next_month->format('n'), $income_day);

    return [
        'start' => $start,
        'end' => $end,
    ];

}

function montly_sync_scheduled_date(array $montly_bill, DateTimeImmutable $reference_start, DateTimeImmutable $reference_end): ?DateTimeImmutable
{

    $bill_day = (int) ($montly_bill['day'] ?? 0);

    if ($bill_day < 1 || $bill_day > 31) {

        return null;
    }

    $candidate_months = [
        $reference_start->format('Y-m'),
        $reference_end->format('Y-m'),
    ];

    foreach (array_unique($candidate_months) as $candidate_month) {

        [$year, $month] = array_map('intval', explode('-', $candidate_month));
        $candidate = montly_sync_day_in_month($year, $month, $bill_day);

        if ($candidate >= $reference_start && $candidate < $reference_end) {

            return $candidate;
        }
    }

    return null;

}

function montly_sync_is_within_bounds(DateTimeImmutable $scheduled_date, array $montly_bill): bool
{

    $first_date = trim(string_value($montly_bill['first_date'] ?? ''));
    $last_date = trim(string_value($montly_bill['last_date'] ?? ''));

    if ($first_date !== '' && $scheduled_date < new DateTimeImmutable($first_date)) {

        return false;
    }

    if ($last_date !== '' && $scheduled_date > new DateTimeImmutable($last_date)) {

        return false;
    }

    return true;

}

function montly_sync_income_day(int $user_id): int
{

    $rows = SQL()->select("
        SELECT day
        FROM incoming
        WHERE user_id = " . $user_id . "
        ORDER BY id ASC
        LIMIT 1
    ");

    $income_day = (int) ($rows[0]['day'] ?? 0);

    if ($income_day < 1 || $income_day > 31) {

        throw new RuntimeException('Income day is missing or invalid.');
    }

    return $income_day;

}

function montly_sync_generated_rows_for_bill_ids(array $montly_bill_ids, int $user_id): array
{

    $ids = array_values(array_filter(array_map('intval', $montly_bill_ids), static fn (int $id): bool => $id > 0));

    if ($ids === []) {

        return [];
    }

    return SQL()->select("
        SELECT id, user_id, id_montly_bill, id_group, name, value, date, reference_start, reference_end
        FROM bills
        WHERE user_id = " . $user_id . "
          AND id_montly_bill IN(" . implode(',', $ids) . ")
        ORDER BY id ASC
    ");

}

function montly_sync_delete_generated_rows_for_bill_ids(array $montly_bill_ids, int $user_id): int
{

    $ids = array_values(array_filter(array_map('intval', $montly_bill_ids), static fn (int $id): bool => $id > 0));

    if ($ids === []) {

        return 0;
    }

    $rows = SQL()->select("
        SELECT COUNT(*) AS total
        FROM bills
        WHERE user_id = " . $user_id . "
          AND id_montly_bill IN(" . implode(',', $ids) . ")
    ");
    $total = (int) ($rows[0]['total'] ?? 0);

    if ($total > 0) {

        SQL()->query("
            DELETE FROM bills
            WHERE user_id = " . $user_id . "
              AND id_montly_bill IN(" . implode(',', $ids) . ")
        ");
    }

    return $total;

}

function montly_sync_refresh_bill_history(int $montly_bill_id, int $user_id): array
{

    $montly_bill_rows = SQL()->select("
        SELECT id, user_id, id_group, name, value, day, first_date, last_date
        FROM montly_bills
        WHERE id = " . $montly_bill_id . "
          AND user_id = " . $user_id . "
        LIMIT 1
    ");

    if ($montly_bill_rows === []) {

        return [
            'updated' => 0,
            'deleted' => montly_sync_delete_generated_rows_for_bill_ids([$montly_bill_id], $user_id),
        ];
    }

    $montly_bill = $montly_bill_rows[0];
    $generated_rows = montly_sync_generated_rows_for_bill_ids([$montly_bill_id], $user_id);
    $summary = [
        'updated' => 0,
        'deleted' => 0,
    ];

    foreach ($generated_rows as $generated_row) {

        $reference_start_raw = trim(string_value($generated_row['reference_start'] ?? ''));
        $reference_end_raw = trim(string_value($generated_row['reference_end'] ?? ''));

        if ($reference_start_raw === '' || $reference_end_raw === '') {

            Delete()
                ->from('bills')
                ->where('id = ' . (int) ($generated_row['id'] ?? 0) . ' AND user_id = ' . $user_id);

            $summary['deleted']++;
            continue;
        }

        $reference_start = new DateTimeImmutable($reference_start_raw);
        $reference_end = new DateTimeImmutable($reference_end_raw);
        $scheduled_date = montly_sync_scheduled_date($montly_bill, $reference_start, $reference_end);

        if ($scheduled_date === null || !montly_sync_is_within_bounds($scheduled_date, $montly_bill)) {

            Delete()
                ->from('bills')
                ->where('id = ' . (int) ($generated_row['id'] ?? 0) . ' AND user_id = ' . $user_id);

            $summary['deleted']++;
            continue;
        }

        Update('bills')
            ->set([
                'id_group' => (int) ($montly_bill['id_group'] ?? 0),
                'name' => string_value($montly_bill['name'] ?? ''),
                'value' => number_format((float) ($montly_bill['value'] ?? 0), 2, '.', ''),
                'date' => $scheduled_date->format('Y-m-d'),
                'reference_start' => $reference_start->format('Y-m-d'),
                'reference_end' => $reference_end->format('Y-m-d'),
            ])
            ->where('id = ' . (int) ($generated_row['id'] ?? 0) . ' AND user_id = ' . $user_id);

        $summary['updated']++;
    }

    return $summary;

}

function montly_sync_run_for_user(?string $today_argument, int $user_id): array
{

    $today = $today_argument !== null && trim($today_argument) !== ''
        ? new DateTimeImmutable($today_argument)
        : new DateTimeImmutable('today');

    $income_day = montly_sync_income_day($user_id);
    $reference = montly_sync_reference_period($today, $income_day);
    $reference_start = $reference['start'];
    $reference_end = $reference['end'];

    $montly_bills = SQL()->select("
        SELECT id, user_id, id_group, name, value, day, first_date, last_date
        FROM montly_bills
        WHERE user_id = " . $user_id . "
        ORDER BY id ASC
    ");

    $existing_rows = SQL()->select("
        SELECT id, user_id, id_montly_bill, id_group, name, value, date, reference_start, reference_end
        FROM bills
        WHERE user_id = " . $user_id . "
          AND date >= '" . $reference_start->format('Y-m-d') . "'
          AND date < '" . $reference_end->format('Y-m-d') . "'
        ORDER BY id ASC
    ");

    $existing_by_montly_id = [];
    $unlinked_rows = [];

    foreach ($existing_rows as $existing_row) {

        $montly_bill_id = (int) ($existing_row['id_montly_bill'] ?? 0);

        if ($montly_bill_id <= 0) {

            $unlinked_rows[] = $existing_row;
            continue;
        }

        if (!isset($existing_by_montly_id[$montly_bill_id])) {

            $existing_by_montly_id[$montly_bill_id] = [];
        }

        $existing_by_montly_id[$montly_bill_id][] = $existing_row;
    }

    $summary = [
        'date' => $today->format('Y-m-d'),
        'user_id' => $user_id,
        'income_day' => $income_day,
        'reference_start' => $reference_start->format('Y-m-d'),
        'reference_end' => $reference_end->format('Y-m-d'),
        'processed' => 0,
        'inserted' => 0,
        'updated' => 0,
        'existing' => 0,
        'deleted' => 0,
        'out_of_bounds' => 0,
        'no_schedule' => 0,
        'details' => [],
    ];

    foreach ($montly_bills as $montly_bill) {

        $montly_bill_id = (int) ($montly_bill['id'] ?? 0);
        $summary['processed']++;
        $scheduled_date = montly_sync_scheduled_date($montly_bill, $reference_start, $reference_end);
        $existing_for_bill = $existing_by_montly_id[$montly_bill_id] ?? [];
        $current_row = $existing_for_bill !== [] ? array_shift($existing_for_bill) : null;
        $existing_by_montly_id[$montly_bill_id] = $existing_for_bill;

        if ($scheduled_date === null) {

            if ($current_row !== null) {

                Delete()
                    ->from('bills')
                    ->where('id = ' . (int) ($current_row['id'] ?? 0) . ' AND user_id = ' . $user_id);

                $summary['deleted']++;
            }

            $summary['no_schedule']++;
            $summary['details'][] = [
                'montly_bill_id' => $montly_bill_id,
                'status' => 'no_schedule',
            ];
            continue;
        }

        if (!montly_sync_is_within_bounds($scheduled_date, $montly_bill)) {

            if ($current_row !== null) {

                Delete()
                    ->from('bills')
                    ->where('id = ' . (int) ($current_row['id'] ?? 0) . ' AND user_id = ' . $user_id);

                $summary['deleted']++;
            }

            $summary['out_of_bounds']++;
            $summary['details'][] = [
                'montly_bill_id' => $montly_bill_id,
                'status' => 'out_of_bounds',
                'scheduled_date' => $scheduled_date->format('Y-m-d'),
            ];
            continue;
        }

        $payload = [
            'user_id' => $user_id,
            'id_montly_bill' => $montly_bill_id,
            'id_group' => (int) ($montly_bill['id_group'] ?? 0),
            'name' => string_value($montly_bill['name'] ?? ''),
            'value' => number_format((float) ($montly_bill['value'] ?? 0), 2, '.', ''),
            'date' => $scheduled_date->format('Y-m-d'),
            'reference_start' => $reference_start->format('Y-m-d'),
            'reference_end' => $reference_end->format('Y-m-d'),
        ];

        if ($current_row === null) {

            foreach ($unlinked_rows as $index => $unlinked_row) {

                if (
                    (int) ($unlinked_row['id_group'] ?? 0) === (int) $payload['id_group']
                    && string_value($unlinked_row['name'] ?? '') === $payload['name']
                ) {

                    $current_row = $unlinked_row;
                    unset($unlinked_rows[$index]);
                    break;
                }
            }
        }

        if ($current_row === null) {

            Insert($payload)->into('bills')->get();

            $summary['inserted']++;
            $summary['details'][] = [
                'montly_bill_id' => $montly_bill_id,
                'status' => 'inserted',
                'scheduled_date' => $payload['date'],
            ];
            continue;
        }

        $has_changes = (int) ($current_row['id_group'] ?? 0) !== (int) $payload['id_group']
            || (int) ($current_row['user_id'] ?? 0) !== $user_id
            || (int) ($current_row['id_montly_bill'] ?? 0) !== $montly_bill_id
            || string_value($current_row['name'] ?? '') !== $payload['name']
            || number_format((float) ($current_row['value'] ?? 0), 2, '.', '') !== $payload['value']
            || string_value($current_row['date'] ?? '') !== $payload['date']
            || string_value($current_row['reference_start'] ?? '') !== $payload['reference_start']
            || string_value($current_row['reference_end'] ?? '') !== $payload['reference_end'];

        if ($has_changes) {

            Update('bills')
                ->set($payload)
                ->where('id = ' . (int) ($current_row['id'] ?? 0) . ' AND user_id = ' . $user_id);

            $summary['updated']++;
            $summary['details'][] = [
                'montly_bill_id' => $montly_bill_id,
                'status' => 'updated',
                'scheduled_date' => $payload['date'],
            ];
        } else {

            $summary['existing']++;
            $summary['details'][] = [
                'montly_bill_id' => $montly_bill_id,
                'status' => 'existing',
                'scheduled_date' => $payload['date'],
            ];
        }
    }

    foreach ($existing_by_montly_id as $leftover_rows) {

        foreach ($leftover_rows as $leftover_row) {

            Delete()
                ->from('bills')
                ->where('id = ' . (int) ($leftover_row['id'] ?? 0) . ' AND user_id = ' . $user_id);

            $summary['deleted']++;
        }
    }

    return $summary;

}

function montly_sync_run(?string $today_argument = null, ?int $user_id = null): array
{

    if ($user_id !== null && $user_id > 0) {

        return montly_sync_run_for_user($today_argument, $user_id);
    }

    $user_rows = SQL()->select("
        SELECT DISTINCT u.id
        FROM users u
        INNER JOIN incoming i ON i.user_id = u.id
        ORDER BY u.id ASC
    ");

    $summary = [
        'date' => ($today_argument !== null && trim($today_argument) !== '') ? $today_argument : date('Y-m-d'),
        'processed_users' => 0,
        'inserted' => 0,
        'updated' => 0,
        'existing' => 0,
        'deleted' => 0,
        'out_of_bounds' => 0,
        'no_schedule' => 0,
        'users' => [],
    ];

    foreach ($user_rows as $user_row) {

        $current_user_id = (int) ($user_row['id'] ?? 0);

        if ($current_user_id <= 0) {

            continue;
        }

        $user_summary = montly_sync_run_for_user($today_argument, $current_user_id);
        $summary['processed_users']++;
        $summary['inserted'] += (int) ($user_summary['inserted'] ?? 0);
        $summary['updated'] += (int) ($user_summary['updated'] ?? 0);
        $summary['existing'] += (int) ($user_summary['existing'] ?? 0);
        $summary['deleted'] += (int) ($user_summary['deleted'] ?? 0);
        $summary['out_of_bounds'] += (int) ($user_summary['out_of_bounds'] ?? 0);
        $summary['no_schedule'] += (int) ($user_summary['no_schedule'] ?? 0);
        $summary['users'][] = $user_summary;
    }

    return $summary;

}
