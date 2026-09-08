# Bénévoles Jambville

[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony 8.1](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker Compose](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)](https://docs.docker.com/compose/)
[![CI](https://github.com/NeitsabLc/benevoles-jambville/actions/workflows/ci.yaml/badge.svg)](https://github.com/NeitsabLc/benevoles-jambville/actions/workflows/ci.yaml)
[![Licence Apache 2.0](https://img.shields.io/badge/Licence-Apache%202.0-D22128?logo=apache&logoColor=white)](LICENSE)

## Description

Bénévoles Jambville est une application web de gestion des bénévoles accueillis à Jambville. Elle centralise les profils, les inscriptions, les présences, les repas, les couchages et les besoins d’accueil dans une interface adaptée aux différents rôles métier.

L’application repose sur Symfony, PostgreSQL, Liquibase, Nginx et Docker Compose.

## Fonctionnalités principales

- authentification locale et première connexion sécurisée ;
- gestion des profils et des rôles métier ;
- inscriptions individuelles et inscriptions d’équipes compagnons ;
- calendrier des présences et gestion des permanences ;
- synthèse anonymisée des repas, couchages et régimes alimentaires ;
- gestion des thématiques et des bénévoles ;
- import CSV avec prévisualisation des modifications ;
- purge et anonymisation des données arrivées à échéance.

## Environnement local

### Prérequis

- Git ;
- Docker avec Docker Compose v2 ;
- GNU Make ;
- Node.js 20 ou supérieur et npm pour les tests navigateur.

PHP, Composer, PostgreSQL, Liquibase et Nginx sont fournis par les conteneurs.

### Installation

```bash
git clone https://github.com/NeitsabLc/benevoles-jambville.git
cd benevoles-jambville
cp .env.example .env
cp app/.env.example app/.env
make install
```

Ne jamais versionner les fichiers `.env`. Avec les valeurs par défaut, l’application est accessible sur <http://localhost:8081>.

Commandes courantes :

```bash
make up
make down
make ps
make logs
```

### Base de données

Liquibase est l’unique source de vérité du schéma ; Doctrine assure le mapping applicatif. Les migrations versionnées se trouvent dans `database/changelog/versioned/`.

```bash
make db-validate
make db-status
make db-sql
make db-update
make db-dev-update
make db-shell
```

`make db-dev-update` ajoute les données de démonstration et ne doit être utilisé qu’en développement ou en test. Un changeset déjà appliqué ne doit jamais être modifié : toute évolution crée un nouveau fichier `Vxxx`. Une base locale à conserver doit toujours être sauvegardée avant une réinitialisation.

## Déploiement sur un serveur

La procédure générale consiste à :

1. préparer un serveur Linux avec Docker Compose, un nom de domaine, TLS et un stockage persistant pour PostgreSQL et les sauvegardes ;
2. récupérer une version publiée et copier `.env.release.example` vers `.env.release` ;
3. injecter les secrets hors de Git et renseigner les images GHCR par digest ;
4. s’authentifier auprès de GHCR si nécessaire, puis vérifier les signatures et télécharger les images ;
5. effectuer une sauvegarde chiffrée avant toute migration ;
6. contrôler puis appliquer les changesets Liquibase ;
7. démarrer les services et vérifier leur état, les journaux, la connexion et l’envoi d’e-mails ;
8. conserver la version précédente et une sauvegarde restaurable pour permettre un retour arrière.

```bash
make release-config
make release-verify
make release-pull
make release-backup-now
make release-db-status
make release-db-update
make release-up
make release-ps
```

Le proxy inverse, les certificats, les secrets, les sauvegardes et la supervision relèvent de la configuration du serveur et ne doivent pas être stockés dans le dépôt.

## Tests et CI

Les contrôles disponibles localement sont :

```bash
make test
make analyse-statique
make style
make backup-restore-test
npm ci
make test-accessibility
make test-e2e
```

`make test` recrée une base PostgreSQL isolée, applique les migrations et exécute PHPUnit. PHPStan assure l’analyse statique ; Playwright et Axe couvrent les parcours fonctionnels, les navigateurs, le mobile et l’accessibilité.

GitHub Actions exécute sur chaque pull request vers `main` la validation du titre, de Docker Compose, Composer, Liquibase et Doctrine, puis PHPStan, le style, PHPUnit, la compilation des assets, l’accessibilité, les parcours E2E, la recherche de secrets et l’analyse des vulnérabilités. Un smoke test vérifie aussi la configuration de production, les rôles PostgreSQL, la sauvegarde-restauration et le durcissement des conteneurs. Les releases publient des images GHCR signées, accompagnées d’un SBOM et d’une provenance.
