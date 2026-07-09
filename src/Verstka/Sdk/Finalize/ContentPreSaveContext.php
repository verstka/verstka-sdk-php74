<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class ContentPreSaveContext
{
    public string $materialId;

    /** @var array<string, mixed> */
    public array $metadata;
    public string $contentUrl;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(string $materialId, array $metadata, string $contentUrl)
    {
        $this->materialId = $materialId;
        $this->metadata = $metadata;
        $this->contentUrl = $contentUrl;
    }
}
