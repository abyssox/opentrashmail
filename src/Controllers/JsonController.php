<?php
declare(strict_types=1);

namespace OpenTrashmail\Controllers;

use OpenTrashmail\Services\Mailbox;

final class JsonController extends AbstractController
{
    /**
     * @param array<string,mixed> $vars
     */
    public function handle(string $routeName, array $vars = []): ?string
    {
        $adminPassword = $this->settings['ADMIN_PASSWORD'] ?? '';

        switch ($routeName) {
            case 'json_listaccounts':
                header('Content-Type: application/json; charset=UTF-8');

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

            case 'json_email':
                header('Content-Type: application/json; charset=UTF-8');

                $email = isset($vars['email']) ? (string)$vars['email'] : null;
                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(404);
                    exit(json_encode(['error' => 'Email not found']));
                }

                $id = isset($vars['id']) ? (string)$vars['id'] : null;

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
                http_response_code(404);
                return json_encode(['error' => 'Not Found']);
        }
    }
}
