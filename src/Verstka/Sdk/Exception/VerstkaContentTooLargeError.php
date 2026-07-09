<?php

declare(strict_types=1);

namespace Verstka\Sdk\Exception;

final class VerstkaContentTooLargeError extends VerstkaApiError
{
    public const DEFAULT_MESSAGE = 'Verstka content file is too large';
}
