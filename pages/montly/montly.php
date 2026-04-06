<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/functions.php';

Auth()->require_auth();

$tabs = montly_available_tabs();
$active_tab = montly_active_tab($_GET['tab'] ?? 'bills');
$active_tab_file = __DIR__ . '/' . $active_tab . '/' . $active_tab . '.php';
?>

<div class="montly-tabs-page" data-montly-tabs data-active-tab="<?= htmlspecialchars($active_tab) ?>">
    <ul class="nav nav-tabs mb-3" role="tablist" aria-label="Monthly sections">
        <?php foreach ($tabs as $tab): ?>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link<?= $tab['id'] === $active_tab ? ' active' : '' ?>"
                    type="button"
                    data-tab="<?= htmlspecialchars($tab['id']) ?>"
                    role="tab"
                    aria-selected="<?= $tab['id'] === $active_tab ? 'true' : 'false' ?>"
                >
                    <?= htmlspecialchars($tab['label']) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="montly-tab-panel" data-tab-panel>
        <?php require $active_tab_file; ?>
    </div>
</div>
