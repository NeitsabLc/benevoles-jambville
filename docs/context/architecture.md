# Architecture — Bénévoles Jambville

> Organisation du projet, stack technique, commandes et gestion des données.
> Versions exactes : `app/composer.json`, `composer.lock`, Dockerfiles et fichiers Compose.

## 01 Vue d'ensemble

```text
Navigateur ──HTTPS──▶ Nginx ──FastCGI──▶ PHP-FPM (Symfony)
                                            │
                                            ├──▶ PostgreSQL 18   (schéma : Liquibase)
                                            └──▶ SMTP            (première connexion, mots de passe)

Service maintenance : purge de campagne, comptes désactivés, thématiques expirées (tâches planifiées)
Service backup      : sauvegardes chiffrées, conservées sept jours
```

## 02 Stack

| Couche | Technologie |
|---|---|
| Langage | PHP 8.4 |
| Framework | Symfony 8.1, Doctrine ORM (mapping et requêtes) |
| Interface | Twig, AssetMapper, Symfony UX Turbo, Stimulus, flatpickr (dates) |
| Base de données | PostgreSQL 18 |
| Migrations | Liquibase (seule source du schéma) |
| Tests | PHPUnit, Playwright (Chromium, Firefox, mobile) + Axe |
| Qualité | PHP-CS-Fixer (`make style`), PHPStan (`make analyse-statique`) |
| Exécution | Docker Compose : `php`, `nginx`, `database`, `liquibase`, `maintenance`, `backup` |

## 03 Commandes `make`

| Usage | Commandes |
|---|---|
| Inventaire | `make help` |
| Installation | `make install`, `make build`, `make composer-install` |
| Environnement | `make up`, `make down`, `make ps`, `make logs SERVICE=php`, `make console ARGS="about"` |
| Base | `make db-validate`, `make db-status`, `make db-sql`, `make db-update`, `make db-dev-update`, `make db-shell` |
| Rôles PostgreSQL | `make db-prepare-roles`, `make db-verify-role-switch` ; `db-sync-role-passwords` et `db-finalize-role-hardening` sur accord |
| Qualité | `make style`, `make style-fix`, `make analyse-statique` |
| Tests | `make test`, `make test-db-reset`, `make test-accessibility`, `make test-e2e`, `make test-browser` |
| Assets | `make assets-compile` |
| Livraison | `make prod-config`, `make release-config`, `make release-verify`, `make release-db-status` |
| Exploitation | `make backup-now`, `make backup-restore-test`, `make maintenance-now` |

## 04 Structure du projet

```text
benevole-jambville/
├─ app/                      application Symfony
│  ├─ src/
│  │  ├─ Controller/         orchestration HTTP, aucune règle métier
│  │  ├─ Service/            règles métier (rooming, présences, profils, import CSV, rôles)
│  │  ├─ Entity/ Repository/ modèle Doctrine et requêtes
│  │  ├─ Security/           contrôle des comptes, CSP
│  │  ├─ Command/            purges et désactivations planifiées
│  │  ├─ EventSubscriber/ Logging/ Doctrine/ Util/
│  │  └─ VersionApplication.php   version affichée (mise à jour par Release Please)
│  ├─ templates/             gabarits par domaine (benevole, presence, rooming…) + commun/ + email/
│  ├─ assets/                CSS, contrôleurs Stimulus, images
│  ├─ config/                configuration Symfony
│  └─ tests/                 PHPUnit
├─ database/changelog/       Liquibase : versioned/, repeatable/ (droits), dev/
├─ docker/                   images php, nginx, postgres, liquibase (+ maintenance.sh)
├─ tests/                    Playwright : accessibility/, e2e/, infrastructure/
├─ scripts/                  scripts CI, smoke test et vérification des images
├─ compose*.yaml             dev, production, livraison par images (release)
└─ Makefile                  point d'entrée des commandes
```

## 05 Configuration importante

- Modèles : `.env.example` (Compose) et `app/.env.example` (Symfony) ; valeurs réelles hors Git.
- Variables clés : `APP_ENV`, `APP_SECRET`, `DEFAULT_URI`, `MAILER_DSN`, `MAILER_FROM`, `DATABASE_*`,
  `POSTGRES_*` (rôles application, migrateur, sauvegarde, healthcheck), `TRUSTED_PROXIES`, `TRUSTED_HOST_PATTERN`,
  `BACKUP_AGE_RECIPIENT` (clé publique de chiffrement), `BACKUP_DIR`.
- Images : versions et digests épinglés (`*_VERSION`, `*_IMAGE_DIGEST`) ; la livraison utilise des images GHCR par digest.
- Sécurité : `app/config/packages/security.yaml`, CSP dans `Security/ContentSecurityPolicySubscriber.php`.

## 06 Données

- Liquibase possède le schéma ; Doctrine ne génère jamais de migration.
- `versioned/` : changesets `Vxxx__description.sql`, immuables une fois appliqués ; toute évolution = nouveau fichier.
- `repeatable/` : `R__privileges.sql`, droits des rôles PostgreSQL rejoués à chaque modification.
- `dev/` : données de démonstration et de tests E2E, filtrées par le contexte `@dev`, jamais hors environnement jetable.
- Vérifier avec `make db-validate` et `make db-sql` ; sauvegarde restaurable avant toute migration durable.
- Conservation et purges : voir `confidentialite.md` (purge de campagne au 10 octobre, comptes désactivés à 30 jours).

## 07 Performance et scalabilité

| Sujet | Règle |
|---|---|
| Cache | Pas de cache applicatif aujourd'hui. Si un calcul coûteux est réutilisé (synthèses, rooming) : pool Symfony `cache.app`, invalidé à l'écriture. Assets compilés et servis par Nginx. |
| Tâches longues | Hors requête : commande Symfony lancée par le service `maintenance`. Pas de Messenger aujourd'hui ; l'ajouter avec un worker dédié si un traitement dépasse quelques secondes (imports, e-mails en masse). |
| Requêtes | Éviter les N+1 sur présences et chambres ; index déclarés dans Liquibase. |
| Modularité | Une fonctionnalité = contrôleur + service + dossier de gabarits dédié. |
