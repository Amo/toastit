# TODO

## Deployment findings for `toastit.cc`

- The app is prepared for a fully containerized production deployment.
- A production ops bundle has been added under [ops/](/Users/amaury/code/toastit/ops):
  - [ops/Dockerfile.frankenphp](/Users/amaury/code/toastit/ops/Dockerfile.frankenphp)
  - [ops/docker-compose.prod.yml](/Users/amaury/code/toastit/ops/docker-compose.prod.yml)
  - [ops/frankenphp/Caddyfile](/Users/amaury/code/toastit/ops/frankenphp/Caddyfile)
  - [ops/frankenphp/entrypoint.sh](/Users/amaury/code/toastit/ops/frankenphp/entrypoint.sh)
  - [ops/env/.env.prod.toastit.cc.dist](/Users/amaury/code/toastit/ops/env/.env.prod.toastit.cc.dist)
  - [ops/apache/toastit.cc.conf](/Users/amaury/code/toastit/ops/apache/toastit.cc.conf)
  - [ops/apache/toastit.cc-le-ssl.conf](/Users/amaury/code/toastit/ops/apache/toastit.cc-le-ssl.conf)
  - [ops/README.md](/Users/amaury/code/toastit/ops/README.md)

- Intended production topology:
  - Apache remains the public edge on ports `80` / `443`
  - Apache reverse-proxies to Toastit on `127.0.0.1:8084`
  - Docker Compose runs:
    - `app` (FrankenPHP)
    - `database` (MariaDB)
    - `inbound-smtp`

## VPS findings

- Host reached via `ssh debian@vps -p 55055`
- Hostname: `vps-cee776a7`
- Apache is already serving public traffic on `:80` and `:443`
- Apache modules needed for reverse proxy are already enabled:
  - `headers`
  - `proxy`
  - `proxy_http`
  - `proxy_wstunnel`
  - `rewrite`
  - `ssl`
- Existing deployment style on the VPS already matches the intended Toastit setup:
  - Apache at the edge
  - app container bound to localhost
  - reverse proxy to `127.0.0.1:<port>`
- `toastit.cc` certificate is currently missing:
  - `/etc/letsencrypt/live/toastit.cc` does not exist

## Docker findings

- Current VPS Docker stack is old:
  - Docker Engine: `20.10.5`
  - `docker-compose` v1: `1.25.0`
- This is usable, but not ideal for a new deployment.
- Recommended before deploying Toastit:
  - upgrade Docker Engine
  - install the Docker Compose plugin (`docker compose`)

## Next steps

1. Upgrade Docker Engine and install modern Compose on the VPS.
2. Create the app directory on the VPS, for example `/home/debian/toastit`.
3. Copy the repository there.
4. Create the real production env file from [ops/env/.env.prod.toastit.cc.dist](/Users/amaury/code/toastit/ops/env/.env.prod.toastit.cc.dist).
5. Install the Apache vhost files from [ops/apache/](/Users/amaury/code/toastit/ops/apache).
6. Issue the Let’s Encrypt certificate for `toastit.cc`.
7. Start the production stack with the production compose file.
8. Run Doctrine migrations inside the app container.
9. Verify:
  - `https://toastit.cc`
  - database connectivity
  - outbound mail
  - inbound SMTP flow
