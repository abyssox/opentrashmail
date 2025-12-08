<?php
declare(strict_types=1);

use OpenTrashmail\Utils\View;
use OpenTrashmail\Utils\System;

$currentUrl   = isset($url) ? (string)$url : '';
$settings     = is_array($settings ?? null) ? $settings : [];
$adminEnabled = !empty($settings['ADMIN_ENABLED']);

$currentUrlEsc = View::escape($currentUrl);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="<?= View::assetUrl('css/uikit.min.css') ?>">
    <link rel="stylesheet" href="<?= View::assetUrl('css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= View::assetUrl('css/prism.css') ?>">
    <link rel="stylesheet" href="<?= View::assetUrl('css/opentrashmail.css') ?>">

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

            <form class="otm-search" onsubmit="return false;" autocomplete="off"
                  data-bwignore="true" data-1p-ignore="true" data-lpignore="true">
                <input id="email" name="email" type="email" class="uk-input"
                       hx-post="/api/address"
                       hx-target="#main"
                       hx-trigger="input changed delay:500ms"
                       autocomplete="off"
                       inputmode="email"
                       placeholder="email address"
                       aria-label="email address">
            </form>

            <div class="otm-links">
                <a href="/random"
                   hx-get="/api/random"
                   hx-target="#main"
                   class="otm-link">
                    <i class="fa-solid fa-shuffle"></i>
                    <span class="uk-margin-small-left">Generate random</span>
                </a>

                <?php if ($adminEnabled): ?>
                    <a href="/admin"
                       hx-get="/api/admin"
                       hx-target="#main"
                       hx-push-url="/admin"
                       class="otm-link">
                        <i class="fa-solid fa-user-shield"></i>
                        <span class="uk-margin-small-left">Admin</span>
                    </a>
                <?php endif; ?>

                <a href="#"
                   id="themeToggle"
                   aria-label="Toggle Dark/Light Theme"
                   title="Toggle Dark/Light Theme"
                   class="otm-link">
                    <i class="fa-solid fa-toggle-on"></i>
                </a>
            </div>
        </div>

    </div>
</nav>

<button class="htmx-indicator uk-button uk-button-default" aria-busy="true">Loading…</button>

<main id="main"
      class="uk-container"
      hx-get="/api/<?= $currentUrlEsc ?>"
      hx-trigger="load">
</main>

<script src="<?= View::assetUrl('js/uikit.min.js') ?>"></script>
<script src="<?= View::assetUrl('js/htmx.min.js') ?>"></script>
<script src="<?= View::assetUrl('js/moment-with-locales.min.js') ?>"></script>
<script src="<?= View::assetUrl('js/opentrashmail.js') ?>"></script>

</body>
</html>
