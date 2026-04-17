<?php

declare(strict_types=1);

namespace PayBridgeNP\WHMCS;

/**
 * WHMCS passes amounts as decimal strings formatted "xxx.xx". PayBridge's API
 * works in paisa (NPR × 100). Do the conversion in one place so rounding is
 * consistent between checkout creation and refund submission.
 */
final class Amount
{
    /**
     * Convert a WHMCS rupee-decimal string/number to integer paisa.
     *
     * Examples:
     *   "1"      → 100
     *   "50.00"  → 5000
     *   "50.95"  → 5095
     *   "1234.5" → 123450
     */
    public static function toPaisa($value): int
    {
        // round() avoids float drift: (float)"50.95" × 100 = 5094.9999999999995
        return (int) round((float) $value * 100);
    }

    /**
     * Convert paisa (from a PayBridge API response) back to rupees for display
     * in order notes and the gateway log.
     */
    public static function toRupees(int $paisa): string
    {
        return number_format($paisa / 100, 2, '.', '');
    }
}
