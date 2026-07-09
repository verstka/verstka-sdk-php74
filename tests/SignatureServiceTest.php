<?php

declare(strict_types=1);

namespace Verstka\Sdk\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Verstka\Sdk\Signature\SignatureService;

final class SignatureServiceTest extends TestCase
{
    public function testSignMatchesReferenceHmac(): void
    {
        $signature = SignatureService::signMaterial('42', 'https://example.com/content', 'my-secret');
        $expected = hash_hmac('sha256', '42:https://example.com/content', 'my-secret');

        self::assertSame($expected, $signature);
    }

    public function testVerifySignatureOk(): void
    {
        $signature = SignatureService::signMaterial('42', 'https://x.test', 'my-secret');

        self::assertTrue(SignatureService::verifySignature('42', 'https://x.test', $signature, 'my-secret'));
    }

    public function testVerifySignatureRejectWrongValue(): void
    {
        self::assertFalse(SignatureService::verifySignature('42', 'https://x.test', 'not-a-sig', 'secret'));
    }

    public function testVerifySignatureRejectsEmpty(): void
    {
        self::assertFalse(SignatureService::verifySignature('42', 'https://x.test', '', 'secret'));
    }

    public function testSignRequiresSecret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SignatureService::signMaterial('42', 'https://x.test', '');
    }
}
