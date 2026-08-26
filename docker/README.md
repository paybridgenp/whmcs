# Local WHMCS dev loop

Run the full PayBridgeNP WHMCS module against a real WHMCS instance on your laptop in ~10 minutes.

## 1. Get a WHMCS dev license

WHMCS is paid software. Before you can install it you need a dev license.

1. If you own a WHMCS license: open a ticket at https://www.whmcs.com/contact/ asking for a dev license — granted free within a day or two.
2. If you don't own one yet: email `marketplace@whmcs.com` citing your module-developer status. Same lead time.

Dev licenses are tied to an IP or a local hostname. For Docker on your laptop, pick `whmcs.test` or similar — you'll tell WHMCS the allowed domain during the license request.

## 2. Drop the WHMCS source in place

Download the WHMCS release zip from your licensed account and extract it into `whmcs-src/`:

```bash
cd packages/whmcs/docker
unzip ~/Downloads/whmcs-v8.13.x.zip -d whmcs-src/
# The zip expands to `whmcs/` — flatten it:
mv whmcs-src/whmcs/* whmcs-src/
rmdir whmcs-src/whmcs
```

`whmcs-src/` is gitignored — it's your local copy and cannot be committed.

## 3. Boot the stack

```bash
cd packages/whmcs/docker
docker compose up -d
docker compose ps        # confirm all three services are healthy
```

Services:
- `http://localhost:8080` — WHMCS (Apache + PHP)
- `http://localhost:8025` — MailHog (captures outbound WHMCS emails)
- `localhost:3306` — MariaDB (only reachable from inside the network)

## 4. Run the WHMCS installer

Open http://localhost:8080/install/install.php in a browser and walk through:

- **DB host:** `db`  (the service name, not `localhost`)
- **DB name / user / pass:** `whmcs` / `whmcs` / `whmcs`
- **Admin email:** anything — MailHog at http://localhost:8025 catches outbound mail
- **License key:** paste your dev license

After install, delete the `install/` dir from inside the container as WHMCS instructs:

```bash
docker compose exec whmcs rm -rf /var/www/whmcs/install
```

## 5. Activate the PayBridgeNP gateway

1. Log in to WHMCS admin → **Configuration → System Settings → Payment Gateways**.
2. Click **All Payment Gateways**, find **PayBridgeNP**, click **Activate**.
3. Paste your `sk_test_…` into **Test Secret Key** and toggle **Test Mode** on.
4. Paste your `whsec_…` into **Webhook Signing Secret**.
5. Save.

## 6. Tunnel for webhooks

PayBridgeNP webhooks can't reach `localhost`. Start cloudflared in another tab:

```bash
cloudflared tunnel --url http://localhost:8080
# → https://<random>.trycloudflare.com
```

Update WHMCS **Configuration → System Settings → General → WHMCS System URL** to that tunnel URL so both the return URL we generate and the webhook URL in PayBridgeNP resolve externally.

In the PayBridgeNP dashboard, register a webhook pointing at:
```
https://<tunnel-url>/modules/gateways/callback/paybridgenp.php
```
Subscribe to `payment.succeeded`, `payment.failed`.

## 7. End-to-end test

Create a test invoice, "Pay Now", complete with eSewa sandbox. Watch:
- Browser lands back on the invoice view.
- WHMCS Gateway Log (Reports → Gateway Log) shows `checkout.created` and `webhook.payment_applied`.
- The invoice flips to Paid.

## Editing and iterating

The Docker stack mounts the module **read-only** from this checkout. Edit any file under `modules/` and the next page load picks it up. No rebuild.

If you edit `modules/gateways/paybridgenp/src/*.php` and classes aren't found:

```bash
cd packages/whmcs && composer dump-autoload
```

(The vendor dir is live-mounted too — the reload is just for the classmap file.)

## Tear down

```bash
docker compose down              # stop services, keep data
docker compose down -v           # also wipe DB (start over)
rm -rf whmcs-src db-data         # nuke everything
```
