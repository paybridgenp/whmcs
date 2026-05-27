# Changelog

All notable changes to the PayBridgeNP WHMCS module.

## [0.2.0] — 2026-05-02

Bundles the PayBridgeNP PHP SDK 3.0 so the module handles the new Stripe-style nested error envelope returned by the API.

### Changed
- Vendored `paybridge-np/sdk` 1.3.0 → **3.0.0**.
- Errors thrown from `\PayBridgeNP\PayBridgeNP` calls are now typed exceptions (`AuthenticationException`, `AccountException`, `PermissionException`, `InvalidRequestException`, `IdempotencyException`, `RateLimitException`). Branch with `instanceof` instead of inspecting status codes.
- Every exception now carries `getErrorType()`, `getErrorCode()`, and `getRequestId()` — quote `getRequestId()` in support requests for fastest triage.

### Backward compatibility
- The SDK still parses the legacy flat `{error: "...", code: "..."}` shape, so installs migrating from v0.1.x will keep working during the API transition window — but this version requires the new envelope on the server side, so upgrade in lockstep with the API.

## [0.1.1] — 2026-04-18

Validated end-to-end against WHMCS 8.13 + PayBridgeNP sandbox (eSewa/Khalti via Docker). Full matrix — checkout, return URL, HMAC webhook, invoice marked Paid, refund (full + declined path), idempotent replay — all passing.

**Distribution:**
- Public repo: [github.com/paybridgenp/whmcs](https://github.com/paybridgenp/whmcs) with GitHub Release.
- Merchant download: [paybridgenp.com/integrations/whmcs](https://paybridgenp.com/integrations/whmcs).
- Docs: [docs.paybridgenp.com/integrations/whmcs](https://docs.paybridgenp.com/integrations/whmcs).
- WHMCS Marketplace: submitted as product #8648 on 2026-04-18, awaiting review.

### Added
- Optional `Public Callback URL` gateway field. Overrides the WHMCS System URL for the return/webhook callback base, so merchants behind a load balancer or developers using a tunnel (cloudflared, ngrok) can point PayBridgeNP at an externally-reachable hostname without touching System URL.
- Dockerfile for local dev (`docker/Dockerfile.whmcs`) — PHP 8.2 + Apache + ionCube Loader. The community `fauzie/docker-whmcs` image shipped PHP 7.3 FPM only, which WHMCS 8.13 can't run.

### Fixed
- Refund API call now sends `reason=other` + `notes=<human context>` instead of a freeform string in `reason`. PayBridgeNP's `/v1/refunds` rejects anything outside the enum `customer_request | duplicate | fraudulent | other`.

## [0.1.0] — 2026-04-17

Initial scaffold.

### Added
- WHMCS third-party gateway contract (`_MetaData`, `_config`, `_link`, `_refund`).
- Return-URL handler + HMAC-verified webhook receiver in `callback/paybridgenp.php`.
- Vendors `paybridge-np/sdk ^1.3` for checkout creation, refunds, and webhook verification.
- Docker-based local dev loop with WHMCS, MariaDB, MailHog.
- PHPUnit tests: HMAC verification, amount conversion, WHMCS-params → checkout-request mapper, secret scrubbing, config fallbacks.
