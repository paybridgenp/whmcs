#!/usr/bin/env bash
# Build the merchant-installable zip for the PayBridgeNP NP WHMCS gateway.
#
# Output: packages/whmcs/dist/paybridgenp-whmcs-<version>.zip
#
# The zip layout matches what gets merged into a merchant's WHMCS install:
#
#   paybridgenp-whmcs-<version>/
#   └── modules/
#       └── gateways/
#           ├── paybridgenp.php
#           ├── paybridgenp/
#           │   ├── init.php
#           │   ├── src/
#           │   ├── vendor/           ← paybridge-np/sdk vendored here, production-only deps
#           │   ├── logo.png
#           │   └── whmcs.json
#           └── callback/paybridgenp.php

set -euo pipefail

# ── Paths ────────────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
MODULE_DIR="$ROOT_DIR/modules/gateways/paybridgenp"
DIST_DIR="$ROOT_DIR/dist"
STAGING_DIR="$(mktemp -d -t paybridgenp-whmcs-XXXXXX)"

trap 'rm -rf "$STAGING_DIR"' EXIT

# ── Version (from whmcs.json) ────────────────────────────────────────────────
if ! command -v php >/dev/null 2>&1; then
    echo "ERROR: php is required to read whmcs.json" >&2
    exit 1
fi
VERSION="$(php -r '
$src = file_get_contents($argv[1]);
preg_match("/define\\(\\s*\x27PAYBRIDGENP_WHMCS_VERSION\x27\\s*,\\s*\x27([^\x27]+)\x27/", $src, $m);
echo $m[1] ?? "0.0.0";
' "$MODULE_DIR/init.php")"
echo "› Building PayBridgeNP NP WHMCS module v$VERSION"

# ── Vendor the SDK (production-only) ─────────────────────────────────────────
echo "› Vendoring paybridge-np/sdk into modules/gateways/paybridgenp/vendor/"
rm -rf "$MODULE_DIR/vendor"

# Write a throwaway composer.json inside the module dir that declares JUST the
# runtime dependency. This gives us a vendor tree with ONLY the SDK (no phpunit)
# without polluting the root composer.json config.
cat > "$MODULE_DIR/composer.json" <<'EOF'
{
  "name": "paybridge-np/whmcs-runtime",
  "description": "Runtime dependencies + PSR-4 autoload shipped with the PayBridgeNP NP WHMCS module.",
  "type": "library",
  "require": {
    "php": ">=7.4",
    "ext-curl": "*",
    "ext-json": "*",
    "paybridge-np/sdk": "^5.0"
  },
  "repositories": [
    {
      "type": "path",
      "url": "../../../../php-sdk",
      "options": { "symlink": false }
    }
  ],
  "autoload": {
    "psr-4": {
      "PayBridgeNP\\WHMCS\\": "src/"
    }
  },
  "config": {
    "optimize-autoloader": true,
    "preferred-install": "dist"
  }
}
EOF

(
    cd "$MODULE_DIR"
    composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --quiet
)

# composer.json/lock are throwaway — don't ship them to merchants
rm -f "$MODULE_DIR/composer.json" "$MODULE_DIR/composer.lock"

# Strip SDK's own tests / phpunit config — not needed in production.
# NOTE the nested vendor/ and composer.lock: when the SDK is resolved from the
# local PATH repository above (which it is until 5.4.0 is on Packagist), Composer
# COPIES the whole source directory — including any vendor/ a developer created
# by running `composer install` in packages/php-sdk to get phpunit. That dragged
# 10MB and 993 phpunit files into the merchant zip (2.2MB zipped, 1999 entries)
# before this was stripped. A Packagist dist never carries vendor/, so removing
# it unconditionally is harmless and makes the artifact identical either way.
rm -rf \
    "$MODULE_DIR/vendor/paybridge-np/sdk/tests" \
    "$MODULE_DIR/vendor/paybridge-np/sdk/vendor" \
    "$MODULE_DIR/vendor/paybridge-np/sdk/composer.lock" \
    "$MODULE_DIR/vendor/paybridge-np/sdk/phpunit.xml" \
    "$MODULE_DIR/vendor/paybridge-np/sdk/.github" \
    "$MODULE_DIR/vendor/paybridge-np/sdk/.gitignore" \
    "$MODULE_DIR/vendor/paybridge-np/sdk/.phpunit.result.cache"

# Composer records the development-only path repository in installed.json.
# InstalledVersions uses installed.php at runtime, so this metadata is safe to
# omit and must not expose the private checkout layout in the merchant ZIP.
rm -f "$MODULE_DIR/vendor/composer/installed.json"

# ── Stage the files that go into the zip ─────────────────────────────────────
STAGE_ROOT="$STAGING_DIR/paybridgenp-whmcs-$VERSION"
mkdir -p "$STAGE_ROOT/modules/gateways/callback" \
         "$STAGE_ROOT/modules/gateways/paybridgenp"

cp "$ROOT_DIR/modules/gateways/paybridgenp.php"          "$STAGE_ROOT/modules/gateways/"
cp "$ROOT_DIR/modules/gateways/callback/paybridgenp.php" "$STAGE_ROOT/modules/gateways/callback/"

# Copy the whole module dir (init.php, src/, vendor/, whmcs.json, logo.png)
cp -R "$MODULE_DIR/." "$STAGE_ROOT/modules/gateways/paybridgenp/"

# Include LICENSE + readme for merchants who unzip and look around
cp "$ROOT_DIR/LICENSE"    "$STAGE_ROOT/" 2>/dev/null || true
cp "$ROOT_DIR/readme.txt" "$STAGE_ROOT/" 2>/dev/null || true

# ── Zip ──────────────────────────────────────────────────────────────────────
mkdir -p "$DIST_DIR"
ZIP_PATH="$DIST_DIR/paybridgenp-whmcs-$VERSION.zip"
rm -f "$ZIP_PATH"


# ── Gate the artifact, not the source tree ───────────────────────────────────
# Root-vendor and unit tests passing prove nothing about what merchants install.
# 2026-08-16: the module source moved to the php-sdk 5.x class name
# (PayBridgeNP\PayBridgeNP) while this script still vendored ^3.0, which only
# defines PayBridgeNP\PayBridge. Nothing shipped broken because the built zips
# predated the source change — but the next build would have. These assertions
# make that impossible to ship silently.
gate_fail() { echo "ERROR: $1" >&2; exit 1; }

WANT_SDK="$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["version"] ?? "";' "$ROOT_DIR/../php-sdk/composer.json")"
GOT_SDK="$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["version"] ?? "";' "$MODULE_DIR/vendor/paybridge-np/sdk/composer.json")"
[ -n "$GOT_SDK" ] || gate_fail "could not read the vendored SDK version"
[ "$WANT_SDK" = "$GOT_SDK" ] || gate_fail "vendored paybridge-np/sdk is $GOT_SDK but packages/php-sdk declares $WANT_SDK"
echo "  SDK:   paybridge-np/sdk $GOT_SDK"

php -r '
require $argv[1];
foreach (["PayBridgeNP\\PayBridgeNP", "PayBridgeNP\\WHMCS\\Config"] as $c) {
    if (!class_exists($c)) { fwrite(STDERR, "ERROR: shipped autoloader cannot resolve $c\n"); exit(1); }
}
' "$MODULE_DIR/vendor/autoload.php" || gate_fail "shipped autoloader failed to resolve a required class"
echo "  Gate:  shipped autoloader resolves PayBridgeNP\\PayBridgeNP"

(
    cd "$STAGING_DIR"
    zip -rq "$ZIP_PATH" "paybridgenp-whmcs-$VERSION"
)

# Scan the built zip for anything private before it can reach the Marketplace.
# This script is tracked in the public repo, so the guard runs when present and
# is skipped with a warning when it is not, rather than hard-failing a
# standalone clone that has no development checkout around it.
HYGIENE_GUARD="$ROOT_DIR/../check-public-package-hygiene.ts"
if [ -f "$HYGIENE_GUARD" ] && command -v bun >/dev/null 2>&1; then
    bun "$HYGIENE_GUARD" --artifact whmcs "$ZIP_PATH"
else
    echo "  NOTE:  public-package hygiene guard not available here; artifact not scanned." >&2
fi

echo "✓ Built: $ZIP_PATH"
echo "  Size:  $(du -h "$ZIP_PATH" | cut -f1)"
echo "  Files: $(unzip -l "$ZIP_PATH" | tail -1 | awk '{print $2}') entries"
