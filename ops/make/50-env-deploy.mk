.PHONY: encrypt-env decrypt-env deploy-prod

encrypt-env:
	@if [ -z "$(ENV_FILE)" ]; then \
		echo "ENV_FILE is required."; \
		exit 1; \
	fi
	@if [ ! -f "$(ENV_SOURCE)" ]; then \
		echo "Plaintext env file not found: $(ENV_SOURCE)"; \
		exit 1; \
	fi
	@mkdir -p "$(ENCRYPTED_ENV_DIR)"
	@openssl enc -$(OPENSSL_CIPHER) -md $(OPENSSL_DIGEST) -pbkdf2 -iter $(OPENSSL_PBKDF2_ITER) -salt \
		$(OPENSSL_PASS_ARGS) \
		-in "$(ENV_SOURCE)" \
		-out "$(ENCRYPTED_ENV_PATH)"
	@echo "Encrypted env file written to $(ENCRYPTED_ENV_PATH)"

decrypt-env:
	@if [ -z "$(ENV_FILE)" ]; then \
		echo "ENV_FILE is required."; \
		exit 1; \
	fi
	@if [ ! -f "$(ENCRYPTED_ENV_PATH)" ]; then \
		echo "Encrypted env file not found: $(ENCRYPTED_ENV_PATH)"; \
		exit 1; \
	fi
	@mkdir -p ops/env
	@openssl enc -d -$(OPENSSL_CIPHER) -md $(OPENSSL_DIGEST) -pbkdf2 -iter $(OPENSSL_PBKDF2_ITER) \
		$(OPENSSL_PASS_ARGS) \
		-in "$(ENCRYPTED_ENV_PATH)" \
		-out "$(ENV_SOURCE)"
	@chmod 600 "$(ENV_SOURCE)"
	@echo "Decrypted env file written to $(ENV_SOURCE)"

deploy-prod:
	@set -eu; \
	if [ -z "$(ENV_FILE)" ]; then \
		echo "ENV_FILE is required."; \
		exit 1; \
	fi; \
	ENCRYPTED_ENV_PATH="$(ENCRYPTED_ENV_DIR)/$(ENV_FILE).enc"; \
	if [ ! -f "$$ENCRYPTED_ENV_PATH" ]; then \
		echo "Encrypted env file not found: $$ENCRYPTED_ENV_PATH"; \
		exit 1; \
	fi; \
	DEPLOY_TAG_EFFECTIVE="$(DEPLOY_TAG)"; \
	if [ -z "$$DEPLOY_TAG_EFFECTIVE" ]; then \
		if [ -f VERSION ]; then \
			DEPLOY_TAG_EFFECTIVE="$$(tr -d '[:space:]' < VERSION)"; \
			if [ -n "$$DEPLOY_TAG_EFFECTIVE" ] && [ "$${DEPLOY_TAG_EFFECTIVE#v}" = "$$DEPLOY_TAG_EFFECTIVE" ]; then \
				DEPLOY_TAG_EFFECTIVE="v$$DEPLOY_TAG_EFFECTIVE"; \
			fi; \
		fi; \
	fi; \
	if [ -z "$$DEPLOY_TAG_EFFECTIVE" ]; then \
		echo "DEPLOY_TAG is required when VERSION is missing."; \
		exit 1; \
	fi; \
	if ! git rev-parse -q --verify "refs/tags/$$DEPLOY_TAG_EFFECTIVE" >/dev/null; then \
		echo "Git tag '$$DEPLOY_TAG_EFFECTIVE' not found locally."; \
		exit 1; \
	fi; \
	TMP_ENV="$$(mktemp)"; \
	trap 'rm -f "$$TMP_ENV"' EXIT; \
	openssl enc -d -$(OPENSSL_CIPHER) -md $(OPENSSL_DIGEST) -pbkdf2 -iter $(OPENSSL_PBKDF2_ITER) \
		$(OPENSSL_PASS_ARGS) \
		-in "$$ENCRYPTED_ENV_PATH" \
		-out "$$TMP_ENV"; \
	set -a; . "$$TMP_ENV"; set +a; \
	: "$${DEPLOY_HOST:?Missing DEPLOY_HOST in env file}"; \
	: "$${DEPLOY_PORT:?Missing DEPLOY_PORT in env file}"; \
	: "$${DEPLOY_USER:?Missing DEPLOY_USER in env file}"; \
	: "$${DEPLOY_DIR:?Missing DEPLOY_DIR in env file}"; \
	: "$${DEPLOY_ENV_FILE:?Missing DEPLOY_ENV_FILE in env file}"; \
	: "$${DEPLOY_INSTANCE_NAME:?Missing DEPLOY_INSTANCE_NAME in env file}"; \
	OWNER="$${DEPLOY_GHCR_USER:-$(GHCR_USER)}"; \
	OWNER_LC="$$(echo "$$OWNER" | tr '[:upper:]' '[:lower:]')"; \
	APP_IMAGE_REF="ghcr.io/$$OWNER_LC/toastit:$$DEPLOY_TAG_EFFECTIVE"; \
	INBOUND_IMAGE_REF="ghcr.io/$$OWNER_LC/toastit-inbound-smtp:$$DEPLOY_TAG_EFFECTIVE"; \
	$(MAKE) build-prod TAG="$$DEPLOY_TAG_EFFECTIVE" GHCR_USER="$$OWNER"; \
	ssh -p "$$DEPLOY_PORT" "$$DEPLOY_USER@$$DEPLOY_HOST" "mkdir -p '$$DEPLOY_DIR/ops/env'"; \
	scp -P "$$DEPLOY_PORT" "$$TMP_ENV" "$$DEPLOY_USER@$$DEPLOY_HOST:$$DEPLOY_DIR/ops/env/$$DEPLOY_ENV_FILE"; \
	ssh -p "$$DEPLOY_PORT" "$$DEPLOY_USER@$$DEPLOY_HOST" "set -euo pipefail; cd '$$DEPLOY_DIR'; chmod 600 \"ops/env/$$DEPLOY_ENV_FILE\"; sudo docker pull \"$$APP_IMAGE_REF\"; sudo docker pull \"$$INBOUND_IMAGE_REF\"; sudo docker rm -f \"$$DEPLOY_INSTANCE_NAME-app-1\" \"$$DEPLOY_INSTANCE_NAME-worker-1\" \"$$DEPLOY_INSTANCE_NAME-inbound-smtp-1\" \"$$DEPLOY_INSTANCE_NAME-database-1\" >/dev/null 2>&1 || true; sudo APP_IMAGE=\"$$APP_IMAGE_REF\" INBOUND_SMTP_IMAGE=\"$$INBOUND_IMAGE_REF\" docker compose --project-name \"$$DEPLOY_INSTANCE_NAME\" --env-file \"ops/env/$$DEPLOY_ENV_FILE\" -f \"ops/docker-compose.prod.yml\" up -d --force-recreate --remove-orphans; sudo APP_IMAGE=\"$$APP_IMAGE_REF\" INBOUND_SMTP_IMAGE=\"$$INBOUND_IMAGE_REF\" docker compose --project-name \"$$DEPLOY_INSTANCE_NAME\" --env-file \"ops/env/$$DEPLOY_ENV_FILE\" -f \"ops/docker-compose.prod.yml\" exec -T app php bin/console doctrine:migrations:migrate -n; sudo APP_IMAGE=\"$$APP_IMAGE_REF\" INBOUND_SMTP_IMAGE=\"$$INBOUND_IMAGE_REF\" docker compose --project-name \"$$DEPLOY_INSTANCE_NAME\" --env-file \"ops/env/$$DEPLOY_ENV_FILE\" -f \"ops/docker-compose.prod.yml\" ps"
