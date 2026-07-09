<?php

declare(strict_types=1);

namespace Verstka\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Verstka\Sdk\Client\VerstkaClient;
use Verstka\Sdk\Exception\VerstkaApiError;
use Verstka\Sdk\Exception\VerstkaMetadataJsonError;
use Verstka\Sdk\Exception\VerstkaVmsJsonError;
use Verstka\Sdk\Signature\SignatureService;
use Verstka\Sdk\Tests\Support\TestConfig;
use Verstka\Sdk\VerstkaSdk;

final class ClientTest extends TestCase
{
    public function testGetEditorUrl(): void
    {
        $config = TestConfig::make();
        $mock = new MockHandler([
            new Response(200, [], json_encode(['url' => 'https://editor.test/session/xyz'], JSON_THROW_ON_ERROR)),
        ]);
        $client = new VerstkaClient($config, new Client(['handler' => HandlerStack::create($mock)]));

        $url = $client->getEditorUrl('M1', ['foo' => 'bar'], ['user_id' => 7]);

        self::assertSame('https://editor.test/session/xyz', $url);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(TestConfig::API_KEY, $body['api_key']);
        self::assertSame(TestConfig::CALLBACK_URL, $body['callback_url']);
        self::assertSame('M1', $body['material_id']);
        self::assertSame(7, $body['metadata']['user_id']);
        self::assertSame('php_' . VerstkaSdk::VERSION, $body['metadata']['version']);
        self::assertSame(['foo' => 'bar'], $body['vms_json']);

        $expectedSig = SignatureService::signMaterial('M1', TestConfig::CALLBACK_URL, TestConfig::API_SECRET);
        self::assertSame($expectedSig, $request->getHeaderLine('X-Verstka-Signature'));
    }

    public function testGetEditorUrlStringVmsJson(): void
    {
        $config = TestConfig::make();
        $mock = new MockHandler([new Response(200, [], json_encode(['url' => 'ok'], JSON_THROW_ON_ERROR))]);
        $client = new VerstkaClient($config, new Client(['handler' => HandlerStack::create($mock)]));

        $client->getEditorUrl('M1', json_encode(['x' => 1], JSON_THROW_ON_ERROR));
        self::assertTrue(true);
    }

    public function testGetEditorUrlInvalidJson(): void
    {
        $config = TestConfig::make();
        $client = new VerstkaClient($config, new Client(['handler' => HandlerStack::create(new MockHandler())]));

        $this->expectException(VerstkaVmsJsonError::class);
        $client->getEditorUrl('M1', '{not json');
    }

    public function testGetEditorUrlInvalidMetadataJson(): void
    {
        $config = TestConfig::make();
        $client = new VerstkaClient($config, new Client(['handler' => HandlerStack::create(new MockHandler())]));

        $this->expectException(VerstkaMetadataJsonError::class);
        $client->getEditorUrl('M1', null, '{nope');
    }

    public function testGetEditorUrlApiError(): void
    {
        $config = TestConfig::make();
        $mock = new MockHandler([new Response(500, [], 'boom')]);
        $client = new VerstkaClient($config, new Client(['handler' => HandlerStack::create($mock)]));

        try {
            $client->getEditorUrl('M1');
            self::fail('Expected VerstkaApiError');
        } catch (VerstkaApiError $exception) {
            self::assertSame(500, $exception->statusCode);
        }
    }

    public function testWebhookAuthMetadata(): void
    {
        $config = new \Verstka\Sdk\Config\VerstkaConfig(
            TestConfig::API_KEY,
            TestConfig::API_SECRET,
            TestConfig::CALLBACK_URL,
            'https://verstka.test/api/v2'
        );
        $mock = new MockHandler([new Response(200, [], json_encode(['url' => 'ok'], JSON_THROW_ON_ERROR))]);
        $client = new VerstkaClient($config, new Client(['handler' => HandlerStack::create($mock)]));

        $client->getEditorUrl('M1', null, [
            'webhook_auth_user' => 'u',
            'webhook_auth_password' => 'p',
        ]);
        $body = json_decode((string) $mock->getLastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('u', $body['metadata']['webhook_auth_user']);
        self::assertSame('p', $body['metadata']['webhook_auth_password']);
    }
}
