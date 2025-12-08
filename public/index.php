<?php
declare(strict_types=1);

use OpenTrashmail\Controllers\AppController;
use OpenTrashmail\Services\Settings;
use OpenTrashmail\Utils\Http;

require __DIR__ . '/../vendor/autoload.php';

const DS   = DIRECTORY_SEPARATOR;
const ROOT = __DIR__ . DS . '..';
const TEMPLATES_PATH = ROOT . DS . 'templates' . DS;

$uri      = $_SERVER['REQUEST_URI'] ?? '/';
$path     = parse_url($uri, PHP_URL_PATH) ?: '/';
$segments = array_values(
    array_filter(
        explode('/', $path),
        static fn(string $s): bool => $s !== ''
    )
);

$url = $segments;

$settingsRaw = Settings::load();
$settings    = is_array($settingsRaw) ? $settingsRaw : [];

if (!empty($settings['ALLOWED_IPS'])) {
    $ip = Http::getUserIp();
    if (!Http::isIpInRange($ip, (string)$settings['ALLOWED_IPS'])) {
        header('HTTP/1.1 403 Forbidden');
        echo "Your IP ($ip) is not allowed to access this site.";
        exit;
    }
}

if (!empty($settings['PASSWORD']) || !empty($settings['ADMIN_PASSWORD'])) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

$controller = new AppController($segments, $settings);

if (!empty($settings['PASSWORD'])) {
    $pw              = (string)$settings['PASSWORD'];
    $auth            = false;
    $requestPassword = array_key_exists('password', $_REQUEST) ? (string)$_REQUEST['password'] : null;
    $headerPassword  = $_SERVER['HTTP_PWD'] ?? null;

    if ($headerPassword !== null && hash_equals($pw, $headerPassword)) {
        $auth = true;
    } elseif ($requestPassword !== null && hash_equals($pw, $requestPassword)) {
        $auth = true;
    } elseif (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        $auth = true;
    } elseif ($requestPassword !== null && !hash_equals($pw, $requestPassword)) {
        echo $controller->renderTemplate('password.html', [
            'error' => 'Wrong password',
        ]);
        exit;
    }

    if ($auth) {
        $_SESSION['authenticated'] = true;
    } else {
        echo $controller->renderTemplate('password.html');
        exit;
    }
}

$hxRequest = $_SERVER['HTTP_HX_REQUEST'] ?? null;

if ($hxRequest !== 'true') {
    $firstSegment = $segments[0] ?? null;

    if ($firstSegment !== 'api' && $firstSegment !== 'rss' && $firstSegment !== 'json') {
        echo $controller->renderTemplate('index.html', [
            'url'      => implode('/', $segments),
            'settings' => $settings,
        ]);
        exit;
    }
}

if ($hxRequest === 'true' && count($segments) === 1 && $segments[0] === 'api') {
    echo $controller->renderTemplate('intro.html');
    exit;
}

$response = $controller->handle();

if ($response === false) {
    return false;
}

if (is_string($response)) {
    echo $response;
}
