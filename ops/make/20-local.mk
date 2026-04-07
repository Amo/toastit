.PHONY: bash up down logs migrate build dev test test-db-prepare mail
.PHONY: logs-prod bash-prod

bash:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) sh -lc 'if command -v bash >/dev/null 2>&1; then exec bash; else exec sh; fi'

up:
	$(DOCKER_COMPOSE) up -d --build

down:
	$(DOCKER_COMPOSE) down

logs:
	$(DOCKER_COMPOSE) logs -f

migrate:
	$(DOCKER_COMPOSE) exec -T $(APP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction

build:
	npm run build

dev:
	npm run dev

test-db-prepare:
	$(DOCKER_COMPOSE) exec -T database mariadb -uroot -p'!ChangeRootMe!' -e "DROP DATABASE IF EXISTS app_test; CREATE DATABASE app_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON app_test.* TO 'app'@'%'; FLUSH PRIVILEGES;"
	$(DOCKER_COMPOSE) exec -T $(APP_SERVICE) php bin/console doctrine:migrations:migrate --env=test --no-interaction

test: test-db-prepare
	$(DOCKER_COMPOSE) exec -T $(APP_SERVICE) sh -lc 'php -r '\''$$ctx = stream_context_create(["http" => ["method" => "DELETE", "ignore_errors" => true]]); file_get_contents("http://mailer:8025/api/v1/messages", false, $$ctx);'\'''
	$(DOCKER_COMPOSE) exec -T -e APP_ENV=test -e APP_DEBUG=1 $(APP_SERVICE) php bin/phpunit

mail:
	@test -n "$(TO)" || (echo "Usage: make mail TO='toast+...@inbound.toastit.local' SUBJECT='Hello'" && exit 1)
	@TO="$(TO)" SUBJECT="$(SUBJECT)" BODY="$(BODY)" $(DOCKER_COMPOSE) exec -T -e TO -e SUBJECT -e BODY inbound-smtp python -c "import os,smtplib; from email.message import EmailMessage; m=EmailMessage(); m['From']='Prototype Sender <sender@example.com>'; m['To']=os.environ['TO']; m['Subject']=os.environ.get('SUBJECT') or 'Toastit inbound prototype'; m.set_content(os.environ.get('BODY') or ''); smtp=smtplib.SMTP('127.0.0.1',2525); smtp.send_message(m); smtp.quit()"

logs-prod:
	@set -eu; \
	PLAINTEXT_ENV="ops/env/$(ENV_FILE)"; \
	ENCRYPTED_ENV="$(ENCRYPTED_ENV_DIR)/$(ENV_FILE).enc"; \
	TMP_ENV=""; \
	if [ -f "$$PLAINTEXT_ENV" ]; then \
		ENV_TO_SOURCE="$$PLAINTEXT_ENV"; \
	elif [ -f "$$ENCRYPTED_ENV" ]; then \
		TMP_ENV="$$(mktemp)"; \
		trap 'rm -f "$$TMP_ENV"' EXIT; \
		openssl enc -d -$(OPENSSL_CIPHER) -md $(OPENSSL_DIGEST) -pbkdf2 -iter $(OPENSSL_PBKDF2_ITER) \
			$(OPENSSL_PASS_ARGS) \
			-in "$$ENCRYPTED_ENV" \
			-out "$$TMP_ENV"; \
		ENV_TO_SOURCE="$$TMP_ENV"; \
	else \
		echo "Env file not found: $$PLAINTEXT_ENV or $$ENCRYPTED_ENV"; \
		exit 1; \
	fi; \
	set -a; . "$$ENV_TO_SOURCE"; set +a; \
	: "$${DEPLOY_HOST:?Missing DEPLOY_HOST in env file}"; \
	: "$${DEPLOY_PORT:?Missing DEPLOY_PORT in env file}"; \
	: "$${DEPLOY_USER:?Missing DEPLOY_USER in env file}"; \
	: "$${DEPLOY_DIR:?Missing DEPLOY_DIR in env file}"; \
	: "$${DEPLOY_ENV_FILE:?Missing DEPLOY_ENV_FILE in env file}"; \
	: "$${DEPLOY_INSTANCE_NAME:?Missing DEPLOY_INSTANCE_NAME in env file}"; \
	ssh -t -p "$$DEPLOY_PORT" "$$DEPLOY_USER@$$DEPLOY_HOST" "set -euo pipefail; cd '$$DEPLOY_DIR'; sudo docker compose --project-name '$$DEPLOY_INSTANCE_NAME' --env-file 'ops/env/$$DEPLOY_ENV_FILE' -f 'ops/docker-compose.prod.yml' logs -f app"

bash-prod:
	@set -eu; \
	PLAINTEXT_ENV="ops/env/$(ENV_FILE)"; \
	ENCRYPTED_ENV="$(ENCRYPTED_ENV_DIR)/$(ENV_FILE).enc"; \
	TMP_ENV=""; \
	if [ -f "$$PLAINTEXT_ENV" ]; then \
		ENV_TO_SOURCE="$$PLAINTEXT_ENV"; \
	elif [ -f "$$ENCRYPTED_ENV" ]; then \
		TMP_ENV="$$(mktemp)"; \
		trap 'rm -f "$$TMP_ENV"' EXIT; \
		openssl enc -d -$(OPENSSL_CIPHER) -md $(OPENSSL_DIGEST) -pbkdf2 -iter $(OPENSSL_PBKDF2_ITER) \
			$(OPENSSL_PASS_ARGS) \
			-in "$$ENCRYPTED_ENV" \
			-out "$$TMP_ENV"; \
		ENV_TO_SOURCE="$$TMP_ENV"; \
	else \
		echo "Env file not found: $$PLAINTEXT_ENV or $$ENCRYPTED_ENV"; \
		exit 1; \
	fi; \
	set -a; . "$$ENV_TO_SOURCE"; set +a; \
	: "$${DEPLOY_HOST:?Missing DEPLOY_HOST in env file}"; \
	: "$${DEPLOY_PORT:?Missing DEPLOY_PORT in env file}"; \
	: "$${DEPLOY_USER:?Missing DEPLOY_USER in env file}"; \
	: "$${DEPLOY_DIR:?Missing DEPLOY_DIR in env file}"; \
	: "$${DEPLOY_ENV_FILE:?Missing DEPLOY_ENV_FILE in env file}"; \
	: "$${DEPLOY_INSTANCE_NAME:?Missing DEPLOY_INSTANCE_NAME in env file}"; \
	ssh -t -p "$$DEPLOY_PORT" "$$DEPLOY_USER@$$DEPLOY_HOST" "set -euo pipefail; cd '$$DEPLOY_DIR'; sudo docker compose --project-name '$$DEPLOY_INSTANCE_NAME' --env-file 'ops/env/$$DEPLOY_ENV_FILE' -f 'ops/docker-compose.prod.yml' exec app sh -lc 'if command -v bash >/dev/null 2>&1; then exec bash -l; else exec sh -l; fi'"
