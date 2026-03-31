.PHONY: up down logs bash migrate test test-db-prepare

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

test-db-prepare:
	docker compose exec -T database mariadb -uroot -p'!ChangeRootMe!' -e "DROP DATABASE IF EXISTS app_test; CREATE DATABASE app_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON app_test.* TO 'app'@'%'; FLUSH PRIVILEGES;"
	docker compose exec -T php php bin/console doctrine:migrations:migrate --env=test --no-interaction

test: test-db-prepare
	rm -f var/storage/mails/*.json
	docker compose exec -T -e APP_ENV=test -e APP_DEBUG=1 php php bin/phpunit
