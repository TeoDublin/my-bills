<?php

require_once __DIR__ . '/../../../includes/constants.php';
require_once __DIR__ . '/../../../includes/class.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../historic/functions.php';

function extra_income_blank_filters(?string $today = null): array
{

    $today ??= date('Y-m-d');

    return [
        'name' => [],
        'data' => [
            'all' => true,
            'da' => $today,
            'a' => $today,
        ],
    ];

}

function extra_income_default_filters(?string $today = null): array
{

    return bills_default_filters($today);

}

function extra_income_normalize_filters(array $source, ?string $today = null): array
{

    $today ??= date('Y-m-d');

    if (!extra_income_has_filter_params($source)) {

        return extra_income_default_filters($today);
    }

    $has_date_filters = array_key_exists('data_all', $source)
        || array_key_exists('data_da', $source)
        || array_key_exists('data_a', $source);

    return [
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

function extra_income_build_where(array $filters, array $options = []): string
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

    if (!empty($filters['name'])) {

        $where_parts[] = "`name` IN(" . bills_quote_sql_list($filters['name']) . ")";
    }

    $selected_ids = array_values(array_filter(array_map('intval', $options['selected_ids'] ?? []), static fn (int $id): bool => $id > 0));

    if ($selected_ids !== []) {

        $where_parts[] = "id IN(" . implode(',', $selected_ids) . ")";
    }

    return implode(' AND ', $where_parts);

}

function extra_income_create_select(string $select = '*'): Select
{

    return Select($select)->from('extra_incomes')->orderby('date desc, id desc');

}

function extra_income_fetch_rows(array $filters, array $options = [], ?int $limit = null, ?int $offset = null, string $select = '*'): array
{

    $query = extra_income_create_select($select)->where(extra_income_build_where($filters, $options));

    if ($limit !== null) {

        $query->limit($limit);
    }

    if ($offset !== null) {

        $query->offset($offset);
    }

    return $query->get();

}

function extra_income_count_rows(array $filters, array $options = []): int
{

    $rows = SQL()->select("
        SELECT COUNT(id) AS total
        FROM extra_incomes
        WHERE " . extra_income_build_where($filters, $options)
    );

    return (int) ($rows[0]['total'] ?? 0);

}

function extra_income_action_scopes(): array
{

    return [
        'selected' => 'Selected',
        'filter' => 'With Applied Filter',
    ];

}

function extra_income_create_bulk_action_payload_from_request(array $input): array
{

    $scope = trim(array_string($input, 'scope'));
    $available_scopes = array_keys(extra_income_action_scopes());

    if (!in_array($scope, $available_scopes, true)) {

        throw new InvalidArgumentException('Unsupported action scope.');
    }

    $today = date('Y-m-d');
    $filters_payload = json_decode(array_string($input, 'filters', '{}'), true);
    $filters = $scope === 'filter'
        ? extra_income_normalize_filters(is_array($filters_payload) ? $filters_payload : [], $today)
        : extra_income_blank_filters($today);

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

function extra_income_has_filter_params(array $source): bool
{

    foreach (['name', 'data_all', 'data_da', 'data_a'] as $key) {

        if (array_key_exists($key, $source)) {

            return true;
        }
    }

    return false;

}

