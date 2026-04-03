.PHONY: up down logs bash migrate build dev test test-db-prepare send-inbound-test-email

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f

bash:
	docker compose exec php sh -lc 'if command -v bash >/dev/null 2>&1; then exec bash; else exec sh; fi'

migrate:
	docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction

build:
	npm run build

dev:
	npm run dev

test-db-prepare:
	docker compose exec -T database mariadb -uroot -p'!ChangeRootMe!' -e "DROP DATABASE IF EXISTS app_test; CREATE DATABASE app_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON app_test.* TO 'app'@'%'; FLUSH PRIVILEGES;"
	docker compose exec -T php php bin/console doctrine:migrations:migrate --env=test --no-interaction

test: test-db-prepare
	docker compose exec -T php sh -lc 'php -r '\''$$ctx = stream_context_create(["http" => ["method" => "DELETE", "ignore_errors" => true]]); file_get_contents("http://mailer:8025/api/v1/messages", false, $$ctx);'\'''
	docker compose exec -T -e APP_ENV=test -e APP_DEBUG=1 php php bin/phpunit

mail:
	@test -n "$(TO)" || (echo "Usage: make send-inbound-test-email TO='toast+...@inbound.toastit.local' SUBJECT='Hello'" && exit 1)
	@TO="$(TO)" SUBJECT="$(SUBJECT)" BODY="$(BODY)" docker compose exec -T -e TO -e SUBJECT -e BODY inbound-smtp python -c "import os,smtplib; from email.message import EmailMessage; m=EmailMessage(); m['From']='Prototype Sender <sender@example.com>'; m['To']=os.environ['TO']; m['Subject']=os.environ.get('SUBJECT') or 'Toastit inbound prototype'; m.set_content(os.environ.get('BODY') or ''); smtp=smtplib.SMTP('127.0.0.1',2525); smtp.send_message(m); smtp.quit()"
