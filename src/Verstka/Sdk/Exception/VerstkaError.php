<?php

declare(strict_types=1);

namespace Verstka\Sdk\Exception;

use Exception;

class VerstkaError extends Exception
{
    public const DEFAULT_MESSAGE = 'Verstka SDK error';

    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? static::DEFAULT_MESSAGE);
    }
}
