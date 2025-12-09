<?php
declare(strict_types=1);

namespace OpenTrashmail\Controllers;

use OpenTrashmail\Services\Mailbox;

final class RssController extends AbstractController
{
    /**
     * @param array<string,mixed> $vars
     */
    public function handle(array $vars = []): ?string
    {
        header('Content-Type: application/rss+xml; charset=UTF-8');

        $email = isset($vars['email']) ? (string)$vars['email'] : null;
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(404);
            exit('Error: Email not found');
        }

        return $this->renderTemplate('rss.xml', [
            'email'     => $email,
            'emaildata' => Mailbox::getEmailsOfEmail($email),
            'url'       => $this->settings['URL'] ?? '',
        ]);
    }
}
