<?php
declare(strict_types=1);

namespace OpenTrashmail\Controllers;

use OpenTrashmail\Services\Mailbox;
use OpenTrashmail\Services\RandomEmail;
use OpenTrashmail\Services\Webhook;

class AppController
{
    private array $settings;
    private array $url;

    public function __construct(array $url, array $settings)
    {
        $this->url = $url;
        $this->settings = $settings;
    }

    public function handle()
    {
        $segment0      = $this->url[0] ?? null;
        $adminPassword = $this->settings['ADMIN_PASSWORD'] ?? '';

        $getEmail = function (int $segmentIndex = 2) {
            return ($_REQUEST['email'] ?? null) ?: ($this->url[$segmentIndex] ?? null);
        };

        $getId = function (int $segmentIndex = 3) {
            return ($_REQUEST['id'] ?? null) ?: ($this->url[$segmentIndex] ?? null);
        };

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

        switch ($segment0) {

            /* API */
            case 'api':
                $action = $this->url[1] ?? null;

                switch ($action) {
                    case 'address':
                        return $this->listAccount($getEmail());

                    case 'read':
                        return $this->readMail($getEmail(), $getId());

                    case 'listaccounts':
                        if ($canSeeAccountList()) {
                            return $this->listAccounts();
                        }
                        return '403 Forbidden';

                    case 'raw-html':
                        return $this->getRawMail($this->url[2] ?? null, $this->url[3] ?? null, true);

                    case 'raw':
                        return $this->getRawMail($this->url[2] ?? null, $this->url[3] ?? null);

                    case 'attachment':
                        return $this->getAttachment($this->url[2] ?? null, $this->url[3] ?? null);

                    case 'delete':
                        return $this->deleteMail($getEmail(), $getId());

                    case 'random':
                        return $this->listAccount(RandomEmail::generateRandomEmail());

                    case 'deleteaccount':
                        return $this->deleteAccount($getEmail());

                    case 'logs':
                        if ($canSeeLogs()) {
                            $linesParam = $this->url[2] ?? null;
                            $lines = (is_numeric($linesParam) && (int)$linesParam > 0)
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
                        }
                        return '403 Forbidden';

                    case 'admin':
                        if (!empty($this->settings['ADMIN_ENABLED'])) {
                            return $this->renderTemplate('admin.html', [
                                'settings' => $this->settings,
                            ]);
                        }
                        return '403 Not activated in config.ini';

                    case 'webhook':
                        $webhookAction = $this->url[2] ?? null;
                        $email         = $getEmail(3);

                        if ($email === null) {
                            http_response_code(400);
                            return '400 Bad Request: missing email';
                        }

                        return match ($webhookAction) {
                            'get'    => $this->getWebhook($email),
                            'save'   => $this->saveWebhook($email, $_REQUEST),
                            'delete' => $this->deleteWebhook($email),
                            default  => '404 Not Found',
                        };

                    default:
                        return false;
                }

            /* RSS */
            case 'rss':
                header('Content-Type: application/rss+xml; charset=UTF-8');

                $email = $this->url[1] ?? null;
                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(404);
                    exit('Error: Email not found');
                }

                return $this->renderTemplate('rss.xml', [
                    'email'     => $email,
                    'emaildata' => Mailbox::getEmailsOfEmail($email),
                    'url'       => $this->settings['URL'] ?? '',
                ]);

            /* JSON API */
            case 'json':
                header('Content-Type: application/json; charset=UTF-8');

                $jsonAction = $this->url[1] ?? null;

                if ($jsonAction === 'listaccounts') {
                    $requestPassword = $_REQUEST['password'] ?? '';

                    if (
                        !empty($this->settings['SHOW_ACCOUNT_LIST'])
                        && (
                            ($adminPassword !== '' && $requestPassword === $adminPassword)
                            || $adminPassword === ''
                        )
                    ) {
                        return json_encode(Mailbox::listEmailAddresses());
                    }

                    http_response_code(403);
                    exit(json_encode(['error' => '403 Forbidden']));
                }

                $email = $jsonAction;
                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(404);
                    exit(json_encode(['error' => 'Email not found']));
                }

                $id = $this->url[2] ?? null;

                if ($id !== null) {
                    if (!Mailbox::emailIDExists($email, $id)) {
                        http_response_code(404);
                        exit(json_encode(['error' => 'Email ID not found']));
                    }

                    if (!is_numeric($id)) {
                        http_response_code(400);
                        exit(json_encode(['error' => 'Invalid ID']));
                    }

                    return json_encode(Mailbox::getEmail($email, $id));
                }

                return json_encode(Mailbox::getEmailsOfEmail($email, true, true));

            default:
                return false;
        }
    }

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

        if (!is_numeric($id)) {
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

        if (!is_numeric($id)) {
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

        if (!is_numeric($id)) {
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
            $mtime = filemtime($dir);
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

    public function error(string $text): string
    {
        return '<h1>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</h1>';
    }

    public function renderTemplate(string $templatename, array $variables = []): string
    {
        ob_start();

        if (!empty($variables)) {
            extract($variables, EXTR_SKIP);
        }

        $templateBase = TEMPLATES_PATH;

        if (file_exists($templateBase . $templatename . '.php')) {
            include $templateBase . $templatename . '.php';
        } elseif (file_exists($templateBase . $templatename)) {
            include $templateBase . $templatename;
        }

        return (string)ob_get_clean();
    }

    public function getWebhook(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        header('Content-Type: application/json; charset=UTF-8');

        $config = Webhook::getConfig($email);

        return json_encode($config ?: ['enabled' => false]);
    }

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
