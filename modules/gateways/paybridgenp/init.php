<?php
/**
 * PayBridgeNP WHMCS gateway — bootstrap file.
 *
 * Loaded by modules/gateways/paybridgenp.php and modules/gateways/callback/paybridgenp.php.
 * Sets up the Composer autoloader for our helper classes and the vendored php-sdk.
 */

declare(strict_types=1);

if (!defined('PAYBRIDGENP_WHMCS_VERSION')) {
    define('PAYBRIDGENP_WHMCS_VERSION', '0.3.0');
}

$vendorAutoload = __DIR__ . '/vendor/autoload.php';

if (!file_exists($vendorAutoload)) {
    // Fall back to a clear error — the module cannot run without the SDK.
    // This happens if a merchant copied only the .php files without the
    // `paybridgenp/vendor/` directory. The release zip always includes it.
    throw new \RuntimeException(
        'PayBridgeNP WHMCS module: missing vendor/autoload.php. '
        . 'Re-install from the official zip (it includes the vendored SDK) '
        . 'or run `composer install` inside modules/gateways/paybridgenp/.'
    );
}

require_once $vendorAutoload;
