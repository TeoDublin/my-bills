<?php

require_once __DIR__ . '/../../../includes/constants.php';
require_once __DIR__ . '/../../../includes/class.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/functions.php';

Auth()->require_auth();

$today = date('Y-m-d');
$group_options = bills_group_options();
$group_names_by_id = [];

foreach ($group_options as $group_option) {

    $group_names_by_id[(int) $group_option['id']] = $group_option['name'];
}

$filters = bills_normalize_filters($_GET, $today);
$view_mode = history_dashboard_view_mode($_GET['view'] ?? 'outcomes');
$view_filters = history_dashboard_filters_for_view($filters, $today, $view_mode);
$name_options = history_dashboard_name_options();
$selected_group_labels = array_values(array_filter(array_map(
    static fn (int $group_id): ?string => $group_names_by_id[$group_id] ?? null,
    $filters['group']
)));
$selected_name_labels = $filters['name'];
$has_filter_labels = !$filters['data']['all']
    || !empty($selected_group_labels)
    || !empty($selected_name_labels);
$selected_group_names_summary = bills_truncate_text(implode(', ', $selected_group_labels), 90);
$selected_name_summary = bills_truncate_text(implode(', ', $selected_name_labels), 90);
$group_filter_active = !empty($filters['group']);
$name_filter_active = !empty($filters['name']);
$data_filter_active = !$filters['data']['all'] && (!empty($filters['data']['da']) || !empty($filters['data']['a']));
$cards = history_dashboard_card_totals($filters, $today);
$group_series = history_dashboard_group_series($view_filters);
$group_name_breakdown = history_dashboard_group_name_breakdown($view_filters);
$weekly_series = history_dashboard_weekly_series($view_filters);
$weekly_name_breakdown = history_dashboard_weekly_name_breakdown($view_filters);
?>

<div class="page-history-dashboard" data-history-dashboard>
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
    </div>

    <div class="history-dashboard-grid">
        <div class="history-dashboard-cards">
            <div class="history-dashboard-card">
                <span>Income</span>
                <strong><?= htmlspecialchars(number_format($cards['income'], 2, ',', '.')) ?></strong>
            </div>
            <button class="history-dashboard-card history-dashboard-card-button<?= $view_mode === 'outcomes' ? ' is-active' : '' ?>" type="button" data-action="set-view" data-view="outcomes">
                <span>Outcomes</span>
                <strong><?= htmlspecialchars(number_format($cards['outcomes'], 2, ',', '.')) ?></strong>
            </button>
            <button class="history-dashboard-card history-dashboard-card-button<?= $view_mode === 'next_outcomes' ? ' is-active' : '' ?>" type="button" data-action="set-view" data-view="next_outcomes">
                <span>Next outcomes</span>
                <strong><?= htmlspecialchars(number_format($cards['next_outcomes'], 2, ',', '.')) ?></strong>
            </button>
            <div class="history-dashboard-card">
                <span>Currently</span>
                <strong><?= htmlspecialchars(number_format($cards['currently'], 2, ',', '.')) ?></strong>
            </div>
            <div class="history-dashboard-card">
                <span>Projected balance</span>
                <strong><?= htmlspecialchars(number_format($cards['projected_balance'], 2, ',', '.')) ?></strong>
            </div>
        </div>

        <div class="history-dashboard-chart-card">
            <div class="d-flex justify-content-end mb-2">
                <button class="btn btn-outline-secondary btn-sm d-none" type="button" data-action="reset-group-chart">Back</button>
            </div>
            <div class="history-dashboard-chart" id="history_dashboard_groups_chart"></div>
        </div>

        <div class="history-dashboard-chart-card">
            <div class="d-flex justify-content-end mb-2">
                <button class="btn btn-outline-secondary btn-sm d-none" type="button" data-action="reset-weekly-chart">Back</button>
            </div>
            <div class="history-dashboard-chart" id="history_dashboard_weekly_chart"></div>
        </div>
    </div>

    <script type="application/json" id="history_dashboard_cards_json"><?= json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/json" id="history_dashboard_view_mode_json"><?= json_encode($view_mode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/json" id="history_dashboard_group_series_json"><?= json_encode($group_series, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/json" id="history_dashboard_group_name_breakdown_json"><?= json_encode($group_name_breakdown, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/json" id="history_dashboard_weekly_series_json"><?= json_encode($weekly_series, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/json" id="history_dashboard_weekly_name_breakdown_json"><?= json_encode($weekly_name_breakdown, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

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
                            <label for="group">Group</label>
                            <select class="form-control" id="group" multiple data-search-placeholder="groups">
                                <?php foreach ($group_options as $group_option): ?>
                                    <option value="<?= (int) $group_option['id'] ?>" <?= in_array((int) $group_option['id'], $filters['group'], true) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($group_option['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                            <label for="name">Bill Name</label>
                            <select class="form-control" id="name" multiple data-search-placeholder="bill names">
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
