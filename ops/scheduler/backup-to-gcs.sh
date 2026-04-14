#!/usr/bin/env bash
set -euo pipefail

TOASTIT_DIR="${TOASTIT_DIR:-/home/debian/toastit}"
TOASTIT_COMPOSE_FILE="${TOASTIT_COMPOSE_FILE:-ops/docker-compose.prod.yml}"
TOASTIT_ENV_FILE="${TOASTIT_ENV_FILE:-ops/env/.env.prod.toastit.cc}"
TOASTIT_PROJECT="${TOASTIT_PROJECT:-toastit-prod}"
BACKUP_LOCAL_DIR="${BACKUP_LOCAL_DIR:-/var/backups/toastit}"
BACKUP_LOCK_FILE="${BACKUP_LOCK_FILE:-/var/lock/toastit-backup.lock}"
GCS_BUCKET="${GCS_BUCKET:?GCS_BUCKET is required}"
GCS_PREFIX="${GCS_PREFIX:-db}"
LOCAL_RETENTION_DAYS="${LOCAL_RETENTION_DAYS:-14}"

if ! command -v gcloud >/dev/null 2>&1; then
  echo "gcloud command not found." >&2
  exit 1
fi

mkdir -p "${BACKUP_LOCAL_DIR}"
mkdir -p "$(dirname "${BACKUP_LOCK_FILE}")"

exec 9>"${BACKUP_LOCK_FILE}"
flock -n 9 || {
  echo "Backup already running, exiting."
  exit 0
}

if [[ -n "${GOOGLE_APPLICATION_CREDENTIALS:-}" ]]; then
  export GOOGLE_APPLICATION_CREDENTIALS
fi

if [[ -n "${GCP_PROJECT:-}" ]]; then
  gcloud config set project "${GCP_PROJECT}" >/dev/null
fi

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
year="$(date -u +%Y)"
month="$(date -u +%m)"
day="$(date -u +%d)"
base_name="toastit-db-${timestamp}"
dump_path="${BACKUP_LOCAL_DIR}/${base_name}.sql.gz"
sha_path="${BACKUP_LOCAL_DIR}/${base_name}.sha256"

cd "${TOASTIT_DIR}"

docker compose \
  --project-name "${TOASTIT_PROJECT}" \
  --env-file "${TOASTIT_ENV_FILE}" \
  -f "${TOASTIT_COMPOSE_FILE}" \
  exec -T database sh -lc \
  'mariadb-dump -u"${MARIADB_USER}" -p"${MARIADB_PASSWORD}" --single-transaction --quick --lock-tables=false "${MARIADB_DATABASE}"' \
  | gzip -c > "${dump_path}"

sha256sum "${dump_path}" > "${sha_path}"

remote_prefix="gs://${GCS_BUCKET%/}/${GCS_PREFIX%/}/${year}/${month}/${day}"
gcloud storage cp "${dump_path}" "${remote_prefix}/"
gcloud storage cp "${sha_path}" "${remote_prefix}/"

find "${BACKUP_LOCAL_DIR}" -type f -name 'toastit-db-*.sql.gz' -mtime "+${LOCAL_RETENTION_DAYS}" -delete
find "${BACKUP_LOCAL_DIR}" -type f -name 'toastit-db-*.sha256' -mtime "+${LOCAL_RETENTION_DAYS}" -delete

echo "Backup uploaded to ${remote_prefix}/${base_name}.sql.gz"
