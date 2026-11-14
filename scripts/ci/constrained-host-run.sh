#!/usr/bin/env bash
# Apply shared-hosting limits and run the constrained-host PHPUnit matrix.
# Called by the "Constrained host" job in .github/workflows/ci.yml after Composer install.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

HIDE_BIN="$(mktemp -d)"
# Shadow mysqldump so BackupService cannot rely on it even if the runner has it.
if command -v mysqldump >/dev/null 2>&1; then
  echo "Shadowing mysqldump from $(command -v mysqldump)"
fi
cat >"${HIDE_BIN}/mysqldump" <<'EOF'
#!/usr/bin/env bash
echo "mysqldump is not available on this constrained host" >&2
exit 127
EOF
chmod +x "${HIDE_BIN}/mysqldump"
export PATH="${HIDE_BIN}:${PATH}"

# Request budget on shared hosts is 30s / 128M / 8M upload. PHPUnit needs a longer
# process limit so the matrix can finish; ConstrainedHostMatrixTest asserts the
# 30s request budget (ini probe + previous→current update wall clock).
export EASM_CONSTRAINED_HOST=1
export EASM_EXPECT_NO_ZIPARCHIVE=1
export EASM_EXPECT_NO_MYSQLDUMP=1
export EASM_EXPECT_NO_SYMLINK=1
export EASM_REQUEST_MAX_EXECUTION_TIME=30

PHP_ARGS=(
  -d max_execution_time=120
  -d memory_limit=128M
  -d upload_max_filesize=8M
  -d post_max_size=8M
  -d disable_functions=symlink,link
  -d "open_basedir=${ROOT}:/tmp"
)

echo "Constrained PHP probe:"
php "${PHP_ARGS[@]}" -r 'echo "zip=".(extension_loaded("zip")?"yes":"no")." memory=".ini_get("memory_limit")." upload=".ini_get("upload_max_filesize")." disable=".ini_get("disable_functions")." open_basedir=".ini_get("open_basedir").PHP_EOL;'
php -d max_execution_time=30 -r 'echo "request_max_execution_time=".ini_get("max_execution_time").PHP_EOL;'
command -v mysqldump >/dev/null 2>&1 && mysqldump --version 2>&1 | head -1 || echo "mysqldump unavailable (expected)"

# Block outbound network after dependencies are installed (loopback stays open).
if command -v iptables >/dev/null 2>&1; then
  sudo iptables -P OUTPUT DROP || true
  sudo iptables -A OUTPUT -o lo -j ACCEPT || true
  sudo iptables -A OUTPUT -d 127.0.0.0/8 -j ACCEPT || true
fi
if command -v ip6tables >/dev/null 2>&1; then
  sudo ip6tables -P OUTPUT DROP || true
  sudo ip6tables -A OUTPUT -o lo -j ACCEPT || true
fi

php "${PHP_ARGS[@]}" artisan test \
  tests/Feature/ConstrainedHostMatrixTest.php \
  tests/Feature/SharedHostingTest.php
