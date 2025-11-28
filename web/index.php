<?php
declare(strict_types=1);

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', __DIR__);

require_once(ROOT . DS . 'inc' . DS . 'OpenTrashmailBackend.class.php');
require_once(ROOT . DS . 'inc' . DS . 'core.php');

$url = array_filter(explode('/',ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),'/')));

$backend = new OpenTrashmailBackend($url);

$settings = loadSettings();

if (!empty($settings['ALLOWED_IPS'])) {
    $ip = getUserIP();
    if (!isIPInRange($ip, $settings['ALLOWED_IPS'])) {
        exit("Your IP ($ip) is not allowed to access this site.");
    }
}

if (!empty($settings['PASSWORD']) || !empty($settings['ADMIN_PASSWORD'])) { // let's only start a session if we need one
    session_start();
}

if (!empty($settings['PASSWORD'])) { // site requires a password
    $pw              = (string) $settings['PASSWORD'];
    $auth            = false;
    $requestPassword = array_key_exists('password', $_REQUEST) ? (string) $_REQUEST['password'] : null;
    $headerPassword  = isset($_SERVER['HTTP_PWD']) ? (string) $_SERVER['HTTP_PWD'] : null;

    if ($headerPassword !== null && hash_equals($pw, $headerPassword)) {
        $auth = true;
    } elseif ($requestPassword !== null && hash_equals($pw, $requestPassword)) {
        $auth = true;
    } elseif (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        $auth = true;
    } elseif ($requestPassword !== null && !hash_equals($pw, $requestPassword)) {
        exit($backend->renderTemplate('password.html', [
            'error' => 'Wrong password',
        ]));
    }

    if ($auth) {
        $_SESSION['authenticated'] = true;
    } else {
        exit($backend->renderTemplate('password.html'));
    }
}

$hxRequest = $_SERVER['HTTP_HX_REQUEST'] ?? null;

if ($hxRequest !== 'true') {
    if (count($url) === 0 || !file_exists(ROOT . DS . implode('/', $url))) {
        // avoid undefined array key 0
        $firstSegment = $url[0] ?? null;

        if ($firstSegment !== 'api' && $firstSegment !== 'rss' && $firstSegment !== 'json') {
            exit($backend->renderTemplate('index.html', [
                'url' => implode('/', $url),
                'settings' => loadSettings(),
            ]));
        }
    }
} elseif (count($url) === 1 && isset($url[0]) && $url[0] === 'api') {
    exit($backend->renderTemplate('intro.html'));
}

$answer = $backend->run();

if ($answer === false) {
    return false;
}

echo $answer;
