<?php
declare(strict_types=1);

namespace OpenTrashmail\Utils;

class System
{
    public static function tailShell(string $filepath, int $lines = 1): string
    {
        ob_start();
        passthru('tail -' . $lines . ' ' . escapeshellarg($filepath));
        $output = ob_get_clean();

        return trim((string)$output);
    }

    public static function getVersion(): string
    {
        $versionFile = \ROOT . \DS . 'VERSION';

        if (is_file($versionFile)) {
            return trim((string)file_get_contents($versionFile));
        }

        return '';
    }
}
