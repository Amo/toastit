#!/bin/sh
set -eu

if [ ! -f composer.lock ]; then
    echo "composer.lock is missing; refusing to start."
    exit 1
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --prefer-dist --no-interaction
fi

mkdir -p var/cache var/log

exec docker-php-entrypoint frankenphp run --config /etc/caddy/Caddyfile
