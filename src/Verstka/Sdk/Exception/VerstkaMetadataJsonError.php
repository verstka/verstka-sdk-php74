<?php

declare(strict_types=1);

namespace Verstka\Sdk\Exception;

final class VerstkaMetadataJsonError extends VerstkaError
{
    public const DEFAULT_MESSAGE = 'Invalid metadata_json format';
}
