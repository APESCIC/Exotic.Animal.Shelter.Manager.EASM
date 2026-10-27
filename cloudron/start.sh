#!/bin/bash
set -euo pipefail

mkdir -p /run/apache2 /run/lock/apache2 \
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

exec apache2-foreground
