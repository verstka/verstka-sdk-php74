<?php

declare(strict_types=1);

namespace Verstka\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Verstka\Sdk\Content\ZipDownloader;
use Verstka\Sdk\Content\ZipExtractor;
use Verstka\Sdk\Exception\VerstkaApiError;
use Verstka\Sdk\Exception\VerstkaContentTooLargeError;
use Verstka\Sdk\Tests\Support\ZipFactory;

final class ContentTest extends TestCase
{
    public function testExtractContentZipBasic(): void
    {
        $zipPath = sys_get_temp_dir() . '/verstka_test_' . uniqid('', true) . '.zip';
        ZipFactory::buildContentZip(
            $zipPath,
            media: ['hero.png' => 'pngdata'],
            vmsJson: ['assets' => ['hero.png' => ['clientUrl' => 'dummy-hero.png']]],
            vmsHtml: '<img src=dummy-hero.png>',
        );

        $tempDir = sys_get_temp_dir() . '/verstka_content_' . uniqid('', true);
        mkdir($tempDir);

        try {
            $extractor = new ZipExtractor();
            $result = $extractor->extractContentZip($zipPath, $tempDir);

            self::assertSame(['hero.png'], array_keys($result->media));
            self::assertSame('pngdata', file_get_contents($result->media['hero.png']));
            self::assertSame('<img src=dummy-hero.png>', $result->vmsHtml);
            self::assertSame(
                ['assets' => ['hero.png' => ['clientUrl' => 'dummy-hero.png']]],
                json_decode((string) $result->vmsJson, true, 512, JSON_THROW_ON_ERROR),
            );
        } finally {
            @unlink($zipPath);
            $this->removeDir($tempDir);
        }
    }

    public function testExtractSkipsUnknownExtensions(): void
    {
        $zipPath = sys_get_temp_dir() . '/verstka_test_' . uniqid('', true) . '.zip';
        ZipFactory::buildContentZip($zipPath, media: ['evil.exe' => 'bad', 'ok.png' => 'ok']);
        $tempDir = sys_get_temp_dir() . '/verstka_content_' . uniqid('', true);
        mkdir($tempDir);

        try {
            $result = (new ZipExtractor())->extractContentZip($zipPath, $tempDir);
            self::assertSame(['ok.png'], array_keys($result->media));
        } finally {
            @unlink($zipPath);
            $this->removeDir($tempDir);
        }
    }

    public function testExtractFontsZip(): void
    {
        $zipPath = sys_get_temp_dir() . '/verstka_test_' . uniqid('', true) . '.zip';
        ZipFactory::buildFontsZip(
            $zipPath,
            fonts: ['Inter-Regular.woff2' => 'font-bytes'],
            vmsFontsJson: ['families' => []],
            vmsFontsCss: '@font-face { src: url(dummy-Inter-Regular.woff2); }',
        );
        $tempDir = sys_get_temp_dir() . '/verstka_fonts_' . uniqid('', true);
        mkdir($tempDir);

        try {
            $result = (new ZipExtractor())->extractFontsZip($zipPath, $tempDir);
            self::assertSame(['Inter-Regular.woff2'], array_keys($result->fontFiles));
            self::assertSame('font-bytes', file_get_contents($result->fontFiles['Inter-Regular.woff2']));
            self::assertNotNull($result->vmsFontsJsonPath);
            self::assertNotNull($result->vmsFontsCssPath);
        } finally {
            @unlink($zipPath);
            $this->removeDir($tempDir);
        }
    }

    public function testDownloadZipSuccess(): void
    {
        $zipPath = sys_get_temp_dir() . '/verstka_test_' . uniqid('', true) . '.zip';
        ZipFactory::buildContentZip($zipPath, media: ['x.png' => 'hi']);
        $zipBytes = file_get_contents($zipPath);

        $mock = new MockHandler([new Response(200, [], $zipBytes)]);
        $downloader = new ZipDownloader(new Client(['handler' => HandlerStack::create($mock)]));
        $dest = sys_get_temp_dir() . '/verstka_out_' . uniqid('', true) . '.zip';

        try {
            $downloader->download('https://x.test/content', $dest, 10 * 1024, 5.0);
            self::assertSame($zipBytes, file_get_contents($dest));
        } finally {
            @unlink($zipPath);
            @unlink($dest);
        }
    }

    public function testDownloadZip403(): void
    {
        $mock = new MockHandler([new Response(403)]);
        $downloader = new ZipDownloader(new Client(['handler' => HandlerStack::create($mock)]));
        $dest = sys_get_temp_dir() . '/verstka_out_' . uniqid('', true) . '.zip';

        try {
            $this->expectException(VerstkaApiError::class);
            $downloader->download('https://x.test/content', $dest, 1024, 5.0);
        } finally {
            @unlink($dest);
        }
    }

    public function testDownloadZipTooLarge(): void
    {
        $mock = new MockHandler([new Response(200, ['Content-Length' => '9999'], 'x')]);
        $downloader = new ZipDownloader(new Client(['handler' => HandlerStack::create($mock)]));
        $dest = sys_get_temp_dir() . '/verstka_out_' . uniqid('', true) . '.zip';

        try {
            $this->expectException(VerstkaContentTooLargeError::class);
            $downloader->download('https://x.test/content', $dest, 10, 5.0);
        } finally {
            @unlink($dest);
        }
    }

    private function removeDir(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
