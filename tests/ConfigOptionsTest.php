<?php

declare(strict_types=1);

namespace PayBridgeNP\WHMCS\Tests;

use PayBridgeNP\WHMCS\Config;
use PHPUnit\Framework\TestCase;

/**
 * The admin dropdown and the Config whitelist must offer the same set.
 *
 * These are two independent lists in two files, and only the whitelist was
 * covered. Verified 2026-08-16: deleting `'fonepay' => 'Fonepay only'` from the
 * dropdown left all 28 tests green — Config would still ACCEPT fonepay, but no
 * merchant could ever select it, so the feature would be silently unreachable.
 *
 * Asserting the two agree catches a half-added provider in either direction:
 * an option with no whitelist entry silently falls back to 'auto', and a
 * whitelist entry with no option is unreachable.
 */
final class ConfigOptionsTest extends TestCase
{
    /** @return array<string,string> */
    private function dropdownOptions(): array
    {
        // paybridgenp.php dies without the WHMCS constant and pulls in the
        // module autoloader, so parse the Options block rather than including
        // it. Brittle by design: if the shape changes, this test should be
        // updated deliberately, not silently pass.
        $src = file_get_contents(__DIR__ . '/../modules/gateways/paybridgenp.php');
        $this->assertNotFalse($src, 'could not read the gateway module');

        $ok = preg_match(
            "/'paymentMethod'\s*=>\s*\[.*?'Options'\s*=>\s*\[(.*?)\]/s",
            $src,
            $m
        );
        $this->assertSame(1, $ok, 'could not locate the paymentMethod Options block');

        preg_match_all("/'([a-z_]+)'\s*=>\s*'([^']+)'/", $m[1], $pairs, PREG_SET_ORDER);
        $this->assertNotEmpty($pairs, 'parsed the Options block but found no entries');

        $out = [];
        foreach ($pairs as $p) {
            $out[$p[1]] = $p[2];
        }
        return $out;
    }

    public function test_every_dropdown_option_is_accepted_by_config(): void
    {
        foreach (array_keys($this->dropdownOptions()) as $value) {
            $this->assertSame(
                $value,
                (new Config(['paymentMethod' => $value]))->paymentMethod(),
                "the dropdown offers '{$value}' but Config falls back to auto for it"
            );
        }
    }

    public function test_every_provider_config_accepts_is_offered_in_the_dropdown(): void
    {
        $offered = array_keys($this->dropdownOptions());
        foreach (['auto', 'esewa', 'khalti', 'fonepay'] as $provider) {
            $accepted = (new Config(['paymentMethod' => $provider]))->paymentMethod() === $provider;
            if ($accepted) {
                $this->assertContains(
                    $provider,
                    $offered,
                    "Config accepts '{$provider}' but no merchant can select it — the dropdown does not offer it"
                );
            }
        }
    }

    public function test_fonepay_is_selectable(): void
    {
        // Named explicitly: nearly every paying merchant is on Fonepay, and this
        // was unreachable until 2026-08-16.
        $this->assertArrayHasKey('fonepay', $this->dropdownOptions());
    }

    public function test_the_auto_label_does_not_enumerate_providers(): void
    {
        // The label used to read "Let payer choose (eSewa or Khalti)" while the
        // hosted page had always shown Fonepay too, because 'auto' omits the
        // provider field entirely. A label that lists providers goes stale the
        // moment one is added.
        $auto = $this->dropdownOptions()['auto'] ?? '';
        foreach (['eSewa', 'Khalti', 'Fonepay'] as $name) {
            $this->assertStringNotContainsStringIgnoringCase(
                $name,
                $auto,
                "the 'auto' label names {$name}; it will go stale when providers change"
            );
        }
    }
}
