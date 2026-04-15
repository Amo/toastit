# Toastit production ops

This VPS already uses Apache as the public edge on ports 80/443.
Toastit should follow the same pattern as the existing Dockerized apps:

- Apache terminates TLS for `toastit.cc`
- Apache reverse-proxies to FrankenPHP bound on `127.0.0.1:${APP_BIND_PORT}`
- Docker Compose runs the application, MariaDB, a Messenger worker, and inbound SMTP fully containerized

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

The production env must also define:

- `MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=async`

The `worker` service consumes queued messages from that transport under `supervisord`.

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

## Hardening override

The repository includes an additional production asset:

- `docker-compose.prod.hardening.override.yml`: container security defaults for app/worker/inbound-smtp/database

Use it when starting the app stack:

```bash
docker compose \
  -f ops/docker-compose.prod.yml \
  -f ops/docker-compose.prod.hardening.override.yml \
  --env-file ops/env/.env.prod.toastit.cc \
  up -d
```

This keeps the current architecture (Symfony + FrankenPHP + MariaDB + inbound SMTP) and adds security constraints without creating a parallel deployment path.

## DB migration runbook (shared DB -> dedicated DB on vps2)

Use this when moving Toastit from a shared MariaDB strategy to the dedicated `database` container in `ops/docker-compose.prod.yml`.

1. Prepare maintenance window
   - Announce a short write-freeze window.
   - Ensure latest backup exists for the current shared DB.
   - Ensure `vps2` env points app/worker to `database:3306`:
     - `DATABASE_URL=mysql://<user>:<pass>@database:3306/<db>?serverVersion=11.4.10-MariaDB&charset=utf8mb4`
2. Export source DB from current shared host
   - `mysqldump --single-transaction --routines --triggers --events -h <shared-host> -u <user> -p <db> > toastit-precutover.sql`
3. Bring up dedicated DB container on vps2
   - `docker compose --project-name toastit-prod --env-file ops/env/.env.prod.toastit.cc -f ops/docker-compose.prod.yml up -d database`
   - Wait for healthy status:
     - `docker compose --project-name toastit-prod --env-file ops/env/.env.prod.toastit.cc -f ops/docker-compose.prod.yml ps`
4. Restore dump into dedicated DB
   - `cat toastit-precutover.sql | docker compose --project-name toastit-prod --env-file ops/env/.env.prod.toastit.cc -f ops/docker-compose.prod.yml exec -T database mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"`
5. Cutover app to dedicated DB
   - Deploy the tagged release:
     - `make deploy-prod ENV_FILE=.env.prod.toastit.cc DEPLOY_TAG=vX.Y.Z GHCR_USER=<owner>`
   - Run migrations (already part of `deploy-prod`).
6. Post-cutover verification
   - Check app health and login flow.
   - Create a test toast, vote/boost/update status, and confirm persistence.
   - Check worker processing and inbound SMTP flow.
7. Rollback plan
   - Keep pre-cutover dump and old shared DB access until validation is complete.
   - If critical issue occurs, revert `DATABASE_URL` to shared DB host in prod env and redeploy previous known-good tag.
   - Re-run smoke tests after rollback.

## Scheduled jobs (backup + daily digest)

This repository includes host-level scheduler assets under `ops/scheduler/`:

- `backup-to-gcs.sh`: MariaDB dump + gzip + upload to GCS
- `send-daily-digest.sh`: runs `app:digest:daily` inside the app container
- `systemd/*.service` and `systemd/*.timer`: ready-to-install units
- `scheduler.env.example`: environment file template for host configuration

Recommended installation on the VPS:

1. Copy env template to `/etc/toastit/scheduler.env` and fill values.
   - For `GOOGLE_APPLICATION_CREDENTIALS`, use a host path readable by `gcloud` (for example `/home/debian/toastit/ops/creds/recaptcha-enterprise-sa.json`), not the in-container `/run/creds/...` path.
2. Copy unit files into `/etc/systemd/system/`.
3. Ensure scripts are executable:
   - `chmod +x /home/debian/toastit/ops/scheduler/backup-to-gcs.sh`
   - `chmod +x /home/debian/toastit/ops/scheduler/send-daily-digest.sh`
4. Reload and enable timers:
   - `sudo systemctl daemon-reload`
   - `sudo systemctl enable --now toastit-backup.timer toastit-digest.timer`
5. Validate:
   - `systemctl list-timers | grep toastit-`
   - `journalctl -u toastit-backup.service -n 100 --no-pager`
   - `journalctl -u toastit-digest.service -n 100 --no-pager`
