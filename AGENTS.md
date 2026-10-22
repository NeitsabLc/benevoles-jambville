# AGENTS.md — Bénévoles Jambville

> Consignes pour les agents IA (Claude Code, Codex…) qui travaillent sur ce dépôt.
> Les suivre garantit un code homogène, sûr et conforme au design system.

## 01 Objectif

Organiser l'accueil des bénévoles et des équipes compagnons à Jambville : comptes, profils, inscriptions,
présences, repas, couchages et rooming, transports, permanences.
Public : bénévoles et salariés d'accueil. Règles détaillées : [metier.md](docs/context/metier.md),
données personnelles : [confidentialite.md](docs/context/confidentialite.md).

## 02 Avant de commencer

- [ ] Lire `git status --short` ; préserver les modifications locales existantes.
- [ ] Lire `docs/context/etat.md` (reprise, fichier local non versionné).
- [ ] Lire les documents utiles à la tâche :

| Besoin | Document |
|---|---|
| Stack, commandes, structure, données, performance | [architecture.md](docs/context/architecture.md) |
| CI, versions, images, recette et production | [infra.md](docs/context/infra.md) |
| Interface : couleurs, typo, icônes, messages | [design-system.md](docs/context/design-system.md) |
| Règles fonctionnelles | [metier.md](docs/context/metier.md) |
| Consentement, conservation, purges | [confidentialite.md](docs/context/confidentialite.md) |
| Choix durables | [decisions.md](docs/context/decisions.md) |

- [ ] Utiliser le design system pour tout écran ou composant.
- [ ] Créer une branche dédiée depuis `main` à jour : `feature/…`, `fix/…`, `refactor/…`, `test/…`, `docs/…`, `chore/…`.

## 03 Règles générales

- [ ] Fichiers `.env` réels : lecture possible en local uniquement, jamais sur un serveur ; ne jamais recopier une valeur.
- [ ] Réutiliser les composants, services et gabarits existants avant d'en créer.
- [ ] Garder le code modulaire et scalable : une responsabilité par classe, service ou gabarit.
- [ ] Ne pas créer de fichier non nécessaire (pas de doc ou script jetable dans le dépôt).
- [ ] Dépôt public : aucun détail d'infrastructure (IP, machines, domaines privés, chemins locaux).
- [ ] Répondre en français ; ne pas pousser ni fusionner sans accord explicite.

## 04 Guidelines de développement

- [ ] Composants fonctionnels : contrôleurs fins, règles métier dans `app/src/Service/` et les entités.
- [ ] Nommage simple et compréhensible, en français comme le code existant (`RoomingService`, `ValidationPresenceService`).
- [ ] Composants petits : extraire un service, un gabarit `commun/` ou un contrôleur Stimulus dès qu'un bloc grossit.
- [ ] Accessibilité WCAG 2.2 AA / RGAA : libellés, contrastes, navigation clavier, focus visible, rôles ARIA.
- [ ] Textes d'interface en français.
- [ ] Droits contrôlés côté serveur ; les besoins alimentaires ne sont jamais nominatifs dans les totaux.

## 05 Sécurité

- [ ] Ne jamais exposer de clé, mot de passe ou jeton (code, logs, tests, docs, Git).
- [ ] Toute configuration sensible passe par une variable d'environnement, documentée dans `.env.example`.
- [ ] Valider toutes les entrées utilisateur côté serveur (Validator, jeton CSRF, contrôle d'accès, imports CSV).
- [ ] Gérer les erreurs : message clair à l'utilisateur, aucune trace technique exposée, journal sans donnée personnelle.
- [ ] Respecter consentement, désactivation et purges (`confidentialite.md`) ; données de test uniquement dans `database/changelog/dev/`.

## 06 Commandes utiles

```bash
make help                 # inventaire complet
make install              # première installation (après copie des .env.example)
make up / make ps         # démarrer / état
make db-status            # migrations en attente
make test                 # tests PHP (reconstruit la base de test)
make test-browser         # accessibilité + parcours Playwright
make style analyse-statique
```

## 07 Pièges connus

- Ne jamais modifier un changeset Liquibase appliqué ; ne jamais lancer `doctrine:schema:update --force`.
- `make test` reconstruit la base de test ; vérifier la base visée avant les tests navigateur.
- Suppressions de volumes, purges et restaurations : accord explicite obligatoire.
- Une présence à la journée ne crée pas de couchage et reste exclue du rooming.
- Les cibles Make et le profil Liquibase diffèrent de Campement et Scout Market : vérifier avec `make help`.

## 08 Après le développement

- [ ] Tests : `make test`, plus `make test-browser` si l'interface change.
- [ ] Qualité : `make style analyse-statique` et `git diff --check`.
- [ ] Sécurité : `docker compose exec php composer audit`, `npm audit --audit-level=high`,
      `gitleaks git --log-opts="origin/main..HEAD"` ; la CI ajoute l'analyse des images.
- [ ] Commits Conventional Commits, description en français : `fix(rooming): conserver l'exception de nuit`.
- [ ] Avant toute PR : `git fetch origin && git rebase origin/main`, puis relancer les tests.
      Une branche déjà poussée ne se repousse qu'avec accord (`--force-with-lease`).
- [ ] Mettre à jour `etat.md` et la doc concernée ; indiquer contrôles réalisés et limites.

## 09 Besoin d'aide ?

- Chercher dans `docs/context/`, puis dans le code existant un cas similaire.
- Demander avant une action destructive, une restauration, un changement de production ou une refonte.
- `CLAUDE.md` contient seulement `@AGENTS.md` ; ce fichier reste la source unique des consignes.
