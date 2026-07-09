<?php

declare(strict_types=1);

namespace Verstka\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Verstka\Sdk\Callback\CallbackProcessor;
use Verstka\Sdk\Exception\VerstkaCallbackDataError;
use Verstka\Sdk\Exception\VerstkaSignatureError;
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;
use Verstka\Sdk\Finalize\ContentPreSaveContext;
use Verstka\Sdk\Finalize\FontsFinalizeContext;
use Verstka\Sdk\Finalize\FontsFinalizeResult;
use Verstka\Sdk\Finalize\FontsPreSaveContext;
use Verstka\Sdk\Finalize\PreSaveDecision;
use Verstka\Sdk\Signature\SignatureService;
use Verstka\Sdk\Storage\StorageAdapter;
use Verstka\Sdk\Tests\Support\TestConfig;
use Verstka\Sdk\Tests\Support\ZipFactory;

final class CallbackProcessorTest extends TestCase
{
    public function testProcessMaterialCallbackHappyPath(): void
    {
        $contentUrl = 'https://verstka.test/download/abc';
        $zipPath = sys_get_temp_dir() . '/verstka_test_' . uniqid('', true) . '.zip';
        ZipFactory::buildContentZip(
            $zipPath,
            ['hero.png' => 'img'],
            ['assets' => ['hero.png' => ['clientUrl' => 'dummy-hero.png']]],
            '<p><img src=dummy-hero.png></p>'
        );
        $zipBytes = file_get_contents($zipPath);
        @unlink($zipPath);

        $mock = new MockHandler([new Response(200, [], $zipBytes)]);
        $processor = new CallbackProcessor(TestConfig::make(), new Client(['handler' => HandlerStack::create($mock)]));
        $storage = new RecordingMediaStorage();
        $captured = [];

        $result = $processor->processMaterialCallback(
            [
                'material_id' => 'M1',
                'content_url' => $contentUrl,
                'metadata' => ['site' => 's1'],
            ],
            SignatureService::signMaterial('M1', $contentUrl, TestConfig::API_SECRET),
            $storage,
            function (ContentFinalizeContext $ctx) use (&$captured): ContentFinalizeResult {
                $captured['ctx'] = $ctx;
                return new ContentFinalizeResult(true, $ctx->vmsJson);
            }
        );

        self::assertTrue($result->success);
        self::assertSame(1, $result->toResponse()['rc']);
        self::assertCount(1, $storage->mediaCalls);
        self::assertSame('hero.png', $storage->mediaCalls[0][0]);
        self::assertArrayHasKey('ctx', $captured);
        self::assertStringContainsString('https://cdn.test/M1/hero.png', (string) $captured['ctx']->vmsHtml);
    }

    public function testProcessMaterialCallbackRejectsInvalidSignature(): void
    {
        $processor = new CallbackProcessor(TestConfig::make(), new Client());

        $this->expectException(VerstkaSignatureError::class);
        $processor->processMaterialCallback(
            ['material_id' => 'M1', 'content_url' => 'https://x.test/c', 'metadata' => []],
            'bad-signature',
            new RecordingMediaStorage(),
            static fn (ContentFinalizeContext $ctx): ContentFinalizeResult => new ContentFinalizeResult(true)
        );
    }

    public function testProcessMaterialCallbackPreSaveRejection(): void
    {
        $processor = new CallbackProcessor(TestConfig::make(), new Client());

        $result = $processor->processMaterialCallback(
            ['material_id' => 'M1', 'content_url' => 'https://x.test/c', 'metadata' => []],
            SignatureService::signMaterial('M1', 'https://x.test/c', TestConfig::API_SECRET),
            new RecordingMediaStorage(),
            static fn (ContentFinalizeContext $ctx): ContentFinalizeResult => new ContentFinalizeResult(true),
            static fn (ContentPreSaveContext $ctx): PreSaveDecision => new PreSaveDecision(false, 'denied')
        );

        self::assertFalse($result->success);
        self::assertSame('denied', $result->message);
    }

    public function testProcessFontsCallbackHappyPath(): void
    {
        $contentUrl = 'https://verstka.test/download/fonts';
        $zipPath = sys_get_temp_dir() . '/verstka_test_' . uniqid('', true) . '.zip';
        ZipFactory::buildFontsZip(
            $zipPath,
            ['Inter-Regular.woff2' => 'fontdata'],
            ['list' => []],
            'url(dummy-Inter-Regular.woff2)'
        );
        $zipBytes = file_get_contents($zipPath);
        @unlink($zipPath);

        $mock = new MockHandler([new Response(200, [], $zipBytes)]);
        $targetDir = sys_get_temp_dir() . '/verstka_font_storage_' . uniqid('', true);
        mkdir($targetDir);
        $processor = new CallbackProcessor(TestConfig::make(), new Client(['handler' => HandlerStack::create($mock)]));
        $storage = new LocalFontStorage($targetDir);

        try {
            $result = $processor->processFontsCallback(
                [
                    'event' => 'site_fonts_updated',
                    'material_id' => 'M1',
                    'content_url' => $contentUrl,
                    'metadata' => [],
                    'fonts' => ['list' => []],
                ],
                SignatureService::signMaterial('M1', $contentUrl, TestConfig::API_SECRET),
                $storage
            );

            self::assertTrue($result->success);
            self::assertSame(1, $result->toResponse()['rc']);
            self::assertFileExists($targetDir . '/Inter-Regular.woff2');
        } finally {
            $this->removeDir($targetDir);
        }
    }

    public function testProcessFontsCallbackRequiresContentUrl(): void
    {
        $processor = new CallbackProcessor(TestConfig::make(), new Client());

        $this->expectException(VerstkaCallbackDataError::class);
        $processor->processFontsCallback(
            ['material_id' => 'M1', 'content_url' => '', 'fonts' => []],
            SignatureService::signMaterial('M1', '', TestConfig::API_SECRET),
            new LocalFontStorage(sys_get_temp_dir())
        );
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

final class RecordingMediaStorage implements StorageAdapter
{
    /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
    public array $mediaCalls = [];

    public function saveMedia(string $filename, string $tempPath, string $materialId, array $metadata): string
    {
        $this->mediaCalls[] = [$filename, $materialId, $metadata];
        return 'https://cdn.test/' . $materialId . '/' . $filename;
    }

    public function saveFontFile(string $filename, string $tempPath, string $materialId, array $metadata): string
    {
        throw new \AssertionError('save_font_file should not be called in material flow');
    }

    public function saveFontsManifest(string $filename, string $tempPath, string $materialId, array $metadata): string
    {
        throw new \AssertionError('save_fonts_manifest should not be called in material flow');
    }
}

final class LocalFontStorage implements StorageAdapter
{
    private string $targetDir;

    public function __construct(string $targetDir)
    {
        $this->targetDir = $targetDir;
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0775, true);
        }
    }

    public function saveMedia(string $filename, string $tempPath, string $materialId, array $metadata): string
    {
        throw new \AssertionError('save_media should not be called in fonts flow');
    }

    public function saveFontFile(string $filename, string $tempPath, string $materialId, array $metadata): string
    {
        copy($tempPath, $this->targetDir . '/' . $filename);
        return 'https://cdn.test/fonts/' . $filename;
    }

    public function saveFontsManifest(string $filename, string $tempPath, string $materialId, array $metadata): string
    {
        copy($tempPath, $this->targetDir . '/' . $filename);
        return 'https://cdn.test/fonts/' . $filename;
    }
}
