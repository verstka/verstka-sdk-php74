<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class FontsPreSaveContext
{
    public string $materialId;

    /** @var array<string, mixed> */
    public array $metadata;
    public string $contentUrl;

    /** @var array<string, mixed> */
    public array $fonts;

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $fonts
     */
    public function __construct(string $materialId, array $metadata, string $contentUrl, array $fonts)
    {
        $this->materialId = $materialId;
        $this->metadata = $metadata;
        $this->contentUrl = $contentUrl;
        $this->fonts = $fonts;
    }
}
