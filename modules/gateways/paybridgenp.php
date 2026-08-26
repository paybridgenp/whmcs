<?php
/**
 * PayBridgeNP — WHMCS Third-Party Payment Gateway Module
 *
 * Merchants install this under modules/gateways/paybridgenp.php. It registers
 * "PayBridgeNP" as an available gateway in WHMCS admin and implements the
 * standard WHMCS gateway contract:
 *
 *   paybridgenp_MetaData() — display name, refund/link-to-invoice support
 *   paybridgenp_config()   — admin config fields
 *   paybridgenp_link()     — returns HTML form that redirects to PayBridgeNP checkout
 *   paybridgenp_refund()   — issues refunds via the php-sdk
 *
 * The user-facing return URL and server-to-server webhook both land on
 * modules/gateways/callback/paybridgenp.php. That file contains the
 * authoritative "apply payment to invoice" logic.
 *
 * See https://developers.whmcs.com/payment-gateways/third-party-gateway/
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    // WHMCS always defines this constant before including gateway files.
    // Abort if the file is hit directly over HTTP.
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/paybridgenp/init.php';

use PayBridgeNP\PayBridgeNP;
use PayBridgeNP\Exceptions\PayBridgeException;
use PayBridgeNP\WHMCS\Amount;
use PayBridgeNP\WHMCS\CheckoutParams;
use PayBridgeNP\WHMCS\Config;
use PayBridgeNP\WHMCS\Logger;

// ── Metadata ─────────────────────────────────────────────────────────────────

/**
 * @return array<string,mixed>
 */
function paybridgenp_MetaData(): array
{
    // Apps & Integrations metadata (name, description, category, support URLs,
    // developer, logo) is read from paybridgenp/whmcs.json — not from this array.
    return [
        'DisplayName'                 => 'PayBridgeNP',
        'APIVersion'                  => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage'            => false,
    ];
}

// ── Admin configuration ──────────────────────────────────────────────────────

/**
 * @return array<string, array<string,mixed>>
 */
function paybridgenp_config(): array
{
    return [
        'FriendlyName'   => [
            'Type'  => 'System',
            'Value' => 'PayBridgeNP',
        ],
        'secretKey'      => [
            'FriendlyName' => 'Live Secret Key',
            'Type'         => 'password',
            'Size'         => '60',
            'Default'      => '',
            'Description'  => 'Your live secret key (sk_live_…) from the PayBridgeNP dashboard.',
        ],
        'testSecretKey'  => [
            'FriendlyName' => 'Test Secret Key',
            'Type'         => 'password',
            'Size'         => '60',
            'Default'      => '',
            'Description'  => 'Your test secret key (sk_test_…). Used when Test Mode is enabled.',
        ],
        'testMode'       => [
            'FriendlyName' => 'Test Mode',
            'Type'         => 'yesno',
            'Description'  => 'Route API calls to the sandbox using the test key above.',
        ],
        'webhookSecret'  => [
            'FriendlyName' => 'Webhook Signing Secret',
            'Type'         => 'password',
            'Size'         => '60',
            'Default'      => '',
            'Description'  => 'The signing secret (whsec_…) shown once when you created the webhook endpoint in the PayBridgeNP dashboard. Required — webhooks are rejected without it.',
        ],
        'publicUrl'      => [
            'FriendlyName' => 'Public Callback URL',
            'Type'         => 'text',
            'Size'         => '60',
            'Default'      => '',
            'Description'  => 'Optional. If set, PayBridgeNP return/webhook URLs use this base instead of the WHMCS System URL. Useful when WHMCS is behind a load balancer, or in dev with a tunnel (cloudflared/ngrok). Example: https://tunnel.example.com/',
        ],
        'paymentMethod'  => [
            'FriendlyName' => 'Payment Method',
            'Type'         => 'dropdown',
            'Options'      => [
                'auto'   => 'Let payer choose from available methods',
                'esewa'  => 'eSewa only',
                'khalti' => 'Khalti only',
                'fonepay' => 'Fonepay only',
            ],
            'Default'      => 'auto',
            'Description'  => 'What to show on the hosted checkout page.',
        ],
        'debugLogging'   => [
            'FriendlyName' => 'Debug Logging',
            'Type'         => 'yesno',
            'Description'  => 'Write full API request/response bodies to the Gateway Log. Leave off in production.',
        ],
    ];
}

// ── Checkout link ────────────────────────────────────────────────────────────

/**
 * Render the "Pay Now" button shown on the client-area invoice. Submitting the
 * form creates a PayBridgeNP checkout session and redirects the customer to the
 * hosted payment page.
 *
 * The form posts back to this same gateway file via WHMCS's standard flow,
 * where `paybridgenpRedirect=1` triggers the redirect. (Using a redirect step
 * instead of server-side `header("Location:")` here means we don't touch the
 * session before WHMCS has finished rendering the invoice page.)
 *
 * @param array<string,mixed> $params
 */
function paybridgenp_link(array $params): string
{
    $config = new Config($params);
    $logger = new Logger($config);

    // ── Step 2: user clicked Pay Now; our own form posted back with the flag.
    //    Create the checkout session server-side and redirect.
    $triggerFromPost = isset($_POST['paybridgenpRedirect']) && $_POST['paybridgenpRedirect'] === '1';
    $triggerFromGet  = isset($_GET['paybridgenpRedirect'])  && $_GET['paybridgenpRedirect']  === '1';
    if ($triggerFromPost || $triggerFromGet) {
        return paybridgenp_create_and_redirect($params, $config, $logger);
    }

    // ── Step 1: render the button.
    $invoiceId = (int) ($params['invoiceid'] ?? 0);
    $label     = htmlspecialchars((string) ($params['langpaynow'] ?? 'Pay Now'), ENT_QUOTES);

    // The form posts to the same invoice viewer URL — WHMCS will re-render
    // the invoice and re-invoke paybridgenp_link() with the flag set.
    $actionUrl = htmlspecialchars(
        rtrim((string) ($params['systemurl'] ?? ''), '/') . '/viewinvoice.php?id=' . $invoiceId,
        ENT_QUOTES
    );

    return <<<HTML
<form method="post" action="{$actionUrl}" style="display:inline-block;">
    <input type="hidden" name="paybridgenpRedirect" value="1" />
    <button type="submit" class="btn btn-primary" style="background:#5B2FDB;border-color:#5B2FDB;">
        {$label}
    </button>
</form>
HTML;
}

/**
 * Create a PayBridgeNP checkout session and issue the client-side redirect.
 *
 * Returns HTML (required by the WHMCS gateway contract) rather than calling
 * header() + exit because WHMCS has already started output by the time
 * paybridgenp_link() runs.
 */
function paybridgenp_create_and_redirect(array $params, Config $config, Logger $logger): string
{
    if ($config->apiKey() === '') {
        $logger->error('checkout.missing_key', ['invoiceid' => $params['invoiceid'] ?? null]);
        return paybridgenp_error_html(
            'PayBridgeNP is not configured. Please contact the site administrator.'
        );
    }

    try {
        $pb   = new PayBridgeNP($config->sdkConfig());
        $body = CheckoutParams::build($params, $config);

        $logger->debug('checkout.request', $body);
        $session = $pb->checkout->create($body);
        $logger->debug('checkout.response', $session);

        $logger->info('checkout.created', [
            'invoiceid'   => $params['invoiceid'] ?? null,
            'session_id'  => $session['id']        ?? null,
        ], 'Success');
    } catch (PayBridgeException $e) {
        $logger->error('checkout.api_error', [
            'invoiceid' => $params['invoiceid'] ?? null,
            'message'   => $e->getMessage(),
        ]);
        return paybridgenp_error_html(
            'Could not start payment: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES)
        );
    } catch (\Throwable $e) {
        $logger->error('checkout.unexpected', [
            'invoiceid' => $params['invoiceid'] ?? null,
            'message'   => $e->getMessage(),
        ]);
        return paybridgenp_error_html('Unexpected error while starting payment. Please try again.');
    }

    $checkoutUrl = htmlspecialchars((string) ($session['checkout_url'] ?? ''), ENT_QUOTES);
    if ($checkoutUrl === '') {
        return paybridgenp_error_html('PayBridgeNP did not return a checkout URL. Please try again.');
    }

    // Meta refresh + JS fallback. <noscript> handles JS-disabled clients.
    return <<<HTML
<div style="text-align:center;padding:20px;">
    <p>Redirecting to PayBridgeNP…</p>
    <p><a href="{$checkoutUrl}">Click here if you are not redirected.</a></p>
</div>
<script>window.location.href = "{$checkoutUrl}";</script>
<noscript><meta http-equiv="refresh" content="0; url={$checkoutUrl}" /></noscript>
HTML;
}

function paybridgenp_error_html(string $msg): string
{
    $safe = htmlspecialchars($msg, ENT_QUOTES);
    return <<<HTML
<div class="alert alert-danger" role="alert" style="margin-top:10px;">
    {$safe}
</div>
HTML;
}

// ── Refunds ──────────────────────────────────────────────────────────────────

/**
 * Invoked by WHMCS when an admin clicks "Refund" on a transaction tied to
 * this gateway. Returns the WHMCS-standard status map.
 *
 * @param array<string,mixed> $params
 * @return array{status: string, rawdata?: mixed, transid?: string, fees?: float}
 */
function paybridgenp_refund(array $params): array
{
    $config = new Config($params);
    $logger = new Logger($config);

    $paymentId = (string) ($params['transid'] ?? '');
    $amount    = Amount::toPaisa($params['amount'] ?? 0);

    if ($paymentId === '' || $amount <= 0) {
        $logger->error('refund.bad_params', [
            'transid'   => $paymentId,
            'amount'    => $params['amount'] ?? null,
            'invoiceid' => $params['invoiceid'] ?? null,
        ]);
        return [
            'status'  => 'declined',
            'rawdata' => 'Missing transaction id or refund amount.',
        ];
    }

    if ($config->apiKey() === '') {
        $logger->error('refund.missing_key', ['transid' => $paymentId]);
        return [
            'status'  => 'declined',
            'rawdata' => 'API key is not configured.',
        ];
    }

    try {
        $pb      = new PayBridgeNP($config->sdkConfig());
        $invoice = (int) ($params['invoiceid'] ?? 0);
        // PayBridgeNP requires `reason` to be one of an enum. WHMCS has no
        // concept of refund taxonomy, so we default to `other` and put the
        // human context in `notes`.
        $request = [
            'paymentId' => $paymentId,
            'amount'    => $amount,
            'reason'    => 'other',
            'notes'     => 'Refund issued from WHMCS admin (invoice #' . $invoice . ')',
        ];

        $logger->debug('refund.request', $request);
        $refund = $pb->refunds->create($request);
        $logger->debug('refund.response', $refund);

        $logger->info('refund.success', [
            'payment_id' => $paymentId,
            'refund_id'  => $refund['id']     ?? null,
            'amount'     => $refund['amount'] ?? null,
            'invoiceid'  => $params['invoiceid'] ?? null,
        ], 'Success');

        return [
            'status'  => 'success',
            'rawdata' => $refund,
            'transid' => (string) ($refund['id'] ?? ''),
        ];
    } catch (PayBridgeException $e) {
        $logger->error('refund.api_error', [
            'payment_id' => $paymentId,
            'message'    => $e->getMessage(),
            'invoiceid'  => $params['invoiceid'] ?? null,
        ]);
        return [
            'status'  => 'declined',
            'rawdata' => $e->getMessage(),
        ];
    } catch (\Throwable $e) {
        $logger->error('refund.unexpected', [
            'payment_id' => $paymentId,
            'message'    => $e->getMessage(),
        ]);
        return [
            'status'  => 'declined',
            'rawdata' => 'Unexpected error: ' . $e->getMessage(),
        ];
    }
}
