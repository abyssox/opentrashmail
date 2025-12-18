<?php

declare(strict_types=1);

namespace OpenTrashmail\Controllers;

use JsonException;
use OpenTrashmail\Services\AccessGuard;
use OpenTrashmail\Services\Mailbox;
use OpenTrashmail\Services\RandomEmail;
use OpenTrashmail\Services\Webhook;
use OpenTrashmail\Utils\Captcha;
use OpenTrashmail\Utils\System;
use OpenTrashmail\Utils\View;
use Throwable;

final class ApiController extends AbstractController
{
    private const int DEFAULT_LOG_LINES = 100;

    /**
     * @param array<string, mixed> $vars
     */
    public function handle(string $routeName, array $vars = []): ?string
    {
        if ($routeName === 'api_captcha_request') {
            $this->handleCaptchaRequest();
        }

        return match ($routeName) {
            'api_intro' => $this->renderIntro($vars),

            'api_address' => $this->listAccount($this->resolveEmail($vars)),
            'api_read' => $this->readMail($this->resolveEmail($vars), $this->resolveId($vars)),

            'api_listaccounts' => $this->canSeeAccountList()
                ? $this->listAccounts()
                : $this->forbidden(),

            'api_raw_html' => $this->getRawMail(
                $this->resolveEmail($vars),
                $this->resolveId($vars),
                true
            ),
            'api_raw' => $this->getRawMail(
                $this->resolveEmail($vars),
                $this->resolveId($vars),
                false
            ),

            'api_attachment' => $this->getAttachment(
                $this->resolveEmail($vars),
                $this->resolveAttachment($vars)
            ),

            'api_delete' => $this->deleteMail($this->resolveEmail($vars), $this->resolveId($vars)),
            'api_random' => $this->listAccount(RandomEmail::generateRandomEmail()),
            'api_deleteaccount' => $this->deleteAccount($this->resolveEmail($vars)),

            'api_logs' => $this->canSeeLogs()
                ? $this->renderLogs($vars)
                : $this->forbidden(),

            'api_admin' => !empty($this->settings['ADMIN_ENABLED'])
                ? $this->renderTemplate('admin.html', ['settings' => $this->settings])
                : $this->forbidden('403 Not activated in config.ini'),

            'api_auth_actions' => $this->authActions(),
            'api_logout' => $this->logout(),

            'api_webhook' => $this->handleWebhook($vars),

            default => $this->notFound(),
        };
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function renderIntro(array $vars): string
    {
        $template = (isset($vars['template']) && is_string($vars['template']) && $vars['template'] !== '')
            ? $vars['template']
            : 'intro.html';

        $data = [
            'settings' => (isset($vars['settings']) && is_array($vars['settings']))
                ? $vars['settings']
                : $this->settings,
            'version' => System::getVersion(),
        ];

        foreach (['error', 'url', 'csrfToken'] as $key) {
            if (isset($vars[$key]) && is_string($vars[$key])) {
                $data[$key] = $vars[$key];
            }
        }

        if (array_key_exists('requireCaptcha', $vars)) {
            $data['requireCaptcha'] = (bool) $vars['requireCaptcha'];
        }

        return $this->renderTemplate($template, $data);
    }

    private function forbidden(string $message = '403 Forbidden'): string
    {
        http_response_code(403);

        return $message;
    }

    private function notFound(string $message = '404 Not Found'): string
    {
        http_response_code(404);

        return $message;
    }

    private function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function canSeeAccountList(): bool
    {
        if (empty($this->settings['SHOW_ACCOUNT_LIST'])) {
            return false;
        }

        $adminPassword = (string) ($this->settings['ADMIN_PASSWORD'] ?? '');
        if ($adminPassword === '') {
            return true;
        }

        return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['admin']);
    }

    private function canSeeLogs(): bool
    {
        if (empty($this->settings['SHOW_LOGS'])) {
            return false;
        }

        $adminPassword = (string) ($this->settings['ADMIN_PASSWORD'] ?? '');
        if ($adminPassword === '') {
            return true;
        }

        return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['admin']);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function resolveEmail(array $vars): ?string
    {
        $email = $_REQUEST['email'] ?? ($vars['email'] ?? null);
        if (!is_string($email)) {
            return null;
        }

        $email = trim($email);

        return $email !== '' ? $email : null;
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function resolveId(array $vars): ?string
    {
        $id = $_REQUEST['id'] ?? ($vars['id'] ?? null);
        if (!is_string($id)) {
            return null;
        }

        $id = trim($id);

        return $id !== '' ? $id : null;
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function resolveAttachment(array $vars): ?string
    {
        $raw = $vars['attachment']
            ?? $vars['file']
            ?? $vars['filename']
            ?? ($_REQUEST['attachment'] ?? null);

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return basename(urldecode($raw));
    }

    private function isValidEmail(?string $email): bool
    {
        return $email !== null
            && $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isValidMailId(?string $id): bool
    {
        return $id !== null && $id !== '' && ctype_digit($id);
    }

    /**
     * @param mixed $value
     * @param int $default
     * @return int
     */
    private function positiveIntOrDefault(mixed $value, int $default): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            $parsed = (int) $value;
            if ($parsed > 0) {
                return $parsed;
            }
        }

        if (is_numeric($value)) {
            $parsed = (int) $value;
            if ($parsed > 0) {
                return $parsed;
            }
        }

        return $default;
    }

    // ---------- Mailbox / account actions ----------

    public function deleteAccount(?string $email): string
    {
        if (!$this->isValidEmail($email)) {
            return $this->error('Invalid email address');
        }

        $path = Mailbox::getDirForEmail($email);
        if (is_dir($path)) {
            Mailbox::deleteTree($path);
        }

        return '';
    }

    public function listAccounts(): string
    {
        $accounts = Mailbox::listEmailAddresses();

        return $this->renderTemplate('account-list.html', [
            'emails' => $accounts,
            'dateformat' => $this->settings['DATEFORMAT'] ?? 'YYYY-MM-DD HH:mm',
        ]);
    }

    public function deleteMail(?string $email, ?string $id): string
    {
        if (!$this->isValidEmail($email)) {
            return $this->error('Invalid email address');
        }

        if (!$this->isValidMailId($id)) {
            return $this->error('Invalid id');
        }

        if (!Mailbox::emailIdExists($email, $id)) {
            return $this->error('Email not found');
        }

        Mailbox::deleteEmail($email, $id);

        return $this->listAccount($email);
    }

    public function readMail(?string $email, ?string $id): string
    {
        if (!$this->isValidEmail($email)) {
            return $this->error('Invalid email address');
        }

        if (!$this->isValidMailId($id)) {
            return $this->error('Invalid id');
        }

        if (!Mailbox::emailIdExists($email, $id)) {
            return $this->error('Email not found');
        }

        $emailData = Mailbox::getEmail($email, $id);
        if ($emailData === null) {
            return $this->error('Email not found');
        }

        if (!isset($emailData['parsed']) || !is_array($emailData['parsed'])) {
            $emailData['parsed'] = [];
        }

        $attachments = $emailData['parsed']['attachments'] ?? ($emailData['attachments'] ?? []);
        $emailData['parsed']['attachments'] = is_array($attachments) ? $attachments : [];

        return $this->renderTemplate('email.html', [
            'emailData' => $emailData,
            'email' => $email,
            'mailid' => $id,
            'dateformat' => $this->settings['DATEFORMAT'] ?? 'YYYY-MM-DD HH:mm',
        ]);
    }

    public function getRawMail(?string $email, ?string $id, bool $html): string
    {
        if (!$this->isValidEmail($email)) {
            return $this->error('Invalid email address');
        }

        if (!$this->isValidMailId($id)) {
            return $this->error('Invalid id');
        }

        if (!Mailbox::emailIdExists($email, $id)) {
            return $this->error('Email not found');
        }

        if ($html) {
            $emailData = Mailbox::getEmail($email, $id);
            if ($emailData === null) {
                return $this->error('Email not found');
            }

            header('Content-Type: text/html; charset=UTF-8');
            $parsed = isset($emailData['parsed']) && is_array($emailData['parsed']) ? $emailData['parsed'] : [];

            return (string) ($parsed['htmlbody'] ?? '');
        }

        header('Content-Type: text/plain; charset=UTF-8');

        return (string) (Mailbox::getRawEmail($email, $id) ?? '');
    }

    public function getAttachment(?string $email, ?string $attachment): string
    {
        if (!$this->isValidEmail($email)) {
            return $this->error('Invalid email address');
        }

        if ($attachment === null || $attachment === '') {
            return $this->error('Attachment not found');
        }

        if (!Mailbox::attachmentExists($email, $attachment)) {
            return $this->error('Attachment not found');
        }

        $file = Mailbox::getDirForEmail($email) . DS . 'attachments' . DS . $attachment;
        if (!is_file($file)) {
            return $this->error('Attachment not found');
        }

        $mime = mime_content_type($file) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($file));
        header('Content-Disposition: inline; filename="' . rawurlencode($attachment) . '"');

        $content = file_get_contents($file);
        if ($content === false) {
            http_response_code(500);

            return 'Failed to read attachment';
        }

        return $content;
    }

    public function listAccount(?string $email): string
    {
        $email = trim((string) $email);

        if (!$this->isValidEmail($email)) {
            $safeEmail = View::escape($email);

            return '<div class="uk-alert uk-alert-danger"><p>Invalid email address: ' . $safeEmail . '</p></div>';
        }

        $dir = Mailbox::ensureMailboxDir($email) ?? Mailbox::getDirForEmail($email);
        $emails = Mailbox::getEmailsOfEmail($email);

        $createdAt = time();
        if (is_dir($dir)) {
            $mtime = filemtime($dir);
            if ($mtime !== false) {
                $createdAt = $mtime;
            }
        }

        $expiresAt = $createdAt + (15 * 60); // expire after 15 minutes

        return $this->renderTemplate('email-table.html', [
            'isadmin' => !empty($this->settings['ADMIN']) && $this->settings['ADMIN'] === $email,
            'email' => $email,
            'emails' => $emails,
            'dateformat' => $this->settings['DATEFORMAT'] ?? 'YYYY-MM-DD HH:mm',
            'expiresAt' => $expiresAt,
        ]);
    }

    // ---------- Logs / admin ----------

    /**
     * @param array<string, mixed> $vars
     */
    private function renderLogs(array $vars): string
    {
        $linesParam = $vars['lines'] ?? null;
        $lines = $this->positiveIntOrDefault($linesParam, self::DEFAULT_LOG_LINES);

        $logDir = ROOT . DS . 'logs' . DS;

        return $this->renderTemplate('logs.html', [
            'lines' => $lines,
            'mailserverlogfile' => $logDir . 'mailserver.log',
            'webservererrorlogfile' => $logDir . 'web.error.log',
            'webserveraccesslogfile' => $logDir . 'web.access.log',
            'cleanupmaildirlogfile' => $logDir . 'cleanup_maildir.log',
            'configfile' => ROOT . DS . 'config.ini',
        ]);
    }

    // ---------- Webhook actions ----------

    /**
     * @param array<string, mixed> $vars
     */
    private function handleWebhook(array $vars): string
    {
        $action = isset($vars['action']) ? (string) $vars['action'] : null;
        $email = $this->resolveEmail($vars);

        if ($email === null) {
            http_response_code(400);

            return '400 Bad Request: missing email';
        }

        return match ($action) {
            'get' => $this->getWebhook($email),
            'save' => $this->saveWebhook($email, $_REQUEST),
            'delete' => $this->deleteWebhook($email),
            default => $this->notFound(),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(array $payload): string
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            http_response_code(500);

            return '{"success":false,"message":"JSON encoding error"}';
        }
    }

    public function getWebhook(string $email): string
    {
        if (!$this->isValidEmail($email)) {
            return $this->error('Invalid email address');
        }

        $config = Webhook::getConfig($email);

        return $this->jsonResponse($config ?: ['enabled' => false]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveWebhook(string $email, array $data): string
    {
        if (!$this->isValidEmail($email)) {
            return $this->error('Invalid email address');
        }

        $webhookUrl = trim((string) ($data['webhook_url'] ?? ''));
        if ($webhookUrl !== '' && filter_var($webhookUrl, FILTER_VALIDATE_URL) === false) {
            return $this->jsonResponse(['success' => false, 'message' => 'Invalid webhook URL']);
        }

        if ($webhookUrl !== '') {
            $parsed = parse_url($webhookUrl);
            $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
            $host = strtolower((string) ($parsed['host'] ?? ''));

            if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
                return $this->jsonResponse(['success' => false, 'message' => 'Invalid webhook URL']);
            }

            $blockedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '[::1]', 'host.docker.internal'];
            if (in_array($host, $blockedHosts, true)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Webhook URL cannot point to internal services',
                ]);
            }

            if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                $publicIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                if ($publicIp === false) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Webhook URL cannot point to private IP addresses',
                    ]);
                }
            }
        }

        $payloadTemplate = (string) ($data['payload_template']
            ?? '{"email":"{{to}}","from":"{{from}}","subject":"{{subject}}","body":"{{body}}"}');

        $placeholders = [
            '{{to}}',
            '{{from}}',
            '{{subject}}',
            '{{body}}',
            '{{htmlbody}}',
            '{{sender_ip}}',
            '{{attachments}}',
        ];

        $replacements = [
            'test',
            'test',
            'test',
            'test',
            'test',
            'test',
            '[]',
        ];

        $testJson = str_replace($placeholders, $replacements, $payloadTemplate);

        try {
            json_decode($testJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->jsonResponse(['success' => false, 'message' => 'Invalid JSON in payload template']);
        }

        $maxAttempts = isset($data['max_attempts']) ? (int) $data['max_attempts'] : 3;
        if ($maxAttempts < 1 || $maxAttempts > 10) {
            return $this->jsonResponse(['success' => false, 'message' => 'Max attempts must be between 1 and 10']);
        }

        $backoffMultiplier = isset($data['backoff_multiplier']) ? (float) $data['backoff_multiplier'] : 2.0;
        if ($backoffMultiplier < 1 || $backoffMultiplier > 5) {
            return $this->jsonResponse(['success' => false, 'message' => 'Backoff multiplier must be between 1 and 5']);
        }

        $enabled = filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        $config = [
            'enabled' => $enabled,
            'webhook_url' => $webhookUrl,
            'payload_template' => $payloadTemplate,
            'retry_config' => [
                'max_attempts' => $maxAttempts,
                'backoff_multiplier' => $backoffMultiplier,
            ],
            'secret_key' => isset($data['secret_key'])
                ? substr((string) $data['secret_key'], 0, 255)
                : '',
        ];

        if (Webhook::saveConfig($email, $config)) {
            return $this->jsonResponse(['success' => true, 'message' => 'Webhook configuration saved']);
        }

        return $this->jsonResponse(['success' => false, 'message' => 'Failed to save webhook configuration']);
    }

    public function deleteWebhook(string $email): string
    {
        if (!$this->isValidEmail($email)) {
            return $this->error('Invalid email address');
        }

        $success = Webhook::deleteConfig($email);

        return $this->jsonResponse([
            'success' => $success,
            'message' => $success
                ? 'Webhook configuration deleted'
                : 'Failed to delete webhook configuration',
        ]);
    }

    // ---------- Auth actions ----------

    private function isAuthenticatedSession(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        return (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true)
            || (!empty($_SESSION['admin']) && $_SESSION['admin'] === true);
    }

    private function authActions(): string
    {
        $this->ensureSessionStarted();

        if (!$this->isAuthenticatedSession()) {
            return '';
        }

        return '<a href="#"'
            . ' hx-post="/api/logout"'
            . ' hx-swap="none"'
            . ' class="otm-link"'
            . ' aria-label="Logout"'
            . ' title="Logout">'
            . '<i class="fa-solid fa-right-from-bracket"></i>'
            . '<span class="uk-margin-small-left">Logout</span>'
            . '</a>';
    }

    private function logout(): string
    {
        $this->ensureSessionStarted();

        AccessGuard::destroyCurrentSession();

        if (($_SERVER['HTTP_HX_REQUEST'] ?? null) === 'true') {
            header('HX-Redirect: /');

            return '';
        }

        header('Location: /', true, 303);

        return '';
    }

    private function handleCaptchaRequest(): never
    {
        $this->ensureSessionStarted();

        try {
            Captcha::processRequest();
        } catch (Throwable) {
            http_response_code(500);
        }

        exit;
    }
}
