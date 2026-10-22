# Infra — Bénévoles Jambville

> Fonctionnement général de la CI, des versions, des images et de la livraison.
> Dépôt public : machines, adresses, domaines, accès et état déployé sont dans la documentation
> d'exploitation privée (projet Infra Homelab), jamais ici.

## 01 Cycle complet

```text
branche ──PR──▶ CI ──squash──▶ main ──▶ PR Release Please ──fusion──▶ tag vX.Y.Z + GitHub Release
                                                                           │
                     ┌─────────────────────────────────────────────────────┘
                     ▼
     Publication des images de version : 5 images ▶ SBOM + provenance ▶ signature ▶ test par digest ▶ promotion GHCR
                     │
                     ▼ automatique
               RECETTE (dépôt de déploiement privé)
                     │
                     ▼ manuel : workflow de production du dépôt de déploiement + confirmation production-VERSION
               PRODUCTION
```

## 02 Intégration continue (sur chaque PR vers `main`)

| Workflow / job | Contrôle |
|---|---|
| Titre conventionnel | Titre de PR au format Conventional Commits (workflow réutilisable) |
| Qualite rapide | Version applicative, secrets (Trivy), refus des PHP vulnérables, configuration Compose, audit npm |
| Betterleaks | Recherche de secrets dans l'historique |
| Tests applicatifs | Audits Composer et ImportMap, Liquibase, mapping Doctrine, assets, PHPStan, style, PHPUnit, Axe, Playwright |
| Image (matrice) | Construction et analyse Trivy de chaque image |
| Qualite et tests | Job de synthèse exigé par la protection de `main` |
| Verification de production (`production-smoke.yaml`) | Superposition Compose de production sur une installation isolée |

- Rapports Trivy publiés dans GitHub Security ; actions épinglées par SHA ; permissions en lecture par défaut.
- Dependabot : Composer, npm, GitHub Actions, images Docker.

## 03 Versions (Release Please)

- Chaque fusion dans `main` lance **Préparer ou publier une version** (`release-please.yml`).
- Release Please ouvre ou actualise une PR de version ; elle met à jour `CHANGELOG.md`, `version.txt` et `app/src/VersionApplication.php`.
- La fusion de cette PR crée le tag `vX.Y.Z` et la GitHub Release. Ne jamais créer de tag à la main.
- `feat` → mineure, `fix` → corrective, `!` → majeure.

## 04 Images

| Image | Rôle |
|---|---|
| `benevole-jambville-php` | Application Symfony (PHP-FPM) et commandes de maintenance |
| `benevole-jambville-nginx` | Serveur web et assets compilés |
| `benevole-jambville-postgres` | PostgreSQL 18 et rôles limités |
| `benevole-jambville-liquibase` | Migrations du schéma |
| `benevole-jambville-backup` | Sauvegardes chiffrées |

- Publiées dans GHCR avec SBOM et provenance, signées sans clé (Cosign + OIDC GitHub).
- Toujours consommées **par digest** ; vérification : `make release-verify`.
- Une version existante se reteste et se repromeut en relançant **Publication des images de version** à la main.

## 05 Livraison

| Environnement | Déclenchement | Garde-fous |
|---|---|---|
| Recette | **Automatique** après la publication des images d'une release | Manifeste immuable (digests), migrations, contrôle HTTPS |
| Production | **Manuel** : workflow du dépôt de déploiement, version + saisie `production-VERSION` | Environnement GitHub protégé, sauvegarde avant migration (`release-backup-now`), vérification des signatures |

- Le déploiement est exécuté par le dépôt de déploiement privé, sur un runner auto-hébergé.
- Une release publiée ne prouve pas son déploiement : vérifier le manifeste et l'état réel.
- Jamais de données de `database/changelog/dev/` en recette ou en production.

## 06 Secrets

- Valeurs locales dans les fichiers d'environnement ignorés par Git ; modèles : `.env.example`, `app/.env.example`.
- CI : `RELEASE_PLEASE_TOKEN` (PR de version, tag, release) et `HOMELAB_DEPLOY_DISPATCH_TOKEN` (déclenchement de la recette) ;
  le `GITHUB_TOKEN` natif publie les images. Détail et droits minimaux : `GITHUB_SETUP.md`.
- Sauvegardes chiffrées avec une clé publique ; la clé privée reste hors des serveurs et des archives.
- Ne documenter que des noms de variables, jamais de valeurs.
