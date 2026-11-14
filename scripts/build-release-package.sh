#!/usr/bin/env bash
# Build easm-X.Y.Z.zip for shared-hosting installs (vendor + public assets, no .env).
# Usage: scripts/build-release-package.sh <version-without-v> [output-dir]
set -euo pipefail

version="${1:-}"
if [[ -z "$version" ]]; then
  echo "Usage: $0 <version-without-v> [output-dir]" >&2
  exit 1
fi

root="$(cd "$(dirname "$0")/.." && pwd)"
out_dir="${2:-$root/dist}"
stage="$out_dir/stage"
zip_name="easm-${version}.zip"
zip_path="$out_dir/$zip_name"

rm -rf "$stage"
mkdir -p "$stage" "$out_dir"

# Front end is static files under public/; there is no Node/Vite build step.
rsync -a \
  --exclude='/.git' --exclude='/.github' --exclude='/.githooks' --exclude='/.cursor' \
  --exclude='/node_modules' --exclude='/tests' --exclude='/dist' --exclude='/release' \
  --exclude='/storage' --exclude='/public/storage' --exclude='/bootstrap/cache/*.php' \
  --exclude='/.env' --exclude='/.env.*' --exclude='/phpunit.xml' \
  --exclude='/package.json' --exclude='/package-lock.json' --exclude='/bs-config.cjs' \
  --exclude='/scripts/local' --exclude='/scripts/*.ps1' \
  --exclude='/database/seeders/Demo*' \
  --exclude='/.playwright-mcp' \
  "$root/" "$stage/"

# Drop any .env that composer scripts might have created.
rm -f "$stage/.env"

if [[ -n "${GITHUB_SHA:-}" ]]; then
  printf '%s\n' "$GITHUB_SHA" > "$stage/REVISION"
fi

# Sanity: production vendor tree and no secrets file.
if [[ ! -d "$stage/vendor" ]]; then
  echo "error: vendor/ missing; run composer install --no-dev before packaging" >&2
  exit 1
fi
if [[ -e "$stage/.env" ]]; then
  echo "error: .env must not be in the package" >&2
  exit 1
fi
if [[ ! -f "$stage/public/css/easm.css" || ! -f "$stage/public/js/easm.js" ]]; then
  echo "error: built public assets missing" >&2
  exit 1
fi

rm -f "$zip_path"
(
  cd "$stage"
  zip -qr "$zip_path" .
)

echo "PACKAGE=$zip_path"
echo "PACKAGE_NAME=$zip_name"
