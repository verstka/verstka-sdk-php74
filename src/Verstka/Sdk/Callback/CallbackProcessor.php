<?php

declare(strict_types=1);

namespace Verstka\Sdk\Callback;

use GuzzleHttp\ClientInterface;
use JsonException;
use Verstka\Sdk\Config\VerstkaConfig;
use Verstka\Sdk\Content\ExtractedContent;
use Verstka\Sdk\Content\ExtractedFonts;
use Verstka\Sdk\Content\ZipDownloader;
use Verstka\Sdk\Content\ZipExtractor;
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
use Verstka\Sdk\Url\UrlBuilder;

final class CallbackProcessor
{
    private const REJECTED_DEFAULT_MESSAGE = 'Operation rejected';
    private const VMS_FONTS_CSS = 'vms_fonts.css';
    private const VMS_FONTS_JSON = 'vms_fonts.json';

    private ZipDownloader $zipDownloader;
    private ZipExtractor $zipExtractor;
    private VerstkaConfig $config;

    public function __construct(VerstkaConfig $config, ClientInterface $httpClient)
    {
        $this->config = $config;
        $this->zipDownloader = new ZipDownloader($httpClient);
        $this->zipExtractor = new ZipExtractor();
    }

    /**
     * @param array<string, mixed> $callbackData
     * @param callable(ContentFinalizeContext): ContentFinalizeResult $onFinalize
     * @param callable(ContentPreSaveContext): PreSaveDecision|null $onPreSave
     */
    public function processMaterialCallback(
        array $callbackData,
        string $signature,
        StorageAdapter $storage,
        callable $onFinalize,
        ?callable $onPreSave = null
    ): MaterialCallbackResult {
        $this->verifyCallback($callbackData, $signature);

        $materialId = (string) ($callbackData['material_id'] ?? '');
        if ($materialId === '') {
            throw new VerstkaCallbackDataError('material_id is required');
        }

        $contentUrl = (string) ($callbackData['content_url'] ?? '');
        $metadata = is_array($callbackData['metadata'] ?? null) ? $callbackData['metadata'] : [];

        if ($onPreSave !== null) {
            $decision = $onPreSave(new ContentPreSaveContext($materialId, $metadata, $contentUrl));
            if (!$decision->allow) {
                return $this->buildMaterialRejection($decision, $callbackData, $metadata);
            }
        }

        $extracted = null;
        try {
            if ($contentUrl !== '') {
                $extracted = $this->downloadMaterial($contentUrl, $materialId, $signature);
            }

            $vmsJsonDict = $this->parseVmsJson($extracted !== null ? $extracted->vmsJson : null);
            $vmsHtml = $extracted !== null ? $extracted->vmsHtml : null;
            $mediaFiles = $extracted !== null ? $extracted->media : [];

            $savedMediaUrls = [];
            foreach ($mediaFiles as $filename => $tempPath) {
                $publicUrl = $this->requireUrl(
                    $storage->saveMedia($filename, $tempPath, $materialId, $metadata),
                    'media file ' . $filename
                );
                $savedMediaUrls[$filename] = $publicUrl;
                $vmsHtml = $this->applyMediaUrlPatches($filename, $publicUrl, $vmsHtml, $vmsJsonDict);
            }

            $finalizeResult = $onFinalize(new ContentFinalizeContext(
                $materialId,
                $metadata,
                $vmsJsonDict,
                $vmsHtml,
                $savedMediaUrls
            ));
        } finally {
            if ($extracted !== null) {
                $this->cleanupTempDir($extracted->tempDir);
            }
        }

        return $this->buildMaterialResult($finalizeResult, $callbackData, $metadata);
    }

    /**
     * @param array<string, mixed> $callbackData
     * @param callable(FontsFinalizeContext): FontsFinalizeResult|null $onFinalize
     * @param callable(FontsPreSaveContext): PreSaveDecision|null $onPreSave
     */
    public function processFontsCallback(
        array $callbackData,
        string $signature,
        StorageAdapter $storage,
        ?callable $onFinalize = null,
        ?callable $onPreSave = null
    ): FontsCallbackResult {
        $this->verifyCallback($callbackData, $signature);

        $materialId = (string) ($callbackData['material_id'] ?? '');
        $contentUrl = (string) ($callbackData['content_url'] ?? '');
        $metadata = is_array($callbackData['metadata'] ?? null) ? $callbackData['metadata'] : [];
        $fontsPayload = is_array($callbackData['fonts'] ?? null) ? $callbackData['fonts'] : [];

        if ($contentUrl === '') {
            throw new VerstkaCallbackDataError('content_url is required for fonts callback');
        }

        if ($onPreSave !== null) {
            $decision = $onPreSave(new FontsPreSaveContext($materialId, $metadata, $contentUrl, $fontsPayload));
            if (!$decision->allow) {
                return $this->buildFontsRejection($decision, $fontsPayload);
            }
        }

        $extracted = null;
        try {
            $extracted = $this->downloadFonts($contentUrl, $materialId, $signature);

            $savedFontUrls = [];
            foreach ($extracted->fontFiles as $basename => $tempPath) {
                $savedFontUrls[$basename] = $this->requireUrl(
                    $storage->saveFontFile($basename, $tempPath, $materialId, $metadata),
                    'font file ' . $basename
                );
            }

            $cssUrl = $this->persistCss(
                $extracted->vmsFontsCssPath,
                $savedFontUrls,
                $storage,
                $materialId,
                $metadata
            );
            $this->fillFontClientUrls($fontsPayload, $savedFontUrls, $cssUrl);
            $jsonUrl = $this->persistJson(
                $extracted->vmsFontsJsonPath,
                $savedFontUrls,
                $cssUrl,
                $storage,
                $materialId,
                $metadata
            );

            $finalizeResult = $onFinalize !== null
                ? $onFinalize(new FontsFinalizeContext(
                    $materialId,
                    $metadata,
                    $fontsPayload,
                    $cssUrl,
                    $jsonUrl,
                    $savedFontUrls
                ))
                : new FontsFinalizeResult(true);
        } finally {
            if ($extracted !== null) {
                $this->cleanupTempDir($extracted->tempDir);
            }
        }

        return $this->buildFontsResult($finalizeResult, $fontsPayload);
    }

    /**
     * @param array<string, mixed> $callbackData
     */
    private function verifyCallback(array $callbackData, string $signature): void
    {
        $contentUrl = (string) ($callbackData['content_url'] ?? '');
        $materialId = (string) ($callbackData['material_id'] ?? '');

        if (!SignatureService::verifySignature($materialId, $contentUrl, $signature, $this->config->apiSecret)) {
            if ($this->config->debug) {
                throw new VerstkaSignatureError(
                    'Invalid signature ' . var_export($signature, true) . ' for material_id=' . var_export($materialId, true)
                );
            }
            throw new VerstkaSignatureError();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseVmsJson(?string $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            $parsed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * @param array<string, mixed>|null $vmsJsonDict
     */
    private function applyMediaUrlPatches(
        string $filename,
        string $publicUrl,
        ?string $vmsHtml,
        ?array $vmsJsonDict
    ): ?string {
        $updatedHtml = $vmsHtml;
        if ($updatedHtml !== null) {
            $dummy = 'dummy-' . $filename;
            if (strpos($updatedHtml, $dummy) !== false) {
                $updatedHtml = str_replace($dummy, $publicUrl, $updatedHtml);
            }
        }

        if (
            $vmsJsonDict !== null
            && isset($vmsJsonDict['assets'])
            && is_array($vmsJsonDict['assets'])
            && isset($vmsJsonDict['assets'][$filename])
            && is_array($vmsJsonDict['assets'][$filename])
        ) {
            $vmsJsonDict['assets'][$filename]['clientUrl'] = $publicUrl;
        }

        return $updatedHtml;
    }

    /**
     * @param array<string, string> $savedFiles
     */
    private function patchCssUrls(string $cssText, array $savedFiles): string
    {
        foreach ($savedFiles as $fileId => $publicUrl) {
            $cssText = str_replace('dummy-' . $fileId, $publicUrl, $cssText);
        }

        return $cssText;
    }

    /**
     * @param array<string, mixed> $fonts
     * @param array<string, string> $savedFiles
     */
    private function fillFontClientUrls(array &$fonts, array $savedFiles, ?string $cssUrl): void
    {
        if (isset($fonts['css']) && is_array($fonts['css']) && $cssUrl !== null) {
            $fonts['css']['clientUrl'] = $cssUrl;
        }

        foreach ($fonts['list'] ?? [] as &$familyEntry) {
            if (!is_array($familyEntry)) {
                continue;
            }
            foreach ($familyEntry['variants'] ?? [] as &$variant) {
                if (!is_array($variant)) {
                    continue;
                }
                $files = $variant['files'] ?? [];
                if (!is_array($files)) {
                    continue;
                }
                foreach ($files as &$fileInfo) {
                    if (!is_array($fileInfo)) {
                        continue;
                    }
                    $fileId = $fileInfo['id'] ?? null;
                    if (is_string($fileId) && isset($savedFiles[$fileId])) {
                        $fileInfo['clientUrl'] = $savedFiles[$fileId];
                    }
                }
                unset($fileInfo);
            }
            unset($variant);
        }
        unset($familyEntry);
    }

    /**
     * @param mixed $url
     */
    private function requireUrl($url, string $context): string
    {
        if (!is_string($url) || $url === '') {
            throw new VerstkaCallbackDataError('storage returned an invalid URL for ' . $context);
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $callbackData
     * @param array<string, mixed> $metadata
     */
    private function buildMaterialResult(
        ContentFinalizeResult $finalizeResult,
        array $callbackData,
        array $metadata
    ): MaterialCallbackResult {
        $data = [];
        if ($finalizeResult->vmsJson !== null) {
            $data['vms_json'] = $finalizeResult->vmsJson;
        }
        if ($this->config->debug) {
            $data['debug_info'] = [
                'callback_data' => $callbackData,
                'metadata' => $metadata,
            ];
        }

        return new MaterialCallbackResult(
            $finalizeResult->success,
            $finalizeResult->success ? 'Saved successfully' : 'Operation failed',
            $data
        );
    }

    /**
     * @param array<string, mixed> $callbackData
     * @param array<string, mixed> $metadata
     */
    private function buildMaterialRejection(
        PreSaveDecision $decision,
        array $callbackData,
        array $metadata
    ): MaterialCallbackResult {
        $data = [];
        if ($this->config->debug) {
            $data['debug_info'] = [
                'callback_data' => $callbackData,
                'metadata' => $metadata,
                'rejected' => true,
            ];
        }

        return new MaterialCallbackResult(
            false,
            $decision->reason ?? self::REJECTED_DEFAULT_MESSAGE,
            $data
        );
    }

    /**
     * @param array<string, mixed> $fallbackFonts
     */
    private function buildFontsResult(FontsFinalizeResult $finalizeResult, array $fallbackFonts): FontsCallbackResult
    {
        $fonts = $finalizeResult->fonts ?? $fallbackFonts;

        return new FontsCallbackResult(
            $finalizeResult->success,
            $finalizeResult->success ? 'Fonts saved successfully' : 'Operation failed',
            $fonts
        );
    }

    /**
     * @param array<string, mixed> $fallbackFonts
     */
    private function buildFontsRejection(PreSaveDecision $decision, array $fallbackFonts): FontsCallbackResult
    {
        return new FontsCallbackResult(
            false,
            $decision->reason ?? self::REJECTED_DEFAULT_MESSAGE,
            $fallbackFonts
        );
    }

    /**
     * @param array<string, string> $savedFontUrls
     */
    private function persistCss(
        ?string $cssPath,
        array $savedFontUrls,
        StorageAdapter $storage,
        string $materialId,
        array $metadata
    ): ?string {
        if ($cssPath === null || !is_file($cssPath)) {
            return null;
        }

        $this->rewriteCssInPlace($cssPath, $savedFontUrls);

        return $this->requireUrl(
            $storage->saveFontsManifest(self::VMS_FONTS_CSS, $cssPath, $materialId, $metadata),
            'manifest ' . self::VMS_FONTS_CSS
        );
    }

    /**
     * @param array<string, string> $savedFontUrls
     */
    private function persistJson(
        ?string $jsonPath,
        array $savedFontUrls,
        ?string $cssUrl,
        StorageAdapter $storage,
        string $materialId,
        array $metadata
    ): ?string {
        if ($jsonPath === null || !is_file($jsonPath)) {
            return null;
        }

        $this->rewriteFontsJsonInPlace($jsonPath, $savedFontUrls, $cssUrl);

        return $this->requireUrl(
            $storage->saveFontsManifest(self::VMS_FONTS_JSON, $jsonPath, $materialId, $metadata),
            'manifest ' . self::VMS_FONTS_JSON
        );
    }

    /**
     * @param array<string, string> $savedFontUrls
     */
    private function rewriteCssInPlace(string $cssPath, array $savedFontUrls): void
    {
        $cssText = file_get_contents($cssPath);
        if ($cssText === false) {
            return;
        }

        $patched = $this->patchCssUrls($cssText, $savedFontUrls);
        if ($patched !== $cssText) {
            file_put_contents($cssPath, $patched);
        }
    }

    /**
     * @param array<string, string> $savedFontUrls
     */
    private function rewriteFontsJsonInPlace(string $jsonPath, array $savedFontUrls, ?string $cssUrl): void
    {
        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            return;
        }

        try {
            $fonts = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return;
        }

        if (!is_array($fonts)) {
            return;
        }

        $this->fillFontClientUrls($fonts, $savedFontUrls, $cssUrl);
        file_put_contents($jsonPath, json_encode($fonts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function downloadMaterial(string $contentUrl, string $materialId, string $signature): ExtractedContent
    {
        $tempDir = $this->makeContentTempDir();
        try {
            $zipPath = $tempDir . '/content.zip';
            $this->zipDownloader->download(
                $this->authorizedUrl($contentUrl, $materialId),
                $zipPath,
                $this->config->maxContentSize,
                $this->config->downloadTimeout,
                ['X-Verstka-Signature' => $signature]
            );

            return $this->zipExtractor->extractContentZip($zipPath, $tempDir);
        } catch (\Throwable $exception) {
            $this->cleanupTempDir($tempDir);
            throw $exception;
        }
    }

    private function downloadFonts(string $contentUrl, string $materialId, string $signature): ExtractedFonts
    {
        $tempDir = $this->makeFontsTempDir();
        try {
            $zipPath = $tempDir . '/fonts.zip';
            $this->zipDownloader->download(
                $this->authorizedUrl($contentUrl, $materialId),
                $zipPath,
                $this->config->maxContentSize,
                $this->config->downloadTimeout,
                ['X-Verstka-Signature' => $signature]
            );

            return $this->zipExtractor->extractFontsZip($zipPath, $tempDir);
        } catch (\Throwable $exception) {
            $this->cleanupTempDir($tempDir);
            throw $exception;
        }
    }

    private function authorizedUrl(string $contentUrl, string $materialId): string
    {
        return UrlBuilder::buildAuthorizedContentUrl($contentUrl, $this->config->apiKey, $materialId);
    }

    private function makeContentTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . '/verstka_content_' . bin2hex(random_bytes(8));
        if (!mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            throw new VerstkaCallbackDataError('Failed to create temp directory');
        }

        return $tempDir;
    }

    private function makeFontsTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . '/verstka_fonts_' . bin2hex(random_bytes(8));
        if (!mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            throw new VerstkaCallbackDataError('Failed to create temp directory');
        }

        return $tempDir;
    }

    private function cleanupTempDir(?string $tempDir): void
    {
        if ($tempDir === null || $tempDir === '') {
            return;
        }

        $this->removeDirectory($tempDir);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
