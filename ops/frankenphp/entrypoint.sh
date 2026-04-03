#!/bin/sh
set -eu

mkdir -p var/cache var/log public/media/profile/avatar public/media/workspace/background
chown -R www-data:www-data var public/media

php bin/console cache:clear --env=prod --no-debug >/dev/null 2>&1 || true

exec docker-php-entrypoint frankenphp run --config /etc/frankenphp/Caddyfile
