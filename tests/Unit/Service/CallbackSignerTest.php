<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Service;

use Freema\N8nBundle\Service\CallbackSigner;
use PHPUnit\Framework\TestCase;

final class CallbackSignerTest extends TestCase
{
    private const UUID = '0d2b4f6e-8a1c-4e3b-9f7d-5c6a8b9e0f12';
    private const NOW = 1_800_000_000;

    public function testSignedCallbackVerifies(): void
    {
        $signer = new CallbackSigner('secret', 3600);
        $signed = $signer->sign(self::UUID, self::NOW);

        $this->assertSame(self::NOW + 3600, $signed['expires']);
        $this->assertTrue($signer->verify(self::UUID, $signed['expires'], $signed['signature'], self::NOW));
        // Query strings carry the expiry as a string.
        $this->assertTrue($signer->verify(self::UUID, (string) $signed['expires'], $signed['signature'], self::NOW));
    }

    public function testSignatureIsBoundToUuidAndExpiry(): void
    {
        $signer = new CallbackSigner('secret', 3600);
        $signed = $signer->sign(self::UUID, self::NOW);

        $this->assertFalse($signer->verify('11111111-2222-4333-8444-555555555555', $signed['expires'], $signed['signature'], self::NOW));
        $this->assertFalse($signer->verify(self::UUID, $signed['expires'] + 86400, $signed['signature'], self::NOW));
    }

    public function testExpiredSignatureIsRejected(): void
    {
        $signer = new CallbackSigner('secret', 3600);
        $signed = $signer->sign(self::UUID, self::NOW);

        $this->assertFalse($signer->verify(self::UUID, $signed['expires'], $signed['signature'], self::NOW + 3601));
    }

    public function testOtherSecretDoesNotVerify(): void
    {
        $signed = (new CallbackSigner('secret', 3600))->sign(self::UUID, self::NOW);

        $this->assertFalse((new CallbackSigner('another', 3600))->verify(self::UUID, $signed['expires'], $signed['signature'], self::NOW));
    }

    public function testMalformedInputIsRejected(): void
    {
        $signer = new CallbackSigner('secret', 3600);
        $signed = $signer->sign(self::UUID, self::NOW);

        $this->assertFalse($signer->verify(self::UUID, null, $signed['signature'], self::NOW));
        $this->assertFalse($signer->verify(self::UUID, '-1', $signed['signature'], self::NOW));
        $this->assertFalse($signer->verify(self::UUID, $signed['expires'], null, self::NOW));
        $this->assertFalse($signer->verify(self::UUID, $signed['expires'], ['x'], self::NOW));
        $this->assertFalse($signer->verify('', $signed['expires'], $signed['signature'], self::NOW));
    }

    public function testEmptySecretSignsNothingAndVerifiesNothing(): void
    {
        $signer = new CallbackSigner('', 3600);

        $this->assertFalse($signer->isConfigured());
        $this->assertFalse($signer->verify(self::UUID, self::NOW + 60, hash_hmac('sha256', 'n8n-bundle-callback|'.self::UUID.'|'.(self::NOW + 60), ''), self::NOW));

        $this->expectException(\LogicException::class);
        $signer->sign(self::UUID);
    }
}
