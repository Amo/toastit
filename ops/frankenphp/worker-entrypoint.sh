#!/bin/sh
set -eu

mkdir -p var/cache var/log public/media/profile/avatar public/media/workspace/background
chown -R www-data:www-data var public/media

php bin/console cache:clear --env="${APP_ENV:-prod}" --no-debug >/dev/null 2>&1 || true

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/messenger-worker.conf
