<?php
require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/app.php';

Auth()->require_auth();

$frontend_config = app_frontend_config();
$menu_items = app_pages();
$current_page = app_page()['id'];
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link rel="stylesheet" href="<?= asset('assets/css/bootstrap-5.3.3.css') ?>">
        <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
        <link rel="stylesheet" href="<?= asset('assets/css/crm-multi-select.css') ?>">
        <link rel="stylesheet" href="<?= asset('templates/basic.crm/basic.crm.css') ?>">
        <link rel="icon" href="<?= asset('favicon.ico') ?>">
        <script src="<?= asset('assets/js/jquery-3.6.0.js') ?>"></script>
        <script src="<?= asset('assets/js/popper-1.14.7.js') ?>"></script>
        <script src="<?= asset('assets/js/bootstrap-5.3.3.js') ?>"></script>
        <script src="<?= asset('assets/js/crm-multi-select.js') ?>"></script>
        <script src="<?= asset('templates/basic.crm/basic.crm.js') ?>"></script>
        <title><?= title() ?></title>
    </head>
    <body data-bs-theme="<?= theme() ?>" class="maybe-flex">

        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="1500" data-bs-animation="true">
                <div class="toast-header p-0 m-0">
                    <div class="d-flex justify-content-start align-content-center" style="height:40px!important">
                        <div class="p-2"><?= icon('square.svg', 'success', 20, 20) ?></div>
                        <div class="p-2"><h4 class="m-0">Success</h4></div>
                    </div>
                </div>
                <div class="toast-body">Data updated successfully</div>
            </div>
        </div>

        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="failToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false" data-bs-animation="true">
                <div class="toast-header p-0 m-0">
                    <div class="d-flex justify-content-start align-content-center flex-fill" style="height:40px!important">
                        <div class="p-2"><?= icon('square.svg', 'fail', 20, 20) ?></div>
                        <div class="p-2"><h4 class="m-0">Failed</h4></div>
                        <div class="ms-auto align-content-center">
                            <button type="button" class="btn-close me-1 p-2" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
                <div class="toast-body">Please try again</div>
            </div>
        </div>

        <div class="d-flex flex-column min-vh-100 app-shell">
            <div class="d-flex flex-row w-100 sticky-top app-shell-header" style="height:70px;">
                <div class="layout-header-left d-flex align-items-center justify-content-center" id="menuToggle" data-header-left role="button" tabindex="0" aria-label="Open or close the side menu">
                    <div class="menu-icon" role="presentation">
                        <span id="menuIcon" class="m-auto">
                            <?= icon('greater.svg', 'primary', 30, 30) ?>
                        </span>
                    </div>
                </div>

                <nav class="navbar flex-fill p-0 m-0 border-bottom">
                    <div class="d-flex w-100 align-items-center">
                        <div class="d-flex justify-content-center align-items-center">
                            <img
                                src="<?= image('logo-' . theme() . '.svg') ?>"
                                alt="<?= title() ?>"
                                height="70"
                                data-app-logo
                            >
                        </div>

                        <div class="p-2 pt-3 me-3 d-flex justify-content-center align-items-center ms-auto">
                            <div class="dropstart" style="z-index:1160!important">
                                <div class="dots dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?= icon('dots.svg', 'primary', 30, 30) ?>
                                </div>
                                <ul class="dropdown-menu dots-dropdown">
                                    <li>
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#appPreferencesModal">
                                            Preferences
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li class="menu-exit">
                                        <div class="d-flex align-items-center dropdown-item">
                                            <?= icon('exit.svg','primary') ?>
                                            <h6 class="p-1 mt-1 ms-1 mb-0">Logout</h6>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>

            <div class="d-flex flex-fill app-shell-main">
                <div class="menu-vertical" id="sidebarMenu">
                    <?php foreach ($menu_items as $item): ?>

                        <div
                            class="menu-option d-flex w-100 justify-content-start py-3<?= $item['id'] === $current_page ? ' menu-active' : '' ?>"
                            id="menu-<?= htmlspecialchars($item['id']) ?>"
                            title="<?= strtolower($item['label']) ?>"
                            data-menu="<?= htmlspecialchars($item['id']) ?>"
                        >
                            <div class="d-flex align-items-start mx-2">
                                <?= icon($item['icon'], 'primary', 31, 30) ?>
                            </div>
                            <div class="menu-label align-self-center hide">
                                <span><?= htmlspecialchars($item['label']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="page-content">
                    <div class="justify-content-center app-page-host">
                        <div id="app" class="p-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="appPreferencesModal" tabindex="-1" aria-labelledby="appPreferencesModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="appPreferencesModalLabel">Preferences</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="mb-1">Theme</h6>
                                    <p class="text-muted mb-0">Choose the application appearance.</p>
                                </div>

                                <div class="preferences-theme-list">
                                    <label class="card preferences-theme-option mb-2" for="appThemeLight">
                                        <div class="card-body py-3">
                                            <div class="form-check m-0">
                                                <input
                                                    class="form-check-input"
                                                    type="radio"
                                                    name="app_theme"
                                                    id="appThemeLight"
                                                    value="light"
                                                    data-theme-option
                                                    <?= theme() === 'light' ? 'checked' : '' ?>
                                                >
                                                <span class="form-check-label fw-semibold">Light theme</span>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="card preferences-theme-option" for="appThemeDark">
                                        <div class="card-body py-3">
                                            <div class="form-check m-0">
                                                <input
                                                    class="form-check-input"
                                                    type="radio"
                                                    name="app_theme"
                                                    id="appThemeDark"
                                                    value="dark"
                                                    data-theme-option
                                                    <?= theme() === 'dark' ? 'checked' : '' ?>
                                                >
                                                <span class="form-check-label fw-semibold">Dark theme</span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" data-action="save-theme-preferences">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            window.APP = <?= json_encode($frontend_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        </script>
    </body>
</html>
