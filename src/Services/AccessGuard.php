<?php
declare(strict_types=1);

namespace OpenTrashmail\Services;

use OpenTrashmail\Controllers\AppController;
use OpenTrashmail\Utils\Captcha;
use OpenTrashmail\Utils\Http;

final class AccessGuard
{
    private const AUTH_FAILED_KEY  = 'auth_failed_password_attempts';
    private const ADMIN_FAILED_KEY = 'admin_failed_password_attempts';

    private const AUTH_CSRF_KEY  = 'auth_csrf_token';
    private const ADMIN_CSRF_KEY = 'admin_csrf_token';

    private const CAPTCHA_AFTER_FAILED = 2;

    /**
     * @param array<string,mixed> $settings
     */
    public static function enforce(array $settings, AppController $controller): void
    {
        self::checkIpAllowList($settings);

        // Captcha endpoint must never be blocked; the widget must be able to fetch challenges.
        if (self::getPath() === '/api/captcha-request') {
            return;
        }

        self::startSessionIfRequired($settings);

        // Site-wide password protection (existing)
        self::enforcePassword($settings, $controller);

        // Admin password protection for /api/admin (new)
        self::enforceAdminPassword($settings, $controller);
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
        // Needed for password/auth sessions, CSRF, captcha storage (session driver).
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

        self::ensureCsrfToken(self::AUTH_CSRF_KEY);
        $captchaRequired = self::isCaptchaRequired(self::AUTH_FAILED_KEY);

        // API header auth (no CSRF/captcha)
        $headerPassword = $_SERVER['HTTP_PWD'] ?? null;
        if ($headerPassword !== null && hash_equals($pw, $headerPassword)) {
            $_SESSION['authenticated'] = true;
            self::resetFailedAttempts(self::AUTH_FAILED_KEY);
            return;
        }

        // Existing authenticated session
        if (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            return;
        }

        // Browser POST (enforce CSRF + optional captcha)
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST' && isset($_POST['password'])) {
            $postedToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : null;

            if (!self::validateCsrfToken(self::AUTH_CSRF_KEY, $postedToken)) {
                self::rotateCsrfToken(self::AUTH_CSRF_KEY);
                echo (string)$controller->handle('api_intro', [
                    'template' => 'password.html',
                    'settings' => $settings,
                    'error' => 'Invalid or expired form token. Please try again.',
                    'requireCaptcha' => $captchaRequired,
                    'csrfToken' => $_SESSION[self::AUTH_CSRF_KEY],
                ]);
                exit;
            }

            if ($captchaRequired && !Captcha::validate($_POST)) {
                echo (string)$controller->handle('api_intro', [
                    'template' => 'password.html',
                    'settings' => $settings,
                    'error' => 'Captcha validation failed',
                    'requireCaptcha' => true,
                    'csrfToken' => $_SESSION[self::AUTH_CSRF_KEY],
                ]);
                exit;
            }

            $postedPw = (string)$_POST['password'];
            if (hash_equals($pw, $postedPw)) {
                $_SESSION['authenticated'] = true;
                self::resetFailedAttempts(self::AUTH_FAILED_KEY);
                self::rotateCsrfToken(self::AUTH_CSRF_KEY);
                return;
            }

            self::incrementFailedAttempts(self::AUTH_FAILED_KEY);
            $captchaRequired = self::isCaptchaRequired(self::AUTH_FAILED_KEY);

            echo (string)$controller->handle('api_intro', [
                'template' => 'password.html',
                'settings' => $settings,
                'error' => 'Wrong password',
                'requireCaptcha' => $captchaRequired,
                'csrfToken' => $_SESSION[self::AUTH_CSRF_KEY],
            ]);
            exit;
        }

        // Optional legacy: password via query (no CSRF/captcha; keep behavior)
        $requestPassword = array_key_exists('password', $_REQUEST) ? (string)$_REQUEST['password'] : null;
        if ($requestPassword !== null && hash_equals($pw, $requestPassword)) {
            $_SESSION['authenticated'] = true;
            self::resetFailedAttempts(self::AUTH_FAILED_KEY);
            return;
        }
        if ($requestPassword !== null) {
            self::incrementFailedAttempts(self::AUTH_FAILED_KEY);
            $captchaRequired = self::isCaptchaRequired(self::AUTH_FAILED_KEY);

            echo (string)$controller->handle('api_intro', [
                'template' => 'password.html',
                'settings' => $settings,
                'error' => 'Wrong password',
                'requireCaptcha' => $captchaRequired,
                'csrfToken' => $_SESSION[self::AUTH_CSRF_KEY],
            ]);
            exit;
        }

        // Not authenticated: show login
        echo (string)$controller->handle('api_intro', [
            'template' => 'password.html',
            'settings' => $settings,
            'requireCaptcha' => $captchaRequired,
            'csrfToken' => $_SESSION[self::AUTH_CSRF_KEY],
        ]);
        exit;
    }

    /**
     * Enforce admin login on /api/admin only (ADMIN_ENABLED + ADMIN_PASSWORD).
     *
     * @param array<string,mixed> $settings
     */
    private static function enforceAdminPassword(array $settings, AppController $controller): void
    {
        $adminEnabled  = (bool)($settings['ADMIN_ENABLED'] ?? false);
        $adminPassword = (string)($settings['ADMIN_PASSWORD'] ?? '');

        if (!$adminEnabled || $adminPassword === '') {
            return;
        }

        if (self::getPath() !== '/api/admin') {
            return;
        }

        if (!empty($_SESSION['admin']) && $_SESSION['admin'] === true) {
            return;
        }

        self::ensureCsrfToken(self::ADMIN_CSRF_KEY);
        $captchaRequired = self::isCaptchaRequired(self::ADMIN_FAILED_KEY);

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        // POST = admin login attempt (CSRF + optional captcha + password)
        if ($method === 'POST' && isset($_POST['password'])) {
            $postedToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : null;

            if (!self::validateCsrfToken(self::ADMIN_CSRF_KEY, $postedToken)) {
                self::rotateCsrfToken(self::ADMIN_CSRF_KEY);
                echo (string)$controller->handle('api_intro', [
                    'template' => 'admin.html',
                    'settings' => $settings,
                    'error' => 'Invalid or expired form token. Please try again.',
                    'requireCaptcha' => $captchaRequired,
                    'csrfToken' => $_SESSION[self::ADMIN_CSRF_KEY],
                ]);
                exit;
            }

            if ($captchaRequired && !Captcha::validate($_POST)) {
                echo (string)$controller->handle('api_intro', [
                    'template' => 'admin.html',
                    'settings' => $settings,
                    'error' => 'Captcha validation failed',
                    'requireCaptcha' => true,
                    'csrfToken' => $_SESSION[self::ADMIN_CSRF_KEY],
                ]);
                exit;
            }

            $postedPw = (string)$_POST['password'];
            if (hash_equals($adminPassword, $postedPw)) {
                $_SESSION['admin'] = true;
                self::resetFailedAttempts(self::ADMIN_FAILED_KEY);
                self::rotateCsrfToken(self::ADMIN_CSRF_KEY);
                return; // allow ApiController::api_admin to render the admin panel
            }

            self::incrementFailedAttempts(self::ADMIN_FAILED_KEY);
            $captchaRequired = self::isCaptchaRequired(self::ADMIN_FAILED_KEY);

            echo (string)$controller->handle('api_intro', [
                'template' => 'admin.html',
                'settings' => $settings,
                'error' => 'Wrong password',
                'requireCaptcha' => $captchaRequired,
                'csrfToken' => $_SESSION[self::ADMIN_CSRF_KEY],
            ]);
            exit;
        }

        // GET = show admin login
        echo (string)$controller->handle('api_intro', [
            'template' => 'admin.html',
            'settings' => $settings,
            'requireCaptcha' => $captchaRequired,
            'csrfToken' => $_SESSION[self::ADMIN_CSRF_KEY],
        ]);
        exit;
    }

    private static function getPath(): string
    {
        $path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/');
        $path = rtrim($path, '/');
        return $path === '' ? '/' : $path;
    }

    private static function ensureCsrfToken(string $sessionKey): void
    {
        $v = $_SESSION[$sessionKey] ?? null;
        if (!is_string($v) || $v === '') {
            $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
        }
    }

    private static function rotateCsrfToken(string $sessionKey): void
    {
        $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
    }

    private static function validateCsrfToken(string $sessionKey, ?string $postedToken): bool
    {
        $sessionToken = $_SESSION[$sessionKey] ?? '';
        return is_string($sessionToken)
            && $sessionToken !== ''
            && is_string($postedToken)
            && $postedToken !== ''
            && hash_equals($sessionToken, $postedToken);
    }

    private static function failedAttempts(string $sessionKey): int
    {
        return isset($_SESSION[$sessionKey]) ? (int)$_SESSION[$sessionKey] : 0;
    }

    private static function incrementFailedAttempts(string $sessionKey): void
    {
        $_SESSION[$sessionKey] = self::failedAttempts($sessionKey) + 1;
    }

    private static function resetFailedAttempts(string $sessionKey): void
    {
        unset($_SESSION[$sessionKey]);
    }

    private static function isCaptchaRequired(string $failedKey): bool
    {
        return self::failedAttempts($failedKey) >= self::CAPTCHA_AFTER_FAILED;
    }
}
