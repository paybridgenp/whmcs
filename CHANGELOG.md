# Changelog

All notable changes to the PayBridgeNP WHMCS module.

## [0.1.1] — 2026-04-18

Validated end-to-end against WHMCS 8.13 + PayBridge sandbox (eSewa/Khalti via Docker). Full matrix — checkout, return URL, HMAC webhook, invoice marked Paid, refund (full + declined path), idempotent replay — all passing.

### Added
- Optional `Public Callback URL` gateway field. Overrides the WHMCS System URL for the return/webhook callback base, so merchants behind a load balancer or developers using a tunnel (cloudflared, ngrok) can point PayBridge at an externally-reachable hostname without touching System URL.
- Dockerfile for local dev (`docker/Dockerfile.whmcs`) — PHP 8.2 + Apache + ionCube Loader. The community `fauzie/docker-whmcs` image shipped PHP 7.3 FPM only, which WHMCS 8.13 can't run.

### Fixed
- Refund API call now sends `reason=other` + `notes=<human context>` instead of a freeform string in `reason`. PayBridge's `/v1/refunds` rejects anything outside the enum `customer_request | duplicate | fraudulent | other`.

## [0.1.0] — 2026-04-17

Initial scaffold.

### Added
- WHMCS third-party gateway contract (`_MetaData`, `_config`, `_link`, `_refund`).
- Return-URL handler + HMAC-verified webhook receiver in `callback/paybridgenp.php`.
- Vendors `paybridge-np/sdk ^1.3` for checkout creation, refunds, and webhook verification.
- Docker-based local dev loop with WHMCS, MariaDB, MailHog.
- PHPUnit tests: HMAC verification, amount conversion, WHMCS-params → checkout-request mapper, secret scrubbing, config fallbacks.
