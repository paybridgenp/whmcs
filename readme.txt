=== PayBridgeNP for WHMCS ===
Contributors: paybridgenp
Tags: whmcs, payment-gateway, nepal, esewa, khalti
Requires at least: WHMCS 8.0
Tested up to: WHMCS 8.13
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept eSewa and Khalti payments inside WHMCS through a single PayBridgeNP integration. One dashboard, one reconciliation, built-in refunds.

== Description ==

PayBridgeNP is a payment gateway aggregator for Nepal. This module connects WHMCS to PayBridge so your customers can pay invoices with eSewa or Khalti on a hosted, mobile-friendly checkout page — no per-method configuration required.

**Why PayBridgeNP for WHMCS:**

* One install — supports every method PayBridgeNP supports today and any method added in the future
* Hosted checkout — PCI-light, mobile-optimised, no iframe weirdness
* Full refund support inside WHMCS admin (partial refunds work too)
* HMAC-signed webhooks as the authoritative payment confirmation — no duplicate postings if the customer closes the tab
* Gateway Log integration for every API call and webhook delivery
* Test mode with sandbox keys — no real money moves

== Installation ==

1. Download `paybridgenp-whmcs-<version>.zip` from the PayBridgeNP GitHub releases page.
2. Extract at the root of your WHMCS installation — files merge into `modules/gateways/`.
3. Log in to WHMCS admin → **Configuration → System Settings → Payment Gateways**.
4. Under **All Payment Gateways**, click **PayBridgeNP** and **Activate**.
5. Enter your **Secret Key** (`sk_live_…` from https://app.paybridgenp.com) and the **Webhook Signing Secret** (`whsec_…`). Save.
6. In your PayBridgeNP dashboard, register a webhook pointing at:
   `https://your-whmcs-url/modules/gateways/callback/paybridgenp.php`
7. Enable the `payment.succeeded`, `payment.failed`, and `payment.cancelled` events.

== Frequently Asked Questions ==

= Does this support recurring / subscription billing? =
Not yet. PayBridgeNP does not vault cards — Nepali wallets don't currently support merchant-initiated charges. WHMCS invoices for renewals are emailed to customers who pay them interactively.

= What currencies are supported? =
NPR only. Non-NPR invoices will be rejected by the API with a clear error.

= Can I use both eSewa and Khalti? =
Yes. By default the payer picks on the hosted checkout page. You can also force a single method in the module settings.

== Changelog ==

= 0.1.1 =
* Added: optional "Public Callback URL" field so admins behind a load balancer or using a tunnel (cloudflared/ngrok) can override the WHMCS System URL for return/webhook callbacks.
* Fixed: refund submission now sends `reason=other` with a notes field describing the WHMCS invoice, matching the PayBridge API enum requirement.

= 0.1.0 =
* Initial release
