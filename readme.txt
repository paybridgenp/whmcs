=== PayBridgeNP for WHMCS ===
Contributors: paybridgenp
Tags: whmcs, payment-gateway, nepal, esewa, khalti, fonepay
Requires at least: WHMCS 8.0
Tested up to: WHMCS 8.13
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept eSewa, Khalti, and Fonepay payments inside WHMCS through a single PayBridgeNP integration. One dashboard, one reconciliation, built-in refunds.

== Description ==

PayBridgeNP is a payment gateway for Nepal. This module connects WHMCS to PayBridgeNP so your customers can pay invoices with any available method - including eSewa, Khalti, and configured Fonepay - on a hosted, mobile-friendly checkout page.

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
7. Enable the `payment.succeeded` and `payment.failed` events.

== Frequently Asked Questions ==

= Does this support recurring / subscription billing? =
Not yet. PayBridgeNP does not vault cards — Nepali wallets don't currently support merchant-initiated charges. WHMCS invoices for renewals are emailed to customers who pay them interactively.

= What currencies are supported? =
NPR only. Non-NPR invoices will be rejected by the API with a clear error.

= Can I use eSewa, Khalti, and Fonepay? =
Yes. By default the payer picks from the methods available for your PayBridgeNP project. You can also force eSewa, Khalti, or Fonepay in the module settings. Fonepay appears when it is configured and enabled for the active mode.

== Changelog ==

= 0.3.0 =
* Added: Fonepay-only checkout option. The default chooser now accurately offers every provider available for the project.
* Changed: vendored PHP SDK dependency to ^5.0.

= 0.1.1 =
* Added: optional "Public Callback URL" field so admins behind a load balancer or using a tunnel (cloudflared/ngrok) can override the WHMCS System URL for return/webhook callbacks.
* Fixed: refund submission now sends `reason=other` with a notes field describing the WHMCS invoice, matching the PayBridge API enum requirement.

= 0.1.0 =
* Initial release
