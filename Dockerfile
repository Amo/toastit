FROM dunglas/frankenphp:1-php8.5-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends supervisor \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    intl \
    opcache \
    pdo_mysql \
    xdebug \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile
COPY docker/frankenphp/docker-entrypoint.sh /usr/local/bin/app-entrypoint
COPY ops/frankenphp/worker-entrypoint.sh /usr/local/bin/worker-entrypoint
COPY ops/supervisor/messenger-worker.conf /etc/supervisor/conf.d/messenger-worker.conf
COPY docker/frankenphp/php/conf.d/zz-uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY docker/frankenphp/php/conf.d/zz-xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

RUN chmod +x /usr/local/bin/worker-entrypoint

ENTRYPOINT ["app-entrypoint"]
