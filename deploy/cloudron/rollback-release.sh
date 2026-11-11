#!/bin/bash
#
# Points /app/data/easm back at an earlier release and takes it out of maintenance mode.
# deploy-cloudron.yml runs it as root after a failed deploy:
#   bash <release>/deploy/cloudron/rollback-release.sh <release>   back to that release
#   bash rollback-release.sh                                        back to the newest release that isn't live
#
# Code only: migrations are not undone. If the newer release ran migrations the older code
# can't handle, restore the pre-deploy Cloudron backup instead.

set -euo pipefail

DATA=/app/data
RELEASES="$DATA/releases"
LIVE="$DATA/easm"
PHP_BIN=php8.3

live=""
[[ -L "$LIVE" ]] && live="$(readlink -f "$LIVE")"

if [[ $# -ge 1 && -n "$1" ]]; then
    target="$(readlink -f "$1")"
else
    target="$(find "$RELEASES" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -rn | cut -d' ' -f2- | grep -vxF "$live" | head -n1 || true)"
fi

[[ -n "$target" && "$target" == "$RELEASES"/* && -f "$target/artisan" ]] || { echo "No release to roll back to." >&2; exit 1; }

as_www_data() {
    if command -v gosu > /dev/null; then
        gosu www-data "$@"
    else
        sudo -E -u www-data "$@"
    fi
}

here="$target/deploy/cloudron"
install -m 0755 "$here/run.sh" "$DATA/run.sh"
install -m 0644 "$here/apache-app.conf" "$DATA/apache/app.conf"

if [[ "$live" == "$target" ]]; then
    echo "==> $(basename "$target") is still live (the deploy failed before the switch)"
else
    echo "==> Rolling back from $(basename "${live:-none}") to $(basename "$target")"
    ln -sfn "$target" "$LIVE.next"
    mv -T "$LIVE.next" "$LIVE"
    as_www_data "$PHP_BIN" "$LIVE/artisan" queue:restart --no-interaction || true
fi

as_www_data "$PHP_BIN" "$LIVE/artisan" up --no-interaction || true
echo "Rolled back to $(basename "$target"). Restart the app."
