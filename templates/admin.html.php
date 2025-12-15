<?php
declare(strict_types=1);

use OpenTrashmail\Utils\View;

$settings = isset($settings) && is_array($settings) ? $settings : [];

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$adminEnabled  = (bool)($settings['ADMIN_ENABLED'] ?? false);
$adminPassword = (string)($settings['ADMIN_PASSWORD'] ?? '');
$error         = isset($error) ? (string)$error : '';

if (!$adminEnabled) {
    return;
}

$csrfToken = isset($csrfToken) && is_string($csrfToken)
        ? $csrfToken
        : (string)($_SESSION['admin_csrf_token'] ?? '');

$requireCaptcha = isset($requireCaptcha)
        ? (bool)$requireCaptcha
        : (((int)($_SESSION['admin_failed_password_attempts'] ?? 0)) >= 2);
?>

<?php if ($adminPassword !== '' && empty($_SESSION['admin'])): ?>

    <section class="uk-section uk-section-muted otm-theme-section otm-admin-screen">
        <div class="uk-container">
            <div class="uk-flex uk-flex-center" style="min-height: 100vh;">
                <div class="uk-width-1-1" style="max-width: 560px;">

                    <div class="uk-card uk-card-default uk-card-body uk-box-shadow-medium uk-border-rounded">
                        <h1 class="uk-card-title uk-text-center">Admin Login</h1>
                        <form method="post"
                              hx-post="/api/admin"
                              hx-target="#main"
                              class="uk-form-stacked">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= View::escape($csrfToken) ?>">

                            <div class="uk-margin">
                                <label class="uk-form-label" for="admin-password">Password</label>
                                <div class="uk-form-controls">
                                    <input class="uk-input"
                                           type="password"
                                           id="admin-password"
                                           name="password"
                                           placeholder="Password"
                                           required>
                                </div>
                            </div>

                            <?php if (!empty($requireCaptcha)): ?>
                                <div class="uk-margin">
                                    <div class="uk-form-controls">
                                        <div class="iconcaptcha-widget" id="iconcaptcha" data-theme="light"></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="uk-margin">
                                <button class="uk-button uk-button-primary uk-button-large uk-width-1-1">Login</button>
                            </div>

                            <?php if ($error !== ''): ?>
                                <div class="uk-alert-danger" uk-alert>
                                    <a class="uk-alert-close" uk-close></a>
                                    <p><?= View::escape($error) ?></p>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <?php return; endif; ?>

<div class="uk-card uk-card-default uk-card-body uk-margin">
    <h1 class="uk-card-title">Admin</h1>

    <div class="uk-margin-top uk-margin-bottom uk-flex uk-flex-left uk-flex-wrap">

        <?php if (!empty($settings['SHOW_ACCOUNT_LIST'])): ?>
            <a href="/listaccounts"
               hx-get="/api/listaccounts"
               hx-target="#adminmain"
               hx-push-url="/listaccounts"
               class="uk-button uk-button-default uk-margin-small-right uk-margin-small-bottom otm-blue-hover">
                <i class="fa-solid fa-list"></i>
                <span class="uk-margin-small-left">List accounts</span>
            </a>
        <?php endif; ?>

        <?php if (!empty($settings['SHOW_LOGS'])): ?>
            <a href="/logs"
               hx-get="/api/logs"
               hx-target="#adminmain"
               hx-push-url="/logs"
               class="uk-button uk-button-default uk-margin-small-right uk-margin-small-bottom otm-blue-hover">
                <i class="fa-solid fa-file-lines"></i>
                <span class="uk-margin-small-left">Show logs</span>
            </a>
        <?php endif; ?>

    </div>

    <div id="adminmain"></div>
</div>
