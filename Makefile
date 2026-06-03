.PHONY: help up down build install shell console test test-unit test-integration migrate fresh logs cs-check

DC  = docker compose
PHP = $(DC) exec php
ARGS ?=

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## Build images, start the stack, install deps, run migrations
	$(DC) up -d --build
	@echo "Waiting for PHP container..."
	@sleep 3
	$(MAKE) install
	$(MAKE) migrate
	@echo ""
	@echo "  Pizza House is up: http://localhost:8080/health"
	@echo ""

down: ## Stop and remove containers
	$(DC) down

build: ## Rebuild images without cache
	$(DC) build --no-cache

install: ## Install Composer dependencies
	$(PHP) composer install --no-interaction --prefer-dist

shell: ## Open a shell in the php container
	$(PHP) bash

console: ## Run Symfony console (use ARGS="cache:clear")
	$(PHP) bin/console $(ARGS)

test: ## Run the full test suite
	$(PHP) vendor/bin/phpunit

test-unit: ## Run only unit tests
	$(PHP) vendor/bin/phpunit --testsuite Unit

test-integration: ## Run only integration tests
	$(PHP) vendor/bin/phpunit --testsuite Integration

migrate: ## Create the database (if missing) and run migrations
	$(PHP) bin/console doctrine:database:create --if-not-exists --no-interaction
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

fresh: ## Drop the database, recreate, run migrations (destructive)
	$(PHP) bin/console doctrine:database:drop --force --if-exists
	$(PHP) bin/console doctrine:database:create
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

logs: ## Tail logs
	$(DC) logs -f

cs-check: ## Quick static checks
	$(PHP) bin/console lint:yaml config
	$(PHP) bin/console lint:container
