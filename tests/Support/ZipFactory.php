<?php

declare(strict_types=1);

namespace Verstka\Sdk\Tests\Support;

use ZipArchive;

final class ZipFactory
{
    /**
     * @param array<string, string> $media
     * @param array<string, mixed>|string|null $vmsJson
     * @param array<string, string> $extraMembers
     */
    public static function buildContentZip(
        string $targetPath,
        array $media = [],
        $vmsJson = null,
        ?string $vmsHtml = null,
        array $extraMembers = []
    ): string {
        $zip = new ZipArchive();
        $zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($media as $name => $payload) {
            $zip->addFromString('vms_media/' . $name, $payload);
        }

        if ($vmsJson !== null) {
            $data = is_string($vmsJson) ? $vmsJson : json_encode($vmsJson, JSON_THROW_ON_ERROR);
            $zip->addFromString('vms_json.json', $data);
        }

        if ($vmsHtml !== null) {
            $zip->addFromString('vms_html.html', $vmsHtml);
        }

        foreach ($extraMembers as $name => $payload) {
            $zip->addFromString($name, $payload);
        }

        $zip->close();

        return $targetPath;
    }

    /**
     * @param array<string, string> $fonts
     * @param array<string, mixed>|null $vmsFontsJson
     */
    public static function buildFontsZip(
        string $targetPath,
        array $fonts = [],
        ?array $vmsFontsJson = null,
        ?string $vmsFontsCss = null
    ): string {
        $zip = new ZipArchive();
        $zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($fonts as $name => $payload) {
            $zip->addFromString('vms_fonts/' . $name, $payload);
        }

        if ($vmsFontsJson !== null) {
            $zip->addFromString('vms_fonts.json', json_encode($vmsFontsJson, JSON_THROW_ON_ERROR));
        }

        if ($vmsFontsCss !== null) {
            $zip->addFromString('vms_fonts.css', $vmsFontsCss);
        }

        $zip->close();

        return $targetPath;
    }
}
