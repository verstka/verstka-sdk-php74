<?php

declare(strict_types=1);

namespace Verstka\Sdk\Exception;

final class VerstkaVmsJsonError extends VerstkaError
{
    public const DEFAULT_MESSAGE = 'Invalid vms_json format';
}
