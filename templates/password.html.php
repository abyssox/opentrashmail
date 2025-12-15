<?php
declare(strict_types=1);

use OpenTrashmail\Utils\View;
use OpenTrashmail\Utils\System;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$error     = isset($error) ? (string)$error : '';
$csrfToken = isset($csrfToken) && is_string($csrfToken) && $csrfToken !== ''
        ? $csrfToken
        : (string)($_SESSION['auth_csrf_token'] ?? '');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="<?= View::assetUrl('css/uikit.min.css') ?>">
    <link rel="stylesheet" href="<?= View::assetUrl('css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= View::assetUrl('css/opentrashmail.css') ?>">
    <link rel="stylesheet" href="<?= View::assetUrl('iconcaptcha/css/iconcaptcha.min.css') ?>">

    <title>OpenTrashmail</title>
</head>

<body>

<nav class="uk-navbar-container">
    <div class="uk-container">

        <div class="otm-nav">
            <a href="/" class="otm-brand">
                <img src="<?= View::assetUrl('imgs/logo-50.png') ?>" width="50" alt="OpenTrashmail Logo">
                <span class="otm-brand-text">
                    OpenTrashmail
                    <small class="version"><?= System::getVersion() ?></small>
                </span>
            </a>
        </div>

    </div>
</nav>

<main>
    <div class="uk-section uk-section-muted uk-flex uk-animation-fade" uk-height-viewport>
        <div class="uk-width-1-1">
            <div class="uk-container">
                <div class="uk-grid-margin uk-grid uk-grid-stack" uk-grid>
                    <div class="uk-width-1-1@m">
                        <div class="uk-margin uk-width-large uk-margin-auto uk-card uk-card-default uk-card-body uk-box-shadow-large">
                            <h3 class="uk-card-title uk-text-center">Login</h3>
                            <form action="/" method="POST">
                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= View::escape($csrfToken) ?>">
                                <div class="uk-margin">
                                    <div class="uk-inline uk-width-1-1">
                                        <span class="uk-form-icon" uk-icon="icon: lock"></span>
                                        <input class="uk-input uk-form-large" type="password" id="password" name="password" placeholder="Password" required>
                                    </div>
                                </div>
                                <?php if (!empty($requireCaptcha)): ?>
                                    <div class="uk-margin">
                                        <div class="uk-inline uk-width-1-1">
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
        </div>
    </div>
</main>

<script src="<?= View::assetUrl('js/uikit.min.js') ?>"></script>
<script src="<?= View::assetUrl('iconcaptcha/js/iconcaptcha.min.js') ?>"></script>
<script src="<?= View::assetUrl('js/opentrashmail.js') ?>"></script>

</body>
</html>
