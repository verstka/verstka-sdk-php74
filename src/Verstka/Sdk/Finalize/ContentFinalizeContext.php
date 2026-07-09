<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class ContentFinalizeContext
{
    public string $materialId;

    /** @var array<string, mixed> */
    public array $metadata;

    /** @var array<string, mixed>|null */
    public ?array $vmsJson;
    public ?string $vmsHtml;

    /** @var array<string, string> */
    public array $savedMediaUrls;

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed>|null $vmsJson
     * @param array<string, string> $savedMediaUrls
     */
    public function __construct(
        string $materialId,
        array $metadata,
        ?array $vmsJson,
        ?string $vmsHtml,
        array $savedMediaUrls = []
    ) {
        $this->materialId = $materialId;
        $this->metadata = $metadata;
        $this->vmsJson = $vmsJson;
        $this->vmsHtml = $vmsHtml;
        $this->savedMediaUrls = $savedMediaUrls;
    }
}
