<?php

declare(strict_types=1);

namespace Verstka\Sdk\Content;

final class ExtractedFonts
{
    /** @var array<string, string> */
    public array $fontFiles;
    public ?string $vmsFontsJsonPath;
    public ?string $vmsFontsCssPath;
    public string $tempDir;

    /**
     * @param array<string, string> $fontFiles
     */
    public function __construct(
        array $fontFiles,
        ?string $vmsFontsJsonPath,
        ?string $vmsFontsCssPath,
        string $tempDir
    ) {
        $this->fontFiles = $fontFiles;
        $this->vmsFontsJsonPath = $vmsFontsJsonPath;
        $this->vmsFontsCssPath = $vmsFontsCssPath;
        $this->tempDir = $tempDir;
    }
}
