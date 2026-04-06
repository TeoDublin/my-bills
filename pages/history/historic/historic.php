<?php

require_once __DIR__ . '/../../../includes/constants.php';
require_once __DIR__ . '/../../../includes/class.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/functions.php';

Auth()->require_auth();

$today = date('Y-m-d');
$preferences_scope = 'page:history';
$user = Auth()->user();
$user_id = (int) ($user['id'] ?? 0);
$normalize_int_range = static function ($value, int $default, int $min, int $max): int {

    $normalized = filter_var($value, FILTER_VALIDATE_INT);

    if ($normalized === false || $normalized === null) {

        return $default;
    }

    return max($min, min($max, $normalized));

};

$default_rows_per_page = $normalize_int_range(
    Preference()->get($user_id, 'rows_per_page', $preferences_scope, 20),
    20,
    5,
    200
);
$default_table_font_size = $normalize_int_range(
    Preference()->get($user_id, 'table_font_size', $preferences_scope, 12),
    12,
    10,
    40
);

$per_page = $default_rows_per_page;
$table_font_size = $default_table_font_size;

if (isset($_GET['rows_per_page'])) {

    $per_page = $normalize_int_range($_GET['rows_per_page'], $default_rows_per_page, 5, 200);
    Preference()->set($user_id, 'rows_per_page', $per_page, $preferences_scope);
}

if (isset($_GET['table_font_size'])) {

    $table_font_size = $normalize_int_range($_GET['table_font_size'], $default_table_font_size, 10, 40);
    Preference()->set($user_id, 'table_font_size', $table_font_size, $preferences_scope);
}

$group_options = bills_group_options();

$group_names_by_id = [];
foreach ($group_options as $group_option) {

    $group_names_by_id[(int) $group_option['id']] = $group_option['name'];
}

$name_rows = SQL()->select("
    SELECT DISTINCT `name`
    FROM view_bills
    WHERE user_id = " . $user_id . "
      AND `name` IS NOT NULL AND `name` <> ''
    ORDER BY `name` ASC
");
$name_options = array_values(array_filter(array_map(
    static fn (array $row): string => trim(string_value($row['name'] ?? '')),
    $name_rows
), static fn (string $value): bool => $value !== ''));

$filters = bills_normalize_filters($_GET, $today);

$current_page = max(1, (int) ($_GET['page_num'] ?? 1));
$offset = ($current_page - 1) * $per_page;

$query = bills_create_select('*')
    ->where(bills_build_where($filters))
    ->orderby('date desc, id desc')
    ->limit($per_page)
    ->offset($offset);

$results = $query->get_table();

$current_page = $results->pages > 0 ? min($current_page, $results->pages) : 1;

if ($results->pages > 0 && $offset >= $results->total) {

    $offset = ($current_page - 1) * $per_page;
    $results = bills_create_select('*')
        ->where(bills_build_where($filters))
        ->orderby('date desc, id desc')
        ->limit($per_page)
        ->offset($offset)
        ->get_table();
}

$pagination_window_start = max(1, $current_page - 2);
$pagination_window_end = $results->pages > 0 ? min($results->pages, $current_page + 2) : 1;

$selected_group_labels = array_values(array_filter(array_map(
    static fn (int $group_id): ?string => $group_names_by_id[$group_id] ?? null,
    $filters['group']
)));
$selected_name_labels = $filters['name'];

$page_state = [
    'filters' => $filters,
    'display_preferences' => [
        'rows_per_page' => $per_page,
        'table_font_size' => $table_font_size,
    ],
    'selected_group_labels' => $selected_group_labels,
    'selected_name_labels' => $selected_name_labels,
    'group_options' => $group_options,
    'name_options' => $name_options,
    'export_kinds' => bills_export_scopes(),
    'export_types' => bills_export_type_options(),
    'bill_form_defaults' => [
        'date' => $today,
        'group_id' => (int) ($group_options[0]['id'] ?? 0),
    ],
    'results' => $results,
    'pagination' => [
        'current_page' => $current_page,
        'per_page' => $per_page,
        'window_start' => $pagination_window_start,
        'window_end' => $pagination_window_end,
        'has_previous' => $current_page > 1,
        'has_next' => $results->pages > 0 && $current_page < $results->pages,
        'previous_page' => max(1, $current_page - 1),
        'next_page' => $results->pages > 0 ? min($results->pages, $current_page + 1) : 1,
    ],
];

$filters = $page_state['filters'];
$results = $page_state['results'];
$pagination = $page_state['pagination'];
$display_preferences = $page_state['display_preferences'];
$selected_group_labels = $page_state['selected_group_labels'];
$selected_name_labels = $page_state['selected_name_labels'];
$table_font_size = (int) ($display_preferences['table_font_size'] ?? 12);
$line_height = max(16, $table_font_size + 6);
$header_line_height = max(18, $table_font_size + 8);
$bill_form_defaults = $page_state['bill_form_defaults'];
$has_filter_labels = !$filters['data']['all']
    || !empty($selected_group_labels)
    || !empty($selected_name_labels);
$selected_group_names_text = implode(', ', $selected_group_labels);
$selected_group_names_summary = bills_truncate_text($selected_group_names_text, 90);
$selected_name_text = implode(', ', $selected_name_labels);
$selected_name_summary = bills_truncate_text($selected_name_text, 90);
$group_filter_active = !empty($filters['group']);
$name_filter_active = !empty($filters['name']);
$data_filter_active = !$filters['data']['all'] && (!empty($filters['data']['da']) || !empty($filters['data']['a']));
$page_numbers = $results->pages > 1 ? range($pagination['window_start'], $pagination['window_end']) : [];
?>

<div class="page-history">
    <div class="page-section-sticky">
        <div class="filter-labels d-flex flex-row align-items-center bg-light p-2<?= $has_filter_labels ? '' : ' none' ?>">
            <span class="fw-bold">APPLIED FILTERS:</span>

            <?php if (!$filters['data']['all'] && !empty($filters['data']['da'])): ?>

                <div class="filter-label bg-gray text-white">
                    <span>From: <?= htmlspecialchars(unformat_date($filters['data']['da'])) ?></span>
                    <button class="filter-label-remove" type="button" data-remove-filter="data_da" aria-label="Remove from date filter">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!$filters['data']['all'] && !empty($filters['data']['a'])): ?>

                <div class="filter-label bg-gray text-white">
                    <span>To: <?= htmlspecialchars(unformat_date($filters['data']['a'])) ?></span>
                    <button class="filter-label-remove" type="button" data-remove-filter="data_a" aria-label="Remove to date filter">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($selected_group_labels)): ?>

                <div class="filter-label bg-gray text-white">
                    <span>Group: <?= htmlspecialchars($selected_group_names_summary) ?></span>
                    <button class="filter-label-remove" type="button" data-remove-filter="group" aria-label="Remove group filter">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($selected_name_labels)): ?>

                <div class="filter-label filter-label-clients bg-gray text-white">
                    <span class="filter-label-summary">Name: <?= htmlspecialchars($selected_name_summary) ?></span>
                    <div class="filter-label-popover">
                        <div class="filter-label-popover-header">
                            <div class="filter-label-popover-title">Selected names</div>
                            <button class="filter-label-popover-close" type="button" data-close-filter-popover aria-label="Close name list">&times;</button>
                        </div>
                        <ul class="filter-label-popover-list">
                            <?php foreach ($selected_name_labels as $selected_name_label): ?>

                                <li><?= htmlspecialchars($selected_name_label) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <button class="filter-label-remove" type="button" data-remove-filter="name" aria-label="Remove name filter">&times;</button>
                </div>
            <?php endif; ?>

            <button class="btn btn-primary ms-auto" data-action="clear-filters">Clear Filters</button>
        </div>

        <div
            class="p-2 mt-2"
            data-history-actions
            data-csrf-token="<?= htmlspecialchars(csrf_token()) ?>"
        >
            <div class="d-flex gap-2 flex-wrap">
                <button
                    class="btn btn-outline-secondary"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#history_actions_collapse"
                    aria-expanded="false"
                    aria-controls="history_actions_collapse"
                >
                    Actions
                </button>

                <button
                    class="btn btn-primary"
                    type="button"
                    data-action="open-bill-create"
                >
                    Add Bill
                </button>
            </div>

            <div class="collapse mt-3" id="history_actions_collapse">
                <div class="card card-body border-0 px-0 pb-0">
                    <div class="d-flex align-items-end" style="width: 600px; max-width: 100%;">
                        <div class="w-40">
                            <select name="actions" class="form-select">
                                <option value="export">Export</option>
                                <option value="delete">Delete</option>
                            </select>
                        </div>

                        <div class="ms-2 w-40">
                            <select class="form-select" id="history_export_scope" name="export_scope">
                                <?php foreach ($page_state['export_kinds'] as $export_kind_value => $export_kind_label): ?>

                                    <option value="<?= htmlspecialchars($export_kind_value) ?>"><?= htmlspecialchars($export_kind_label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button class="btn btn-primary ms-2 w-20" type="button" data-action="run-page-action">Avvia</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-section-sticky-shell">
        <div class="page-section-sticky-toolbar d-flex justify-content-end align-items-center px-2 pt-2">
            <button
                class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#history_page_setup_modal"
                aria-label="Open table settings"
                title="Table settings"
            >
                <?= icon('gear.svg', 'primary', '18', '18') ?>
            </button>
        </div>

        <?php if (!$results->result): ?>

            <div class="card card-body mt-3 text-center"><h5>No results found</h5></div>
        <?php else: ?>

            <div class="page-section-sticky-scroll px-1">
                <table class="table table-striped table-hover text-center page-section-sticky-table mb-0" data-table-selectable>
                    <thead>
                        <tr style="font-size:<?= $table_font_size ?>px;line-height:<?= $header_line_height ?>px;">
                            <th><input type="checkbox" data-table-select-all></th>
                            <th class="w-10">id</th>
                            <th class="w-25">group</th>
                            <th class="w-30">name</th>
                            <th class="w-15">value</th>
                            <th class="w-20-date">date</th>
                            <th class="w-10"><?= icon('bin.svg', 'primary', $header_line_height, $header_line_height); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results->result as $bill): ?>

                            <tr
                                data-id="<?= (int) $bill['id'] ?>"
                                data-group-id="<?= (int) $bill['id_group'] ?>"
                                data-name="<?= htmlspecialchars(string_value($bill['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-value="<?= htmlspecialchars(number_format((float) $bill['value'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-date="<?= htmlspecialchars(string_value($bill['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                style="font-size:<?= $table_font_size ?>px;line-height:<?= $line_height ?>px;word-break:break-word;cursor:pointer;"
                            >
                                <td data-table-row-select-cell><input type="checkbox" data-table-row-select></td>
                                <td><?= (int) $bill['id'] ?></td>
                                <td><?= htmlspecialchars(string_value($bill['group_name'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars(string_value($bill['name'] ?? '')) ?></td>
                                <td><?= htmlspecialchars(number_format((float) $bill['value'], 2, ',', '.')) ?></td>
                                <td><?= htmlspecialchars($bill['date'] ? format($bill['date'], 'd/m/y') : '-') ?></td>
                                <td>
                                    <button
                                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center justify-content-center"
                                        type="button"
                                        data-action="delete-bill"
                                        data-bill-id="<?= (int) $bill['id'] ?>"
                                        aria-label="Delete bill <?= (int) $bill['id'] ?>"
                                        title="Delete bill"
                                    >
                                        <?= icon('bin.svg', 'primary', '14', '14') ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($results->pages > 1): ?>

            <div class="d-flex flex-column flex-md-row align-items-center justify-content-between px-2 py-3 gap-2">
                <div class="small text-muted">
                    Page <?= $pagination['current_page'] ?> of <?= $results->pages ?>, total rows <?= $results->total ?>
                </div>

                <nav aria-label="Table pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item<?= $pagination['has_previous'] ? '' : ' disabled' ?>">
                            <button
                                class="page-link"
                                type="button"
                                data-page-number="1"
                                <?= $pagination['has_previous'] ? '' : 'disabled' ?>
                            >
                                First
                            </button>
                        </li>

                        <li class="page-item<?= $pagination['has_previous'] ? '' : ' disabled' ?>">
                            <button
                                class="page-link"
                                type="button"
                                data-page-number="<?= $pagination['previous_page'] ?>"
                                <?= $pagination['has_previous'] ? '' : 'disabled' ?>
                            >
                                Prev
                            </button>
                        </li>

                        <?php if ($pagination['window_start'] > 1): ?>

                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>

                        <?php foreach ($page_numbers as $page_number): ?>

                            <li class="page-item<?= $page_number === $pagination['current_page'] ? ' active' : '' ?>">
                                <button
                                    class="page-link"
                                    type="button"
                                    data-page-number="<?= $page_number ?>"
                                    <?= $page_number === $pagination['current_page'] ? 'aria-current="page"' : '' ?>
                                >
                                    <?= $page_number ?>
                                </button>
                            </li>
                        <?php endforeach; ?>

                        <?php if ($pagination['window_end'] < $results->pages): ?>

                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>

                        <li class="page-item<?= $pagination['has_next'] ? '' : ' disabled' ?>">
                            <button
                                class="page-link"
                                type="button"
                                data-page-number="<?= $pagination['next_page'] ?>"
                                <?= $pagination['has_next'] ? '' : 'disabled' ?>
                            >
                                Next
                            </button>
                        </li>

                        <li class="page-item<?= $pagination['has_next'] ? '' : ' disabled' ?>">
                            <button
                                class="page-link"
                                type="button"
                                data-page-number="<?= $results->pages ?>"
                                <?= $pagination['has_next'] ? '' : 'disabled' ?>
                            >
                                Last
                            </button>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <div class="floating-menu text-center pb-5">
        <div class="content p-0 h-100">
            <div class="pt-3 p-2">
                <h6>FILTER</h6>
            </div>

            <div class="accordion p-1<?= $group_filter_active ? ' filter-accordion-active' : '' ?>" id="filter_group">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_filter_group" aria-expanded="false" aria-controls="collapse_filter_group">
                            Group
                        </button>
                    </h2>
                    <div id="collapse_filter_group" class="accordion-collapse collapse" data-bs-parent="#filter_group">
                        <div class="accordion-body">
                            <div>
                                <label for="group">Group</label>
                                <select class="form-control" id="group" multiple data-search-placeholder="groups">
                                    <?php foreach ($page_state['group_options'] as $group_option): ?>

                                        <option
                                            value="<?= (int) $group_option['id'] ?>"
                                            <?= in_array((int) $group_option['id'], $filters['group'], true) ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($group_option['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion p-1<?= $name_filter_active ? ' filter-accordion-active' : '' ?>" id="filter_name">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_filter_name" aria-expanded="false" aria-controls="collapse_filter_name">
                            Name
                        </button>
                    </h2>
                    <div id="collapse_filter_name" class="accordion-collapse collapse" data-bs-parent="#filter_name">
                        <div class="accordion-body">
                            <div>
                                <label for="name">Bill Name</label>
                                <select class="form-control" id="name" multiple data-search-placeholder="bill names">
                                    <?php foreach ($page_state['name_options'] as $name_option): ?>

                                        <option value="<?= htmlspecialchars($name_option) ?>" <?= in_array($name_option, $filters['name'], true) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($name_option) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion p-1<?= $data_filter_active ? ' filter-accordion-active' : '' ?>" id="filter_data">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_filter_data" aria-expanded="false" aria-controls="collapse_filter_data">
                            Date
                        </button>
                    </h2>
                    <div id="collapse_filter_data" class="accordion-collapse collapse" data-bs-parent="#filter_data">
                        <div class="accordion-body">
                            <div>
                                <label for="data_da">Da</label>
                                <input class="form-control" type="date" id="data_da" value="<?= htmlspecialchars($filters['data']['da']) ?>">
                            </div>
                            <div>
                                <label for="data_a">A</label>
                                <input class="form-control" type="date" id="data_a" value="<?= htmlspecialchars($filters['data']['a']) ?>">
                            </div>
                            <div class="mt-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="data_all" value="1" <?= $filters['data']['all'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="data_all">
                                        Select all
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky-bottom w-100">
            <button class="btn btn-primary w-100" data-action="apply-filters">Apply Filters</button>
        </div>
    </div>

    <div class="floating-menu-btn">
        <button class="h-100 left" type="button"><?= icon('arrow-filled-left.svg', 'primary') ?></button>
        <button class="h-100 right" type="button"><?= icon('arrow-filled-right.svg', 'primary') ?></button>
    </div>
</div>

<div class="modal fade" id="history_page_setup_modal" tabindex="-1" aria-labelledby="history_page_setup_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="history_page_setup_modal_label">Table settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="rows_per_page">Rows per page</label>
                        <input
                            class="form-control"
                            id="rows_per_page"
                            type="number"
                            min="5"
                            max="200"
                            step="1"
                            value="<?= (int) $display_preferences['rows_per_page'] ?>"
                        >
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="table_font_size">Table text size (px)</label>
                        <input
                            class="form-control"
                            id="table_font_size"
                            type="number"
                            min="10"
                            max="40"
                            step="1"
                            value="<?= (int) $display_preferences['table_font_size'] ?>"
                        >
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-action="save-setup">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="history_add_bill_modal" tabindex="-1" aria-labelledby="history_add_bill_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="history_add_bill_modal_label" data-bill-modal-title>New bill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="history_bill_group_id">Group</label>
                        <div class="d-flex gap-2 align-items-start">
                            <select class="form-select" id="history_bill_group_id" data-history-group-select data-default-value="<?= (int) $bill_form_defaults['group_id'] ?>">
                                <?php foreach ($group_options as $group_option): ?>

                                    <option
                                        value="<?= (int) $group_option['id'] ?>"
                                        <?= (int) $group_option['id'] === (int) $bill_form_defaults['group_id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($group_option['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button
                                class="btn btn-outline-secondary flex-shrink-0"
                                type="button"
                                data-action="open-group-manager"
                            >
                                Manage groups
                            </button>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="history_bill_name">Name</label>
                        <input class="form-control" id="history_bill_name" type="text" maxlength="255" placeholder="Bill name">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="history_bill_value">Value</label>
                        <input class="form-control" id="history_bill_value" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="history_bill_date">Date</label>
                        <input class="form-control" id="history_bill_date" type="date" value="<?= htmlspecialchars($bill_form_defaults['date']) ?>" data-default-value="<?= htmlspecialchars($bill_form_defaults['date']) ?>">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" data-action="save-bill" data-bill-modal-submit>Save bill</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="history_group_manage_modal" tabindex="-1" aria-labelledby="history_group_manage_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="history_group_manage_modal_label">Group management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="history_new_group_name">New group</label>
                        <div class="d-flex gap-2">
                            <input class="form-control" id="history_new_group_name" type="text" maxlength="120" placeholder="Group name">
                            <button type="button" class="btn btn-primary flex-shrink-0" data-action="create-group">Create</button>
                        </div>
                    </div>

                    <div class="col-12">
                        <hr class="my-1">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="history_manage_group_id">Edit group</label>
                        <select class="form-select" id="history_manage_group_id" data-history-group-select>
                            <?php foreach ($group_options as $group_option): ?>

                                <option value="<?= (int) $group_option['id'] ?>"><?= htmlspecialchars($group_option['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="history_manage_group_name">New name</label>
                        <div class="d-flex gap-2">
                            <input class="form-control" id="history_manage_group_name" type="text" maxlength="120" placeholder="New group name">
                            <button type="button" class="btn btn-outline-primary flex-shrink-0" data-action="rename-group">Save changes</button>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-outline-primary" data-action="delete-group">Delete group</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="history_export_setup_modal" tabindex="-1" aria-labelledby="history_export_setup_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="history_export_setup_modal_label">Configure export</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="history_export_type">Export type</label>
                <select class="form-select" id="history_export_type" name="export_type">
                    <?php foreach ($page_state['export_types'] as $export_type_value => $export_type_label): ?>

                        <option value="<?= htmlspecialchars($export_type_value) ?>"><?= htmlspecialchars($export_type_label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-action="confirm-export-setup">Continue</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="history_grid_import_modal" tabindex="-1" aria-labelledby="history_grid_import_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="history_grid_import_modal_label">Import XLSX file</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="grid_import_file">Rows to export</label>
                <input class="form-control" id="grid_import_file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                <div class="form-text">The source module structure is preserved. This page uses the selected and filtered scopes.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-action="confirm-grid-export">Continue</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="history_action_progress_modal" tabindex="-1" aria-labelledby="history_action_progress_modal_label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="history_action_progress_modal_label">Action progress</h5>
            </div>
            <div class="modal-body">
                <div class="mb-2 fw-semibold" data-progress-title>Preparing export</div>
                <div class="progress" role="progressbar" aria-label="Export progress" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" data-progress-bar style="width: 0%">0%</div>
                </div>
                <div class="small text-primary mt-3 d-none" data-progress-error></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary d-none" data-action="close-progress">Close</button>
                <a class="btn btn-primary d-none" href="#" data-action="download-export">Download file</a>
            </div>
        </div>
    </div>
</div>
