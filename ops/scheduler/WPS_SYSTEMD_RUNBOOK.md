# WPS Scheduler Runbook (systemd)

This runbook configures two host-level scheduled tasks on WPS:

1. Daily digest (`toastit-digest.timer`)
2. Daily MariaDB backup to GCS (`toastit-backup.timer`)

## 1) Prepare scheduler environment

```bash
sudo mkdir -p /etc/toastit
sudo cp /home/debian/toastit/ops/scheduler/scheduler.env.example /etc/toastit/scheduler.env
sudo chmod 640 /etc/toastit/scheduler.env
```

Edit `/etc/toastit/scheduler.env` and set:

- `TOASTIT_DIR=/home/debian/toastit`
- `TOASTIT_COMPOSE_FILE=ops/docker-compose.prod.yml`
- `TOASTIT_ENV_FILE=ops/env/.env.prod.toastit.cc`
- `TOASTIT_PROJECT=toastit-prod`
- `GCS_BUCKET=<your-bucket>`
- `GCS_PREFIX=db`
- `GCP_PROJECT=<your-project>`
- `GOOGLE_APPLICATION_CREDENTIALS=/home/debian/toastit/ops/creds/recaptcha-enterprise-sa.json`

Important: `GOOGLE_APPLICATION_CREDENTIALS` must be a **host** path readable by `gcloud`.

## 2) Validate prerequisites

```bash
gcloud --version
gcloud storage ls gs://<your-bucket>
chmod +x /home/debian/toastit/ops/scheduler/send-daily-digest.sh
chmod +x /home/debian/toastit/ops/scheduler/backup-to-gcs.sh
```

## 3) Install systemd units

```bash
sudo cp /home/debian/toastit/ops/scheduler/systemd/toastit-digest.service /etc/systemd/system/
sudo cp /home/debian/toastit/ops/scheduler/systemd/toastit-digest.timer /etc/systemd/system/
sudo cp /home/debian/toastit/ops/scheduler/systemd/toastit-backup.service /etc/systemd/system/
sudo cp /home/debian/toastit/ops/scheduler/systemd/toastit-backup.timer /etc/systemd/system/
sudo systemctl daemon-reload
```

## 4) Enable and start timers

```bash
sudo systemctl enable --now toastit-digest.timer toastit-backup.timer
systemctl status toastit-digest.timer --no-pager
systemctl status toastit-backup.timer --no-pager
systemctl list-timers --all | grep -E 'toastit-(digest|backup)'
```

Default schedules:

- Digest: `07:15` daily (`RandomizedDelaySec=120`)
- Backup: `03:10` daily (`RandomizedDelaySec=180`)

## 5) End-to-end manual test

```bash
sudo systemctl start toastit-digest.service
journalctl -u toastit-digest.service -n 100 --no-pager

sudo systemctl start toastit-backup.service
journalctl -u toastit-backup.service -n 100 --no-pager

ls -lh /var/backups/toastit | tail -n 10
gcloud storage ls gs://<your-bucket>/db/$(date -u +%Y)/$(date -u +%m)/$(date -u +%d)/
```

## 6) Acceptance checklist

- Both timers are active and scheduled.
- Manual digest service run succeeds.
- Manual backup service run succeeds.
- Backup artifacts exist locally (`.sql.gz`, `.sha256`) and on GCS.
- No recurring auth, compose, or timeout errors in journals.

## 7) Troubleshooting

- GCP auth error: check `GOOGLE_APPLICATION_CREDENTIALS` path and file permissions.
- GCS access error: verify Storage IAM roles on the service account.
- Compose error: verify `TOASTIT_ENV_FILE` and project name.
- Concurrency: lock files are expected at:
  - `/var/lock/toastit-digest.lock`
  - `/var/lock/toastit-backup.lock`
