.DEFAULT_GOAL := help

DOCKER_COMPOSE := docker compose
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

.PHONY: db-shell
db-shell: ## Ouvrir une console PostgreSQL
	$(DOCKER_COMPOSE) exec database psql -U "$${POSTGRES_USER}" -d "$${POSTGRES_DB}"

.PHONY: test
test: ## Exécuter les tests
	$(PHP) php bin/phpunit

