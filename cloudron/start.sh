#!/bin/bash
set -euo pipefail

# Nothing is installed or built here: the image already has vendor/ and the
# package manifest. Start-up only prepares /app/data, migrates (idempotent) and
# warms the caches.

mkdir -p /run/apache2 /run/lock/apache2 \
    /app/data/cache \
    /app/data/storage/app/public /app/data/storage/app/private \
    /app/data/storage/framework/cache/data /app/data/storage/framework/sessions \
    /app/data/storage/framework/views /app/data/storage/logs

php /app/code/cloudron/write-env.php

chown -R www-data:www-data /app/data

as_www() {
    setpriv --reuid=www-data --regid=www-data --init-groups "$@"
}

cd /app/code
as_www php artisan migrate --force --no-interaction
as_www php artisan view:cache --no-interaction
as_www php artisan route:cache --no-interaction || echo "route:cache failed; serving without a route cache"
as_www php artisan event:cache --no-interaction || echo "event:cache failed; serving without an event cache"

exec apache2-foreground
