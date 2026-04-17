<?php

declare(strict_types=1);

namespace PayBridgeNP\WHMCS\Tests;

use PayBridgeNP\PayBridge;
use PayBridgeNP\Exceptions\SignatureVerificationException;
use PHPUnit\Framework\TestCase;

/**
 * The WHMCS callback relies on the SDK's WebhooksResource::constructEvent()
 * for HMAC verification. These tests prove that the same code path the
 * callback exercises rejects tampering, replays, and missing headers.
 *
 * If these pass, the callback can trust the event it receives.
 */
final class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_testsecret';

    private function sign(string $payload, int $timestamp): string
    {
        $v1 = hash_hmac('sha256', $timestamp . '.' . $payload, self::SECRET);
        return 't=' . $timestamp . ',v1=' . $v1;
    }

    public function test_accepts_valid_signature(): void
    {
        $payload = json_encode([
            'id'   => 'evt_1',
            'type' => 'payment.succeeded',
            'data' => ['id' => 'pay_1', 'amount' => 5000, 'metadata' => ['invoiceid' => '42']],
        ]);
        $sig = $this->sign($payload, time());

        $event = PayBridge::webhooks()->constructEvent($payload, $sig, self::SECRET);
        $this->assertSame('payment.succeeded',  $event['type']);
        $this->assertSame('42',                  $event['data']['metadata']['invoiceid']);
    }

    public function test_rejects_missing_header(): void
    {
        $this->expectException(SignatureVerificationException::class);
        PayBridge::webhooks()->constructEvent('{"a":1}', null, self::SECRET);
    }

    public function test_rejects_tampered_payload(): void
    {
        $payload   = '{"a":1}';
        $sig       = $this->sign($payload, time());
        $tampered  = '{"a":2}';

        $this->expectException(SignatureVerificationException::class);
        PayBridge::webhooks()->constructEvent($tampered, $sig, self::SECRET);
    }

    public function test_rejects_wrong_secret(): void
    {
        $payload = '{"a":1}';
        $sig     = $this->sign($payload, time());

        $this->expectException(SignatureVerificationException::class);
        PayBridge::webhooks()->constructEvent($payload, $sig, 'whsec_wrong');
    }

    public function test_rejects_replay_outside_five_minute_window(): void
    {
        $payload   = '{"a":1}';
        $oldSig    = $this->sign($payload, time() - 600); // 10 min ago

        $this->expectException(SignatureVerificationException::class);
        PayBridge::webhooks()->constructEvent($payload, $oldSig, self::SECRET);
    }

    public function test_rejects_malformed_header(): void
    {
        $this->expectException(SignatureVerificationException::class);
        PayBridge::webhooks()->constructEvent('{"a":1}', 'not a real header', self::SECRET);
    }
}
