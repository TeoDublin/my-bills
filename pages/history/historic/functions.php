<?php

require_once __DIR__ . '/../../../includes/constants.php';
require_once __DIR__ . '/../../../includes/class.php';
require_once __DIR__ . '/../../../includes/functions.php';

function bills_blank_filters(?string $today = null): array
{

    $today ??= date('Y-m-d');

    return [
        'group' => [],
        'name' => [],
        'data' => [
            'all' => true,
            'da' => $today,
            'a' => $today,
        ],
    ];

}

function bills_reference_income_day(): ?int
{

    $user_id = auth_user_id();

    if ($user_id <= 0) {

        return null;
    }

    $rows = SQL()->select("
        SELECT day
        FROM incoming
        WHERE user_id = " . $user_id . "
        ORDER BY id ASC
        LIMIT 1
    ");

    $day = filter_var($rows[0]['day'] ?? null, FILTER_VALIDATE_INT);

    if ($day === false || $day === null || $day < 1 || $day > 31) {

        return null;
    }

    return (int) $day;

}

function bills_reference_day_in_month(int $year, int $month, int $day): DateTimeImmutable
{

    $last_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $normalized_day = min($day, $last_day);

    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $normalized_day));

}

function bills_reference_period(?string $today = null): ?array
{

    $income_day = bills_reference_income_day();

    if ($income_day === null) {

        return null;
    }

    $today_date = $today !== null && $today !== ''
        ? new DateTimeImmutable($today)
        : new DateTimeImmutable('today');

    $current_anchor = bills_reference_day_in_month(
        (int) $today_date->format('Y'),
        (int) $today_date->format('n'),
        $income_day
    );

    if ($today_date < $current_anchor) {

        $previous_month = $today_date->modify('first day of previous month');
        $start = bills_reference_day_in_month(
            (int) $previous_month->format('Y'),
            (int) $previous_month->format('n'),
            $income_day
        );
    } else {

        $start = $current_anchor;
    }

    $next_month = $start->modify('first day of next month');
    $end = bills_reference_day_in_month(
        (int) $next_month->format('Y'),
        (int) $next_month->format('n'),
        $income_day
    );

    return [
        'da' => $start->format('Y-m-d'),
        'a' => $end->format('Y-m-d'),
    ];

}

function bills_default_filters(?string $today = null): array
{

    $filters = bills_blank_filters($today);
    $reference_period = bills_reference_period($today);

    if ($reference_period === null) {

        return $filters;
    }

    $filters['data']['all'] = false;
    $filters['data']['da'] = $reference_period['da'];
    $filters['data']['a'] = $reference_period['a'];

    return $filters;

}

function bills_normalize_filters(array $source, ?string $today = null): array
{

    $today ??= date('Y-m-d');

    if (!bills_has_filter_params($source)) {

        return bills_default_filters($today);
    }

    $has_date_filters = array_key_exists('data_all', $source)
        || array_key_exists('data_da', $source)
        || array_key_exists('data_a', $source);

    return [
        'group' => bills_normalize_int_list($source['group'] ?? []),
        'name' => bills_normalize_string_list($source['name'] ?? []),
        'data' => [
            'all' => isset($source['data_all']) && string_value($source['data_all']) === '1'
                ? true
                : !array_key_exists('data_da', $source) && !array_key_exists('data_a', $source),
            'da' => array_key_exists('data_da', $source)
                ? (bills_is_clear_marker($source['data_da']) ? '' : (bills_is_valid_date($source['data_da']) ? string_value($source['data_da']) : ''))
                : ($has_date_filters ? '' : $today),
            'a' => array_key_exists('data_a', $source)
                ? (bills_is_clear_marker($source['data_a']) ? '' : (bills_is_valid_date($source['data_a']) ? string_value($source['data_a']) : ''))
                : ($has_date_filters ? '' : $today),
        ],
    ];

}

function bills_build_where(array $filters, array $options = []): string
{

    $user_id = (int) ($options['user_id'] ?? auth_user_id_or_fail());
    $where_parts = ['1=1', 'user_id = ' . $user_id];

    if (!($filters['data']['all'] ?? false)) {

        if (!empty($filters['data']['da'])) {

            $where_parts[] = "`date` >= '" . bills_escape_sql(string_value($filters['data']['da'])) . "'";
        }

        if (!empty($filters['data']['a'])) {

            $where_parts[] = "`date` <= '" . bills_escape_sql(string_value($filters['data']['a'])) . "'";
        }
    }

    if (!empty($filters['group'])) {

        $where_parts[] = "id_group IN(" . implode(',', array_map('intval', $filters['group'])) . ")";
    }

    if (!empty($filters['name'])) {

        $where_parts[] = "`name` IN(" . bills_quote_sql_list($filters['name']) . ")";
    }

    $selected_ids = array_values(array_filter(array_map('intval', $options['selected_ids'] ?? []), static fn (int $id): bool => $id > 0));
    if ($selected_ids !== []) {

        $where_parts[] = "id IN(" . implode(',', $selected_ids) . ")";
    }

    return implode(' AND ', $where_parts);

}

function bills_create_select(string $select = '*'): Select
{

    return Select($select)->from('view_bills')->orderby('date desc, id desc');

}

function bills_group_options(): array
{

    $user_id = auth_user_id_or_fail();

    return Select('*')
        ->from('bills_groups')
        ->where('user_id = ' . $user_id)
        ->orderby('name asc')
        ->get();

}

function bills_group_options_payload(): array
{

    return array_values(array_map(
        static fn (array $group): array => [
            'id' => (int) ($group['id'] ?? 0),
            'name' => trim(string_value($group['name'] ?? '')),
        ],
        bills_group_options()
    ));

}

function bills_fetch_rows(array $filters, array $options = [], ?int $limit = null, ?int $offset = null, string $select = '*'): array
{

    $query = bills_create_select($select)->where(bills_build_where($filters, $options));

    if ($limit !== null) {

        $query->limit($limit);
    }

    if ($offset !== null) {

        $query->offset($offset);
    }

    return $query->get();

}

function bills_count_rows(array $filters, array $options = []): int
{

    $rows = SQL()->select("
        SELECT COUNT(id) AS total
        FROM view_bills
        WHERE " . bills_build_where($filters, $options)
    );

    return (int) ($rows[0]['total'] ?? 0);

}

function bills_fetch_date_groups(array $filters, array $options = [], ?int $limit = null, ?int $offset = null): array
{

    $query = bills_create_select('date, COUNT(*) AS count')
        ->where(bills_build_where($filters, $options))
        ->groupby('date')
        ->orderby('date asc');

    if ($limit !== null) {

        $query->limit($limit);
    }

    if ($offset !== null) {

        $query->offset($offset);
    }

    return $query->get();

}

function bills_count_date_groups(array $filters, array $options = []): int
{

    $rows = SQL()->select("
        SELECT COUNT(*) AS total
        FROM (
            SELECT `date`
            FROM view_bills
            WHERE " . bills_build_where($filters, $options) . "
            GROUP BY `date`
        ) grouped_bills
    ");

    return (int) ($rows[0]['total'] ?? 0);

}

function bills_export_scopes(): array
{

    return [
        'selected' => 'Selected',
        'filter' => 'With Applied Filter',
    ];

}

function bills_export_definition_keys(): array
{

    $definition_paths = glob(__DIR__ . '/export/*.php');

    if ($definition_paths === false || $definition_paths === []) {

        return [];
    }

    $definition_keys = [];

    foreach ($definition_paths as $definition_path) {

        $definition_keys[] = basename($definition_path, '.php');
    }

    sort($definition_keys);

    return array_values(array_unique(array_filter($definition_keys, static fn (string $definition_key): bool => $definition_key !== '')));

}

function bills_export_type_options(): array
{

    $options = [];

    foreach (bills_export_definition_keys() as $definition_key) {

        $options['pages/history/historic/export/' . $definition_key . '.php'] = strtoupper($definition_key);
    }

    return $options;

}

function bills_create_export_payload_from_request(array $input, array $files = []): array
{

    $scope = trim(array_string($input, 'scope'));
    $export_type = pathinfo(trim(array_string($input, 'handler')), PATHINFO_FILENAME);
    $available_scopes = array_keys(bills_export_scopes());
    $available_export_types = bills_export_definition_keys();

    if (!in_array($scope, $available_scopes, true)) {

        throw new InvalidArgumentException('Unsupported export scope.');
    }

    if (!in_array($export_type, $available_export_types, true)) {

        throw new InvalidArgumentException('Unsupported export type.');
    }

    $filters_payload = json_decode(array_string($input, 'filters', '{}'), true);
    $today = date('Y-m-d');
    $filters = $scope === 'filter'
        ? bills_normalize_filters(is_array($filters_payload) ? $filters_payload : [], $today)
        : bills_blank_filters($today);

    $selected_ids_payload = json_decode(array_string($input, 'selected_ids', '[]'), true);
    $selected_ids = array_values(array_filter(array_map(
        'intval',
        is_array($selected_ids_payload) ? $selected_ids_payload : []
    ), static fn (int $id): bool => $id > 0));

    if ($scope === 'selected' && $selected_ids === []) {

        throw new InvalidArgumentException('Select at least one row first.');
    }

    return [
        'scope' => $scope,
        'export_type' => $export_type,
        'filters' => $filters,
        'selected_ids' => $selected_ids,
    ];

}

function bills_create_bulk_action_payload_from_request(array $input): array
{

    $scope = trim(array_string($input, 'scope'));
    $available_scopes = array_keys(bills_export_scopes());

    if (!in_array($scope, $available_scopes, true)) {

        throw new InvalidArgumentException('Unsupported action scope.');
    }

    $filters_payload = json_decode(array_string($input, 'filters', '{}'), true);
    $today = date('Y-m-d');
    $filters = $scope === 'filter'
        ? bills_normalize_filters(is_array($filters_payload) ? $filters_payload : [], $today)
        : bills_blank_filters($today);

    $selected_ids_payload = json_decode(array_string($input, 'selected_ids', '[]'), true);
    $selected_ids = array_values(array_filter(array_map(
        'intval',
        is_array($selected_ids_payload) ? $selected_ids_payload : []
    ), static fn (int $id): bool => $id > 0));

    if ($scope === 'selected' && $selected_ids === []) {

        throw new InvalidArgumentException('Select at least one row first.');
    }

    return [
        'scope' => $scope,
        'filters' => $filters,
        'selected_ids' => $selected_ids,
    ];

}

function bills_export_query_options(array $payload): array
{

    return [
        'selected_ids' => $payload['selected_ids'] ?? [],
    ];

}

function bills_async_download_name(string $definition_key, string $extension = 'xlsx'): string
{

    $normalized_extension = strtolower(trim($extension));
    $normalized_extension = $normalized_extension !== '' ? $normalized_extension : 'xlsx';

    return 'bills-' . strtolower(trim($definition_key)) . '-' . date('Ymd-His') . '.' . $normalized_extension;

}

function bills_normalize_string_list($value): array
{

    if (bills_is_clear_marker($value)) {

        return [];
    }

    $values = is_array($value) ? $value : ($value !== null && $value !== '' ? [$value] : []);

    return array_values(array_filter(array_map(
        static fn ($item): string => trim(string_value($item)),
        $values
    ), static fn (string $item): bool => $item !== '' && !bills_is_clear_marker($item)));

}

function bills_normalize_int_list($value): array
{

    if (bills_is_clear_marker($value)) {

        return [];
    }

    $values = is_array($value) ? $value : ($value !== null && $value !== '' ? [$value] : []);

    return array_values(array_filter(array_map(
        static fn ($item): int => (int) $item,
        $values
    ), static fn (int $item): bool => $item > 0));

}

function bills_is_clear_marker($value): bool
{

    return is_string($value) && trim($value) === '__clear__';

}

function bills_has_filter_params(array $source): bool
{

    foreach (['group', 'name', 'data_all', 'data_da', 'data_a'] as $key) {

        if (array_key_exists($key, $source)) {

            return true;
        }
    }

    return false;

}

function bills_is_valid_date($value): bool
{

    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;

}

function bills_escape_sql(string $value): string
{

    return str_replace("'", "\\'", $value);

}

function bills_quote_sql_list(array $values): string
{

    return implode(',', array_map(
        static fn ($item): string => "'" . bills_escape_sql(string_value($item)) . "'",
        $values
    ));

}

function bills_truncate_text(string $text, int $max_characters): string
{

    if ($max_characters <= 0) {

        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {

        if (mb_strlen($text) <= $max_characters) {

            return $text;
        }

        return rtrim(mb_substr($text, 0, max(1, $max_characters - 3))) . '...';
    }

    if (strlen($text) <= $max_characters) {

        return $text;
    }

    return rtrim(substr($text, 0, max(1, $max_characters - 3))) . '...';

}
