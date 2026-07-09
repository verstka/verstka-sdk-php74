<?php

declare(strict_types=1);

namespace Verstka\Sdk\Exception;

class VerstkaApiError extends VerstkaError
{
    public ?int $statusCode;

    public function __construct(?string $message = null, ?int $statusCode = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }
}
