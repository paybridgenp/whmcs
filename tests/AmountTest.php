<?php

declare(strict_types=1);

namespace PayBridgeNP\WHMCS\Tests;

use PayBridgeNP\WHMCS\Amount;
use PHPUnit\Framework\TestCase;

final class AmountTest extends TestCase
{
    /**
     * @dataProvider paisaCases
     *
     * @param string|int|float $input
     */
    public function test_to_paisa_converts_rupee_decimals_to_integer_paisa($input, int $expected): void
    {
        $this->assertSame($expected, Amount::toPaisa($input));
    }

    /** @return array<string, array{0: string|int|float, 1: int}> */
    public static function paisaCases(): array
    {
        return [
            'one rupee as int'      => [1, 100],
            'one rupee as string'   => ['1', 100],
            'fifty rupees'          => ['50.00', 5000],
            'fractional — rounds up' => ['50.95', 5095],
            // The reason this case exists: (float)"50.95" * 100 yields
            // 5094.9999999999995, which (int) would truncate to 5094.
            // round() first avoids the drift.
            'float drift trap'      => [50.95, 5095],
            'thousands'             => ['1234.50', 123450],
            'zero'                  => ['0.00', 0],
        ];
    }

    public function test_to_rupees_formats_with_two_decimals(): void
    {
        $this->assertSame('50.00',   Amount::toRupees(5000));
        $this->assertSame('50.95',   Amount::toRupees(5095));
        $this->assertSame('1234.50', Amount::toRupees(123450));
        $this->assertSame('0.00',    Amount::toRupees(0));
    }
}
