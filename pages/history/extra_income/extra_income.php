<?php

require_once __DIR__ . '/../../../includes/constants.php';
require_once __DIR__ . '/../../../includes/class.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/functions.php';

Auth()->require_auth();

$today = date('Y-m-d');
$preferences_scope = 'page:history:extra_income';
$user_id = auth_user_id_or_fail();
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

$name_rows = SQL()->select("
    SELECT DISTINCT `name`
    FROM extra_incomes
    WHERE user_id = " . $user_id . "
      AND `name` IS NOT NULL AND `name` <> ''
    ORDER BY `name` ASC
");
$name_options = array_values(array_filter(array_map(
    static fn (array $row): string => trim(string_value($row['name'] ?? '')),
    $name_rows
), static fn (string $value): bool => $value !== ''));

$filters = extra_income_normalize_filters($_GET, $today);

$current_page = max(1, (int) ($_GET['page_num'] ?? 1));
$offset = ($current_page - 1) * $per_page;

$query = extra_income_create_select('*')
    ->where(extra_income_build_where($filters))
    ->orderby('date desc, id desc')
    ->limit($per_page)
    ->offset($offset);

$results = $query->get_table();

$current_page = $results->pages > 0 ? min($current_page, $results->pages) : 1;

if ($results->pages > 0 && $offset >= $results->total) {

    $offset = ($current_page - 1) * $per_page;
    $results = extra_income_create_select('*')
        ->where(extra_income_build_where($filters))
        ->orderby('date desc, id desc')
        ->limit($per_page)
        ->offset($offset)
        ->get_table();
}

$pagination_window_start = max(1, $current_page - 2);
$pagination_window_end = $results->pages > 0 ? min($results->pages, $current_page + 2) : 1;
$selected_name_labels = $filters['name'];
$selected_name_summary = bills_truncate_text(implode(', ', $selected_name_labels), 90);
$has_filter_labels = !$filters['data']['all'] || !empty($selected_name_labels);
$data_filter_active = !$filters['data']['all'] && (!empty($filters['data']['da']) || !empty($filters['data']['a']));
$name_filter_active = !empty($filters['name']);
$line_height = max(16, $table_font_size + 6);
$header_line_height = max(18, $table_font_size + 8);
$page_numbers = $results->pages > 1 ? range($pagination_window_start, $pagination_window_end) : [];
?>

<div class="page-history page-extra-income">
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

        <div class="p-2 mt-2" data-extra-income-actions data-csrf-token="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="d-flex gap-2 flex-wrap">
                <button
                    class="btn btn-outline-secondary"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#extra_income_actions_collapse"
                    aria-expanded="false"
                    aria-controls="extra_income_actions_collapse"
                >
                    Actions
                </button>

                <button class="btn btn-primary" type="button" data-action="open-income-create">Add income</button>
            </div>

            <div class="collapse mt-3" id="extra_income_actions_collapse">
                <div class="card card-body border-0 px-0 pb-0">
                    <div class="d-flex align-items-end" style="width: 420px; max-width: 100%;">
                        <div class="w-md-50">
                            <select name="actions" class="form-select">
                                <option value="delete">Delete</option>
                            </select>
                        </div>

                        <div class="ms-2 w-md-50">
                            <select class="form-select" id="extra_income_action_scope" name="action_scope">
                                <?php foreach (extra_income_action_scopes() as $scope_value => $scope_label): ?>
                                    <option value="<?= htmlspecialchars($scope_value) ?>"><?= htmlspecialchars($scope_label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button class="btn btn-primary ms-2" type="button" data-action="run-page-action">Run</button>
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
                data-bs-target="#extra_income_page_setup_modal"
                aria-label="Open table settings"
                title="Table settings"
            >
                <?= icon('gear.svg', 'primary', '18', '18') ?>
            </button>
        </div>

        <div class="page-section-sticky-scroll">
            <table class="table table-hover align-middle mb-0" data-table-selectable>
                <thead>
                    <tr style="font-size: <?= (int) $table_font_size ?>px; line-height: <?= (int) $header_line_height ?>px;">
                        <th class="text-center" style="width: 44px;">
                            <input type="checkbox" data-table-select-all>
                        </th>
                        <th>Name</th>
                        <th style="width: 140px;">Value</th>
                        <th style="width: 160px;">Date</th>
                        <th class="text-center" style="width: 60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results->result as $row): ?>
                        <tr
                            data-id="<?= (int) $row['id'] ?>"
                            data-name="<?= htmlspecialchars(string_value($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-value="<?= htmlspecialchars(string_value($row['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-date="<?= htmlspecialchars(string_value($row['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            style="font-size: <?= (int) $table_font_size ?>px; line-height: <?= (int) $line_height ?>px;"
                        >
                            <td class="text-center" data-table-row-select-cell>
                                <input type="checkbox" data-table-row-select>
                            </td>
                            <td><?= htmlspecialchars(string_value($row['name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars(number_format((float) ($row['value'] ?? 0), 2, ',', '.')) ?></td>
                            <td><?= htmlspecialchars(unformat_date(string_value($row['date'] ?? ''))) ?></td>
                            <td class="text-center">
                                <button class="btn btn-link p-0" type="button" data-action="delete-income" data-id="<?= (int) $row['id'] ?>" aria-label="Delete income">
                                    <?= icon('bin.svg', 'danger') ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($results->result === []): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">No incomes found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($results->pages > 1): ?>
            <div class="page-section-sticky-toolbar d-flex align-items-center justify-content-between px-2 py-2">
                <div style="font-size: <?= (int) $table_font_size ?>px; line-height: <?= (int) $line_height ?>px;">
                    Page <?= (int) $current_page ?> of <?= (int) $results->pages ?>
                </div>
                <nav aria-label="Extra income pagination">
                    <ul class="pagination mb-0">
                        <li class="page-item<?= $current_page <= 1 ? ' disabled' : '' ?>">
                            <button class="page-link" type="button" data-page-number="<?= (int) max(1, $current_page - 1) ?>">Previous</button>
                        </li>
                        <?php foreach ($page_numbers as $page_number): ?>
                            <li class="page-item<?= $page_number === $current_page ? ' active' : '' ?>">
                                <button class="page-link" type="button" data-page-number="<?= (int) $page_number ?>"><?= (int) $page_number ?></button>
                            </li>
                        <?php endforeach; ?>
                        <li class="page-item<?= $current_page >= $results->pages ? ' disabled' : '' ?>">
                            <button class="page-link" type="button" data-page-number="<?= (int) min($results->pages, $current_page + 1) ?>">Next</button>
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

            <div class="accordion p-1<?= $name_filter_active ? ' filter-accordion-active' : '' ?>" id="extra_income_filter_name">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_extra_income_filter_name" aria-expanded="false" aria-controls="collapse_extra_income_filter_name">
                            Name
                        </button>
                    </h2>
                    <div id="collapse_extra_income_filter_name" class="accordion-collapse collapse" data-bs-parent="#extra_income_filter_name">
                        <div class="accordion-body">
                            <label for="extra_income_name">Income name</label>
                            <select class="form-control" id="extra_income_name" multiple data-search-placeholder="income names">
                                <?php foreach ($name_options as $name_option): ?>
                                    <option value="<?= htmlspecialchars($name_option) ?>" <?= in_array($name_option, $filters['name'], true) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name_option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion p-1<?= $data_filter_active ? ' filter-accordion-active' : '' ?>" id="extra_income_filter_data">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_extra_income_filter_data" aria-expanded="false" aria-controls="collapse_extra_income_filter_data">
                            Date
                        </button>
                    </h2>
                    <div id="collapse_extra_income_filter_data" class="accordion-collapse collapse" data-bs-parent="#extra_income_filter_data">
                        <div class="accordion-body">
                            <div>
                                <label for="data_da">From</label>
                                <input class="form-control" type="date" id="data_da" value="<?= htmlspecialchars($filters['data']['da']) ?>">
                            </div>
                            <div>
                                <label for="data_a">To</label>
                                <input class="form-control" type="date" id="data_a" value="<?= htmlspecialchars($filters['data']['a']) ?>">
                            </div>
                            <div class="mt-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="data_all" value="1" <?= $filters['data']['all'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="data_all">Select all</label>
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

<div class="modal fade" id="extra_income_page_setup_modal" tabindex="-1" aria-labelledby="extra_income_page_setup_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="extra_income_page_setup_modal_label">Table settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="rows_per_page">Rows per page</label>
                    <input class="form-control" id="rows_per_page" type="number" min="5" max="200" step="1" value="<?= (int) $per_page ?>">
                </div>
                <div>
                    <label class="form-label" for="table_font_size">Text size</label>
                    <input class="form-control" id="table_font_size" type="number" min="10" max="40" step="1" value="<?= (int) $table_font_size ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-action="save-setup">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="extra_income_add_modal" tabindex="-1" aria-labelledby="extra_income_add_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="extra_income_add_modal_label" data-income-modal-title>New income</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="extra_income_modal_name">Name</label>
                    <input class="form-control" id="extra_income_modal_name" type="text" maxlength="255" placeholder="Income name">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="extra_income_modal_value">Value</label>
                    <input class="form-control" id="extra_income_modal_value" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
                </div>
                <div>
                    <label class="form-label" for="extra_income_modal_date">Date</label>
                    <input class="form-control" id="extra_income_modal_date" type="date" value="<?= htmlspecialchars($today) ?>" data-default-value="<?= htmlspecialchars($today) ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" data-action="save-income" data-income-modal-submit>Save income</button>
            </div>
        </div>
    </div>
</div>
