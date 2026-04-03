# Toastit production ops

This VPS already uses Apache as the public edge on ports 80/443.
Toastit should follow the same pattern as the existing Dockerized apps:

- Apache terminates TLS for `toastit.cc`
- Apache reverse-proxies to FrankenPHP bound on `127.0.0.1:${APP_BIND_PORT}`
- Docker Compose runs the application, MariaDB, and inbound SMTP fully containerized

## Files

- `docker-compose.prod.yml`: production stack
- `Dockerfile.frankenphp`: production image
- `frankenphp/Caddyfile`: internal app web server config
- `frankenphp/entrypoint.sh`: runtime bootstrap
- `env/.env.prod.toastit.cc.dist`: env template
- `apache/toastit.cc.conf`: HTTP vhost redirect
- `apache/toastit.cc-le-ssl.conf`: HTTPS reverse-proxy vhost

## Suggested VPS layout

- app root: `/home/debian/toastit`
- compose file: `/home/debian/toastit/ops/docker-compose.prod.yml`
- env file: `/home/debian/toastit/ops/env/.env.prod.toastit.cc`

## First deploy

1. Copy the repository to `/home/debian/toastit`
2. Copy `ops/env/.env.prod.toastit.cc.dist` to `ops/env/.env.prod.toastit.cc` and fill real values
3. Build and start:

```bash
cd /home/debian/toastit/ops
sudo docker-compose --env-file env/.env.prod.toastit.cc -f docker-compose.prod.yml up -d --build
```

4. Run migrations:

```bash
cd /home/debian/toastit
sudo docker-compose --env-file ops/env/.env.prod.toastit.cc -f ops/docker-compose.prod.yml exec -T app php bin/console doctrine:migrations:migrate -n
```

## Apache setup on this VPS

Enable modules if needed:

```bash
sudo a2enmod proxy proxy_http headers rewrite ssl
```

Install the vhosts from `ops/apache/` into `/etc/apache2/sites-available/`, enable them, then reload Apache.

Toastit will be proxied to `127.0.0.1:8084` by default.

## Notes

- Outbound mail must use a real SMTP provider in production.
- Inbound SMTP is exposed on `${INBOUND_SMTP_BIND_PORT}`. Point MX or mail forwarding there only if you actually want the VPS to receive mail directly.
- If direct MX delivery is not desired, keep the inbound app flow and use an external mail provider to forward mail to the VPS or to the HTTP endpoint.
