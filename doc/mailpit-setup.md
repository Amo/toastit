# Mailpit Quick Setup

Mailpit is already included in the local Docker setup for this project.

## What is already configured

- `compose.override.yaml` starts a `mailer` service with the `axllent/mailpit` image
- `.env.dev` uses `MAILER_DSN=smtp://mailer:1025`
- `.env.test` uses `MAILER_DSN=smtp://mailer:1025`

This means Symfony sends emails to Mailpit automatically in `dev` and `test`.

## Start it

From the project root:

```bash
make up
```

That starts:

- the app container
- the database
- Mailpit

## Open the Mailpit UI

Because the compose file publishes dynamic host ports, get the mapped web port with:

```bash
docker compose port mailer 8025
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

If you need the mapped SMTP port on your host machine, run:

```bash
docker compose port mailer 1025
```

## Quick check

1. Run `make up`
2. Trigger any feature that sends an email
3. Open the Mailpit UI
4. Confirm the message appears in the inbox

## Useful project note

The test suite already clears Mailpit before running mail-related assertions, and the `make test` target also clears stored messages before PHPUnit starts.
