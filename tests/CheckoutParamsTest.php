<?php

declare(strict_types=1);

namespace PayBridgeNP\WHMCS\Tests;

use PayBridgeNP\WHMCS\CheckoutParams;
use PayBridgeNP\WHMCS\Config;
use PHPUnit\Framework\TestCase;

final class CheckoutParamsTest extends TestCase
{
    private function makeConfig(array $overrides = []): Config
    {
        return new Config(array_merge([
            'systemurl'     => 'https://host.example/whmcs/',
            'paymentMethod' => 'auto',
            'testMode'      => '',
        ], $overrides));
    }

    private function makeParams(array $overrides = []): array
    {
        return array_merge([
            'systemurl'     => 'https://host.example/whmcs/',
            'invoiceid'     => 42,
            'amount'        => '100.00',
            'currency'      => 'NPR',
            'whmcsVersion'  => '8.13.0',
            'clientdetails' => [
                'userid' => 7,
                'firstname' => 'Aarav',
                'lastname' => 'Sharma',
                'email' => 'a@b.com',
                'phonenumber' => '9800000000',
                'address1' => 'Balaju',
                'address2' => '',
                'city' => 'Kathmandu',
                'state' => 'Bagmati',
                'postcode' => '44600',
                'countrycode' => 'NP',
            ],
        ], $overrides);
    }

    public function test_builds_the_expected_checkout_body(): void
    {
        $body = CheckoutParams::build($this->makeParams(), $this->makeConfig());

        $this->assertSame(10000, $body['amount']);
        $this->assertSame('NPR', $body['currency']);
        $this->assertSame(
            'https://host.example/whmcs/modules/gateways/callback/paybridgenp.php?invoiceid=42',
            $body['return_url']
        );
        $this->assertSame(
            'https://host.example/whmcs/modules/gateways/callback/paybridgenp.php?invoiceid=42&cancelled=1',
            $body['cancel_url']
        );
        $this->assertArrayNotHasKey('provider', $body, 'auto method must not set provider');
        $this->assertSame('42',        $body['metadata']['invoiceid']);
        $this->assertSame('whmcs',     $body['metadata']['source']);
        $this->assertSame('8.13.0',    $body['metadata']['whmcs_version']);
        $this->assertSame('7',         $body['metadata']['client_id']);
        $this->assertSame('a@b.com',   $body['metadata']['client_email']);
        $this->assertSame([
            'name' => 'Aarav Sharma',
            'email' => 'a@b.com',
            'phone' => '9800000000',
            'address' => [
                'line1' => 'Balaju',
                'city' => 'Kathmandu',
                'state' => 'Bagmati',
                'postalCode' => '44600',
                'country' => 'NP',
            ],
        ], $body['customer']);
    }

    public function test_omits_empty_customer_details(): void
    {
        $body = CheckoutParams::build(
            $this->makeParams(['clientdetails' => ['userid' => 7]]),
            $this->makeConfig()
        );

        $this->assertArrayNotHasKey('customer', $body);
    }

    public function test_forces_provider_when_method_configured(): void
    {
        $body = CheckoutParams::build(
            $this->makeParams(),
            $this->makeConfig(['paymentMethod' => 'khalti'])
        );
        $this->assertSame('khalti', $body['provider']);
    }

    public function test_forces_fonepay_when_configured(): void
    {
        // Catches the new Fonepay setting being accepted but omitted from checkout requests.
        $body = CheckoutParams::build(
            $this->makeParams(),
            $this->makeConfig(['paymentMethod' => 'fonepay'])
        );
        $this->assertSame('fonepay', $body['provider']);
    }

    public function test_currency_is_uppercased(): void
    {
        $body = CheckoutParams::build(
            $this->makeParams(['currency' => 'npr']),
            $this->makeConfig()
        );
        $this->assertSame('NPR', $body['currency']);
    }

    public function test_public_url_overrides_system_url_for_callbacks(): void
    {
        $body = CheckoutParams::build(
            $this->makeParams(),
            $this->makeConfig(['publicUrl' => 'https://tunnel.trycloudflare.com'])
        );
        $this->assertStringStartsWith(
            'https://tunnel.trycloudflare.com/modules/gateways/callback/paybridgenp.php?invoiceid=42',
            $body['return_url']
        );
        $this->assertStringStartsWith(
            'https://tunnel.trycloudflare.com/modules/gateways/callback/paybridgenp.php?invoiceid=42&cancelled=1',
            $body['cancel_url']
        );
    }
}
