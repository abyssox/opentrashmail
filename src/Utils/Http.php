<?php
declare(strict_types=1);

namespace OpenTrashmail\Utils;

use InvalidArgumentException;

class Http
{
    public static function getUserIp(): string
    {
        // Cloudflare header first, if present and valid
        $cfIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
        if (is_string($cfIp) && filter_var($cfIp, FILTER_VALIDATE_IP)) {
            return $cfIp;
        }

        $client  = (string) ($_SERVER['HTTP_CLIENT_IP']       ?? '');
        $forward = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        $remote  = (string) ($_SERVER['REMOTE_ADDR']          ?? '');

        if ($forward !== '') {
            $parts = array_map('trim', explode(',', $forward));
            foreach ($parts as $part) {
                if (filter_var($part, FILTER_VALIDATE_IP)) {
                    return $part;
                }
            }
        }

        if (filter_var($client, FILTER_VALIDATE_IP)) {
            return $client;
        }

        if (filter_var($remote, FILTER_VALIDATE_IP)) {
            return $remote;
        }

        return '';
    }

    public static function isIpInRange(string $ip, string $range): bool
    {
        if (str_contains($range, ',')) {
            $ranges = array_map('trim', explode(',', $range));
            return array_any($ranges, fn($singleRange) => self::isIpInRange($ip, $singleRange));
        }

        // Get mask bits
        [$net, $maskBits] = explode('/', $range);

        $maskBits = (int)$maskBits;

        // Size
        $size = str_contains($ip, ':') ? 16 : 4;

        // Convert to binary
        $ipBin  = inet_pton($ip);
        $netBin = inet_pton($net);
        if (!$ipBin || !$netBin) {
            throw new InvalidArgumentException('Invalid IP address');
        }

        // Build mask
        $solid     = (int)floor($maskBits / 8);
        $solidBits = $solid * 8;
        $mask      = str_repeat(chr(255), $solid);

        for ($i = $solidBits; $i < $maskBits; $i += 8) {
            $bits = max(0, min(8, $maskBits - $i));
            $mask .= chr(((2 ** $bits) - 1) << (8 - $bits));
        }

        $mask = str_pad($mask, $size, chr(0));

        // Compare the mask
        return ($ipBin & $mask) === ($netBin & $mask);
    }
}
