# Journal des modifications

Ce fichier recense les changements fonctionnels, techniques et de sécurité
significatifs. Les détails d’infrastructure et de livraison de production sont
volontairement conservés hors du dépôt.

Le format s’inspire de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/)
et le projet suit une numérotation de version sémantique.

## Non publié

### Corrigé

- restauration du générateur de repas et des autres interactions JavaScript en
  supprimant un import CSS redondant bloqué par la CSP, avec une initialisation
  compatible avec les chargements directs et la navigation Turbo.

### Ajouté

- smoke test CI de la superposition Compose de production, incluant migrations,
  rôles PostgreSQL limités, HBA SCRAM, durcissement des conteneurs, maintenance,
  requête HTTP, sauvegarde chiffrée et restauration réelle ;
- audits de sécurité des dépendances npm et ImportMap, analyse Trivy des images
  PHP, Nginx, PostgreSQL, Liquibase et sauvegarde, et mises à jour Dependabot ;
- chiffrement des sauvegardes PostgreSQL avec `age` et commande locale de test
  de restauration avec une identité éphémère ;
- contrôle de style PHP automatisé dans la CI.

### Modifié

- compilation systématique des assets de production avant les contrôles
  d’accessibilité Playwright et Axe ;
- extraction de l’analyse des imports CSV et des validations de profil et de
  présence dans des services dédiés, avec des limites explicites sur les
  saisies persistées ;
- images tierces référencées par tag et digest immuable, et images de production
  PHP et Nginx construites sans montage du code applicatif ;
- validation silencieuse des configurations Compose afin de ne plus exposer les
  secrets résolus dans les journaux de CI.

### Sécurité

- restriction des hôtes et proxies de confiance par configuration Symfony et
  rejet des en-têtes `Host` non autorisés ;
- protection CSRF de la déconnexion ;
- exécution non privilégiée des services PHP, Nginx, PostgreSQL, Liquibase,
  maintenance et sauvegarde avec racine en lecture seule, capacités Linux
  retirées, élévation de privilèges interdite et limites explicites de
  ressources ;
- authentification PostgreSQL de production en SCRAM et vérification automatisée
  des privilèges des rôles applicatif et sauvegarde.

## 1.1.1 — 2026-08-12

### Ajouté

- contrôles automatisés d’accessibilité avec Playwright et Axe sur les pages
  publiques et les parcours des trois rôles applicatifs.

### Modifié

- amélioration des contrastes, de la taille des actions du calendrier et des
  associations entre libellés et champs de formulaire ;
- découverte dynamique du port Nginx par la CI, maintien du nom de contrôle
  requis par la protection de `main` et alignement du port local sur `8081`
  dans les configurations et la documentation ;
- reconstruction de la base de tests à partir du nom PostgreSQL configuré,
  sans dépendre du nom par défaut ;
- alignement de la documentation du modèle de branches sur `main`, désormais
  branche GitHub par défaut ;
- clarification de la politique de confidentialité : la purge annuelle est
  exécutée à partir du 10 octobre, au premier cycle de maintenance disponible.

### Sécurité

- masquage des mots de passe PostgreSQL temporaires dans les journaux GitHub
  Actions.

## 1.1.0 — 2026-08-11

### Ajouté

- comparaison détaillée des valeurs actuelles et importées, dont le rôle cible,
  avant application d'un import CSV ;
- service centralisé de calcul du rôle à partir des codes fonction et structure ;
- PHPStan au niveau 6 et analyse de l'image PHP par Trivy dans la CI ;
- suivi technique des campagnes déjà purgées afin de ne rejouer la purge qu'en
  cas de rattrapage nécessaire ;
- tests des sessions désactivées, changements de rôle importés, thématiques
  expirées, purges annuelles, périodes exclusives et saisies de calendrier.

### Modifié

- la maintenance vérifie quotidiennement les échéances et rattrape une purge
  annuelle manquée, sans réexécuter une campagne déjà traitée ;
- les thématiques événementielles expirées sont désormais désactivées par le
  cycle normal de maintenance ;
- les mots de passe des rôles PostgreSQL limités peuvent être resynchronisés
  explicitement après une modification des fichiers d'environnement ;
- l'image PHP applique les mises à jour de sécurité Debian disponibles pendant
  sa construction.

### Sécurité

- révocation des sessions déjà ouvertes lors de la désactivation d'un compte ;
- audit des dépendances Composer et blocage de la CI sur les vulnérabilités
  corrigibles de sévérité haute ou critique dans l'image PHP.

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
