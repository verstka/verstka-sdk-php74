<?php

declare(strict_types=1);

namespace Verstka\Sdk\Integration;

use Throwable;
use Verstka\Sdk\Client\VerstkaClient;
use Verstka\Sdk\Exception\VerstkaApiError;
use Verstka\Sdk\Exception\VerstkaCallbackDataError;
use Verstka\Sdk\Exception\VerstkaError;
use Verstka\Sdk\Exception\VerstkaMetadataJsonError;
use Verstka\Sdk\Exception\VerstkaSignatureError;
use Verstka\Sdk\Exception\VerstkaVmsJsonError;
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;
use Verstka\Sdk\Finalize\ContentPreSaveContext;
use Verstka\Sdk\Finalize\FontsFinalizeContext;
use Verstka\Sdk\Finalize\FontsFinalizeResult;
use Verstka\Sdk\Finalize\FontsPreSaveContext;
use Verstka\Sdk\Finalize\PreSaveDecision;
use Verstka\Sdk\Storage\StorageAdapter;

final class CallbackDispatcher
{
    public const FONTS_CALLBACK_EVENT = 'site_fonts_updated';

    /**
     * @param array<string, mixed> $payload
     */
    public static function isFontsCallbackPayload(array $payload): bool
    {
        return ($payload['event'] ?? null) === self::FONTS_CALLBACK_EVENT;
    }

    public static function mapException(Throwable $exception): ErrorResponse
    {
        if ($exception instanceof VerstkaSignatureError) {
            return new ErrorResponse(400, 'invalid_signature', $exception->getMessage());
        }
        if ($exception instanceof VerstkaCallbackDataError) {
            return new ErrorResponse(400, 'invalid_callback_data', $exception->getMessage());
        }
        if ($exception instanceof VerstkaVmsJsonError) {
            return new ErrorResponse(400, 'invalid_vms_json', $exception->getMessage());
        }
        if ($exception instanceof VerstkaMetadataJsonError) {
            return new ErrorResponse(400, 'invalid_metadata_json', $exception->getMessage());
        }
        if ($exception instanceof VerstkaApiError) {
            $status = $exception->statusCode;
            if ($status === null || $status < 400 || $status >= 600) {
                $status = 502;
            }

            return new ErrorResponse($status, 'verstka_api_error', $exception->getMessage());
        }
        if ($exception instanceof VerstkaError) {
            return new ErrorResponse(500, 'verstka_error', $exception->getMessage());
        }

        return new ErrorResponse(500, 'internal_error', $exception->getMessage() ?: 'Internal server error');
    }

    /**
     * @param array<string, mixed> $payload
     * @param callable(ContentFinalizeContext): ContentFinalizeResult $onContentFinalize
     * @param callable(FontsFinalizeContext): FontsFinalizeResult|null $onFontsFinalize
     * @param callable(ContentPreSaveContext): PreSaveDecision|null $onContentPreSave
     * @param callable(FontsPreSaveContext): PreSaveDecision|null $onFontsPreSave
     *
     * @return array<string, mixed>
     */
    public static function dispatch(
        VerstkaClient $client,
        array $payload,
        string $signature,
        StorageAdapter $storage,
        callable $onContentFinalize,
        ?callable $onFontsFinalize = null,
        ?callable $onContentPreSave = null,
        ?callable $onFontsPreSave = null
    ): array {
        if (self::isFontsCallbackPayload($payload)) {
            return $client->processFontsCallback(
                $payload,
                $signature,
                $storage,
                $onFontsFinalize,
                $onFontsPreSave
            )->toResponse();
        }

        return $client->processMaterialCallback(
            $payload,
            $signature,
            $storage,
            $onContentFinalize,
            $onContentPreSave
        )->toResponse();
    }
}
