<?php
declare(strict_types=1);

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use OpenTrashmail\Controllers\AppController;
use OpenTrashmail\Services\AccessGuard;
use OpenTrashmail\Services\Settings;
use function FastRoute\cachedDispatcher;

require __DIR__ . '/../vendor/autoload.php';

const DS = DIRECTORY_SEPARATOR;
const ROOT = __DIR__ . DS . '..';
const TEMPLATES_PATH = ROOT . DS . 'templates' . DS;
const CACHE_FILE = '/tmp/opentrashmail-route.cache';

$settingsRaw = Settings::load();
$settings = is_array($settingsRaw) ? $settingsRaw : [];

$controller = new AppController($settings);

$dispatcher = cachedDispatcher(
    static function (RouteCollector $r): void {
        $r->addRoute('GET', '/api[/]', 'api_intro');
        $r->addRoute(['GET', 'POST'], '/api/address[/{email}]', 'api_address');
        $r->addRoute('GET', '/api/read/{email}/{id}', 'api_read');
        $r->addRoute('GET', '/api/listaccounts', 'api_listaccounts');
        $r->addRoute('GET', '/api/raw-html/{email}/{id}', 'api_raw_html');
        $r->addRoute('GET', '/api/raw/{email}/{id}', 'api_raw');
        $r->addRoute('GET', '/api/attachment/{email}/{attachment}', 'api_attachment');
        $r->addRoute('GET', '/api/delete/{email}/{id}', 'api_delete');
        $r->addRoute('GET', '/api/random', 'api_random');
        $r->addRoute(['GET', 'POST'], '/api/deleteaccount/{email}', 'api_deleteaccount');
        $r->addRoute('GET', '/api/logs[/{lines}]', 'api_logs');
        $r->addRoute(['GET', 'POST'], '/api/admin', 'api_admin');
        $r->addRoute(['GET', 'POST'], '/api/webhook/{action}/{email}', 'api_webhook');
        $r->addRoute(['GET', 'POST', 'OPTIONS'], '/api/captcha-request', 'api_captcha_request');
        $r->addRoute('GET', '/rss/{email}', 'rss');
        $r->addRoute(['GET', 'POST'], '/json/listaccounts', 'json_listaccounts');
        $r->addRoute('GET', '/json/{email}[/{id}]', 'json_email');
    },
    [
        'cacheFile' => CACHE_FILE,
        'cacheDisabled' => false,
    ]
);

AccessGuard::enforce($settings, $controller);

$httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$path = rawurldecode($uri);
$path = $path === '' ? '/' : $path;

$hxRequest = $_SERVER['HTTP_HX_REQUEST'] ?? null;

if ($hxRequest !== 'true') {
    $trimmed = ltrim($path, '/');
    $firstSegment = $trimmed !== '' ? strtok($trimmed, '/') : null;

    if ($firstSegment !== 'api' && $firstSegment !== 'rss' && $firstSegment !== 'json') {
        echo $controller->handle('api_intro', [
            'template' => 'index.html',
            'url' => $trimmed,
            'settings' => $settings,
        ]);
        return;
    }
}

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
