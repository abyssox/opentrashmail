<?php
$lines = isset($lines) && is_numeric($lines) ? (int) $lines : 100;
$mailserverlogfile      = $mailserverlogfile      ?? '';
$webservererrorlogfile  = $webservererrorlogfile  ?? '';
$webserveraccesslogfile = $webserveraccesslogfile ?? '';
$configfile             = $configfile             ?? '';
?>

<a href="#" hx-push-url="/logs/10" hx-get="/api/logs/10" <?= $lines==10?'disabled':'' ?> hx-target="#adminmain" role="button">Last 10 lines</a>
<a href="#" hx-push-url="/logs/50" hx-get="/api/logs/50" <?= $lines==50?'disabled':'' ?> hx-target="#adminmain" role="button">Last 50 lines</a>
<a href="#" hx-push-url="/logs/100" hx-get="/api/logs/100" <?= $lines==100?'disabled':'' ?> hx-target="#adminmain" role="button">Last 100 lines</a>
<a href="#" hx-push-url="/logs/200" hx-get="/api/logs/200" <?= $lines==200?'disabled':'' ?> hx-target="#adminmain" role="button">Last 200 lines</a>
<a href="#" hx-push-url="/logs/500" hx-get="/api/logs/500" <?= $lines==500?'disabled':'' ?> hx-target="#adminmain" role="button">Last 500 lines</a>

<hr>

<h2>Mailserver log</h2>
<div>
    <pre><code class="language-log"><?= file_exists($mailserverlogfile)?tailShell($mailserverlogfile, $lines):'- Mailserver log file not found -' ?></code><pre>
</div>

<h2>Webserver error log</h2>
<div>
    <pre><code class="language-log"><?= file_exists($webservererrorlogfile)?tailShell($webservererrorlogfile, $lines):'- Webserver error log file not found -' ?></code><pre>
</div>

<h2>Webserver access log</h2>
<div>
    <pre><code class="language-log"><?= file_exists($webserveraccesslogfile)?tailShell($webserveraccesslogfile, $lines):'- Webserver access log file not found -' ?></code><pre>
</div>

<h2>Current config</h2>
<div>
    <?php
    $configOutput = '- Config file not found -';

    if (file_exists($configfile)) {
        $raw = file_get_contents($configfile);

        // mask ADMIN_PASSWORD
        $configOutput = preg_replace(
                '/^(\s*ADMIN_PASSWORD\s*=\s*).*/mi',
                '$1********',
                $raw
        );
    }
    ?>
    <pre><code class="language-ini"><?= $configOutput ?></code></pre>
</div>



<script src="/js/prism.js"></script>
