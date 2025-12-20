# Makefile pour redmine-mcp
# Usage: make [target]

.PHONY: install-dev install-prod test static-analysis cs-fix cs-check code-quality validate cache-clear clean clean-dev help dev prod docker-build docker-up docker-down docker-rebuild docker-logs deploy

# Default target
.DEFAULT_GOAL := help

dev: ## Setup complet développement
	$(MAKE) install-dev

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

phpstan: ## Analyse statique PHPStan
	vendor/bin/phpstan analyse src

static-analysis: ## Vérification formatage + PHPStan
	$(MAKE) cs-check phpstan

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
	@echo "REDMINE-MCP MAKEFILE"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*?##/ { printf "  %-20s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# Docker
docker-build: ## Build l'image Docker
	docker build -t redmine-mcp .

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
