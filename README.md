# Bénévoles Jambville

Application Symfony de gestion des profils, inscriptions, présences, repas,
couchages et besoins d’accueil des bénévoles de Jambville.

> Ce document concerne exclusivement l’installation et l’utilisation sur un
> poste de développement local. L’architecture, le déploiement, la migration et
> l’exploitation sur `web01` sont décrits dans
> [docs/PRODUCTION.md](docs/PRODUCTION.md).

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
npx playwright install chromium firefox
make test-accessibility
make test-e2e
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
contrôle avec Playwright et Axe les pages publiques et les pages des trois
rôles ; l’application doit être démarrée.

`make test-e2e` exécute les parcours métier complets dans Chromium, complète
les contrôles de compatibilité dans Firefox, puis vérifie le menu et
les gestes tactiles dans un viewport mobile. Une fixture SQL strictement
cantonnée aux identifiants `E2E-*` réinitialise automatiquement ces comptes et données avant et après la
suite. `make test-browser` enchaîne accessibilité et E2E. Ces contrôles sont
également exécutés par la CI.

## Emails locaux

La configuration d’exemple utilise `null://null` : aucun email réel n’est envoyé.
Pour tester un serveur SMTP local, adapter uniquement `MAILER_DSN` et
`MAILER_FROM` dans `app/.env.local`.

## Structure du dépôt

```text
app/                  application Symfony
database/changelog/   schéma Liquibase et données de démonstration
docker/               images et configuration de l’environnement local
deploy/               exemples et scripts d’infrastructure sans secret
docs/                 procédures de production et prompt de migration Campement
compose.yaml          services Docker Compose locaux
```

Le guide versionné [docs/PRODUCTION.md](docs/PRODUCTION.md) décrit la production
sur `web01`. Les secrets, les fichiers d’environnement réels, les sauvegardes,
la configuration Traefik active et le DAT/DIN/DEX local restent hors du dépôt.

## Modèle de branches

- `main` est la branche stable et la source unique des releases ;
- chaque évolution ou correction est préparée sur une branche courte issue de
  `main`, puis fusionnée par pull request après validation de la CI ;
- les titres suivent Conventional Commits afin que Release Please calcule la
  prochaine version et actualise une unique pull request de release.

## Livraison par images

Les commits ordinaires de `main` ne publient aucune image. La fusion de la pull
request Release Please publie la GitHub Release et déclenche la construction des
cinq images candidates (PHP, Nginx, PostgreSQL, Liquibase et sauvegarde) sous une
étiquette immuable `sha-<commit>`, avec SBOM, provenance et signature Sigstore.
Les candidates sont testées par digest puis promues vers la version `X.Y.Z` sans
reconstruction. Le dépôt `homelab-deploy` déploie ensuite automatiquement ces
mêmes digests en recette sur `web02`, sans activer le service de sauvegarde.

La production reste une promotion manuelle depuis `homelab-deploy`, avec la
version validée en recette et une confirmation explicite. Une sauvegarde
PostgreSQL chiffrée est exigée avant Liquibase et le service planifié de
sauvegarde reste actif en production.

Les commandes suivantes restent disponibles pour le diagnostic manuel :

```bash
make release-pull
make release-backup-now
make release-db-status
make release-db-update
make release-up
make release-ps
```

`release-pull` vérifie la signature Sigstore sans clé de chaque image, son
workflow, sa référence Git et le SHA attendu avant son
téléchargement. La surcharge `compose.release.yaml` supprime
toutes les constructions locales et `release-up` interdit explicitement toute
reconstruction. Le volume PostgreSQL existant est conservé ; la sauvegarde
précède obligatoirement l'application des migrations Liquibase.

Après un clonage destiné au développement local :

```bash
git switch main
```
