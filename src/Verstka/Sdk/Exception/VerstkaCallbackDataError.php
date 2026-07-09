<?php

declare(strict_types=1);

namespace Verstka\Sdk\Exception;

final class VerstkaCallbackDataError extends VerstkaError
{
    public const DEFAULT_MESSAGE = 'Malformed callback data';
}
