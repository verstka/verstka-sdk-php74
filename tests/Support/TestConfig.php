<?php

declare(strict_types=1);

namespace Verstka\Sdk\Tests\Support;

use Verstka\Sdk\Config\VerstkaConfig;

final class TestConfig
{
    public const API_SECRET = 'test-secret';
    public const API_KEY = 'test-api-key';
    public const CALLBACK_URL = 'https://app.example.com/verstka/callback';

    public static function make(bool $debug = false): VerstkaConfig
    {
        return new VerstkaConfig(
            self::API_KEY,
            self::API_SECRET,
            self::CALLBACK_URL,
            'https://verstka.test/api/v2',
            1024 * 1024,
            5.0,
            5.0,
            $debug
        );
    }
}
