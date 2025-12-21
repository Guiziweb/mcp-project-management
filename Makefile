# Makefile pour mcp-project-management
# Usage: make [target]

.PHONY: install-dev install-prod test static-analysis cs-fix cs-check code-quality validate cache-clear clean clean-dev help dev prod docker-build docker-up docker-down docker-rebuild docker-logs deploy lint-container lint-twig lint

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
