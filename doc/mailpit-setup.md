# Mailpit Quick Setup

Mailpit is already included in the local Docker setup for this project.

## What is already configured

- `compose.override.yaml` starts a `mailer` service with the `axllent/mailpit` image
- `.env.dev` uses `MAILER_DSN=smtp://mailer:1025`
- `.env.test` uses `MAILER_DSN=smtp://mailer:1025`

This means Symfony sends emails to Mailpit automatically in `dev` and `test`.

## Prerequisites (first time)

1. `make hosts-setup` (adds the required hostnames to /etc/hosts)
2. `make up` (will auto-create `.env.dev` from `.env.dist` on first run if missing)

## Start it

From the project root:

```bash
make up
```

That starts the core stack:

- the app container (FrankenPHP)
- the database (MariaDB)
- Mailpit (mailer)
- inbound-smtp bridge
- worker (for Messenger queues)

## Open the Mailpit UI

Because the compose file publishes dynamic host ports, get the mapped web port with:

```bash
# use the same compose command your system supports:
docker compose port mailer 8025
# or
docker-compose port mailer 8025
```

Then open the returned address in your browser.

Example output:

```text
0.0.0.0:32788
```

In that case, open:

```text
http://localhost:32788
```

## SMTP details

Inside Docker, Symfony should use:

```text
smtp://mailer:1025
```

If you need the mapped SMTP port on your host machine, run one of:

```bash
docker compose port mailer 1025
docker-compose port mailer 1025
```

## Quick check

1. Run `make hosts-setup`
2. Run `make up`
3. Trigger any feature that sends an email (e.g. login challenge)
4. Open the Mailpit UI (via the port command above)
5. Confirm the message appears in the inbox

## Useful project note

The test suite already clears Mailpit before running mail-related assertions, and the `make test` target also clears stored messages before PHPUnit starts.
