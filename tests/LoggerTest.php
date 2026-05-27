<?php

declare(strict_types=1);

namespace PayBridgeNP\WHMCS\Tests;

use PayBridgeNP\WHMCS\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    public function test_scrub_redacts_known_secret_keys(): void
    {
        $cleaned = Logger::scrub([
            'api_key'           => 'sk_live_xxx',
            'apikey'            => 'sk_live_yyy',
            'secret'            => 'shh',
            'secretKey'         => 'sk_live_zzz',
            'testSecretKey'     => 'sk_test_xxx',
            'webhookSecret'     => 'whsec_xxx',
            'signing_secret'    => 'whsec_yyy',
            'authorization'     => 'Bearer abc',
            'safe_field'        => 'keep me',
            'nested' => [
                'api_key' => 'sk_live_nope',
                'kept'    => 'yes',
            ],
        ]);

        $this->assertSame('[REDACTED]', $cleaned['api_key']);
        $this->assertSame('[REDACTED]', $cleaned['apikey']);
        $this->assertSame('[REDACTED]', $cleaned['secret']);
        $this->assertSame('[REDACTED]', $cleaned['secretKey']);
        $this->assertSame('[REDACTED]', $cleaned['testSecretKey']);
        $this->assertSame('[REDACTED]', $cleaned['webhookSecret']);
        $this->assertSame('[REDACTED]', $cleaned['signing_secret']);
        $this->assertSame('[REDACTED]', $cleaned['authorization']);
        $this->assertSame('keep me',    $cleaned['safe_field']);
        $this->assertSame('[REDACTED]', $cleaned['nested']['api_key']);
        $this->assertSame('yes',        $cleaned['nested']['kept']);
    }

    public function test_scrub_redacts_signature_header_variants(): void
    {
        $cleaned = Logger::scrub([
            'X-PayBridgeNP-Signature' => 't=123,v1=abc',
            'x-paybridge-signature' => 't=456,v1=def',
        ]);
        $this->assertSame('[REDACTED]', $cleaned['X-PayBridgeNP-Signature']);
        $this->assertSame('[REDACTED]', $cleaned['x-paybridge-signature']);
    }
}
