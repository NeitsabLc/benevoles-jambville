.DEFAULT_GOAL := help

DOCKER_COMPOSE := docker compose
DOCKER_COMPOSE_PROD := docker compose -f compose.yaml -f compose.prod.yaml
PHP := $(DOCKER_COMPOSE) exec php
PHP_RUN := $(DOCKER_COMPOSE) run --rm php
LIQUIBASE := $(DOCKER_COMPOSE) --profile outils run --rm liquibase

.PHONY: help
help: ## Afficher les commandes disponibles
	@awk 'BEGIN {FS = ":.*##"; printf "\nCommandes disponibles :\n\n"} /^[a-zA-Z0-9_-]+:.*?##/ {printf "  %-24s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

.PHONY: install
install: build up composer-install db-update ## Installer le projet

.PHONY: build
build: ## Construire les images Docker
	$(DOCKER_COMPOSE) build

.PHONY: up
up: ## Démarrer l'environnement
	$(DOCKER_COMPOSE) up -d

.PHONY: down
down: ## Arrêter l'environnement
	$(DOCKER_COMPOSE) down

.PHONY: ps
ps: ## Afficher l'état des conteneurs
	$(DOCKER_COMPOSE) ps

.PHONY: prod-config
prod-config: ## Valider et afficher la configuration Compose de production
	$(DOCKER_COMPOSE_PROD) config

.PHONY: prod-up
prod-up: ## Démarrer les services avec la configuration de production
	$(DOCKER_COMPOSE_PROD) up -d

.PHONY: prod-ps
prod-ps: ## Afficher l'état des services de production
	$(DOCKER_COMPOSE_PROD) ps

.PHONY: logs
logs: ## Afficher les journaux
	$(DOCKER_COMPOSE) logs -f --tail=100

.PHONY: logs-php
logs-php: ## Afficher les journaux PHP
	$(DOCKER_COMPOSE) logs -f --tail=100 php

.PHONY: logs-nginx
logs-nginx: ## Afficher les journaux Nginx
	$(DOCKER_COMPOSE) logs -f --tail=100 nginx

.PHONY: logs-database
logs-database: ## Afficher les journaux PostgreSQL
	$(DOCKER_COMPOSE) logs -f --tail=100 database

.PHONY: composer-install
composer-install: ## Installer les dépendances PHP
	$(PHP_RUN) composer install

.PHONY: console
console: ## Exécuter une commande Symfony : make console ARGS="about"
	$(PHP) php bin/console $(ARGS)

.PHONY: db-validate
db-validate: ## Valider les changelogs Liquibase
	$(LIQUIBASE) validate

.PHONY: db-status
db-status: ## Afficher les changesets en attente
	$(LIQUIBASE) status

.PHONY: db-sql
db-sql: ## Afficher le SQL Liquibase sans l'appliquer
	$(LIQUIBASE) update-sql

.PHONY: db-update
db-update: ## Appliquer les changements de base de données
	$(LIQUIBASE) update

.PHONY: db-prepare-roles
db-prepare-roles: ## Créer les rôles limités absents, sans modifier les rôles existants
	$(DOCKER_COMPOSE) exec -T database /docker-entrypoint-initdb.d/10-init-roles.sh

.PHONY: db-verify-role-switch
db-verify-role-switch: ## Vérifier que PHP utilise bien le rôle applicatif limité
	$(PHP) php -r '$$pdo = new PDO(sprintf("pgsql:host=%s;dbname=%s", getenv("DATABASE_HOST"), getenv("DATABASE_NAME")), getenv("DATABASE_USER"), getenv("DATABASE_PASSWORD")); $$role = $$pdo->query("SELECT current_user")->fetchColumn(); if ($$role !== "benevole_jambville_app") { fwrite(STDERR, "Rôle PostgreSQL inattendu: ".$$role.PHP_EOL); exit(1); } echo $$role.PHP_EOL;'

.PHONY: db-sync-role-passwords
db-sync-role-passwords: ## Synchroniser explicitement les mots de passe des rôles limités avec .env
	$(DOCKER_COMPOSE) exec -T database /usr/local/bin/sync-role-passwords

.PHONY: db-finalize-role-hardening
db-finalize-role-hardening: ## Retirer explicitement les attributs élevés après la bascule vérifiée
	$(DOCKER_COMPOSE) exec -T database /usr/local/bin/finalize-role-hardening

.PHONY: db-dev-update
db-dev-update: ## Appliquer les changements et les données de démonstration
	$(LIQUIBASE) update --context-filter=dev

.PHONY: db-shell
db-shell: ## Ouvrir une console PostgreSQL
	$(DOCKER_COMPOSE) exec database psql -U "$${POSTGRES_USER}" -d "$${POSTGRES_DB}"

.PHONY: test-db-reset
test-db-reset: ## Reconstruire la base de test isolée
	$(DOCKER_COMPOSE) exec -T database sh -c 'base_test="$${POSTGRES_DB}_test"; dropdb --if-exists --force --username="$$POSTGRES_USER" "$$base_test" && createdb --username="$$POSTGRES_USER" --owner="$$POSTGRES_USER" "$$base_test"'
	@base_test="$$($(DOCKER_COMPOSE) exec -T database printenv POSTGRES_DB)_test"; \
		$(DOCKER_COMPOSE) --profile outils run --rm -e LIQUIBASE_COMMAND_URL="jdbc:postgresql://database:5432/$$base_test" liquibase update --context-filter=dev

.PHONY: test
test: test-db-reset ## Reconstruire la base de test puis exécuter les tests
	$(PHP) php bin/phpunit

.PHONY: analyse-statique
analyse-statique: ## Analyser le code PHP avec PHPStan
	$(PHP) php bin/console cache:warmup --env=dev
	$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=512M

.PHONY: backup-now
backup-now: ## Créer immédiatement une sauvegarde via le service de production
	$(DOCKER_COMPOSE_PROD) run --rm -e BACKUP_ONCE=1 backup

.PHONY: maintenance-now
maintenance-now: ## Exécuter immédiatement un cycle de maintenance de production
	$(DOCKER_COMPOSE_PROD) run --rm -e MAINTENANCE_ONCE=1 maintenance
