<?php

declare(strict_types=1);

namespace Verstka\Sdk\Session;

use JsonException;
use Verstka\Sdk\Config\VerstkaConfig;
use Verstka\Sdk\Exception\VerstkaApiError;
use Verstka\Sdk\Exception\VerstkaMetadataJsonError;
use Verstka\Sdk\Exception\VerstkaVmsJsonError;
use Verstka\Sdk\Signature\SignatureService;
use Verstka\Sdk\VerstkaSdk;

final class SessionBuilder
{
    /**
     * @param array<string, mixed>|string|null $vmsJson
     * @param array<string, mixed>|string|null $metadata
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    public static function buildSessionPayload(
        VerstkaConfig $config,
        string $materialId,
        $vmsJson = null,
        $metadata = null
    ): array {
        if ($materialId === '') {
            throw new VerstkaApiError('material_id is required');
        }

        $existingMetadata = self::coerceJson($metadata, VerstkaMetadataJsonError::class) ?? [];
        $mergedMetadata = array_merge(['version' => 'php_' . VerstkaSdk::VERSION], $existingMetadata);

        $payload = [
            'api_key' => $config->apiKey,
            'callback_url' => $config->callbackUrl,
            'material_id' => $materialId,
            'metadata' => $mergedMetadata,
        ];

        $vmsJsonDict = self::coerceJson($vmsJson, VerstkaVmsJsonError::class);
        if ($vmsJsonDict !== null) {
            $payload['vms_json'] = $vmsJsonDict;
        }

        $signature = SignatureService::signMaterial($materialId, $config->callbackUrl, $config->apiSecret);

        return [$payload, $signature];
    }

    /**
     * @return array<string, mixed>
     */
    public static function parseEditorResponse(int $statusCode, string $body): array
    {
        if ($statusCode !== 200) {
            throw new VerstkaApiError(
                'Invalid verstka response: ' . $statusCode . ' ' . $body,
                $statusCode
            );
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new VerstkaApiError('Non-JSON response from Verstka: ' . $exception->getMessage());
        }

        if (!is_array($data) || !array_key_exists('url', $data)) {
            throw new VerstkaApiError(json_encode($data, JSON_THROW_ON_ERROR));
        }

        $url = $data['url'];
        if (!is_string($url) || $url === '') {
            throw new VerstkaApiError('Unexpected url value: ' . var_export($url, true));
        }

        return $data;
    }

    /**
     * @param array<string, mixed>|string|null $value
     *
     * @return array<string, mixed>|null
     */
    private static function coerceJson($value, string $errorClass): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            try {
                $parsed = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new $errorClass($errorClass . ': ' . $exception->getMessage());
            }
        } else {
            $parsed = $value;
        }

        if (!is_array($parsed)) {
            throw new $errorClass('Expected JSON object, got ' . gettype($parsed));
        }

        return $parsed;
    }
}
