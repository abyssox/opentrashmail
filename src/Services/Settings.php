<?php
declare(strict_types=1);

namespace OpenTrashmail\Services;

class Settings
{
    private static bool $loaded = false;
    private static array|false $settings = false;

    public static function load(): array|false
    {
        if (self::$loaded) {
            return self::$settings;
        }

        self::$loaded = true;
        $configFile   = \ROOT . \DS . 'config.ini';

        if (!is_file($configFile)) {
            self::$settings = false;
            return self::$settings;
        }

        $parsed         = parse_ini_file($configFile);
        self::$settings = is_array($parsed) ? $parsed : false;

        return self::$settings;
    }
}
