<?php
/**
 * PayBridgeNP — WHMCS callback endpoint.
 *
 * Two distinct HTTP flows land here:
 *
 *   1) Browser return URL.  PayBridge appends ?session_id=…&status=…&payment_id=…
 *      to the return_url we passed when creating the checkout session. We show
 *      the customer a WHMCS-native page (invoice or checkout) depending on status.
 *
 *   2) Server-to-server webhook.  PayBridge POSTs a signed JSON body with an
 *      X-PayBridge-Signature header. This is the *authoritative* confirmation
 *      that moves the invoice to paid — it is idempotent by payment id.
 *
 * We detect which flow we're in by the presence of the signature header.
 *
 * Security:
 *   - Webhook HMAC verified with whsec_… from module config (replay window: 5min)
 *   - invoiceid from metadata is bounced through WHMCS's checkCbInvoiceID() and
 *     payment ids through checkCbTransID() to prevent duplicate applies
 *   - No state is mutated on the return-URL branch — webhook is source of truth
 */

declare(strict_types=1);

use PayBridgeNP\PayBridge;
use PayBridgeNP\Exceptions\SignatureVerificationException;
use PayBridgeNP\WHMCS\Amount;
use PayBridgeNP\WHMCS\Config;
use PayBridgeNP\WHMCS\Logger;

require_once __DIR__ . '/../../../init.php';               // WHMCS bootstrap
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../paybridgenp/init.php';         // our autoloader

$gatewayModuleName = 'paybridgenp';
$gateway           = getGatewayVariables($gatewayModuleName);

if (!$gateway['type']) {
    http_response_code(400);
    exit('Module Not Activated');
}

$config = new Config($gateway);
$logger = new Logger($config);

$hasSignature = !empty($_SERVER['HTTP_X_PAYBRIDGE_SIGNATURE']);

// ── Branch 1: webhook ────────────────────────────────────────────────────────
if ($hasSignature) {
    paybridgenp_handle_webhook($config, $logger);
    exit;
}

// ── Branch 2: browser return ─────────────────────────────────────────────────
paybridgenp_handle_return($config, $logger);
exit;

// =========================================================================
// WEBHOOK HANDLER
// =========================================================================

function paybridgenp_handle_webhook(Config $config, Logger $logger): void
{
    $payload   = (string) file_get_contents('php://input');
    $signature = (string) ($_SERVER['HTTP_X_PAYBRIDGE_SIGNATURE'] ?? '');
    $secret    = $config->webhookSecret();

    if ($secret === '') {
        $logger->error('webhook.no_secret_configured', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        http_response_code(400);
        echo 'Webhook signing secret not configured';
        return;
    }

    try {
        $event = PayBridge::webhooks()->constructEvent($payload, $signature, $secret);
    } catch (SignatureVerificationException $e) {
        $logger->error('webhook.invalid_signature', [
            'message' => $e->getMessage(),
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        http_response_code(400);
        echo 'Invalid signature';
        return;
    }

    $logger->debug('webhook.event', $event);

    $type = (string) ($event['type'] ?? '');
    $data = (array)  ($event['data'] ?? []);

    switch ($type) {
        case 'payment.succeeded':
            paybridgenp_apply_success($data, $config, $logger);
            break;

        case 'payment.failed':
        case 'payment.cancelled':
            paybridgenp_apply_failure($data, $type, $logger);
            break;

        default:
            // Unknown event types are 200'd so PayBridge doesn't retry forever.
            $logger->info('webhook.ignored', ['type' => $type], 'Info');
            break;
    }

    http_response_code(200);
    echo 'OK';
}

/**
 * @param array<string,mixed> $data  The `data` payload of a payment.succeeded event
 */
function paybridgenp_apply_success(array $data, Config $config, Logger $logger): void
{
    $paymentId = (string) ($data['id']           ?? '');
    $provider  = (string) ($data['provider']     ?? '');
    $provRef   = (string) ($data['provider_ref'] ?? '');
    $amount    = (int)    ($data['amount']       ?? 0);
    $metadata  = (array)  ($data['metadata']     ?? []);
    $invoiceId = (int)    ($metadata['invoiceid'] ?? 0);

    if ($paymentId === '' || $invoiceId === 0) {
        $logger->error('webhook.missing_ids', [
            'payment_id' => $paymentId,
            'invoiceid'  => $invoiceId,
        ]);
        return;
    }

    // Guard: ensure the invoice exists and belongs to this WHMCS install.
    // checkCbInvoiceID returns the validated id or dies with a logged error.
    $invoiceId = checkCbInvoiceID($invoiceId, $config->gatewayName());

    // Guard: dedupe by PayBridge payment id. If the same webhook is re-delivered
    // (or the browser-return path ever applies a payment in a future revision),
    // checkCbTransID bails silently before we double-credit.
    checkCbTransID($paymentId);

    $rupees = Amount::toRupees($amount);
    $note   = sprintf(
        'PayBridgeNP: %s (ref: %s)',
        strtoupper($provider) ?: 'payment',
        $provRef ?: '—'
    );

    addInvoicePayment(
        $invoiceId,
        $paymentId,    // transaction id
        (float) $rupees, // amount in the invoice's currency (NPR)
        0.0,           // fees — unknown at this layer
        $config->gatewayName()
    );

    $logger->info('webhook.payment_applied', [
        'invoiceid'  => $invoiceId,
        'payment_id' => $paymentId,
        'provider'   => $provider,
        'amount_npr' => $rupees,
        'note'       => $note,
    ], 'Successful');
}

/**
 * @param array<string,mixed> $data
 */
function paybridgenp_apply_failure(array $data, string $eventType, Logger $logger): void
{
    // We don't move the invoice to any particular state on failure — WHMCS
    // keeps it unpaid, the customer can retry, and the failure is recorded in
    // the Gateway Log for merchant visibility.
    $logger->info('webhook.payment_failed', [
        'type'      => $eventType,
        'id'        => $data['id']        ?? null,
        'invoiceid' => $data['metadata']['invoiceid'] ?? null,
        'reason'    => $data['reason']    ?? null,
    ], 'Unsuccessful');
}

// =========================================================================
// RETURN-URL HANDLER (browser)
// =========================================================================

/**
 * The customer has been redirected back from the hosted checkout. We don't
 * touch invoice state here — the webhook owns that. We just land them on the
 * right WHMCS page with an appropriate flash message.
 */
function paybridgenp_handle_return(Config $config, Logger $logger): void
{
    $invoiceId = (int)    ($_GET['invoiceid']  ?? 0);
    $status    = (string) ($_GET['status']     ?? '');
    $sessionId = (string) ($_GET['session_id'] ?? '');
    $cancelled = !empty($_GET['cancelled']);

    $systemUrl = rtrim($config->systemUrl(), '/');
    $target    = $invoiceId > 0
        ? $systemUrl . '/viewinvoice.php?id=' . $invoiceId
        : $systemUrl . '/clientarea.php';

    $logger->debug('return.hit', [
        'invoiceid'  => $invoiceId,
        'status'     => $status,
        'session_id' => $sessionId,
        'cancelled'  => $cancelled,
    ]);

    if ($cancelled) {
        $target = paybridgenp_append_flash(
            $target,
            'paybridge_status',
            'cancelled'
        );
    } elseif ($status === 'success') {
        // The webhook may have already applied the payment before the browser
        // redirect completed. Either way, land them on the invoice — if it's
        // paid, WHMCS shows the "Paid" banner; if not, it'll update once the
        // webhook catches up.
        $target = paybridgenp_append_flash(
            $target,
            'paybridge_status',
            'submitted'
        );
    } elseif ($status !== '') {
        $target = paybridgenp_append_flash(
            $target,
            'paybridge_status',
            'failed'
        );
    }

    header('Location: ' . $target, true, 302);
}

function paybridgenp_append_flash(string $url, string $key, string $value): string
{
    $glue = (strpos($url, '?') === false) ? '?' : '&';
    return $url . $glue . urlencode($key) . '=' . urlencode($value);
}
