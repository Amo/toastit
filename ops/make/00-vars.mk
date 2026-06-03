LOCAL_ENV_FILE ?= .env.dev
# Prefer `docker compose` (the Compose plugin subcommand) when available.
# Fall back to `docker-compose` (standalone binary, e.g. Homebrew on macOS without full Docker Desktop plugin registration).
DOCKER_COMPOSE_CMD ?= $(shell docker compose version > /dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
DOCKER_COMPOSE ?= $(DOCKER_COMPOSE_CMD) --env-file $(LOCAL_ENV_FILE)
DOCKER_COMPOSE_PROD ?= docker compose -f ops/docker-compose.prod.yml
APP_SERVICE := php
GHCR_USER ?= amo
GHCR_USER_LC := $(shell echo "$(GHCR_USER)" | tr '[:upper:]' '[:lower:]')
GHCR_TOKEN ?=
ENV_FILE ?= .env.prod.toastit.cc
ENV_SOURCE ?= ops/env/$(ENV_FILE)
ENCRYPTED_ENV_DIR ?= ops/env/encrypted
ENCRYPTED_ENV_PATH ?= $(ENCRYPTED_ENV_DIR)/$(ENV_FILE).enc
OPENSSL_CIPHER ?= aes-256-cbc
OPENSSL_DIGEST ?= sha256
OPENSSL_PBKDF2_ITER ?= 210000
ENV_PASSPHRASE ?=
DEPLOY_TAG ?=
ENV_PATH ?= ops/env/$(ENV_FILE)
REMOTE_GIT_PULL ?= 0
LOCAL_HTTPS_HOST ?= toastit.test
LOCAL_HTTPS_API_HOST ?= api.toastit.test
LOCAL_HTTPS_PORT ?= 443
LOCAL_HTTPS_CERT_FILE ?= .certs/toastit.test.pem
LOCAL_HTTPS_KEY_FILE ?= .certs/toastit.test-key.pem

OPENSSL_PASS_ARGS :=
ifneq ($(strip $(ENV_PASSPHRASE)),)
OPENSSL_PASS_ARGS := -pass env:ENV_PASSPHRASE
endif

.DEFAULT_GOAL := help
