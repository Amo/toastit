DOCKER_COMPOSE := docker compose
DOCKER_COMPOSE_PROD := docker compose -f ops/docker-compose.prod.yml
APP_SERVICE := php
GHCR_USER ?= amo
GHCR_USER_LC := $(shell echo "$(GHCR_USER)" | tr '[:upper:]' '[:lower:]')
GHCR_TOKEN ?=
ENV_FILE ?=
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

OPENSSL_PASS_ARGS :=
ifneq ($(strip $(ENV_PASSPHRASE)),)
OPENSSL_PASS_ARGS := -pass env:ENV_PASSPHRASE
endif

.DEFAULT_GOAL := help
