<?php

declare(strict_types=1);

namespace Verstka\Sdk\Url;

use InvalidArgumentException;

final class UrlBuilder
{
    public static function buildAuthorizedContentUrl(
        string $contentUrl,
        string $apiKey,
        string $materialId,
    ): string {
        if ($contentUrl === '') {
            throw new InvalidArgumentException('content_url must be a non-empty string');
        }

        $parts = parse_url($contentUrl);
        if ($parts === false) {
            throw new InvalidArgumentException('content_url must be a valid URL');
        }

        $query = [];
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
        }

        if ($apiKey !== '') {
            $query['api_key'] = $apiKey;
        }
        if ($materialId !== '') {
            $query['material_id'] = $materialId;
        }

        $parts['query'] = http_build_query($query);

        return self::buildUrl($parts);
    }

    /**
     * @param array<string, mixed> $parts
     */
    private static function buildUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $pass = ($user !== '' || $pass !== '') ? $pass . '@' : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }
}
