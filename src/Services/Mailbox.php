<?php
declare(strict_types=1);

namespace OpenTrashmail\Services;

use JsonException;

class Mailbox
{
    public static function ensureMailboxDir(string $email): ?string
    {
        $dir = self::getDirForEmail($email);

        if (is_dir($dir)) {
            return $dir;
        }

        if (!mkdir($dir, 0o770, true) && !is_dir($dir)) {
            error_log(sprintf('[OpenTrashmail] Failed to create mailbox dir "%s"', $dir));
            return null;
        }

        return $dir;
    }

    public static function getDirForEmail(string $email): string
    {
        static $baseDir     = null;
        static $realBaseDir = null;

        $email = strtolower($email);
        $email = str_replace(
            ['../', '..\\', '/', '\\', "\0"],
            '_',
            $email
        );

        if ($baseDir === null) {
            $baseDir     = ROOT . DS . 'data';
            $realBaseDir = realpath($baseDir) ?: $baseDir;
        }

        $path = $baseDir . DS . $email;

        $realPath = realpath($path);
        if ($realPath !== false && str_starts_with($realPath, rtrim($realBaseDir, DS) . DS)) {
            return $realPath;
        }

        return $path;
    }

    private static function endsWith(string $haystack, string $needle): bool
    {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }

        return substr($haystack, -$length) === $needle;
    }

    private static function loadEmailJson(string $email, string $id): ?array
    {
        $file = self::getDirForEmail($email) . DS . $id . '.json';

        if (!is_file($file)) {
            return null;
        }

        $json = file_get_contents($file);
        if ($json === false || $json === '') {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    public static function getEmail(string $email, string $id): ?array
    {
        return self::loadEmailJson($email, $id);
    }

    public static function getRawEmail(string $email, string $id): ?string
    {
        $data = self::loadEmailJson($email, $id);

        if ($data === null || !array_key_exists('raw', $data)) {
            return null;
        }

        return is_string($data['raw']) ? $data['raw'] : (string)$data['raw'];
    }

    public static function emailIdExists(string $email, string $id): bool
    {
        $file = self::getDirForEmail($email) . DS . $id . '.json';

        return is_file($file);
    }

    public static function getEmailsOfEmail(
        string $email,
        bool $includeBody = false,
        bool $includeAttachments = false
    ): array {
        $result   = [];
        $settings = Settings::load();

        $isAdminInbox = is_array($settings)
            && !empty($settings['ADMIN'])
            && $settings['ADMIN'] === $email;

        if ($isAdminInbox) {
            $addresses = self::listEmailAddresses();
            if ($addresses === []) {
                return [];
            }
        } else {
            if ($email === '') {
                return [];
            }
            $addresses = [$email];
        }

        foreach ($addresses as $address) {
            $dir = self::getDirForEmail($address);

            if (!is_dir($dir)) {
                continue;
            }

            $handle = opendir($dir);
            if ($handle === false) {
                continue;
            }

            while (($entry = readdir($handle)) !== false) {
                if (!self::endsWith($entry, '.json')) {
                    continue;
                }

                $time     = substr($entry, 0, -5);
                $filePath = $dir . DS . $entry;

                $raw = @file_get_contents($filePath) ?: '';
                $json = json_decode($raw, true);
                if (!is_array($json) || !isset($json['parsed'], $json['raw'])) {
                    continue;
                }

                $parsed = $json['parsed'];

                $row = [
                    'email'   => $address,
                    'id'      => $time,
                    'from'    => $parsed['from']    ?? '',
                    'subject' => $parsed['subject'] ?? '',
                    'md5'     => md5($time . $json['raw']),
                    'maillen' => strlen($json['raw']),
                ];

                if ($includeBody) {
                    $row['body'] = $parsed['body'] ?? '';
                }

                if (
                    $includeAttachments &&
                    !empty($parsed['attachments']) &&
                    is_array($parsed['attachments'])
                ) {
                    $row['attachments'] = array_map(
                        static function ($attachment) use ($settings, $address) {
                            $baseUrl = is_array($settings) && !empty($settings['URL'])
                                ? $settings['URL']
                                : '';

                            return rtrim((string)$baseUrl, '/') . '/api/attachment/' . $address . '/' . $attachment;
                        },
                        $parsed['attachments']
                    );
                }

                $result[$time] = $row;
            }

            closedir($handle);
        }

        if ($result !== []) {
            ksort($result);
        }

        return $result;
    }

    public static function listEmailAddresses(): array
    {
        $out  = [];
        $base = ROOT . DS . 'data' . DS;

        if ($handle = @opendir($base)) {
            while (false !== ($entry = readdir($handle))) {
                if (filter_var($entry, FILTER_VALIDATE_EMAIL)) {
                    $out[] = $entry;
                }
            }
            closedir($handle);
        }

        return $out;
    }

    public static function attachmentExists(string $email, string $attachment): bool
    {
        $file = self::getDirForEmail($email) . DS . 'attachments' . DS . $attachment;

        return is_file($file);
    }

    public static function listAttachmentsOfMailId(string $email, string $id): array
    {
        $file = self::getDirForEmail($email) . DS . $id . '.json';

        if (!is_file($file)) {
            return [];
        }

        $json = file_get_contents($file);
        if ($json === false) {
            return [];
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        $attachments = $data['parsed']['attachments'] ?? null;

        return is_array($attachments) ? $attachments : [];
    }

    public static function deleteEmail(string $email, string $id): bool
    {
        $dir         = self::getDirForEmail($email);
        $attachments = self::listAttachmentsOfMailId($email, $id);

        foreach ($attachments as $attachment) {
            @unlink($dir . DS . 'attachments' . DS . $attachment);
        }

        return @unlink($dir . DS . $id . '.json');
    }

    public static function countEmailsOfAddress(string $email): int
    {
        $count = 0;
        $dir   = self::getDirForEmail($email);

        if ($handle = @opendir($dir)) {
            while (false !== ($entry = readdir($handle))) {
                if (self::endsWith($entry, '.json')) {
                    $count++;
                }
            }
            closedir($handle);
        }

        return $count;
    }

    public static function deleteTree(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DS . $item;

            if (is_link($path) || !is_dir($path)) {
                if (!unlink($path)) {
                    return false;
                }
            } elseif (!self::deleteTree($path)) {
                return false;
            }
        }

        return rmdir($dir);
    }
}
