# PayBridgeNP for WHMCS

Official [PayBridgeNP](https://paybridgenp.com) gateway module for WHMCS. Accept **eSewa** and **Khalti** payments on invoices inside your WHMCS billing panel with a single integration and full refund support from admin.

<p align="center">
  <img src="modules/gateways/paybridgenp/logo.png" alt="PayBridgeNP logo" width="96" height="96" />
</p>

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2+-blue.svg)](LICENSE)
![WHMCS 8.0+](https://img.shields.io/badge/WHMCS-8.0%2B-orange)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)

## Install in 5 minutes

1. Download the latest `paybridgenp-whmcs-*.zip` from [Releases](https://github.com/paybridgenp/whmcs/releases/latest) — about 65 KB, the PayBridgeNP PHP SDK is vendored inside so you don't need Composer.
2. Extract the ZIP at the root of your WHMCS install (the directory containing `configuration.php`). Files merge into `modules/gateways/`.
3. In WHMCS admin, go to **Configuration → System Settings → Payment Gateways → All Payment Gateways**, find **PayBridgeNP**, click **Activate**.
4. Paste your test or live API key (`sk_test_…` / `sk_live_…`) and your webhook signing secret (`whsec_…`) from the [PayBridgeNP dashboard](https://dashboard.paybridgenp.com).
5. In the dashboard, register a webhook endpoint pointing at:
   ```
   https://your-whmcs-url/modules/gateways/callback/paybridgenp.php
   ```
   Subscribe to `payment.succeeded`, `payment.failed`, and `payment.cancelled`.

Full setup walkthrough with screenshots: [docs.paybridgenp.com/integrations/whmcs/install](https://docs.paybridgenp.com/integrations/whmcs/install).

## Features

- **eSewa + Khalti in one module.** No per-method configuration, no duplicate webhooks.
- **Full refunds inside admin.** Click Refund on any transaction — partial refunds supported. Calls PayBridgeNP's refund API directly.
- **HMAC-signed webhooks** with a 5-minute replay window. Unsigned events rejected with HTTP 400.
- **Idempotent payment application** via `checkCbTransID` — replayed webhooks never double-post.
- **Gateway Log integration** with automatic secret scrubbing (API keys, signatures, and signing secrets never land in the log).
- **Optional Public Callback URL override** for installs behind a load balancer or local dev with cloudflared / ngrok tunnels.

## Requirements

- WHMCS **8.0 or newer**
- PHP **7.4 or newer** with the **ionCube Loader** extension (WHMCS requires it anyway)
- Invoice currency set to **NPR** (Nepalese Rupee)
- A [PayBridgeNP account](https://dashboard.paybridgenp.com/signup) with an API key and a webhook signing secret

## Repository layout

```
modules/gateways/
├── paybridgenp.php                     # Main gateway (MetaData, config, link, refund)
├── paybridgenp/
│   ├── init.php                         # Composer autoloader bootstrap
│   ├── whmcs.json                       # Manifest shown in Apps & Integrations
│   ├── logo.png
│   └── src/
│       ├── Amount.php                   # paisa ↔ rupees conversion
│       ├── CheckoutParams.php           # WHMCS params → checkout request body
│       ├── Config.php                   # Normalised settings accessor
│       └── Logger.php                   # Gateway-log wrapper with secret scrubbing
└── callback/
    └── paybridgenp.php                  # Return URL + webhook handler
```

## Building the release ZIP

```bash
composer install
bash scripts/package.sh
# → dist/paybridgenp-whmcs-<version>.zip
```

The script vendors a production-only copy of `paybridge-np/sdk`, strips tests, and zips everything merchants need to drop into their WHMCS install.

## Running the tests

```bash
composer install
composer test
```

26 PHPUnit tests cover HMAC signature verification (valid, tampered, replay, missing, wrong-secret, malformed), amount paisa conversion, WHMCS-params → checkout-request mapping, config fallbacks, and logger secret scrubbing.

## Local development with Docker

Bring up a full WHMCS 8.13 on PHP 8.2 + Apache + ionCube in Docker, with live-mounted module files:

```bash
cd docker
# Drop your WHMCS release zip into whmcs-src/ (see docker/README.md)
docker compose up -d
```

Walkthrough: [`docker/README.md`](./docker/README.md).

## Documentation

- **Integration guide:** [docs.paybridgenp.com/integrations/whmcs](https://docs.paybridgenp.com/integrations/whmcs)
- **How it works / security model:** [/integrations/whmcs/how-it-works](https://docs.paybridgenp.com/integrations/whmcs/how-it-works)
- **Troubleshooting:** [/integrations/whmcs/troubleshooting](https://docs.paybridgenp.com/integrations/whmcs/troubleshooting)
- **PayBridgeNP PHP SDK:** [packagist.org/packages/paybridge-np/sdk](https://packagist.org/packages/paybridge-np/sdk)
- **PayBridgeNP API:** [docs.paybridgenp.com](https://docs.paybridgenp.com)

## Support

- **Documentation** — [docs.paybridgenp.com](https://docs.paybridgenp.com)
- **Dashboard & account** — [dashboard.paybridgenp.com](https://dashboard.paybridgenp.com)
- **Email** — [support@paybridgenp.com](mailto:support@paybridgenp.com)
- **Issues & feature requests** — [open an issue](https://github.com/paybridgenp/whmcs/issues)

## License

GPL-2.0-or-later. See [LICENSE](./LICENSE).
