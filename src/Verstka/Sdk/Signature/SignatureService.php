<?php

declare(strict_types=1);

namespace Verstka\Sdk\Signature;

use InvalidArgumentException;

final class SignatureService
{
    public static function signMaterial(string $materialId, string $url, string $secret): string
    {
        if ($secret === '') {
            throw new InvalidArgumentException('secret is required to compute a signature');
        }

        return hash_hmac('sha256', $materialId . ':' . $url, $secret);
    }

    public static function verifySignature(
        string $materialId,
        string $url,
        string $signature,
        string $secret,
    ): bool {
        if ($signature === '') {
            return false;
        }

        $expected = self::signMaterial($materialId, $url, $secret);

        return hash_equals($expected, $signature);
    }
}
