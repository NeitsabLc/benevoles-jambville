# Journal des modifications

Ce fichier recense les changements fonctionnels, techniques et de sécurité
significatifs. Les détails d’infrastructure et de livraison de production sont
volontairement conservés hors du dépôt.

Le format s’inspire de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/)
et le projet suit une numérotation de version sémantique.

## Non publié

Aucun changement pour le moment.

## 1.0.1 — 2026-08-10

### Sécurité

- désactivation explicite de l'affichage des erreurs PHP en production et
  journalisation des erreurs vers la sortie standard du conteneur ;
- masquage des jetons de première connexion et de leurs référents dans les
  journaux d'accès Nginx, avec interdiction de mise en cache et de transmission
  du référent sur ce parcours ;
- remplacement de `script-src 'unsafe-inline'` par un nonce CSP généré pour
  chaque requête et retrait des gestionnaires JavaScript intégrés au HTML ;
- obligation de définir séparément les mots de passe PostgreSQL de
  l'application et des sauvegardes, sans repli sur le compte administrateur ;
- déclaration explicite des attributs `Secure`, `HttpOnly` et `SameSite=Lax`
  pour le cookie de session en production.

## 1.0.0 — 2026-08-10

### Ajouté

- séparation des rôles PostgreSQL utilisés par l’application, les sauvegardes
  et les migrations ;
- procédure en plusieurs phases pour préparer les rôles, vérifier la bascule et
  déclencher manuellement leur durcissement final ;
- migration additive `V006` permettant d’anonymiser l’auteur d’une inscription
  ou d’une journée sans supprimer la donnée métier ;
- jeu de données local enrichi avec plusieurs bénévoles, inscriptions, repas et
  permanences fictifs ;
- tests de purge des comptes et des inscriptions traversant une frontière de
  campagne ;
- en-têtes HTTP de sécurité complémentaires ;
- base PostgreSQL `_test` dédiée et reconstruite avant chaque suite PHPUnit.

### Modifié

- la purge d’un compte supprime uniquement ses inscriptions personnelles et
  anonymise ses autres contributions ;
- l’archivage d’une campagne conserve les inscriptions traversantes et devient
  idempotent ;
- les paramètres Doctrine sont transmis séparément afin d’éviter les problèmes
  d’encodage des mots de passe dans une URL ;
- la documentation du dépôt est recentrée sur le développement local et le
  contexte fonctionnel ;
- les tests fonctionnels restaurent leurs données avec le gestionnaire Doctrine
  actif afin de rester indépendants de leur ordre d’exécution.

### Sécurité

- limitation des privilèges des comptes PostgreSQL applicatif et de sauvegarde ;
- restriction des proxies de confiance aux réseaux explicitement autorisés ;
- protection locale des fichiers de secrets avec des permissions restrictives ;
- ajout d’une politique de sécurité du contenu, de HSTS, de Permissions Policy,
  de COOP, de CORP et masquage de la version PHP ;
- retrait de références Git distantes locales invalides.

## 0.2.0 — 2026-08-10

### Ajouté

- collecte des informations pratiques après la première activation du compte ;
- besoins spécifiques de couchage dans le profil et la synthèse ;
- modification contrôlée des rôles métier par l’équipe pilote ;
- affichage de l’adresse email dans la gestion des bénévoles ;
- informations légales, politique de confidentialité et politique de
  conservation des données ;
- maintenance automatique des thématiques, comptes désactivés et campagnes
  arrivées à échéance.

### Modifié

- tri des présences et des identités par prénom ;
- clarification des allergies alimentaires dans le profil ;
- amélioration de la lisibilité du calendrier et de la synthèse ;
- prise en charge précise des timestamps PostgreSQL avec microsecondes ;
- durcissement initial des fichiers d’environnement et des proxies de confiance.

## 0.1.0 — 2026-08-09

### Ajouté

- socle Symfony, PostgreSQL, Liquibase et Docker Compose ;
- authentification locale et parcours de première connexion ;
- profils bénévoles et gestion du mot de passe ;
- calendrier des présences ;
- inscriptions individuelles et équipes compagnons ;
- repas et couchages associés aux inscriptions ;
- synthèse accueil et hôtellerie ;
- gestion des thématiques, permanences et bénévoles ;
- import CSV avec prévisualisation ;
- interface responsive et contrôles d’autorisation côté serveur.
