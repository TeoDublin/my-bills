<?php
require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';

$session = Session();
$error_message = $session->pull_flash('error');
$success_message = $session->pull_flash('success');
$last_username = $session->pull_flash('last_username', $_GET['username'] ?? '');
$forgot_view_open = (bool) $session->pull_flash('forgot_panel_open', false);
$page_theme = $page_theme ?? theme();
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link rel="stylesheet" href="<?= asset('assets/css/bootstrap-5.3.3.css') ?>">
        <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
        <link rel="stylesheet" href="<?= asset('templates/login/login.css') ?>">
        <link rel="icon" href="<?= asset('favicon.ico') ?>">
        <script src="<?= asset('templates/login/login.js') ?>"></script>
        <title><?= title() ?> Login</title>
    </head>
    <body data-bs-theme="<?= htmlspecialchars($page_theme) ?>" class="auth-page">
        <div class="auth-background" aria-hidden="true">
            <div class="auth-background-wave auth-background-wave-a"></div>
            <div class="auth-background-wave auth-background-wave-b"></div>
            <div class="auth-background-grid"></div>
            <div class="auth-background-particles">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
        <div class="auth-shell">
            <div class="auth-card">
                <div class="auth-panel">
                    <div class="auth-header">
                        <img src="<?= image('logo-' . $page_theme . '.svg') ?>" alt="<?= title() ?>" class="auth-logo">
                        <span class="auth-kicker">Personal finance</span>
                        <h1 class="h3 mb-1">Welcome back</h1>
                        <p class="text-secondary mb-0">Sign in to continue.</p>
                    </div>

                    <div class="auth-alerts">
                        <?php if ($error_message): ?>

                            <div class="alert alert-danger mb-0"><?= htmlspecialchars($error_message) ?></div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>

                            <div class="alert alert-success mb-0"><?= htmlspecialchars($success_message) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="auth-stage<?= $forgot_view_open ? ' is-forgot' : '' ?>" data-auth-stage>
                        <div class="auth-stage-track">
                            <section class="auth-view">
                                <div class="auth-view-header">
                                    <div>
                                        <h2 class="h5 mb-1">Login</h2>
                                        <p class="small text-secondary mb-0">Use your username and password to enter.</p>
                                    </div>
                                </div>

                                <form method="post" action="<?= url('templates/login/login.submit.php') ?>" class="d-flex flex-column gap-3">
                                    <?= csrf_input() ?>
                                    <div>
                                        <label class="form-label" for="username">Username</label>
                                        <input class="form-control" id="username" name="username" value="<?= htmlspecialchars(string_value($last_username)) ?>" autocomplete="username" required>
                                    </div>
                                    <div>
                                        <label class="form-label" for="password">Password</label>
                                        <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                                    </div>
                                    <button class="btn btn-primary" type="submit">Login</button>
                                    <button
                                        class="btn btn-link auth-toggle-link"
                                        type="button"
                                        data-auth-view-toggle="forgot"
                                    >
                                        Forgot password?
                                    </button>
                                </form>
                            </section>

                            <section class="auth-view auth-view-muted">
                                <div class="auth-view-header">
                                    <div>
                                        <h2 class="h5 mb-1">Forgot password</h2>
                                        <p class="small text-secondary mb-0">Enter username and email. If they match, a reset link will be sent.</p>
                                    </div>
                                </div>

                                <form method="post" action="<?= url('templates/login/forgot-password.submit.php') ?>" class="d-flex flex-column gap-3">
                                    <?= csrf_input() ?>
                                    <div>
                                        <label class="form-label" for="reset_username">Username</label>
                                        <input class="form-control" id="reset_username" name="username" value="<?= htmlspecialchars(string_value($last_username)) ?>" autocomplete="username" required>
                                    </div>
                                    <div>
                                        <label class="form-label" for="reset_email">Email</label>
                                        <input class="form-control" id="reset_email" name="email" type="email" autocomplete="email" required>
                                    </div>
                                    <button class="btn btn-outline-primary" type="submit">Send reset link</button>
                                    <button
                                        class="btn btn-link auth-toggle-link"
                                        type="button"
                                        data-auth-view-toggle="login"
                                    >
                                        Back to login
                                    </button>
                                </form>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
