<?php

declare(strict_types=1);

namespace PayBridgeNP\WHMCS\Tests;

use PayBridgeNP\WHMCS\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_live_key_used_when_test_mode_off(): void
    {
        $c = new Config([
            'testMode'      => '',
            'secretKey'     => 'sk_live_abc',
            'testSecretKey' => 'sk_test_xyz',
        ]);
        $this->assertFalse($c->isTestMode());
        $this->assertSame('sk_live_abc', $c->apiKey());
    }

    public function test_test_key_used_when_test_mode_on(): void
    {
        $c = new Config([
            'testMode'      => 'on',
            'secretKey'     => 'sk_live_abc',
            'testSecretKey' => 'sk_test_xyz',
        ]);
        $this->assertTrue($c->isTestMode());
        $this->assertSame('sk_test_xyz', $c->apiKey());
    }

    public function test_falls_back_to_live_key_when_test_key_missing(): void
    {
        // Merchant enabled test mode but forgot to paste the test key.
        // Better to hit the live API and fail clearly than to blank out.
        $c = new Config([
            'testMode'      => 'on',
            'secretKey'     => 'sk_live_abc',
            'testSecretKey' => '',
        ]);
        $this->assertSame('sk_live_abc', $c->apiKey());
    }

    public function test_payment_method_rejects_unknown_values(): void
    {
        $c = new Config(['paymentMethod' => 'paypal']);
        $this->assertSame('auto', $c->paymentMethod());
    }

    public function test_system_url_always_has_trailing_slash(): void
    {
        $with    = new Config(['systemurl' => 'https://host.example/whmcs/']);
        $without = new Config(['systemurl' => 'https://host.example/whmcs']);
        $empty   = new Config(['systemurl' => '']);

        $this->assertSame('https://host.example/whmcs/', $with->systemUrl());
        $this->assertSame('https://host.example/whmcs/', $without->systemUrl());
        $this->assertSame('',                           $empty->systemUrl());
    }

    public function test_debug_logging_is_off_by_default(): void
    {
        $this->assertFalse((new Config([]))->debugLogging());
        $this->assertFalse((new Config(['debugLogging' => '']))->debugLogging());
        $this->assertTrue((new Config(['debugLogging' => 'on']))->debugLogging());
    }
}
