#!/usr/bin/env bash
set -euo pipefail

TOASTIT_DIR="${TOASTIT_DIR:-/home/debian/toastit}"
TOASTIT_COMPOSE_FILE="${TOASTIT_COMPOSE_FILE:-ops/docker-compose.prod.yml}"
TOASTIT_ENV_FILE="${TOASTIT_ENV_FILE:-ops/env/.env.prod.toastit.cc}"
TOASTIT_PROJECT="${TOASTIT_PROJECT:-toastit-prod}"
DIGEST_LOCK_FILE="${DIGEST_LOCK_FILE:-/var/lock/toastit-digest.lock}"
DIGEST_DATE="${DIGEST_DATE:-yesterday}"

mkdir -p "$(dirname "${DIGEST_LOCK_FILE}")"

exec 9>"${DIGEST_LOCK_FILE}"
flock -n 9 || {
  echo "Daily digest already running, exiting."
  exit 0
}

cd "${TOASTIT_DIR}"

docker compose \
  --project-name "${TOASTIT_PROJECT}" \
  --env-file "${TOASTIT_ENV_FILE}" \
  -f "${TOASTIT_COMPOSE_FILE}" \
  exec -T app php bin/console app:digest:daily --env=prod --date="${DIGEST_DATE}" --no-interaction

echo "Daily digest completed for ${DIGEST_DATE}."
