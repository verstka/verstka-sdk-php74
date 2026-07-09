<?php

declare(strict_types=1);

namespace Verstka\Sdk\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Verstka\Sdk\Callback\CallbackProcessor;
use Verstka\Sdk\Callback\FontsCallbackResult;
use Verstka\Sdk\Callback\MaterialCallbackResult;
use Verstka\Sdk\Config\VerstkaConfig;
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;
use Verstka\Sdk\Finalize\ContentPreSaveContext;
use Verstka\Sdk\Finalize\FontsFinalizeContext;
use Verstka\Sdk\Finalize\FontsFinalizeResult;
use Verstka\Sdk\Finalize\FontsPreSaveContext;
use Verstka\Sdk\Finalize\PreSaveDecision;
use Verstka\Sdk\Session\SessionBuilder;
use Verstka\Sdk\Storage\StorageAdapter;

final class VerstkaClient
{
    private CallbackProcessor $processor;
    private ClientInterface $httpClient;
    private VerstkaConfig $config;

    public function __construct(VerstkaConfig $config, ?ClientInterface $httpClient = null)
    {
        if ($httpClient === null) {
            $httpClient = new Client(['timeout' => $config->requestTimeout]);
        }
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->processor = new CallbackProcessor($config, $httpClient);
    }

    /**
     * @param array<string, mixed>|string|null $vmsJson
     * @param array<string, mixed>|string|null $metadata
     */
    public function getEditorUrl(
        string $materialId,
        $vmsJson = null,
        $metadata = null
    ): string {
        [$payload, $signature] = SessionBuilder::buildSessionPayload(
            $this->config,
            $materialId,
            $vmsJson,
            $metadata
        );

        $response = $this->httpClient->request('POST', $this->config->getSessionOpenUrl(), [
            'json' => $payload,
            'headers' => ['X-Verstka-Signature' => $signature],
            'timeout' => $this->config->requestTimeout,
            'http_errors' => false,
        ]);

        $data = SessionBuilder::parseEditorResponse(
            $response->getStatusCode(),
            (string) $response->getBody()
        );

        return $data['url'];
    }

    /**
     * @param array<string, mixed> $callbackData
     * @param callable(ContentFinalizeContext): ContentFinalizeResult $onFinalize
     * @param callable(ContentPreSaveContext): PreSaveDecision|null $onPreSave
     */
    public function processMaterialCallback(
        array $callbackData,
        string $signature,
        StorageAdapter $storage,
        callable $onFinalize,
        ?callable $onPreSave = null
    ): MaterialCallbackResult {
        return $this->processor->processMaterialCallback(
            $callbackData,
            $signature,
            $storage,
            $onFinalize,
            $onPreSave
        );
    }

    /**
     * @param array<string, mixed> $callbackData
     * @param callable(FontsFinalizeContext): FontsFinalizeResult|null $onFinalize
     * @param callable(FontsPreSaveContext): PreSaveDecision|null $onPreSave
     */
    public function processFontsCallback(
        array $callbackData,
        string $signature,
        StorageAdapter $storage,
        ?callable $onFinalize = null,
        ?callable $onPreSave = null
    ): FontsCallbackResult {
        return $this->processor->processFontsCallback(
            $callbackData,
            $signature,
            $storage,
            $onFinalize,
            $onPreSave
        );
    }
}
