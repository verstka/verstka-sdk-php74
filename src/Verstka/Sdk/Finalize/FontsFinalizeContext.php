<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class FontsFinalizeContext
{
    public string $materialId;

    /** @var array<string, mixed> */
    public array $metadata;

    /** @var array<string, mixed> */
    public array $fonts;
    public ?string $cssUrl;
    public ?string $jsonUrl;

    /** @var array<string, string> */
    public array $savedFontUrls;

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $fonts
     * @param array<string, string> $savedFontUrls
     */
    public function __construct(
        string $materialId,
        array $metadata,
        array $fonts,
        ?string $cssUrl,
        ?string $jsonUrl,
        array $savedFontUrls = []
    ) {
        $this->materialId = $materialId;
        $this->metadata = $metadata;
        $this->fonts = $fonts;
        $this->cssUrl = $cssUrl;
        $this->jsonUrl = $jsonUrl;
        $this->savedFontUrls = $savedFontUrls;
    }
}
