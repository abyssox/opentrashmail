<?php
declare(strict_types=1);

namespace OpenTrashmail\Utils;

use InvalidArgumentException;

class Http
{
    public static function getUserIp(): string
    {
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        // Trust proxy-injected headers only when the TCP connection comes from a
        // private/loopback address — i.e. the server sits behind a local reverse
        // proxy. On direct connections every forwarded header is attacker-controlled.
        $isPrivate = filter_var($remote, FILTER_VALIDATE_IP) !== false
            && filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

        if ($isPrivate) {
            $cfIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
            if (is_string($cfIp) && filter_var($cfIp, FILTER_VALIDATE_IP)) {
                return $cfIp;
            }

            $forward = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($forward !== '') {
                $parts = array_map('trim', explode(',', $forward));
                foreach ($parts as $part) {
                    if (filter_var($part, FILTER_VALIDATE_IP)) {
                        return $part;
                    }
                }
            }
        }

        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '';
    }

    public static function isIpInRange(string $ip, string $range): bool
    {
        if (str_contains($range, ',')) {
            $ranges = array_map('trim', explode(',', $range));
            return array_any($ranges, fn($singleRange) => self::isIpInRange($ip, $singleRange));
        }

        if (!str_contains($range, '/')) {
            $range .= str_contains($range, ':') ? '/128' : '/32';
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
