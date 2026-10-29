# Décisions — Bénévoles Jambville

> Choix durables et leçons apprises. Chaque entrée précise son motif ;
> une nouvelle décision remplace explicitement l'ancienne au lieu de s'y ajouter.
> Dates : premier commit qui introduit ou formalise la décision.

## 01 Décisions

| Date | Décision | Motif |
|---|---|---|
| 2026-08-10 | Liquibase possède le schéma ; Doctrine ne sert qu'au mapping et aux requêtes | Une seule chaîne de migration, relisible en SQL |
| 2026-08-10 | Un changeset appliqué est immuable ; sauvegarde avant toute migration durable | Rejouabilité et retour arrière |
| 2026-08-10 | Bases de test dédiées et recréables ; une base métier n'est jamais une cible de test destructive | Protéger les données réelles |
| 2026-09-28 | GitHub est la forge de référence ; `CONTRIBUTING.md` définit le cycle vers `main` | Un seul flux de revue |
| 2026-09-28 | Release Please et images signées par digest | Livraison traçable |
| 2026-09-29 | Une présence à la journée ne crée pas de couchage et est exclue du rooming | Ne pas réserver de lit inutile |
| 2026-09-30 | Totaux alimentaires toujours sans noms | Minimiser l'exposition de données pouvant relever de la santé |
| 2026-10-22 | Contrôleurs fins ; règles métier et contrôles d'accès côté serveur | Testabilité, sécurité |
| 2026-10-22 | Les détails privés de production appartiennent au projet Infra Homelab | Dépôt public |
| 2026-10-28 | Pas de double authentification locale, par exception à la règle commune ; l'authentification basculera à terme sur le SSO SGDF | Éviter un second mécanisme appelé à disparaître |
| 2026-10-28 | Code, tables et colonnes en français, par exception à la règle commune (code en anglais) | Vocabulaire métier SGDF entièrement français |

## 02 Leçons apprises

> Chaque bug qui a coûté du temps devient une règle.

| Ce qui a cassé | Correction | Règle retenue |
|---|---|---|
| Les migrations Liquibase n'étaient pas embarquées dans l'image (#59) | Ajout des changelogs à l'image Liquibase | Le test de l'image par digest applique les migrations avant toute promotion |
| Purges et sauvegardes lancées comme services permanents (#46, #49) | Exécution en tâche ponctuelle | Toute tâche planifiée est ponctuelle et rend un code de sortie contrôlé |
| Image PHP vulnérable (CVE PHP 8.4, #75) | Image épinglée par digest, refus des versions vulnérables en CI | La CI refuse une image PHP antérieure à la version corrigée |
| Betterleaks échouait faute d'environnement propagé (#55) | Environnement transmis au job | Les outils de sécurité de la CI sont testés sur une PR avant d'être rendus obligatoires |
