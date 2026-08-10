# Bénévoles Jambville

Application Symfony de gestion des profils, inscriptions, présences, repas,
couchages et besoins d’accueil des bénévoles de Jambville.

> Ce document concerne exclusivement l’installation et l’utilisation sur un
> poste de développement local. Il ne décrit ni l’infrastructure, ni les accès,
> ni la procédure de livraison de la production.

## Fonctionnalités disponibles

- authentification locale et première connexion sécurisée ;
- gestion des profils et des trois rôles métier ;
- inscriptions individuelles et inscriptions d’équipes compagnons ;
- calendrier des présences et gestion des permanences ;
- synthèse des repas, couchages et régimes alimentaires anonymisés ;
- gestion des thématiques et des bénévoles ;
- import CSV avec prévisualisation ;
- purge et anonymisation des données arrivées à échéance.

Le détail des règles métier et l’état du projet sont consignés dans
[PROJECT_CONTEXT.md](PROJECT_CONTEXT.md). Les changements fonctionnels et
techniques sont recensés dans [CHANGELOG.md](CHANGELOG.md).

## Environnement local

### Prérequis

- Docker avec Docker Compose ;
- `make` ;
- Git.

PHP, PostgreSQL, Liquibase et Nginx sont fournis par les conteneurs du projet.

### Installation

Créer les fichiers de configuration locale à partir des exemples :

```bash
cp .env.example .env
cp app/.env.example app/.env
```

Les valeurs d’exemple sont réservées au développement. Adapter les secrets
locaux si nécessaire et ne jamais commiter `.env` ou `app/.env`. Un fichier
`app/.env.local` ignoré par Git peut être utilisé pour des surcharges propres au
poste.

Construire puis démarrer l’environnement :

```bash
make install
```

L’application locale est alors accessible sur <http://localhost:8081>.

Pour charger le jeu de démonstration local :

```bash
make db-dev-update
```

Ce jeu contient plusieurs profils, inscriptions, repas et permanences fictifs.
Il ne doit jamais être chargé hors d’un environnement local ou de test.

### Commandes courantes

```bash
make up
make down
make ps
make logs
make console ARGS="about"
```

## Base de données locale

Liquibase est l’unique source de vérité du schéma. Les changements versionnés se
trouvent dans `database/changelog/versioned/`. Un changeset appliqué est
immuable : toute évolution doit créer un nouveau fichier `Vxxx`.

```bash
make db-validate
make db-status
make db-sql
make db-update
```

Doctrine sert au mapping et aux requêtes applicatives. Il ne doit jamais créer
ou modifier le schéma.

## Tests

```bash
make test
```

Cette commande reconstruit une base PostgreSQL locale dédiée dont le nom se
termine par `_test`, applique les migrations et exécute PHPUnit. Elle ne modifie
pas les données de développement.

## Emails locaux

La configuration d’exemple utilise `null://null` : aucun email réel n’est envoyé.
Pour tester un serveur SMTP local, adapter uniquement `MAILER_DSN` et
`MAILER_FROM` dans `app/.env.local`.

## Structure du dépôt

```text
app/                  application Symfony
database/changelog/   schéma Liquibase et données de démonstration
docker/               images et configuration de l’environnement local
compose.yaml          services Docker Compose locaux
```

Les procédures opérationnelles de production sont volontairement conservées
hors de ce dépôt.
