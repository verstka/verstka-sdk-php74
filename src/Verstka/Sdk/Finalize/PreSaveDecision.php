<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class PreSaveDecision
{
    public bool $allow;
    public ?string $reason;

    public function __construct(bool $allow, ?string $reason = null)
    {
        $this->allow = $allow;
        $this->reason = $reason;
    }
}
