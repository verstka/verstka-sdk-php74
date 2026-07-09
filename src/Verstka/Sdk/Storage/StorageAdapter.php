<?php

declare(strict_types=1);

namespace Verstka\Sdk\Storage;

interface StorageAdapter
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function saveMedia(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata
    ): string;

    /**
     * @param array<string, mixed> $metadata
     */
    public function saveFontFile(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata
    ): string;

    /**
     * @param array<string, mixed> $metadata
     */
    public function saveFontsManifest(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata
    ): string;
}
