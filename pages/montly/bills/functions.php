<?php

require_once __DIR__ . '/../../../includes/constants.php';
require_once __DIR__ . '/../../../includes/class.php';
require_once __DIR__ . '/../../../includes/functions.php';

function montly_blank_filters(): array
{

    return [
        'group' => [],
        'name' => [],
        'day' => [
            'all' => true,
            'from' => '',
            'to' => '',
        ],
    ];

}

function montly_default_filters(): array
{

    return montly_blank_filters();

}

function montly_normalize_filters(array $source): array
{

    if (!montly_has_filter_params($source)) {

        return montly_default_filters();
    }

    $has_day_filters = array_key_exists('day_all', $source)
        || array_key_exists('day_from', $source)
        || array_key_exists('day_to', $source);

    return [
        'group' => montly_normalize_int_list($source['group'] ?? []),
        'name' => montly_normalize_string_list($source['name'] ?? []),
        'day' => [
            'all' => isset($source['day_all']) && string_value($source['day_all']) === '1'
                ? true
                : !array_key_exists('day_from', $source) && !array_key_exists('day_to', $source),
            'from' => array_key_exists('day_from', $source)
                ? (montly_is_clear_marker($source['day_from']) ? '' : montly_normalize_day($source['day_from']))
                : ($has_day_filters ? '' : ''),
            'to' => array_key_exists('day_to', $source)
                ? (montly_is_clear_marker($source['day_to']) ? '' : montly_normalize_day($source['day_to']))
                : ($has_day_filters ? '' : ''),
        ],
    ];

}

function montly_build_where(array $filters, array $options = []): string
{

    $user_id = (int) ($options['user_id'] ?? auth_user_id_or_fail());
    $where_parts = ['1=1', 'user_id = ' . $user_id];

    if (!($filters['day']['all'] ?? false)) {

        if (!empty($filters['day']['from'])) {

            $where_parts[] = "`day` >= " . (int) $filters['day']['from'];
        }

        if (!empty($filters['day']['to'])) {

            $where_parts[] = "`day` <= " . (int) $filters['day']['to'];
        }
    }

    if (!empty($filters['group'])) {

        $where_parts[] = "id_group IN(" . implode(',', array_map('intval', $filters['group'])) . ")";
    }

    if (!empty($filters['name'])) {

        $where_parts[] = "`name` IN(" . montly_quote_sql_list($filters['name']) . ")";
    }

    $selected_ids = array_values(array_filter(array_map('intval', $options['selected_ids'] ?? []), static fn (int $id): bool => $id > 0));
    if ($selected_ids !== []) {

        $where_parts[] = "id IN(" . implode(',', $selected_ids) . ")";
    }

    return implode(' AND ', $where_parts);

}

function montly_create_select(string $select = '*'): Select
{

    return Select($select)->from('view_montly_bills')->orderby('day desc, id desc');

}

function montly_group_options(): array
{

    $user_id = auth_user_id_or_fail();

    return Select('*')
        ->from('bills_groups')
        ->where('user_id = ' . $user_id)
        ->orderby('name asc')
        ->get();

}

function montly_group_options_payload(): array
{

    return array_values(array_map(
        static fn (array $group): array => [
            'id' => (int) ($group['id'] ?? 0),
            'name' => trim(string_value($group['name'] ?? '')),
        ],
        montly_group_options()
    ));

}

function montly_fetch_rows(array $filters, array $options = [], ?int $limit = null, ?int $offset = null, string $select = '*'): array
{

    $query = montly_create_select($select)->where(montly_build_where($filters, $options));

    if ($limit !== null) {

        $query->limit($limit);
    }

    if ($offset !== null) {

        $query->offset($offset);
    }

    return $query->get();

}

function montly_count_rows(array $filters, array $options = []): int
{

    $rows = SQL()->select("
        SELECT COUNT(id) AS total
        FROM view_montly_bills
        WHERE " . montly_build_where($filters, $options)
    );

    return (int) ($rows[0]['total'] ?? 0);

}

function montly_action_scopes(): array
{

    return [
        'selected' => 'Selected',
        'filter' => 'With Applied Filter',
    ];

}

function montly_create_bulk_action_payload_from_request(array $input): array
{

    $scope = trim(array_string($input, 'scope'));
    $available_scopes = array_keys(montly_action_scopes());

    if (!in_array($scope, $available_scopes, true)) {

        throw new InvalidArgumentException('Unsupported action scope.');
    }

    $filters_payload = json_decode(array_string($input, 'filters', '{}'), true);
    $filters = $scope === 'filter'
        ? montly_normalize_filters(is_array($filters_payload) ? $filters_payload : [])
        : montly_blank_filters();

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

function montly_normalize_string_list($value): array
{

    if (montly_is_clear_marker($value)) {

        return [];
    }

    $values = is_array($value) ? $value : ($value !== null && $value !== '' ? [$value] : []);

    return array_values(array_filter(array_map(
        static fn ($item): string => trim(string_value($item)),
        $values
    ), static fn (string $item): bool => $item !== '' && !montly_is_clear_marker($item)));

}

function montly_normalize_int_list($value): array
{

    if (montly_is_clear_marker($value)) {

        return [];
    }

    $values = is_array($value) ? $value : ($value !== null && $value !== '' ? [$value] : []);

    return array_values(array_filter(array_map(
        static fn ($item): int => (int) $item,
        $values
    ), static fn (int $item): bool => $item > 0));

}

function montly_normalize_day($value): string
{

    $day = filter_var($value, FILTER_VALIDATE_INT);

    if ($day === false || $day === null || $day < 1 || $day > 31) {

        return '';
    }

    return strval($day);

}

function montly_day_is_valid($value): bool
{

    return montly_normalize_day($value) !== '';

}

function montly_is_valid_date($value): bool
{

    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;

}

function montly_is_clear_marker($value): bool
{

    return is_string($value) && trim($value) === '__clear__';

}

function montly_has_filter_params(array $source): bool
{

    foreach (['group', 'name', 'day_all', 'day_from', 'day_to'] as $key) {

        if (array_key_exists($key, $source)) {

            return true;
        }
    }

    return false;

}

function montly_escape_sql(string $value): string
{

    return str_replace("'", "\\'", $value);

}

function montly_quote_sql_list(array $values): string
{

    return implode(',', array_map(
        static fn ($item): string => "'" . montly_escape_sql(string_value($item)) . "'",
        $values
    ));

}

function montly_truncate_text(string $text, int $max_characters): string
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
