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
- import CSV avec prévisualisation des champs et rôles modifiés ;
- purge et anonymisation des données arrivées à échéance.

Le détail des règles métier et l’état du projet sont consignés dans
[PROJECT_CONTEXT.md](PROJECT_CONTEXT.md). Les changements fonctionnels et
techniques sont recensés dans [CHANGELOG.md](CHANGELOG.md).

## Environnement local

### Prérequis

- Docker avec Docker Compose ;
- `make` ;
- Git ;
- Node.js 20 ou supérieur et npm pour les contrôles d’accessibilité.

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
make db-sync-role-passwords
```

En production, la première migration d’une base vierge est exécutée avec le
compte d’amorçage PostgreSQL. Exécuter ensuite
`make db-finalize-role-hardening` pour transférer les objets au rôle migrateur
limité. Toutes les migrations suivantes utilisent
`benevole_jambville_migrator` et son secret `POSTGRES_MIGRATOR_PASSWORD`.
La bascule crée également le compte administratif opérationnel défini par
`POSTGRES_HEALTHCHECK_USER`, puis désactive la connexion au compte d’amorçage
`POSTGRES_USER`. Les restaurations utilisent uniquement ce compte opérationnel.
Le HBA de production autorise uniquement ces rôles depuis le sous-réseau
`BENEVOLE_NETWORK_SUBNET`, avec SCRAM, puis rejette toute autre connexion.

Doctrine sert au mapping et aux requêtes applicatives. Il ne doit jamais créer
ou modifier le schéma.

## Tests

```bash
make test
make analyse-statique
make style
make backup-restore-test
npm ci
npx playwright install chromium
make test-accessibility
```

Le test de sauvegarde génère une paire de clés `age` éphémère, produit un dump
chiffré puis le restaure dans une base temporaire. En production, seule la clé
publique `BACKUP_AGE_RECIPIENT` est fournie au service de sauvegarde ; la clé
privée de restauration doit être conservée séparément de l’hôte.

`make test` reconstruit une base PostgreSQL locale dédiée dont le nom se
termine par `_test`, applique les migrations et exécute PHPUnit. Elle ne modifie
pas les données de développement.

`make analyse-statique` exécute PHPStan au niveau 6 sur le code applicatif.
`make test-accessibility` reconstruit d'abord les assets de production, puis
contrôle avec Playwright et Axe les pages publiques
et les parcours des trois rôles ; l’application doit être démarrée. La commande
applique et réinitialise elle-même les données de démonstration nécessaires aux
scénarios E2E. Ces contrôles sont également exécutés par la CI.

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

## Modèle de branches

- `main` contient uniquement les versions stables destinées à la production et
  constitue la branche GitHub par défaut ;
- `dev` est la branche d’intégration pour les travaux de développement ;
- les changements sont préparés sur une branche courte créée depuis `dev`, puis
  fusionnés dans `dev` après validation ;
- une livraison fusionne `dev` dans `main`, met à jour la version et le
  changelog, puis crée un tag annoté `vX.Y.Z` ;
- un correctif urgent part de `main` et doit ensuite être reporté dans `dev`.

## Livraison manuelle par images

Chaque commit de `main` publie dans GHCR cinq images candidates immuables
(PHP, Nginx, PostgreSQL, Liquibase et sauvegarde), puis les teste ensemble avec
la configuration de production. Un tag `vX.Y.Z` ne reconstruit rien : il
attribue uniquement l’étiquette exacte `X.Y.Z` aux digests déjà validés. Les
étiquettes flottantes `X.Y` et `X` ne sont pas publiées afin d’éviter toute mise
à jour implicite.

Sur le serveur, copier `.env.release.example` vers `.env.release` et renseigner
les cinq références `ghcr.io/...@sha256:...` affichées par GitHub. La livraison
reste entièrement manuelle :

```bash
make release-pull
make release-backup-now
make release-db-status
make release-db-update
make release-up
make release-ps
```

`release-pull` vérifie l'attestation GitHub signée de chaque image avant son
téléchargement. La surcharge `compose.release.yaml` supprime
toutes les constructions locales et `release-up` interdit explicitement toute
reconstruction. Le volume PostgreSQL existant est conservé ; la sauvegarde
précède obligatoirement l'application des migrations Liquibase.

Après un clonage destiné au développement local :

```bash
git switch dev
```
