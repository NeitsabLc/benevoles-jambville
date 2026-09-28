# Configuration GitHub après migration

Aucune valeur secrète n’est versionnée. Les secrets ci-dessous doivent être
créés dans **Settings > Secrets and variables > Actions**.

## Secrets à conserver

| Secret | Usage | Droits conseillés |
|---|---|---|
| `RELEASE_PLEASE_TOKEN` | création et mise à jour de la pull request de release, puis création du tag et de la GitHub Release après fusion | fine-grained PAT limité à ce dépôt, avec `Contents` et `Pull requests` en écriture |
| `HOMELAB_DEPLOY_DISPATCH_TOKEN` | envoi de l’événement de déploiement en recette vers `NeitsabLc/homelab-deploy` | fine-grained PAT limité au dépôt de déploiement, avec `Contents` en écriture |

Le `GITHUB_TOKEN` natif publie les images dans GHCR et obtient le jeton OIDC
utilisé par Cosign. Aucun secret de registre supplémentaire n’est nécessaire.

## Cycle de release

La préparation reste volontairement manuelle : depuis l’onglet **Actions**, lancer
le workflow **Préparer ou publier une version** sur `main`. Release Please crée ou
actualise une pull request qui regroupe les changements conventionnels depuis le
dernier tag et calcule la prochaine version.

La fusion de cette pull request relance le même workflow, qui crée le tag
`vX.Y.Z` et la GitHub Release. Sa publication déclenche automatiquement :

1. la construction des cinq images candidates dans GHCR ;
2. la production du SBOM et de la provenance ;
3. la signature sans clé avec Cosign et GitHub OIDC ;
4. le smoke test exact des images candidates ;
5. leur promotion sous le tag de version ;
6. le déploiement en recette via `NeitsabLc/homelab-deploy`.

Une version existante peut être retestée et repromue sans reconstruction en
lançant manuellement **Publication des images de version** avec sa version sans
préfixe `v`. La production reste déclenchée manuellement depuis le workflow
**Promouvoir Bénévoles Jambville en production** du dépôt de déploiement.

## Réglages du dépôt

- Protéger `main` et exiger les contrôles `Qualite et tests` et
  `Configuration de production` avant fusion.
- Interdire les poussées directes et exiger au moins une revue.
- Activer le squash des pull requests et conserver leur titre Conventional
  Commits comme titre du commit.
- Conserver les permissions Actions en lecture par défaut ; les workflows
  élèvent explicitement `packages` et `id-token` uniquement pour la publication.
- Conserver Dependabot actif pour Composer, npm, GitHub Actions et les images
  Docker.

Les anciennes branches Dependabot et Release Please datant d’avant la migration
peuvent être supprimées une fois les nouvelles pull requests recréées depuis
`main`.
