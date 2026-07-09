<?php

declare(strict_types=1);

namespace Verstka\Sdk\Exception;

final class VerstkaSignatureError extends VerstkaError
{
    public const DEFAULT_MESSAGE = 'Invalid callback signature';
}
