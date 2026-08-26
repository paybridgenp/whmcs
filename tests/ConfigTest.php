<?php

declare(strict_types=1);

namespace PayBridgeNP\WHMCS\Tests;

use PayBridgeNP\WHMCS\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('PAYBRIDGENP_API_BASE');
    }

    protected function tearDown(): void
    {
        putenv('PAYBRIDGENP_API_BASE');
    }

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

    public function test_sdk_config_uses_local_api_override_when_set(): void
    {
        putenv('PAYBRIDGENP_API_BASE=http://host.docker.internal:3000/');

        $this->assertSame([
            'api_key' => 'sk_test_xyz',
            'base_url' => 'http://host.docker.internal:3000',
        ], (new Config(['testMode' => 'on', 'testSecretKey' => 'sk_test_xyz']))->sdkConfig());
    }

    public function test_sdk_config_defaults_to_production_api(): void
    {
        $this->assertSame([
            'api_key' => 'sk_live_abc',
        ], (new Config(['secretKey' => 'sk_live_abc']))->sdkConfig());
    }

    public function test_payment_method_rejects_unknown_values(): void
    {
        $c = new Config(['paymentMethod' => 'paypal']);
        $this->assertSame('auto', $c->paymentMethod());
    }

    public function test_payment_method_accepts_fonepay(): void
    {
        // Catches Fonepay being offered in WHMCS config but silently falling back to auto.
        $this->assertSame('fonepay', (new Config(['paymentMethod' => 'fonepay']))->paymentMethod());
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
