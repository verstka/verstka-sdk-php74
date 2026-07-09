<?php

declare(strict_types=1);

namespace Verstka\Sdk\Storage;

use RuntimeException;

final class LocalStorageAdapter implements StorageAdapter
{
    private string $root;
    private string $baseUrl;
    private string $materialsSubdir;
    private string $fontsSubdir;

    public function __construct(
        string $root,
        string $baseUrl,
        string $materialsSubdir = 'materials',
        string $fontsSubdir = 'fonts'
    ) {
        $this->root = rtrim(realpath($root) ?: $root, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->materialsSubdir = trim($materialsSubdir, '/');
        $this->fontsSubdir = trim($fontsSubdir, '/');

        if (!is_dir($this->root) && !mkdir($this->root, 0775, true) && !is_dir($this->root)) {
            throw new RuntimeException('Failed to create storage root: ' . $this->root);
        }
    }

    public function saveMedia(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata
    ): string {
        $targetDir = $this->root . '/' . $this->materialsSubdir . '/' . $materialId;

        return $this->copyFile($targetDir, $filename, $tempPath, $this->materialsSubdir . '/' . $materialId);
    }

    public function saveFontFile(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata
    ): string {
        $targetDir = $this->root . '/' . $this->fontsSubdir;

        return $this->copyFile($targetDir, $filename, $tempPath, $this->fontsSubdir);
    }

    public function saveFontsManifest(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata
    ): string {
        return $this->saveFontFile($filename, $tempPath, $materialId, $metadata);
    }

    private function copyFile(string $targetDir, string $filename, string $tempPath, string $urlPrefix): string
    {
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Failed to create directory: ' . $targetDir);
        }

        $targetPath = $targetDir . '/' . $filename;
        if (!copy($tempPath, $targetPath)) {
            throw new RuntimeException('Failed to copy file to: ' . $targetPath);
        }

        return $this->baseUrl . '/' . $urlPrefix . '/' . $filename;
    }
}
