#!/bin/sh
set -eu

cd /var/www/html

if [ -n "${APP_KEY:-}" ] && [ "${APP_KEY#base64:}" = "${APP_KEY}" ]; then
    APP_KEY="base64:$(printf '%s' "${APP_KEY}" | openssl base64 -A)"
    export APP_KEY
fi

mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan package:discover --no-ansi --no-interaction >/dev/null 2>&1 || true

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
