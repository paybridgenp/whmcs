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
    "paybridge-np/sdk": "^3.0"
  },
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

# Strip SDK's own tests / phpunit config — not needed in production
rm -rf \
    "$MODULE_DIR/vendor/paybridge-np/sdk/tests" \
    "$MODULE_DIR/vendor/paybridge-np/sdk/phpunit.xml" \
    "$MODULE_DIR/vendor/paybridge-np/sdk/.github" \
    "$MODULE_DIR/vendor/paybridge-np/sdk/.gitignore"

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

(
    cd "$STAGING_DIR"
    zip -rq "$ZIP_PATH" "paybridgenp-whmcs-$VERSION"
)

echo "✓ Built: $ZIP_PATH"
echo "  Size:  $(du -h "$ZIP_PATH" | cut -f1)"
echo "  Files: $(unzip -l "$ZIP_PATH" | tail -1 | awk '{print $2}') entries"
