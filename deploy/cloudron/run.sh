#!/bin/bash
#
# Startup script for the APES Cloudron LAMP app. activate-release.sh installs it as /app/data/run.sh.
#
# The LAMP image's start.sh sources this file under `set -eu`, with readonly variables such as
# `php_version`, before it starts Apache. Everything runs inside a ( ... ) subshell with `set +e`,
# so no failure, `exit` or assignment here can stop start.sh. Long-running work goes to the background.

(
    set +e
    set -u

    APP_DIR=/app/data/easm
    PHP_BIN=php8.3

    as_www_data() {
        if command -v gosu > /dev/null; then
            gosu www-data "$@"
        else
            sudo -E -u www-data "$@"
        fi
    }

    log() {
        echo "[run.sh $(date -u +%H:%M:%S)] $*"
    }

    if [[ ! -f "$APP_DIR/artisan" ]]; then
        log "No EASM release at $APP_DIR yet; run deploy-cloudron.yml first."
        exit 0
    fi

    # Config is never cached, so Cloudron's database and mail variables are read live.
    as_www_data "$PHP_BIN" "$APP_DIR/artisan" config:clear --no-interaction > /dev/null || log "config:clear failed"

    if [[ ! -f /app/data/shared/storage/app/installed ]]; then
        log "EASM is not installed yet; open /install. Queue worker and scheduler not started."
        exit 0
    fi

    supervise() {
        local name="$1"
        shift
        (
            while true; do
                log "Starting $name"
                as_www_data "$PHP_BIN" "$APP_DIR/artisan" "$@"
                log "$name exited with status $?, restarting in 5 seconds"
                sleep 5
            done
        ) &
    }

    supervise "queue worker" queue:work --sleep=3 --tries=3 --max-time=3600 --no-interaction
    supervise "scheduler" schedule:work --no-interaction

    log "Queue worker and scheduler started"
)
