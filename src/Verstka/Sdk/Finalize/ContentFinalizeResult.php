<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class ContentFinalizeResult
{
    public bool $success;

    /** @var array<string, mixed>|null */
    public ?array $vmsJson;

    /**
     * @param array<string, mixed>|null $vmsJson
     */
    public function __construct(bool $success, ?array $vmsJson = null)
    {
        $this->success = $success;
        $this->vmsJson = $vmsJson;
    }
}
