<?php

declare(strict_types=1);

namespace Verstka\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Verstka\Sdk\Exception\VerstkaSignatureError;
use Verstka\Sdk\Integration\CallbackDispatcher;

final class CallbackDispatcherTest extends TestCase
{
    public function testIsFontsCallbackPayload(): void
    {
        self::assertTrue(CallbackDispatcher::isFontsCallbackPayload(['event' => 'site_fonts_updated']));
        self::assertFalse(CallbackDispatcher::isFontsCallbackPayload(['event' => 'article_saved']));
    }

    public function testMapException(): void
    {
        $mapped = CallbackDispatcher::mapException(new VerstkaSignatureError());
        self::assertSame(400, $mapped->status);
        self::assertSame('invalid_signature', $mapped->code);
    }
}
