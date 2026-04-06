<?php

require_once __DIR__ . '/../../../includes/constants.php';
require_once __DIR__ . '/../../../includes/class.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../historic/functions.php';
require_once __DIR__ . '/../../montly/income/functions.php';

function history_dashboard_name_options(): array
{

    $rows = SQL()->select("
        SELECT DISTINCT `name`
        FROM view_bills
        WHERE `name` IS NOT NULL AND `name` <> ''
        ORDER BY `name` ASC
    ");

    return array_values(array_filter(array_map(
        static fn (array $row): string => trim(string_value($row['name'] ?? '')),
        $rows
    ), static fn (string $value): bool => $value !== ''));

}

function history_dashboard_card_totals(array $filters, string $today): array
{

    $base_where = bills_build_where($filters);
    $income_entry = income_current_entry();

    $outcomes_rows = SQL()->select("
        SELECT COALESCE(SUM(value), 0) AS total
        FROM view_bills
        WHERE " . $base_where . "
    ");

    $next_outcomes_rows = SQL()->select("
        SELECT COALESCE(SUM(value), 0) AS total
        FROM view_bills
        WHERE " . $base_where . "
          AND `date` >= '" . bills_escape_sql($today) . "'
    ");

    return [
        'income' => (float) ($income_entry['value'] ?? 0),
        'outcomes' => (float) ($outcomes_rows[0]['total'] ?? 0),
        'next_outcomes' => (float) ($next_outcomes_rows[0]['total'] ?? 0),
        'currently' => (float) ($income_entry['value'] ?? 0)
            - (float) ($outcomes_rows[0]['total'] ?? 0),
        'projected_balance' => (float) ($income_entry['value'] ?? 0)
            - (float) ($outcomes_rows[0]['total'] ?? 0)
            - (float) ($next_outcomes_rows[0]['total'] ?? 0),
    ];

}

function history_dashboard_view_mode($value): string
{

    $view = trim(string_value($value));

    return in_array($view, ['outcomes', 'next_outcomes'], true) ? $view : 'outcomes';

}

function history_dashboard_filters_for_view(array $filters, string $today, string $view_mode): array
{

    if ($view_mode !== 'next_outcomes') {

        return $filters;
    }

    $view_filters = $filters;
    $view_filters['data']['all'] = false;

    if (empty($view_filters['data']['da']) || $view_filters['data']['da'] < $today) {

        $view_filters['data']['da'] = $today;
    }

    return $view_filters;

}

function history_dashboard_group_series(array $filters): array
{

    $rows = SQL()->select("
        SELECT id_group, COALESCE(group_name, '-') AS label, COALESCE(SUM(value), 0) AS total
        FROM view_bills
        WHERE " . bills_build_where($filters) . "
        GROUP BY id_group, group_name
        ORDER BY total DESC, label ASC
    ");

    return array_values(array_map(
        static fn (array $row): array => [
            'name' => trim(string_value($row['label'] ?? '-')),
            'y' => round((float) ($row['total'] ?? 0), 2),
            'custom' => [
                'groupKey' => 'group_' . (int) ($row['id_group'] ?? 0),
            ],
        ],
        $rows
    ));

}

function history_dashboard_group_name_breakdown(array $filters): array
{

    $rows = SQL()->select("
        SELECT
            id_group,
            `name`,
            COALESCE(SUM(value), 0) AS total
        FROM view_bills
        WHERE " . bills_build_where($filters) . "
        GROUP BY id_group, `name`
        ORDER BY id_group ASC, total DESC, `name` ASC
    ");

    $breakdown = [];

    foreach ($rows as $row) {

        $group_id = (int) ($row['id_group'] ?? 0);
        $group_key = 'group_' . $group_id;

        if (!isset($breakdown[$group_key])) {

            $breakdown[$group_key] = [
                'categories' => [],
                'data' => [],
            ];
        }

        $breakdown[$group_key]['categories'][] = string_value($row['name'] ?? '');
        $breakdown[$group_key]['data'][] = round((float) ($row['total'] ?? 0), 2);
    }

    return $breakdown;

}

function history_dashboard_weekly_series(array $filters): array
{

    $rows = SQL()->select("
        SELECT
            YEARWEEK(`date`, 1) AS week_key,
            MIN(`date`) AS week_start,
            MAX(`date`) AS week_end,
            COALESCE(SUM(value), 0) AS total
        FROM view_bills
        WHERE " . bills_build_where($filters) . "
        GROUP BY YEARWEEK(`date`, 1)
        ORDER BY MIN(`date`) ASC
    ");

    return [
        'categories' => array_values(array_map(
            static fn (array $row): string => format(string_value($row['week_start'] ?? ''), 'd/m/y')
                . ' - ' .
                format(string_value($row['week_end'] ?? ''), 'd/m/y'),
            $rows
        )),
        'data' => array_values(array_map(
            static fn (array $row): array => [
                'y' => round((float) ($row['total'] ?? 0), 2),
                'custom' => [
                    'weekKey' => (string) ($row['week_key'] ?? ''),
                ],
            ],
            $rows
        )),
    ];

}

function history_dashboard_weekly_name_breakdown(array $filters): array
{

    $rows = SQL()->select("
        SELECT
            YEARWEEK(`date`, 1) AS week_key,
            `name`,
            COALESCE(SUM(value), 0) AS total
        FROM view_bills
        WHERE " . bills_build_where($filters) . "
        GROUP BY YEARWEEK(`date`, 1), `name`
        ORDER BY YEARWEEK(`date`, 1) ASC, total DESC, `name` ASC
    ");

    $breakdown = [];

    foreach ($rows as $row) {

        $week_key = string_value($row['week_key'] ?? '');

        if ($week_key === '') {

            continue;
        }

        if (!isset($breakdown[$week_key])) {

            $breakdown[$week_key] = [
                'categories' => [],
                'data' => [],
            ];
        }

        $breakdown[$week_key]['categories'][] = string_value($row['name'] ?? '');
        $breakdown[$week_key]['data'][] = round((float) ($row['total'] ?? 0), 2);
    }

    return $breakdown;

}
