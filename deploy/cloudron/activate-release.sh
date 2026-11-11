#!/bin/bash
#
# Activates an EASM release on the APES Cloudron LAMP app. deploy-cloudron.yml runs it as root:
#   bash /app/data/releases/<id>/deploy/cloudron/activate-release.sh /app/data/releases/<id>
#
# Layout:
#   /app/data/releases/<id>/   one folder per release (the newest KEEP_RELEASES are kept)
#   /app/data/shared/.env      settings shared by every release; the install wizard writes it
#   /app/data/shared/storage/  uploads, logs, sessions and the install lock
#   /app/data/easm             link to the live release; Apache serves /app/data/easm/public
#
# The first run also pins PHP 8.3 and creates /app/data/shared with a new APP_KEY.
# Exit status 0 means /app/data/easm points at the new release; restart the app next.
# If it fails before the switch, the old release stays live but may be in maintenance
# mode: rollback-release.sh <old release> brings it back.

set -euo pipefail

DATA=/app/data
RELEASES="$DATA/releases"
SHARED="$DATA/shared"
LIVE="$DATA/easm"
PHP_VERSION=8.3
PHP_BIN="php$PHP_VERSION"
KEEP_RELEASES="${KEEP_RELEASES:-5}"

target="$(readlink -f "${1:?Usage: activate-release.sh <release folder>}")"
here="$target/deploy/cloudron"

[[ "$target" == "$RELEASES"/* && -f "$target/artisan" ]] || { echo "Not a release folder under $RELEASES: $target" >&2; exit 1; }
[[ ! -e "$LIVE" || -L "$LIVE" ]] || { echo "$LIVE exists and is not a link. Move it aside first." >&2; exit 1; }
if [[ -L "$LIVE" && "$(readlink -f "$LIVE")" == "$target" ]]; then
    echo "$(basename "$target") is already live; refusing to activate it again." >&2
    exit 1
fi

as_www_data() {
    if command -v gosu > /dev/null; then
        gosu www-data "$@"
    else
        sudo -E -u www-data "$@"
    fi
}

echo "==> PHP $PHP_VERSION"
if ! grep -qx "PHP_VERSION=$PHP_VERSION" "$DATA/PHP_VERSION" 2> /dev/null; then
    printf '; Set by EASM deploy/cloudron/activate-release.sh\nPHP_VERSION=%s\n' "$PHP_VERSION" > "$DATA/PHP_VERSION"
    echo "    Pinned PHP $PHP_VERSION; the restart after this script applies it to Apache"
fi

echo "==> Shared settings and storage"
mkdir -p "$SHARED"/storage/{app/public,app/private,framework/{cache/data,sessions,views},logs}
if [[ ! -f "$SHARED/.env" ]]; then
    cp "$here/env.example" "$SHARED/.env"
    key="base64:$(head -c 32 /dev/urandom | base64 -w0)"
    sed -i "s|^APP_KEY=.*|APP_KEY=${key}|" "$SHARED/.env"
    unset key
    echo "    Created $SHARED/.env with a new APP_KEY (not shown). Copy it into the password manager from the Cloudron File Manager."
fi
chown -R www-data:www-data "$SHARED"
chmod 0640 "$SHARED/.env"

echo "==> Linking $(basename "$target") to the shared settings and storage"
rm -rf "$target/storage"
ln -s "$SHARED/storage" "$target/storage"
ln -sfn "$SHARED/.env" "$target/.env"
mkdir -p "$target/bootstrap/cache"
chown -R www-data:www-data "$target"

artisan() {
    as_www_data "$PHP_BIN" "$target/artisan" "$@" --no-interaction
}

if [[ -L "$LIVE" ]]; then
    echo "==> Maintenance mode on"
    as_www_data "$PHP_BIN" "$LIVE/artisan" down --retry=15 --no-interaction || true
fi

echo "==> Preparing the release"
artisan package:discover
artisan storage:link --force
# Config is never cached: the install wizard writes .env while the app runs.
artisan config:clear
if [[ -f "$SHARED/storage/app/installed" ]]; then
    artisan migrate --force
else
    echo "    Not installed yet: the /install wizard runs the migrations"
fi
artisan route:cache || echo "    route:cache failed; serving without a route cache"
artisan event:cache || echo "    event:cache failed; serving without an event cache"
artisan view:cache || echo "    view:cache failed; views compile on first use"

echo "==> Startup script and Apache site"
install -m 0755 "$here/run.sh" "$DATA/run.sh"
install -m 0644 "$here/apache-app.conf" "$DATA/apache/app.conf"

echo "==> Switching $LIVE to $(basename "$target")"
ln -sfn "$target" "$LIVE.next"
mv -T "$LIVE.next" "$LIVE"

as_www_data "$PHP_BIN" "$LIVE/artisan" queue:restart --no-interaction || true
as_www_data "$PHP_BIN" "$LIVE/artisan" up --no-interaction || true

echo "==> Keeping the newest $KEEP_RELEASES releases"
live="$(readlink -f "$LIVE")"
{
    find "$RELEASES" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -rn | cut -d' ' -f2- \
        | grep -vxF "$live" | tail -n +"$KEEP_RELEASES" \
        | while IFS= read -r old; do rm -rf -- "$old"; done
} || true

echo "Activated $(basename "$target"). Restart the app so Apache and run.sh pick it up."
