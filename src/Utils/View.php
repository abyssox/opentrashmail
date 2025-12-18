<?php
declare(strict_types=1);

namespace OpenTrashmail\Utils;

class View
{
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function arrayToUnorderedList(array $items): string
    {
        $out = '<ul>';
        foreach ($items as $elem) {
            $out .= '<li>' . (string)$elem . '</li>';
        }
        $out .= '</ul>';

        return $out;
    }

    public static function assetUrl(string $path): string
    {
        $webPath  = '/assets/' . ltrim($path, '/');
        $filePath = \ROOT . '/public' . $webPath;

        $version = is_file($filePath) ? filemtime($filePath) : null;

        if ($version !== null) {
            $webPath .= '?v=' . $version;
        }

        return htmlspecialchars($webPath, ENT_QUOTES, 'UTF-8');
    }
}
