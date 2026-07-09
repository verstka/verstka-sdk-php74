<?php

declare(strict_types=1);

namespace Verstka\Sdk\Content;

final class ExtractedContent
{
    /** @var array<string, string> */
    public array $media;
    public ?string $vmsJson;
    public ?string $vmsHtml;
    public string $tempDir;

    /**
     * @param array<string, string> $media
     */
    public function __construct(array $media, ?string $vmsJson, ?string $vmsHtml, string $tempDir)
    {
        $this->media = $media;
        $this->vmsJson = $vmsJson;
        $this->vmsHtml = $vmsHtml;
        $this->tempDir = $tempDir;
    }
}
