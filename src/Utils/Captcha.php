<?php
declare(strict_types=1);

namespace OpenTrashmail\Utils;

use IconCaptcha\Challenge\Generators\GD;
use IconCaptcha\IconCaptcha;

final class Captcha
{
    public static function config(): array
    {
        return self::getCaptchaConfig();
    }

    public static function processRequest(): void
    {
        $captcha = new IconCaptcha(self::getCaptchaConfig());
        $captcha->handleCors();
        $captcha->request()->process();
    }

    public static function validate(array $post): bool
    {
        $captcha = new IconCaptcha(self::getCaptchaConfig());
        return $captcha->validate($post)->success();
    }

    private static function getCaptchaConfig(): array
    {
        return [
            'iconPath' => '/var/www/opentrashmail/public/assets/iconcaptcha/icons/',

            'ipAddress' => static fn() => $_SERVER['REMOTE_ADDR'],

            'token' => null,

            'storage' => [
                'driver' => 'session',
                'datetimeFormat' => 'Y-m-d H:i:s'
            ],

            'challenge' => [
                'availableIcons' => 250,
                'iconAmount' => ['min' => 5, 'max' => 8],
                'rotate' => true,
                'flip' => ['horizontally' => true, 'vertically' => true],
                'border' => true,
                'generator' => GD::class,
            ],

            'validation' => [
                'inactivityExpiration' => 120,
                'completionExpiration' => 300,
                'attempts' => [
                    'enabled' => true,
                    'amount' => 3,
                    'timeout' => 60,
                    'valid' => 30,
                    'storage' => [
                        'driver' => null,
                        'options' => [
                            'table' => 'iconcaptcha_attempts',
                            'purging' => true,
                        ],
                    ],
                ],
            ],

            'session' => [
                'driver' => null,
                'options' => [
                    'purging' => true,
                    'identifierTries' => 100,
                ],
            ],

            'cors' => [
                'enabled' => false,
                'origins' => [],
                'credentials' => true,
                'cache' => 86400,
            ],
        ];
    }
}
