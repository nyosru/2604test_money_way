DOCKER_COMPOSE = docker compose
CONTAINER = docker compose

.PHONY: build composer-install test test-filter shell composer-shell

bash:
	$(DOCKER_COMPOSE) run --rm php bash

build:
	$(DOCKER_COMPOSE) build

composer-install:
	$(DOCKER_COMPOSE) run --rm composer install

test:
	$(DOCKER_COMPOSE) run --rm php ./vendor/bin/phpunit

test-filter:
	$(DOCKER_COMPOSE) run --rm php ./vendor/bin/phpunit --filter "$(FILTER)"

shell:
	$(DOCKER_COMPOSE) run --rm php sh

composer-shell:
	$(DOCKER_COMPOSE) run --rm composer sh
