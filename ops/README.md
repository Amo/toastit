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

## Recommended deploy model

Use the `EventPlanning` pattern:

- build and push immutable images from a git tag
- upload a temporary decrypted env file to the VPS
- pull images on the VPS
- recreate the Compose project from image refs only

Main targets:

```bash
make ghcr-login GHCR_USER=<owner> GHCR_TOKEN=<token>
make build-prod TAG=v0.1.0 GHCR_USER=<owner>
make encrypt-env ENV_FILE=.env.prod.toastit.cc
make deploy-prod ENV_FILE=.env.prod.toastit.cc DEPLOY_TAG=v0.1.0 GHCR_USER=<owner>
```

The deploy flow builds and pushes both runtime images:

- `ghcr.io/<owner>/toastit:<tag>`
- `ghcr.io/<owner>/toastit-inbound-smtp:<tag>`

Those image refs are injected by `make deploy-prod` at deploy time.
They should not be stored in the env file, because the tag changes on each deploy.

The VPS should keep a checked-out repo only for the Compose and Apache files, not as a remote build workspace.

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
