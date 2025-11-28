<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/css/pico.min.css">
    <link rel="stylesheet" href="/css/prism.css">
    <link rel="stylesheet" href="/css/opentrashmail.css">
    <link rel="stylesheet" href="/css/all.min.css">
    <title>OpenTrashmail</title>
</head>

<body>
<?php
$currentUrl    = isset($url) ? $url : '';
$adminEnabled  = isset($this->settings['ADMIN_ENABLED']) ? $this->settings['ADMIN_ENABLED'] : false;
?>

<div class="topnav" id="OTMTopnav">
    <a href="/"><img src="/imgs/logo-50.png" width="50px" /> OpenTrashmail <small class="version"><?= getVersion() ?></small></a>
    <a><input id="email" hx-post="/api/address" hx-target="#main" name="email" type="email" style="margin-bottom:0px" hx-trigger="input changed delay:500ms" placeholder="email address" aria-label="email address"></a>
    <a href="/random" hx-get="/api/random" hx-target="#main"><i class="fa-solid fa-shuffle"></i> Generate random</a>
    <?php if ($adminEnabled == true): ?>
        <a href="/admin" hx-get="/api/admin" hx-target="#main" hx-push-url="/admin"><i class="fa-solid fa-user-shield"></i> Admin</a>
    <?php endif; ?>

    <a href="#" id="themeToggle" aria-label="Toggle Dark/Light Theme" title="Toggle Dark/Light Theme">
        <i class="fa-solid fa-toggle-on"></i>
    </a>

    <a href="javascript:void(0);" class="icon" onclick="navbarmanager()">
        <i class="fa-solid fa-bars"></i>
    </a>
</div>

<button class="htmx-indicator" aria-busy="true">Loading…</button>

<main id="main" class="container" hx-get="/api/<?= $currentUrl ?>" hx-trigger="load"></main>

<script src="/js/opentrashmail.js"></script>
<script src="/js/htmx.min.js"></script>
<script src="/js/moment-with-locales.min.js"></script>
</body>

</html>
