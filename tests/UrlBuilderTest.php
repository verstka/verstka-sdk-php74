<?php

declare(strict_types=1);

namespace Verstka\Sdk\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Verstka\Sdk\Url\UrlBuilder;

final class UrlBuilderTest extends TestCase
{
    public function testAddsApiKeyAndMaterialId(): void
    {
        $url = UrlBuilder::buildAuthorizedContentUrl('https://x.test/content', 'KEY', 'M1');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        self::assertSame('KEY', $params['api_key']);
        self::assertSame('M1', $params['material_id']);
    }

    public function testPreservesExistingParams(): void
    {
        $url = UrlBuilder::buildAuthorizedContentUrl('https://x.test/c?foo=1&bar=2', 'K', 'M');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        self::assertSame('1', $params['foo']);
        self::assertSame('2', $params['bar']);
        self::assertSame('K', $params['api_key']);
        self::assertSame('M', $params['material_id']);
    }

    public function testOverridesExistingAuthParams(): void
    {
        $url = UrlBuilder::buildAuthorizedContentUrl('https://x.test/c?api_key=OLD', 'NEW', 'M');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        self::assertSame('NEW', $params['api_key']);
    }

    public function testEmptyValuesAreSkipped(): void
    {
        $url = UrlBuilder::buildAuthorizedContentUrl('https://x.test/c?keep=1', '', '');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        self::assertArrayNotHasKey('api_key', $params);
        self::assertArrayNotHasKey('material_id', $params);
        self::assertSame('1', $params['keep']);
    }

    public function testRequiresUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UrlBuilder::buildAuthorizedContentUrl('', 'K', 'M');
    }
}
