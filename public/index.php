<?php
declare(strict_types=1);

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use OpenTrashmail\Controllers\AppController;
use OpenTrashmail\Services\Settings;
use OpenTrashmail\Utils\Http;
use function FastRoute\simpleDispatcher;

require __DIR__ . '/../vendor/autoload.php';

const DS             = DIRECTORY_SEPARATOR;
const ROOT           = __DIR__ . DS . '..';
const TEMPLATES_PATH = ROOT . DS . 'templates' . DS;

$settingsRaw = Settings::load();
$settings    = is_array($settingsRaw) ? $settingsRaw : [];

if (!empty($settings['ALLOWED_IPS'])) {
    $ip = Http::getUserIp();
    if (!Http::isIpInRange($ip, (string)$settings['ALLOWED_IPS'])) {
        exit(sprintf('Your IP (%s) is not allowed to access this site.', $ip));
    }
}

if (!empty($settings['PASSWORD']) || !empty($settings['ADMIN_PASSWORD'])) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

$controller = new AppController($settings);

if (!empty($settings['PASSWORD'])) {
    $pw              = (string)$settings['PASSWORD'];
    $auth            = false;
    $requestPassword = array_key_exists('password', $_REQUEST)
        ? (string)$_REQUEST['password']
        : null;
    $headerPassword  = $_SERVER['HTTP_PWD'] ?? null;

    if ($headerPassword !== null && hash_equals($pw, $headerPassword)) {
        $auth = true;
    } elseif ($requestPassword !== null && hash_equals($pw, $requestPassword)) {
        $auth = true;
    } elseif (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        $auth = true;
    } elseif ($requestPassword !== null && !hash_equals($pw, $requestPassword)) {
        exit($controller->handle(
            'api_intro',
            ['template' => 'password.html', 'error' => 'Wrong password']
        ));
    }

    if ($auth) {
        $_SESSION['authenticated'] = true;
    } else {
        echo $controller->handle(
            'api_intro',
            ['template' => 'password.html']
        );
        return;
    }
}

$httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri        = $_SERVER['REQUEST_URI'] ?? '/';

if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);
$path = $uri === '' ? '/' : $uri;

$segments = array_values(
    array_filter(
        explode('/', $path),
        static fn(string $s): bool => $s !== ''
    )
);

$hxRequest = $_SERVER['HTTP_HX_REQUEST'] ?? null;

if ($hxRequest !== 'true') {
    $firstSegment = $segments[0] ?? null;

    if ($firstSegment !== 'api' && $firstSegment !== 'rss' && $firstSegment !== 'json') {
        $urlForShell = ltrim($path, '/');

        echo $controller->handle(
            'api_intro',
            [
                'template' => 'index.html',
                'url'      => $urlForShell,
                'settings' => $settings,
            ]
        );
        return;
    }
}

$dispatcher = simpleDispatcher(
    static function (RouteCollector $r): void {
        // API
        $r->addRoute('GET', '/api[/]', 'api_intro');
        $r->addRoute(['GET', 'POST'], '/api/address[/{email}]', 'api_address');
        $r->addRoute('GET', '/api/read/{email}/{id}', 'api_read');
        $r->addRoute('GET', '/api/listaccounts', 'api_listaccounts');
        $r->addRoute('GET', '/api/raw-html/{email}/{id}', 'api_raw_html');
        $r->addRoute('GET', '/api/raw/{email}/{id}', 'api_raw');
        $r->addRoute('GET', '/api/attachment/{email}/{attachment}', 'api_attachment');
        $r->addRoute(['GET'], '/api/delete/{email}/{id}', 'api_delete');
        $r->addRoute('GET', '/api/random', 'api_random');
        $r->addRoute(['GET', 'POST'], '/api/deleteaccount/{email}', 'api_deleteaccount');
        $r->addRoute('GET', '/api/logs[/{lines}]', 'api_logs');
        $r->addRoute(['GET', 'POST'], '/api/admin', 'api_admin');
        $r->addRoute(['GET', 'POST'], '/api/webhook/{action}/{email}', 'api_webhook');

        // RSS
        $r->addRoute('GET', '/rss/{email}', 'rss');

        // JSON
        $r->addRoute(['GET', 'POST'], '/json/listaccounts', 'json_listaccounts');
        $r->addRoute('GET', '/json/{email}[/{id}]', 'json_email');
    }
);

$routeInfo = $dispatcher->dispatch($httpMethod, $path);

switch ($routeInfo[0]) {
    case Dispatcher::NOT_FOUND:
        http_response_code(404);
        echo '404 Not Found';
        return;

    case Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        echo '405 Method Not Allowed';
        return;

    case Dispatcher::FOUND:
        $routeName = $routeInfo[1];
        $vars = $routeInfo[2];

        $response = $controller->handle($routeName, $vars);

        if ($response !== null) {
            echo $response;
        }
        return;
}
