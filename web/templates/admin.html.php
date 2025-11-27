<h1>Admin</h1>

<?php
$reqPassword = $_REQUEST['password'] ?? null;
$adminPassword = $settings['ADMIN_PASSWORD'] ?? '';

if ($reqPassword !== null && $reqPassword === $adminPassword) {
    $_SESSION['admin'] = true;
} else if ($reqPassword !== null && $reqPassword !== $adminPassword) {
    echo '<div class="error">Wrong password</div>';
}
?>

<?php if ($adminPassword !== "" && empty($_SESSION['admin'])): ?>
    <form method="post" hx-post="/api/admin" hx-target="#main">
        <input type="password" name="password" placeholder="password" />
        <input type="submit" value="Login" />
    </form>
    <?php return; endif; ?>


<nav>
    <ul>
        <li>
            <?php if (!empty($settings['SHOW_ACCOUNT_LIST'])): ?>
                <a href="/listaccounts"
                   hx-get="/api/listaccounts"
                   hx-target="#adminmain"
                   hx-push-url="/listaccounts">
                    <i class="fas fa-list"></i> List accounts
                </a>
            <?php endif; ?>
        </li>
        <li>
            <?php if (!empty($settings['SHOW_LOGS'])): ?>
                <a href="/logs"
                   hx-get="/api/logs"
                   hx-target="#adminmain"
                   hx-push-url="/logs">
                    <i class="fas fa-list"></i> Show logs
                </a>
            <?php endif; ?>
        </li>
    </ul>
</nav>

<div id="adminmain"></div>
