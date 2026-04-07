.PHONY: help

help:
	@echo "Toastit Makefile"
	@echo
	@echo "Usage:"
	@echo "  make <target> [VAR=value ...]"
	@echo
	@echo "Local development:"
	@echo "  make up"
	@echo "  make down"
	@echo "  make logs"
	@echo "  make bash"
	@echo "  make migrate"
	@echo "  make build"
	@echo "  make dev"
	@echo "  make test"
	@echo "  make mail TO='toast+...@inbound.toastit.local'"
	@echo
	@echo "Production remote access:"
	@echo "  make bash-prod [ENV_FILE=<name>]"
	@echo "  make logs-prod [ENV_FILE=<name>]"
	@echo
	@echo "Registry and image targets:"
	@echo "  make ghcr-login"
	@echo "  make build-prod TAG=<tag> [GHCR_USER=<owner>]"
	@echo "    Builds and pushes:"
	@echo "      ghcr.io/<owner>/toastit:<tag>"
	@echo "      ghcr.io/<owner>/toastit-inbound-smtp:<tag>"
	@echo
	@echo "Encrypted env management:"
	@echo "  make encrypt-env ENV_FILE=<name> [ENV_PASSPHRASE=...]"
	@echo "  make decrypt-env ENV_FILE=<name> [ENV_PASSPHRASE=...]"
	@echo
	@echo "Deploy:"
	@echo "  make deploy-prod ENV_FILE=<name> DEPLOY_TAG=<tag> [GHCR_USER=<owner>] [ENV_PASSPHRASE=...]"
	@echo "    - decrypts env locally to a temp file"
	@echo "    - builds and pushes the app + inbound SMTP images"
	@echo "    - uploads the temp env file to the VPS"
	@echo "    - pulls images on the VPS"
	@echo "    - recreates the instance from image refs only"
	@echo "    - runs Doctrine migrations in the remote app container"
