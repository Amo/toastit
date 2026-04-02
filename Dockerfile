FROM dunglas/frankenphp:1-php8.5-bookworm

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
COPY docker/frankenphp/php/conf.d/zz-xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

ENTRYPOINT ["app-entrypoint"]
