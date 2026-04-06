<?php

require_once __DIR__ . '/../../../includes/constants.php';
require_once __DIR__ . '/../../../includes/class.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/functions.php';

Auth()->require_auth();

$income_entry = income_current_entry();
$income_value = $income_entry ? number_format((float) ($income_entry['value'] ?? 0), 2, '.', '') : '';
$income_day = $income_entry ? (string) ((int) ($income_entry['day'] ?? 0)) : date('j');
?>

<div class="page-income" data-income-root data-csrf-token="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="income-form-card">
        <div class="income-form-fields">
            <div>
                <label class="form-label" for="income_value">Income value</label>
                <input class="form-control form-control-lg" id="income_value" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" value="<?= htmlspecialchars($income_value) ?>">
            </div>
            <div>
                <label class="form-label" for="income_day">Income day</label>
                <input class="form-control form-control-lg" id="income_day" type="number" min="1" max="31" step="1" value="<?= htmlspecialchars($income_day) ?>" placeholder="1-31">
            </div>
            <button class="btn btn-primary btn-lg income-submit" type="button" data-action="save-income">Save income</button>
        </div>
    </div>
</div>
