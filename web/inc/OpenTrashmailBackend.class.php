<?php
declare(strict_types=1);

class OpenTrashmailBackend
{
    private $url;
    private $settings;

    public function __construct($url)
    {
        $this->url      = $url;
        $this->settings = loadSettings();
    }

    public function run()
    {
        $segment0 = $this->url[0] ?? null;
        $adminPassword = $this->settings['ADMIN_PASSWORD'] ?? '';

        // api calls
        if ($segment0 === 'api') {
            // avoid undefined index for action
            $action = $this->url[1] ?? null;

            switch ($action) {
                case 'address':
                    // $_REQUEST['email'] may be undefined
                    $email = ($_REQUEST['email'] ?? null) ?: ($this->url[2] ?? null);
                    return $this->listAccount($email);

                case 'read':
                    $email = ($_REQUEST['email'] ?? null) ?: ($this->url[2] ?? null);
                    $id    = ($_REQUEST['id'] ?? null) ?: ($this->url[3] ?? null);
                    return $this->readMail($email, $id);

                case 'listaccounts':

                    if (
                        $this->settings['SHOW_ACCOUNT_LIST'] &&
                        (
                            ($adminPassword !== '' && !empty($_SESSION['admin'])) ||
                            $adminPassword === ''
                        )
                    ) {
                        return $this->listAccounts();
                    }
                    return '403 Forbidden';

                case 'raw-html':
                    $email = $this->url[2] ?? null;
                    $id    = $this->url[3] ?? null;
                    return $this->getRawMail($email, $id, true);

                case 'raw':
                    $email = $this->url[2] ?? null;
                    $id    = $this->url[3] ?? null;
                    return $this->getRawMail($email, $id);

                case 'attachment':
                    $email      = $this->url[2] ?? null;
                    $attachment = $this->url[3] ?? null;
                    return $this->getAttachment($email, $attachment);

                case 'delete':
                    $email = ($_REQUEST['email'] ?? null) ?: ($this->url[2] ?? null);
                    $id    = ($_REQUEST['id'] ?? null) ?: ($this->url[3] ?? null);
                    return $this->deleteMail($email, $id);

                case 'random':
                    $addr = generateRandomEmail();
                    return $this->listAccount($addr);

                case 'deleteaccount':
                    $email = ($_REQUEST['email'] ?? null) ?: ($this->url[2] ?? null);
                    return $this->deleteAccount($email);

                case 'logs':
                    if (
                        $this->settings['SHOW_LOGS'] &&
                        (
                            ($adminPassword !== '' && !empty($_SESSION['admin'])) ||
                            $adminPassword === ''
                        )
                    ) {
                        $linesParam = $this->url[2] ?? null;
                        return $this->renderTemplate('logs.html', [
                            'lines'                 => (is_numeric($linesParam) && $linesParam > 0) ? $linesParam : 100,
                            'mailserverlogfile'     => ROOT . DS . '../logs' . DS . 'mailserver.log',
                            'webservererrorlogfile' => ROOT . DS . '../logs' . DS . 'web.error.log',
                            'webserveraccesslogfile'=> ROOT . DS . '../logs' . DS . 'web.access.log',
                            'configfile'            => ROOT . DS . '../config.ini',
                        ]);
                    }
                    return '403 Forbidden';

                case 'admin':
                    if ($this->settings['ADMIN_ENABLED'] == true) {
                        return $this->renderTemplate('admin.html', [
                            'settings' => $this->settings,
                        ]);
                    }
                    return '403 Not activated in config.ini';

                case 'webhook':
                    $webhookAction = $this->url[2] ?? null;
                    if ($webhookAction === 'get') {
                        $email = ($_REQUEST['email'] ?? null) ?: ($this->url[3] ?? null);
                        return $this->getWebhook($email);
                    }

                    if ($webhookAction === 'save') {
                        $email = ($_REQUEST['email'] ?? null) ?: ($this->url[3] ?? null);
                        return $this->saveWebhook($email, $_REQUEST);
                    }

                    if ($webhookAction === 'delete') {
                        $email = ($_REQUEST['email'] ?? null) ?: ($this->url[3] ?? null);
                        return $this->deleteWebhook($email);
                    }
                    break;

                default:
                    return false;
            }
        }

        // rss feed
        if ($segment0 === 'rss') {
            header("Content-Type: application/rss+xml; charset=UTF8");
            $email = $this->url[1] ?? null;
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(404);
                exit('Error: Email not found');
            }
            return $this->renderTemplate('rss.xml', [
                'email'    => $email,
                'emaildata'=> getEmailsOfEmail($email),
                'url'      => $this->settings['URL'],
            ]);
        }

        // json api
        if ($segment0 === 'json') {
            header("Content-Type: application/json; charset=UTF8");
            $jsonAction = $this->url[1] ?? null;

            if ($jsonAction === 'listaccounts') {
                $requestPassword = $_REQUEST['password'] ?? '';
                if (
                    $this->settings['SHOW_ACCOUNT_LIST'] &&
                    (
                        ($adminPassword !== "" && ($requestPassword === $adminPassword)) ||
                        $adminPassword === ''
                    )
                ) {
                    return json_encode(listEmailAddresses());
                }
                exit(json_encode(['error' => '403 Forbidden']));
            }

            $email = $this->url[1] ?? null;
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(404);
                exit(json_encode(['error' => 'Email not found']));
            }

            $id = $this->url[2] ?? null;
            if ($id) { // user wants a specific email ID
                if (!emailIDExists($email, $id)) {
                    http_response_code(404);
                    exit(json_encode(['error' => 'Email ID not found']));
                }

                if (!is_numeric($id)) {
                    http_response_code(400);
                    exit(json_encode(['error' => 'Invalid ID']));
                }
                return json_encode(getEmail($email, $id));
            }

            return json_encode(getEmailsOfEmail($email, true, true));
        }

        return false;
    }

    public function deleteAccount($email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        $path = getDirForEmail($email);
        if (is_dir($path)) {
            delTree($path);
        }

        return '';
    }

    public function listAccounts(): string
    {
        $accounts = listEmailAddresses();

        return $this->renderTemplate('account-list.html', [
            'emails'     => $accounts,
            'dateformat' => $this->settings['DATEFORMAT'],
        ]);
    }

    public function deleteMail($email, $id): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        if (!is_numeric($id)) {
            return $this->error('Invalid id');
        }

        if (!emailIDExists($email, $id)) {
            return $this->error('Email not found');
        }
        deleteEmail($email, $id);
        return '';
    }

    public function getRawMail($email, $id, $htmlbody = false): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        if (!is_numeric($id)) {
            return $this->error('Invalid id');
        }

        if (!emailIDExists($email, $id)) {
            return $this->error('Email not found');
        }

        $emailData = getEmail($email, $id);

        if ($htmlbody) {
            exit($emailData['parsed']['htmlbody']);
        }

        header('Content-Type: text/plain');
        echo $emailData['raw'];
        exit;
    }

    public function getAttachment($email, $attachment): ?string
    {
        $attachment = basename(urldecode((string) $attachment));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        if (!attachmentExists($email, $attachment)) {
            return $this->error('Attachment not found');
        }

        $dir  = getDirForEmail($email);
        $file = $dir . DS . 'attachments' . DS . $attachment;
        $mime = mime_content_type($file);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    public function readMail($email, $id): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        if (!is_numeric($id)) {
            return $this->error('Invalid id');
        }

        if (!emailIDExists($email, $id)) {
            return $this->error('Email not found');
        }

        $emailData = getEmail($email, $id);

        return $this->renderTemplate('email.html', [
            'emaildata'  => $emailData,
            'email'      => $email,
            'mailid'     => $id,
            'dateformat' => $this->settings['DATEFORMAT'],
        ]);
    }

    public function listAccount($email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $safeEmail = htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8');
            return '
                <div class="uk-alert uk-alert-danger" uk-alert>
                    <p>Invalid email address: ' . $safeEmail . '</p>
                </div>
            ';
        }

        $emails = getEmailsOfEmail($email);

        return $this->renderTemplate('email-table.html', [
            'isadmin'   => ($this->settings['ADMIN'] === $email),
            'email'     => $email,
            'emails'    => $emails,
            'dateformat'=> $this->settings['DATEFORMAT'],
        ]);
    }

    public function error($text): string
    {
        return '<h1>' . $text . '</h1>';
    }

    public function renderTemplate(string $templatename, array $variables = []): string
    {
        ob_start();

        if (!empty($variables)) {
            extract($variables);
        }

        $templateBase = ROOT . DS . 'templates' . DS;

        if (file_exists($templateBase . $templatename . '.php')) {
            include $templateBase . $templatename . '.php';
        } elseif (file_exists($templateBase . $templatename)) {
            include $templateBase . $templatename;
        }

        return ob_get_clean();
    }

    public function getWebhook(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        header('Content-Type: application/json; charset=UTF-8');

        $config = getWebhookConfig($email);

        return json_encode($config ?: ['enabled' => false]);
    }


    public function saveWebhook(string $email, array $data): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        header("Content-Type: application/json; charset=UTF8");

        // Validate webhook URL
        $webhook_url = isset($data['webhook_url']) ? filter_var($data['webhook_url'], FILTER_SANITIZE_URL) : '';
        if ($webhook_url && !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            return json_encode(['success' => false, 'message' => 'Invalid webhook URL']);
        }

        // Prevent SSRF by blocking internal IPs and local domains
        if ($webhook_url) {
            $parsed = parse_url($webhook_url);
            $host   = $parsed['host'] ?? '';

            // Block localhost and common internal hostnames
            $blocked_hosts = ['localhost', '127.0.0.1', '0.0.0.0', '[::1]', 'host.docker.internal'];
            if (in_array(strtolower($host), $blocked_hosts, true)) {
                return json_encode(['success' => false, 'message' => 'Webhook URL cannot point to internal services']);
            }

            // Block private IP ranges
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return json_encode(['success' => false, 'message' => 'Webhook URL cannot point to private IP addresses']);
                }
            }
        }

        // Validate payload template is valid JSON
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
            // Just validate that it is valid JSON – result is ignored
            json_decode($testJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return json_encode(['success' => false, 'message' => 'Invalid JSON in payload template']);
        }

        // Validate retry config
        $max_attempts = isset($data['max_attempts']) ? (int) $data['max_attempts'] : 3;
        if ($max_attempts < 1 || $max_attempts > 10) {
            return json_encode(['success' => false, 'message' => 'Max attempts must be between 1 and 10']);
        }

        $backoff_multiplier = isset($data['backoff_multiplier']) ? (float) $data['backoff_multiplier'] : 2;
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
            'secret_key'       => isset($data['secret_key']) ? substr($data['secret_key'], 0, 255) : '',
        ];

        if (saveWebhookConfig($email, $config)) {
            return json_encode(['success' => true, 'message' => 'Webhook configuration saved']);
        }

        return json_encode(['success' => false, 'message' => 'Failed to save webhook configuration']);
    }

    public function deleteWebhook(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address');
        }

        header("Content-Type: application/json; charset=UTF8");

        $success = deleteWebhookConfig($email);

        return json_encode([
            'success' => $success,
            'message' => $success
                ? 'Webhook configuration deleted'
                : 'Failed to delete webhook configuration',
        ]);
    }
}
