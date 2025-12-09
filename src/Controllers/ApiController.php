<?php
declare(strict_types=1);

namespace OpenTrashmail\Controllers;

use OpenTrashmail\Services\Mailbox;
use OpenTrashmail\Services\RandomEmail;
use OpenTrashmail\Services\Webhook;

final class ApiController extends AbstractController
{
    /**
     * @param string $routeName
     * @param array<string,mixed> $vars
     *
     * @return string|null
     */
    public function handle(string $routeName, array $vars = []): ?string
    {
        $adminPassword = $this->settings['ADMIN_PASSWORD'] ?? '';

        $canSeeAccountList = function () use ($adminPassword): bool {
            return !empty($this->settings['SHOW_ACCOUNT_LIST'])
                && (
                    ($adminPassword !== '' && !empty($_SESSION['admin']))
                    || $adminPassword === ''
                );
        };

        $canSeeLogs = function () use ($adminPassword): bool {
            return !empty($this->settings['SHOW_LOGS'])
                && (
                    ($adminPassword !== '' && !empty($_SESSION['admin']))
                    || $adminPassword === ''
                );
        };

        switch ($routeName) {
            case 'api_intro':
                $template = isset($vars['template']) && is_string($vars['template'])
                    ? $vars['template']
                    : 'intro.html';

                $data = [];

                if (isset($vars['error']) && is_string($vars['error'])) {
                    $data['error'] = $vars['error'];
                }

                if (isset($vars['url']) && is_string($vars['url'])) {
                    $data['url'] = $vars['url'];
                }

                if (isset($vars['settings']) && is_array($vars['settings'])) {
                    $data['settings'] = $vars['settings'];
                } else {
                    $data['settings'] = $this->settings;
                }

                return $this->renderTemplate($template, $data);

            case 'api_address':
                $email = $this->resolveEmail($vars);
                return $this->listAccount($email);

            case 'api_read':
                $email = $this->resolveEmail($vars);
                $id    = $this->resolveId($vars);
                return $this->readMail($email, $id);

            case 'api_listaccounts':
                if ($canSeeAccountList()) {
                    return $this->listAccounts();
                }
                http_response_code(403);
                return '403 Forbidden';

            case 'api_raw_html':
                return $this->getRawMail(
                    $this->resolveEmail($vars),
                    $this->resolveId($vars),
                    true
                );

            case 'api_raw':
                return $this->getRawMail(
                    $this->resolveEmail($vars),
                    $this->resolveId($vars),
                    false
                );

            case 'api_attachment':
                $email      = $this->resolveEmail($vars);
                $attachment = isset($vars['attachment']) ? (string)$vars['attachment'] : null;
                return $this->getAttachment($email, $attachment);

            case 'api_delete':
                $email = $this->resolveEmail($vars);
                $id    = $this->resolveId($vars);
                return $this->deleteMail($email, $id);

            case 'api_random':
                return $this->listAccount(RandomEmail::generateRandomEmail());

            case 'api_deleteaccount':
                $email = $this->resolveEmail($vars);
                return $this->deleteAccount($email);

            case 'api_logs':
                if (!$canSeeLogs()) {
                    http_response_code(403);
                    return '403 Forbidden';
                }

                $linesParam = $vars['lines'] ?? null;
                $lines      = (is_numeric($linesParam) && (int)$linesParam > 0)
                    ? (int)$linesParam
                    : 100;

                $logDir = ROOT . DS . 'logs' . DS;

                return $this->renderTemplate('logs.html', [
                    'lines'                  => $lines,
                    'mailserverlogfile'      => $logDir . 'mailserver.log',
                    'webservererrorlogfile'  => $logDir . 'web.error.log',
                    'webserveraccesslogfile' => $logDir . 'web.access.log',
                    'cleanupmaildirlogfile'  => $logDir . 'cleanup_maildir.log',
                    'configfile'             => ROOT . DS . 'config.ini',
                ]);

            case 'api_admin':
                if (!empty($this->settings['ADMIN_ENABLED'])) {
                    return $this->renderTemplate('admin.html', [
                        'settings' => $this->settings,
                    ]);
                }
                http_response_code(403);
                return '403 Not activated in config.ini';

            case 'api_webhook':
                $action = isset($vars['action']) ? (string)$vars['action'] : null;
                $email  = $this->resolveEmail($vars);

                if ($email === null) {
                    http_response_code(400);
                    return '400 Bad Request: missing email';
                }

                return match ($action) {
                    'get'    => $this->getWebhook($email),
                    'save'   => $this->saveWebhook($email, $_REQUEST),
                    'delete' => $this->deleteWebhook($email),
                    default  => '404 Not Found',
                };

            default:
                http_response_code(404);
                return '404 Not Found';
        }
    }

    /**
     * @param array<string,mixed> $vars
     */
    private function resolveEmail(array $vars): ?string
    {
        $email = $_REQUEST['email'] ?? ($vars['email'] ?? null);

        if (!is_string($email) || $email === '') {
            return null;
        }

        return $email;
    }

    /**
     * @param array<string,mixed> $vars
     */
    private function resolveId(array $vars): ?string
    {
        $id = $_REQUEST['id'] ?? ($vars['id'] ?? null);

        if (!is_string($id) || $id === '') {
            return null;
        }

        return $id;
    }

    // ---------- Mailbox / account actions ----------

    public function deleteAccount(?string $email): string
    {
        if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
            'emails'     => $accounts,
            'dateformat' => $this->settings['DATEFORMAT'] ?? 'YYYY-MM-DD HH:mm',
        ]);
    }

    public function deleteMail(?string $email, ?string $id): string
    {
        if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        if (!is_string($id) || !is_numeric($id)) {
            return $this->error('Invalid id');
        }

        if (!Mailbox::emailIDExists($email, $id)) {
            return $this->error('Email not found');
        }

        Mailbox::deleteEmail($email, $id);
        return '';
    }

    public function getRawMail(?string $email, ?string $id, bool $htmlbody = false): ?string
    {
        if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        if (!is_string($id) || !is_numeric($id)) {
            return $this->error('Invalid id');
        }

        if (!Mailbox::emailIDExists($email, $id)) {
            return $this->error('Email not found');
        }

        $emailData = Mailbox::getEmail($email, $id);
        if ($emailData === null) {
            return $this->error('Email not found');
        }

        if ($htmlbody) {
            exit($emailData['parsed']['htmlbody'] ?? '');
        }

        header('Content-Type: text/plain; charset=UTF-8');
        echo $emailData['raw'] ?? '';
        exit;
    }

    public function getAttachment(?string $email, ?string $attachment): ?string
    {
        if ($attachment === null) {
            return $this->error('Attachment not found');
        }

        $attachment = basename(urldecode((string)$attachment));

        if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        if (!Mailbox::attachmentExists($email, $attachment)) {
            return $this->error('Attachment not found');
        }

        $dir  = Mailbox::getDirForEmail($email);
        $file = $dir . DS . 'attachments' . DS . $attachment;

        $mime = mime_content_type($file) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($file));
        readfile($file);
        exit;
    }

    public function readMail(?string $email, ?string $id): string
    {
        if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        if (!is_string($id) || !is_numeric($id)) {
            return $this->error('Invalid id');
        }

        if (!Mailbox::emailIDExists($email, $id)) {
            return $this->error('Email not found');
        }

        $emailData = Mailbox::getEmail($email, $id);

        return $this->renderTemplate('email.html', [
            'emaildata'  => $emailData,
            'email'      => $email,
            'mailid'     => $id,
            'dateformat' => $this->settings['DATEFORMAT'] ?? 'YYYY-MM-DD HH:mm',
        ]);
    }

    public function listAccount(?string $email): string
    {
        $email = trim((string)$email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            return '
                <div class="uk-alert uk-alert-danger">
                    <p>Invalid email address: ' . $safeEmail . '</p>
                </div>
            ';
        }

        $dir    = Mailbox::ensureMailboxDir($email) ?? Mailbox::getDirForEmail($email);
        $emails = Mailbox::getEmailsOfEmail($email);

        $createdAt = time();

        if (is_dir($dir)) {
            $mtime     = filemtime($dir);
            $createdAt = $mtime !== false ? $mtime : $createdAt;
        }

        $expiresAt = $createdAt + (15 * 60); // expire after 15 minutes

        return $this->renderTemplate('email-table.html', [
            'isadmin'    => !empty($this->settings['ADMIN']) && $this->settings['ADMIN'] === $email,
            'email'      => $email,
            'emails'     => $emails,
            'dateformat' => $this->settings['DATEFORMAT'] ?? 'YYYY-MM-DD HH:mm',
            'expiresAt'  => $expiresAt,
        ]);
    }

    // ---------- Webhook actions ----------

    public function getWebhook(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        header('Content-Type: application/json; charset=UTF-8');

        $config = Webhook::getConfig($email);

        return json_encode($config ?: ['enabled' => false]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function saveWebhook(string $email, array $data): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        header('Content-Type: application/json; charset=UTF-8');

        $webhook_url = isset($data['webhook_url'])
            ? filter_var($data['webhook_url'], FILTER_SANITIZE_URL)
            : '';

        if ($webhook_url && !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            return json_encode(['success' => false, 'message' => 'Invalid webhook URL']);
        }

        if ($webhook_url) {
            $parsed = parse_url($webhook_url);
            $host   = $parsed['host'] ?? '';

            $blocked_hosts = ['localhost', '127.0.0.1', '0.0.0.0', '[::1]', 'host.docker.internal'];
            if (in_array(strtolower($host), $blocked_hosts, true)) {
                return json_encode(['success' => false, 'message' => 'Webhook URL cannot point to internal services']);
            }

            if (filter_var($host, FILTER_VALIDATE_IP)) {
                if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return json_encode(['success' => false, 'message' => 'Webhook URL cannot point to private IP addresses']);
                }
            }
        }

        $payload_template = $data['payload_template']
            ?? '{"email":"{{to}}","from":"{{from}}","subject":"{{subject}}","body":"{{body}}"}';

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

        $testJson = str_replace($placeholders, $replacements, $payload_template);

        try {
            json_decode($testJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return json_encode(['success' => false, 'message' => 'Invalid JSON in payload template']);
        }

        $max_attempts = isset($data['max_attempts']) ? (int)$data['max_attempts'] : 3;
        if ($max_attempts < 1 || $max_attempts > 10) {
            return json_encode(['success' => false, 'message' => 'Max attempts must be between 1 and 10']);
        }

        $backoff_multiplier = isset($data['backoff_multiplier']) ? (float)$data['backoff_multiplier'] : 2.0;
        if ($backoff_multiplier < 1 || $backoff_multiplier > 5) {
            return json_encode(['success' => false, 'message' => 'Backoff multiplier must be between 1 and 5']);
        }

        $config = [
            'enabled'          => isset($data['enabled']) ? filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN) : false,
            'webhook_url'      => $webhook_url,
            'payload_template' => $payload_template,
            'retry_config'     => [
                'max_attempts'       => $max_attempts,
                'backoff_multiplier' => $backoff_multiplier,
            ],
            'secret_key'       => isset($data['secret_key']) ? substr((string)$data['secret_key'], 0, 255) : '',
        ];

        if (Webhook::saveConfig($email, $config)) {
            return json_encode(['success' => true, 'message' => 'Webhook configuration saved']);
        }

        return json_encode(['success' => false, 'message' => 'Failed to save webhook configuration']);
    }

    public function deleteWebhook(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        header('Content-Type: application/json; charset=UTF-8');

        $success = Webhook::deleteConfig($email);

        return json_encode([
            'success' => $success,
            'message' => $success
                ? 'Webhook configuration deleted'
                : 'Failed to delete webhook configuration',
        ]);
    }
}
