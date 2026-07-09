<?php

declare(strict_types=1);

namespace Verstka\Sdk\Config;

final class VerstkaConfig
{
    public const DEFAULT_API_URL = 'https://api.r2.verstka.org/integration';
    public const DEFAULT_MAX_CONTENT_SIZE = 200 * 1024 * 1024;

    public string $apiKey;
    public string $apiSecret;
    public string $callbackUrl;
    public string $apiUrl;
    public int $maxContentSize;
    public float $requestTimeout;
    public float $downloadTimeout;
    public bool $debug;

    public function __construct(
        string $apiKey,
        string $apiSecret,
        string $callbackUrl,
        string $apiUrl = self::DEFAULT_API_URL,
        int $maxContentSize = self::DEFAULT_MAX_CONTENT_SIZE,
        float $requestTimeout = 60.0,
        float $downloadTimeout = 120.0,
        bool $debug = false
    ) {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->callbackUrl = $callbackUrl;
        $this->apiUrl = $apiUrl;
        $this->maxContentSize = $maxContentSize;
        $this->requestTimeout = $requestTimeout;
        $this->downloadTimeout = $downloadTimeout;
        $this->debug = $debug;
    }

    public function getSessionOpenUrl(): string
    {
        return rtrim($this->apiUrl, '/') . '/session/open';
    }
}
