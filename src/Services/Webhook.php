<?php
declare(strict_types=1);

namespace OpenTrashmail\Services;

use JsonException;

class Webhook
{
    public static function getConfig(string $email): ?array
    {
        $webhookFile = Mailbox::getDirForEmail($email) . \DS . 'webhook.json';

        if (!is_file($webhookFile)) {
            return null;
        }

        $json = file_get_contents($webhookFile);
        if ($json === false) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    public static function saveConfig(string $email, array $config): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $dir = Mailbox::getDirForEmail($email);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            return false;
        }

        $webhookFile = $dir . \DS . 'webhook.json';

        return file_put_contents($webhookFile, json_encode($config, JSON_PRETTY_PRINT)) !== false;
    }

    public static function deleteConfig(string $email): bool
    {
        $webhookFile = Mailbox::getDirForEmail($email) . \DS . 'webhook.json';

        if (is_file($webhookFile)) {
            return unlink($webhookFile);
        }

        return true;
    }
}
