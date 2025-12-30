# Makefile pour mcp-project-management
# Usage: make [target]

.PHONY: install-dev install-prod test static-analysis cs-fix cs-check code-quality validate cache-clear clean clean-dev help dev prod docker-build docker-up docker-down docker-rebuild docker-logs deploy lint-container lint-twig lint db-migrate db-diff db-status db-reset

# Default target
.DEFAULT_GOAL := help

dev: ## Dev avec Docker (volumes montés, hot reload)
	@if [ ! -f docker-compose.override.yml ]; then \
		cp docker-compose.override.yml.dist docker-compose.override.yml; \
		echo "Created docker-compose.override.yml from template"; \
	fi
	docker compose up

prod: ## Setup production (sans dev deps)
	$(MAKE) install-prod clean-dev

install-dev: ## Installer toutes les dépendances dev
	composer install --prefer-dist --no-progress

install-prod: ## Installer uniquement les deps prod
	composer install --prefer-dist --no-progress --no-dev --optimize-autoloader

test: ## Lancer tous les tests
	vendor/bin/phpunit --testdox

test-coverage: ## Tests avec couverture de code
	vendor/bin/phpunit --testdox --coverage-text --coverage-clover=coverage.xml

# Database
db-migrate: ## Exécuter les migrations
	php bin/console doctrine:migrations:migrate --no-interaction

db-diff: ## Générer une migration depuis les changements d'entités
	php bin/console doctrine:migrations:diff

db-status: ## Voir l'état des migrations
	php bin/console doctrine:migrations:status

db-reset: ## Reset la DB (drop + migrate)
	php bin/console doctrine:database:drop --force --if-exists
	php bin/console doctrine:database:create
	php bin/console doctrine:migrations:migrate --no-interaction

phpstan: ## Analyse statique PHPStan
	vendor/bin/phpstan analyse src

lint-container: ## Vérifier la configuration du container Symfony
	php bin/console lint:container

lint-twig: ## Vérifier les templates Twig
	php bin/console lint:twig templates/

lint: ## Lancer tous les lints (container + twig)
	$(MAKE) lint-container lint-twig

static-analysis: ## Vérification formatage + PHPStan + lints
	$(MAKE) cs-check phpstan lint

cs-fix: ## Corriger le formatage du code
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

cs-check: ## Vérifier le formatage (dry-run)
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff

code-quality: ## Pipeline complet (fix + phpstan + tests)
	$(MAKE) cs-fix phpstan test

validate: ## Valider composer.json
	composer validate --strict

cache-clear: ## Vider le cache Symfony
	php bin/console cache:clear

clean: ## Nettoyer les fichiers temporaires
	rm -rf var/cache/* var/log/* coverage.xml .phpunit.cache

clean-dev: ## Supprimer node_modules
	rm -rf node_modules/ package-lock.json

help: ## Afficher cette aide
	@echo "MCP-PROJECT-MANAGEMENT MAKEFILE"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*?##/ { printf "  %-20s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# Docker
docker-build: ## Build l'image Docker
	docker build -t mcp-project-management .

docker-up: ## Lancer le container
	docker compose up -d

docker-down: ## Arrêter le container
	docker compose down

docker-rebuild: ## Rebuild complet (down + build + up)
	docker compose down
	docker compose build --no-cache
	docker compose up -d

docker-logs: ## Voir les logs du container
	docker compose logs -f

deploy: ## Déployer (rebuild Docker)
	$(MAKE) docker-rebuild
