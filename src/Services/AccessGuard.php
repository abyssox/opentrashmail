<?php
declare(strict_types=1);

namespace OpenTrashmail\Services;

use OpenTrashmail\Controllers\AppController;
use OpenTrashmail\Utils\Http;

final class AccessGuard
{
    /**
     * @param array<string,mixed> $settings
     */
    public static function enforce(array $settings, AppController $controller): void
    {
        self::checkIpAllowList($settings);
        self::startSessionIfRequired($settings);
        self::enforcePassword($settings, $controller);
    }

    /**
     * @param array<string,mixed> $settings
     */
    private static function checkIpAllowList(array $settings): void
    {
        if (empty($settings['ALLOWED_IPS'])) {
            return;
        }

        $ip = Http::getUserIp();
        if (!Http::isIpInRange($ip, (string)$settings['ALLOWED_IPS'])) {
            exit(sprintf('Your IP (%s) is not allowed to access this site.', $ip));
        }
    }

    /**
     * @param array<string,mixed> $settings
     */
    private static function startSessionIfRequired(array $settings): void
    {
        if (empty($settings['PASSWORD']) && empty($settings['ADMIN_PASSWORD'])) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * @param array<string,mixed> $settings
     */
    private static function enforcePassword(array $settings, AppController $controller): void
    {
        if (empty($settings['PASSWORD'])) {
            return;
        }

        $pw = (string)$settings['PASSWORD'];
        $auth = false;
        $requestPassword = array_key_exists('password', $_REQUEST)
            ? (string)$_REQUEST['password']
            : null;
        $headerPassword = $_SERVER['HTTP_PWD'] ?? null;

        if ($headerPassword !== null && hash_equals($pw, $headerPassword)) {
            $auth = true;
        } elseif ($requestPassword !== null && hash_equals($pw, $requestPassword)) {
            $auth = true;
        } elseif (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            $auth = true;
        } elseif ($requestPassword !== null && !hash_equals($pw, $requestPassword)) {
            exit($controller->handle('api_intro', [
                'template' => 'password.html',
                'error' => 'Wrong password',
            ]));
        }

        if ($auth) {
            $_SESSION['authenticated'] = true;
            return;
        }

        echo $controller->handle('api_intro', ['template' => 'password.html']);
        exit;
    }
}
