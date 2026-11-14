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

# ZipArchive disabled for the matrix (Composer already ran with zip available).
# open_basedir is probed in a subprocess (not on PHPUnit — that hung CI).
# Outbound block is application-level (Http::fake in ConstrainedHostMatrixTest);
# iptables REJECT was tried and hung this job on GitHub-hosted runners.
PHP_ARGS=(
  -d max_execution_time=120
  -d memory_limit=128M
  -d upload_max_filesize=8M
  -d post_max_size=8M
  -d disable_functions=symlink,link
  -d disable_classes=ZipArchive
)

echo "Constrained PHP probe:"
php "${PHP_ARGS[@]}" -r 'echo "ZipArchive_disabled=".(str_contains(strtolower((string)ini_get("disable_classes")),"ziparchive")?"yes":"no")." memory=".ini_get("memory_limit")." upload=".ini_get("upload_max_filesize")." disable_functions=".ini_get("disable_functions").PHP_EOL;'
php -d max_execution_time=30 -r 'echo "request_max_execution_time=".ini_get("max_execution_time").PHP_EOL;'
command -v mysqldump >/dev/null 2>&1 && mysqldump --version 2>&1 | head -1 || echo "mysqldump unavailable (expected)"

echo "open_basedir probe (subprocess):"
php -d "open_basedir=${ROOT}:/tmp" -r '
  $base = getcwd();
  $file = $base."/storage/framework/tmp/open-basedir-ci-probe.txt";
  if (!is_dir(dirname($file))) { mkdir(dirname($file), 0755, true); }
  if (file_put_contents($file, "ok") === false) { fwrite(STDERR, "open_basedir write failed\n"); exit(2); }
  $ok = file_get_contents($file) === "ok";
  @unlink($file);
  echo $ok ? "open_basedir_probe=ok\n" : "open_basedir_probe=bad\n";
  exit($ok ? 0 : 3);
'
export EASM_OPEN_BASEDIR_PROBED=1

echo "Running constrained PHPUnit matrix..."
# Cap wall clock so a hang fails the job instead of waiting for job timeout.
timeout 180 php "${PHP_ARGS[@]}" vendor/bin/phpunit \
  tests/Feature/ConstrainedHostMatrixTest.php \
  tests/Feature/SharedHostingTest.php
