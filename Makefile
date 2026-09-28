.DEFAULT_GOAL := help

DOCKER_COMPOSE := docker compose
DOCKER_COMPOSE_PROD := $(DOCKER_COMPOSE) -f compose.yaml -f compose.prod.yaml
RELEASE_ENV ?= .env.release
DOCKER_COMPOSE_RELEASE := $(DOCKER_COMPOSE) --env-file .env --env-file $(RELEASE_ENV) -f compose.yaml -f compose.prod.yaml -f compose.release.yaml
PHP := $(DOCKER_COMPOSE) exec php
PHP_RUN := $(DOCKER_COMPOSE) run --rm php
LIQUIBASE := $(DOCKER_COMPOSE) --profile outils run --rm liquibase

.PHONY: help install build up down ps \
	prod-config prod-up prod-ps \
	release-config release-verify release-pull release-backup-now release-db-status release-db-update release-up release-ps release-maintenance-now \
	logs composer-install console \
	db-validate db-status db-sql db-update db-prepare-roles db-verify-role-switch db-sync-role-passwords db-finalize-role-hardening db-dev-update db-shell \
	test-db-reset test analyse-statique style style-fix assets-compile test-accessibility test-e2e test-browser \
	backup-now backup-restore-test maintenance-now

help: ## Afficher les commandes disponibles
	@awk 'BEGIN {FS = ":.*##"; printf "\nCommandes disponibles :\n\n"} /^[a-zA-Z0-9_-]+:.*?##/ {printf "  %-24s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: build up composer-install db-update ## Installer le projet

build: ## Construire les images Docker
	$(DOCKER_COMPOSE) build

up: ## Démarrer l'environnement
	$(DOCKER_COMPOSE) up -d

down: ## Arrêter l'environnement
	$(DOCKER_COMPOSE) down

ps: ## Afficher l'état des conteneurs
	$(DOCKER_COMPOSE) ps

prod-config: ## Valider silencieusement la configuration Compose de production
	@$(DOCKER_COMPOSE_PROD) config --quiet

prod-up: ## Démarrer les services avec la configuration de production
	$(DOCKER_COMPOSE_PROD) up -d

prod-ps: ## Afficher l'état des services de production
	$(DOCKER_COMPOSE_PROD) ps

release-config: ## Valider la configuration de livraison utilisant GHCR
	@$(DOCKER_COMPOSE_RELEASE) config --quiet

release-verify: ## Vérifier les digests et signatures Sigstore
	@set -a; . ./$(RELEASE_ENV); set +a; ./scripts/verify-release-images.sh

release-pull: release-config release-verify ## Télécharger manuellement les cinq images vérifiées
	$(DOCKER_COMPOSE_RELEASE) --profile outils --profile backup pull php nginx database liquibase backup

release-backup-now: release-pull ## Sauvegarder ponctuellement la base avant une livraison par images
	$(DOCKER_COMPOSE_RELEASE) --profile backup run --rm --no-deps backup

release-db-status: release-pull ## Contrôler les migrations avec l'image Liquibase livrée
	$(DOCKER_COMPOSE_RELEASE) --profile outils run --rm liquibase status

release-db-update: release-pull ## Appliquer les migrations avec l'image Liquibase livrée
	$(DOCKER_COMPOSE_RELEASE) --profile outils run --rm liquibase update

release-up: release-pull ## Démarrer manuellement les services persistants depuis les images GHCR
	$(DOCKER_COMPOSE_RELEASE) up -d --no-build --wait --wait-timeout 120 database php nginx

release-ps: ## Afficher l'état des conteneurs issus des images GHCR
	$(DOCKER_COMPOSE_RELEASE) ps

release-maintenance-now: release-pull ## Exécuter un cycle de maintenance avec l'image PHP livrée
	$(DOCKER_COMPOSE_RELEASE) --profile maintenance run --rm maintenance

logs: ## Afficher les journaux : make logs SERVICE=php
	$(DOCKER_COMPOSE) logs -f --tail=100 $(SERVICE)

composer-install: ## Installer les dépendances PHP
	$(PHP_RUN) composer install

console: ## Exécuter une commande Symfony : make console ARGS="about"
	$(PHP) php bin/console $(ARGS)

db-validate: ## Valider les changelogs Liquibase
	$(LIQUIBASE) validate

db-status: ## Afficher les changesets en attente
	$(LIQUIBASE) status

db-sql: ## Afficher le SQL Liquibase sans l'appliquer
	$(LIQUIBASE) update-sql

db-update: ## Appliquer les changements de base de données
	$(LIQUIBASE) update

db-prepare-roles: ## Créer les rôles limités absents, sans modifier les rôles existants
	$(DOCKER_COMPOSE) exec -T database /docker-entrypoint-initdb.d/10-init-roles.sh

db-verify-role-switch: ## Vérifier que PHP utilise bien le rôle applicatif limité
	$(PHP) php -r '$$pdo = new PDO(sprintf("pgsql:host=%s;dbname=%s", getenv("DATABASE_HOST"), getenv("DATABASE_NAME")), getenv("DATABASE_USER"), getenv("DATABASE_PASSWORD")); $$role = $$pdo->query("SELECT current_user")->fetchColumn(); if ($$role !== "benevole_jambville_app") { fwrite(STDERR, "Rôle PostgreSQL inattendu: ".$$role.PHP_EOL); exit(1); } echo $$role.PHP_EOL;'

db-sync-role-passwords: ## Synchroniser explicitement les mots de passe des rôles limités avec .env
	$(DOCKER_COMPOSE) exec -T database /usr/local/bin/sync-role-passwords

db-finalize-role-hardening: ## Transférer les objets au rôle migrateur et vérifier les rôles limités
	$(DOCKER_COMPOSE) exec -T database /usr/local/bin/finalize-role-hardening

db-dev-update: ## Appliquer les changements et les données de démonstration
	$(LIQUIBASE) update --context-filter=dev

db-shell: ## Ouvrir une console PostgreSQL
	$(DOCKER_COMPOSE) exec database psql -U "$${POSTGRES_USER}" -d "$${POSTGRES_DB}"

test-db-reset: db-prepare-roles ## Reconstruire la base de test isolée après création des rôles dédiés manquants
	$(DOCKER_COMPOSE) exec -T database sh -c 'base_test="$${POSTGRES_DB}_test"; dropdb --if-exists --force --username="$$POSTGRES_USER" "$$base_test" && createdb --username="$$POSTGRES_USER" --owner="$$POSTGRES_USER" "$$base_test"'
	@base_test="$$($(DOCKER_COMPOSE) exec -T database printenv POSTGRES_DB)_test"; \
		$(DOCKER_COMPOSE) --profile outils run --rm -e LIQUIBASE_COMMAND_URL="jdbc:postgresql://database:5432/$$base_test" liquibase update --context-filter=dev

test: test-db-reset ## Reconstruire la base de test puis exécuter les tests
	$(PHP) php bin/phpunit

analyse-statique: ## Analyser le code PHP avec PHPStan
	$(PHP) php bin/console cache:warmup --env=dev
	$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=512M

style: ## Vérifier le style du code PHP
	$(PHP) composer lint:php

style-fix: ## Corriger automatiquement le style du code PHP
	$(PHP) composer fix:php

assets-compile: ## Reconstruire proprement les assets de production
	$(PHP) php bin/console cache:clear --env=prod --no-debug
	$(PHP) php bin/console importmap:install --env=prod --no-debug
	$(PHP) php bin/console asset-map:compile --env=prod --no-debug

test-accessibility: db-dev-update assets-compile ## Auditer l'accessibilité des pages sur les données de démonstration
	npm run test:accessibility

test-e2e: db-dev-update assets-compile ## Exécuter les parcours métier Playwright sur Chromium, Firefox et mobile
	npm run test:e2e

test-browser: db-dev-update assets-compile ## Exécuter tous les contrôles navigateur
	npm run test:browser

backup-now: ## Créer immédiatement une sauvegarde ponctuelle
	$(DOCKER_COMPOSE_PROD) --profile backup run --rm backup

backup-restore-test: ## Chiffrer puis restaurer une sauvegarde dans une base temporaire
	./scripts/ci-backup-restore.sh

maintenance-now: ## Exécuter immédiatement un cycle de maintenance de production
	$(DOCKER_COMPOSE_PROD) --profile maintenance run --rm maintenance
