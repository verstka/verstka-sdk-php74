<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class FontsFinalizeResult
{
    public bool $success;

    /** @var array<string, mixed>|null */
    public ?array $fonts;

    /**
     * @param array<string, mixed>|null $fonts
     */
    public function __construct(bool $success, ?array $fonts = null)
    {
        $this->success = $success;
        $this->fonts = $fonts;
    }
}
