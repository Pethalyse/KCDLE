#!/usr/bin/env sh
set -e

cd /var/www/html

mkdir -p storage/logs \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache \
         bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    rm -f bootstrap/cache/*.php
    php artisan package:discover --ansi
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan migrate --force --no-interaction --seed
fi

exec "$@"
