<?php
$reqPassword = $_REQUEST['password'] ?? null;
$adminPassword = $settings['ADMIN_PASSWORD'] ?? '';
$error = '';

if ($reqPassword !== null) {
    if ($adminPassword !== '' && hash_equals($adminPassword, (string)$reqPassword)) {
        $_SESSION['admin'] = true;
    } else {
        $error = 'Wrong password';
    }
}
?>

<?php if ($adminPassword !== "" && empty($_SESSION['admin'])): ?>

    <div class="uk-flex uk-flex-center">
        <div class="uk-width-medium">

            <div class="uk-card uk-card-default uk-card-body uk-margin">
                <h1 class="uk-card-title uk-text-center">Admin Login</h1>

                <?php if (!empty($error)) : ?>
                    <div class="uk-alert-danger" uk-alert>
                        <a class="uk-alert-close" uk-close></a>
                        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" hx-post="/api/admin" hx-target="#main" class="uk-form-stacked">

                    <div class="uk-margin">
                        <label class="uk-form-label" for="admin-password">Password</label>
                        <div class="uk-form-controls">
                            <input class="uk-input" type="password" id="admin-password" name="password"
                                   placeholder="Password" required>
                        </div>
                    </div>

                    <div class="uk-margin">
                        <button type="submit" class="uk-button uk-button-primary uk-width-1-1">
                            Login
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

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
