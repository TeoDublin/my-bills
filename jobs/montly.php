<?php

require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/class.php';
require_once __DIR__ . '/../includes/functions.php';

if (PHP_SAPI !== 'cli') {

    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

function montly_job_day_in_month(int $year, int $month, int $day): DateTimeImmutable
{

    $last_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $normalized_day = min($day, $last_day);

    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $normalized_day));

}

function montly_job_reference_period(DateTimeImmutable $today, int $income_day): array
{

    $current_anchor = montly_job_day_in_month((int) $today->format('Y'), (int) $today->format('n'), $income_day);

    if ($today < $current_anchor) {

        $previous_month = $today->modify('first day of previous month');
        $start = montly_job_day_in_month((int) $previous_month->format('Y'), (int) $previous_month->format('n'), $income_day);
    } else {

        $start = $current_anchor;
    }

    $next_month = $start->modify('first day of next month');
    $end = montly_job_day_in_month((int) $next_month->format('Y'), (int) $next_month->format('n'), $income_day);

    return [
        'start' => $start,
        'end' => $end,
    ];

}

function montly_job_scheduled_date(array $montly_bill, DateTimeImmutable $reference_start, DateTimeImmutable $reference_end): ?DateTimeImmutable
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
        $candidate = montly_job_day_in_month($year, $month, $bill_day);

        if ($candidate >= $reference_start && $candidate < $reference_end) {

            return $candidate;
        }
    }

    return null;

}

function montly_job_is_within_bounds(DateTimeImmutable $scheduled_date, array $montly_bill): bool
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

function montly_job_income_day(): int
{

    $rows = SQL()->select("
        SELECT day
        FROM incoming
        ORDER BY id ASC
        LIMIT 1
    ");

    $income_day = (int) ($rows[0]['day'] ?? 0);

    if ($income_day < 1 || $income_day > 31) {

        throw new RuntimeException('Income day is missing or invalid.');
    }

    return $income_day;

}

function montly_job_already_present(array $montly_bill, DateTimeImmutable $reference_start, DateTimeImmutable $reference_end): bool
{

    $rows = SQL()->select("
        SELECT id
        FROM bills
        WHERE id_group = " . (int) ($montly_bill['id_group'] ?? 0) . "
          AND name = '" . SQL()->escape(string_value($montly_bill['name'] ?? '')) . "'
          AND date >= '" . $reference_start->format('Y-m-d') . "'
          AND date < '" . $reference_end->format('Y-m-d') . "'
        LIMIT 1
    ");

    return $rows !== [];

}

try {

    $today_argument = $argv[1] ?? '';
    $today = $today_argument !== ''
        ? new DateTimeImmutable($today_argument)
        : new DateTimeImmutable('today');

    $income_day = montly_job_income_day();
    $reference = montly_job_reference_period($today, $income_day);
    $reference_start = $reference['start'];
    $reference_end = $reference['end'];

    $montly_bills = SQL()->select("
        SELECT id, id_group, name, value, day, first_date, last_date
        FROM montly_bills
        ORDER BY id ASC
    ");

    $summary = [
        'date' => $today->format('Y-m-d'),
        'income_day' => $income_day,
        'reference_start' => $reference_start->format('Y-m-d'),
        'reference_end' => $reference_end->format('Y-m-d'),
        'processed' => 0,
        'inserted' => 0,
        'existing' => 0,
        'out_of_bounds' => 0,
        'no_schedule' => 0,
        'details' => [],
    ];

    foreach ($montly_bills as $montly_bill) {

        $summary['processed']++;
        $scheduled_date = montly_job_scheduled_date($montly_bill, $reference_start, $reference_end);

        if ($scheduled_date === null) {

            $summary['no_schedule']++;
            $summary['details'][] = [
                'montly_bill_id' => (int) ($montly_bill['id'] ?? 0),
                'status' => 'no_schedule',
            ];
            continue;
        }

        if (!montly_job_is_within_bounds($scheduled_date, $montly_bill)) {

            $summary['out_of_bounds']++;
            $summary['details'][] = [
                'montly_bill_id' => (int) ($montly_bill['id'] ?? 0),
                'status' => 'out_of_bounds',
                'scheduled_date' => $scheduled_date->format('Y-m-d'),
            ];
            continue;
        }

        if (montly_job_already_present($montly_bill, $reference_start, $reference_end)) {

            $summary['existing']++;
            $summary['details'][] = [
                'montly_bill_id' => (int) ($montly_bill['id'] ?? 0),
                'status' => 'existing',
                'scheduled_date' => $scheduled_date->format('Y-m-d'),
            ];
            continue;
        }

        Insert([
            'id_group' => (int) ($montly_bill['id_group'] ?? 0),
            'name' => string_value($montly_bill['name'] ?? ''),
            'value' => number_format((float) ($montly_bill['value'] ?? 0), 2, '.', ''),
            'date' => $scheduled_date->format('Y-m-d'),
        ])->into('bills')->get();

        $summary['inserted']++;
        $summary['details'][] = [
            'montly_bill_id' => (int) ($montly_bill['id'] ?? 0),
            'status' => 'inserted',
            'scheduled_date' => $scheduled_date->format('Y-m-d'),
        ];
    }

    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}
catch (Throwable $exception) {

    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
