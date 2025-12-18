<?php

declare(strict_types=1);

namespace OpenTrashmail\Controllers;

use JsonException;
use OpenTrashmail\Services\Mailbox;

final class JsonController extends AbstractController
{
    /**
     * @param array<string, mixed> $vars
     */
    public function handle(string $routeName, array $vars = []): ?string
    {
        $this->sendJsonHeaders();

        return match ($routeName) {
            'json_listaccounts' => $this->listAccounts(),
            'json_email' => $this->handleEmailRequest($vars),
            default => $this->jsonError(404, 'Not Found'),
        };
    }

    private function sendJsonHeaders(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    }

    private function listAccounts(): string
    {
        $requestPassword = $_REQUEST['password'] ?? null;

        if (!$this->canShowAccountList($requestPassword)) {
            return $this->jsonError(403, '403 Forbidden');
        }

        return $this->jsonOk(Mailbox::listEmailAddresses());
    }

    /**
     * @param mixed $requestPassword
     */
    private function canShowAccountList(mixed $requestPassword): bool
    {
        if (empty($this->settings['SHOW_ACCOUNT_LIST'])) {
            return false;
        }

        $adminPassword = (string) ($this->settings['ADMIN_PASSWORD'] ?? '');
        if ($adminPassword === '') {
            return true;
        }

        return is_string($requestPassword) && hash_equals($adminPassword, $requestPassword);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function handleEmailRequest(array $vars): string
    {
        $email = $this->resolveEmail($vars);
        if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->jsonError(404, 'Email not found');
        }

        $id = $this->resolveId($vars);
        if ($id !== null) {
            if (!Mailbox::emailIDExists($email, $id)) {
                return $this->jsonError(404, 'Email ID not found');
            }

            if (!ctype_digit($id)) {
                return $this->jsonError(400, 'Invalid ID');
            }

            return $this->jsonOk(Mailbox::getEmail($email, $id));
        }

        return $this->jsonOk(Mailbox::getEmailsOfEmail($email, true, true));
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

    private function jsonOk(mixed $data): string
    {
        return $this->jsonResponse($data, 200);
    }

    private function jsonError(int $statusCode, string $message): string
    {
        return $this->jsonResponse(['error' => $message], $statusCode);
    }

    private function jsonResponse(mixed $data, int $statusCode): string
    {
        http_response_code($statusCode);

        try {
            return json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException) {
            http_response_code(500);
            return '{"error":"Internal Server Error"}';
        }
    }
}
