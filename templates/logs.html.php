<?php
declare(strict_types=1);

use OpenTrashmail\Utils\System;
use OpenTrashmail\Utils\View;

$lines = isset($lines) && is_numeric($lines) ? (int)$lines : 100;

$mailserverlogfile      = is_string($mailserverlogfile ?? null)      ? $mailserverlogfile      : '';
$webservererrorlogfile  = is_string($webservererrorlogfile ?? null)  ? $webservererrorlogfile  : '';
$webserveraccesslogfile = is_string($webserveraccesslogfile ?? null) ? $webserveraccesslogfile : '';
$cleanupmaildirlogfile  = is_string($cleanupmaildirlogfile ?? null)  ? $cleanupmaildirlogfile  : '';
$configfile             = is_string($configfile ?? null)             ? $configfile             : '';

$lineOptions = [10, 50, 100, 200, 500];
?>

<div class="uk-margin-small-bottom">
    <div class="uk-button-group">
        <?php foreach ($lineOptions as $opt): ?>
            <?php $active = ($lines === $opt); ?>
            <a href="#"
               hx-push-url="/logs/<?= $opt ?>"
               hx-get="/api/logs/<?= $opt ?>"
               hx-target="#adminmain"
               class="uk-button uk-button-small <?= $active ? 'uk-button-primary uk-disabled' : 'uk-button-default' ?> otm-blue-hover"
               <?php if ($active): ?>aria-disabled="true"<?php endif; ?>>
                Last <?= $opt ?> lines
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Mailserver Log -->
<div class="uk-card uk-card-default uk-card-body uk-margin">
    <h2 class="uk-card-title uk-margin-small-bottom">Mailserver log</h2>
    <div class="uk-overflow-auto">
        <pre class="uk-margin-remove uk-text-small"><code class="language-log">
<?= $mailserverlogfile && file_exists($mailserverlogfile)
        ? View::escape(System::tailShell($mailserverlogfile, $lines))
        : '- Mailserver log file not found -' ?>
</code></pre>
    </div>
</div>

<!-- Webserver Error Log -->
<div class="uk-card uk-card-default uk-card-body uk-margin">
    <h2 class="uk-card-title uk-margin-small-bottom">Webserver error log</h2>
    <div class="uk-overflow-auto">
        <pre class="uk-margin-remove uk-text-small"><code class="language-log">
<?= $webservererrorlogfile && file_exists($webservererrorlogfile)
        ? View::escape(System::tailShell($webservererrorlogfile, $lines))
        : '- Webserver error log file not found -' ?>
</code></pre>
    </div>
</div>

<!-- Webserver Access Log -->
<div class="uk-card uk-card-default uk-card-body uk-margin">
    <h2 class="uk-card-title uk-margin-small-bottom">Webserver access log</h2>
    <div class="uk-overflow-auto">
        <pre class="uk-margin-remove uk-text-small"><code class="language-log">
<?= $webserveraccesslogfile && file_exists($webserveraccesslogfile)
        ? View::escape(System::tailShell($webserveraccesslogfile, $lines))
        : '- Webserver access log file not found -' ?>
</code></pre>
    </div>
</div>

<!-- Cleanup maildir Log -->
<div class="uk-card uk-card-default uk-card-body uk-margin">
    <h2 class="uk-card-title uk-margin-small-bottom">Cleanup maildir log</h2>
    <div class="uk-overflow-auto">
        <pre class="uk-margin-remove uk-text-small"><code class="language-log">
<?= $cleanupmaildirlogfile && file_exists($cleanupmaildirlogfile)
        ? View::escape(System::tailShell($cleanupmaildirlogfile, $lines))
        : '- Cleanup maildir log file not found -' ?>
</code></pre>
    </div>
</div>

<!-- Current Config -->
<div class="uk-card uk-card-default uk-card-body uk-margin">
    <h2 class="uk-card-title uk-margin-small-bottom">Current config</h2>
    <div class="uk-overflow-auto">
        <?php
        $configOutput = '- Config file not found -';

        if ($configfile && file_exists($configfile)) {
            $raw = file_get_contents($configfile);
            if ($raw !== false) {
                $configOutput = preg_replace(
                        '/^(\s*ADMIN_PASSWORD\s*=\s*).*/mi',
                        '$1********',
                        $raw
                );
            }
        }
        ?>
        <pre class="uk-margin-remove uk-text-small"><code class="language-ini">
<?= View::escape($configOutput) ?>
</code></pre>
    </div>
</div>

<script src="<?= View::assetUrl('js/prism.js') ?>"></script>
