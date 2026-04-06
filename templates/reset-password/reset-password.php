<?php
require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';

$session = Session();
$error_message = $session->pull_flash('error');
$success_message = $session->pull_flash('success');
$page_theme = $page_theme ?? theme();
$reset_token = get_string('token');
$login_image = root('assets/images/login-' . $page_theme . '.svg');
$login_imageUrl = file_exists($login_image) ? image('login-' . $page_theme . '.svg') : image('login-dark.svg');
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link rel="stylesheet" href="<?= asset('assets/css/bootstrap-5.3.3.css') ?>">
        <link rel="stylesheet" href="<?= asset('assets/css/crm-icons.css') ?>">
        <link rel="stylesheet" href="<?= url('templates/reset-password/reset-password.css') ?>">
        <link rel="icon" href="<?= asset('favicon.ico') ?>">
        <title><?= title() ?> Reset Password</title>
    </head>
    <body data-bs-theme="<?= htmlspecialchars($page_theme) ?>" class="auth-page">
        <div class="auth-shell">
            <div class="auth-card">
                <div class="auth-visual">
                    <img src="<?= $login_imageUrl ?>" alt="<?= title() ?> reset password">
                </div>

                <div class="auth-panel">
                    <div class="auth-header">
                        <img src="<?= image('logo-' . $page_theme . '.svg') ?>" alt="<?= title() ?>" class="auth-logo">
                        <h1 class="h3 mb-1">Set a new password</h1>
                        <p class="text-secondary mb-0">Choose a new password, then return to the login page.</p>
                    </div>

                    <?php if ($error_message): ?>

                        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>

                        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= url('templates/reset-password/reset-password.submit.php') ?>" class="d-flex flex-column gap-3">
                        <?= csrf_input() ?>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($reset_token) ?>">
                        <div>
                            <label class="form-label" for="new_password">New password</label>
                            <input class="form-control" id="new_password" name="password" type="password" autocomplete="new-password" required>
                        </div>
                        <div>
                            <label class="form-label" for="confirm_password">Confirm password</label>
                            <input class="form-control" id="confirm_password" name="password_confirm" type="password" autocomplete="new-password" required>
                        </div>
                        <button class="btn btn-primary" type="submit">Save password</button>
                        <a class="btn btn-link px-0 text-start" href="<?= url('index.php') ?>">Back to login</a>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
