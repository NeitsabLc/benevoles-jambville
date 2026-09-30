# Contexte du projet Bénévoles Jambville

Dernière révision : 30 septembre 2026
Version applicative affichée : 1.5.1
Schéma de données : Liquibase V015

## 1. Objet et état du projet

Bénévoles Jambville est une application web privée qui organise l’accueil des bénévoles et des équipes compagnons à Jambville. Elle couvre les comptes, profils, inscriptions, présences, repas, couchages, transports depuis Meulan, permanences et affectations de chambres.

Le dépôt de référence est hébergé sur GitHub. La branche main peut contenir des changements postérieurs à la dernière version publiée ; la version réellement déployée doit être vérifiée dans le dépôt d’infrastructure.

## 2. Utilisateurs et habilitations

- Bénévole : consulte et modifie son profil, gère ses inscriptions et consulte le calendrier partagé autorisé.
- Salarié d’accueil : consulte les synthèses opérationnelles, gère les permanences, les chambres et le rooming selon les droits attribués.
- Équipe pilote : dispose des droits d’accueil et administre en plus les utilisateurs, imports, thématiques, inscriptions compagnons et inscriptions d’autres bénévoles.
- Administrateur technique : exploite l’infrastructure et n’utilise les données personnelles que pour le support, la sécurité et la continuité du service.

Les autorisations applicatives sont contrôlées côté serveur. L’interface ne constitue pas à elle seule une mesure de contrôle d’accès.

## 3. Fonctions métier

### Profils et comptes

Le profil contient l’identité, les coordonnées, les rôles, les informations de tenue et d’hébergement et, de manière facultative, les particularités alimentaires. La première connexion passe par un jeton limité à sept jours. Un compte désactivé peut être réactivé pendant trente jours.

### Inscriptions et accueil

Les inscriptions peuvent être individuelles ou concerner une équipe compagnons. Elles décrivent une période, une thématique, les repas, le couchage, les enfants, un commentaire d’accueil et, si nécessaire, l’heure d’arrivée à Meulan pour organiser le transport jusqu’à Jambville.

Une présence à la journée ne génère pas de besoin de couchage et est exclue du rooming. Les synthèses opérationnelles peuvent comporter des données nominatives nécessaires à l’accueil ; seuls les totaux alimentaires sont présentés sans noms.

### Rooming, calendrier et permanences

Le rooming répartit les personnes qui dorment sur place dans les chambres disponibles, avec contrôle de capacité et prise en compte des enfants. Le calendrier consolide les présences et les permanences. Les configurations de chambres et disponibilités sont versionnées en base.

### Administration

L’équipe pilote gère les utilisateurs, les règles d’attribution de rôles, les thématiques et les imports CSV avec prévisualisation. La structure de données prépare une future identité externe, mais l’authentification active reste locale à ce jour.

## 4. Architecture technique

- PHP 8.4 et Symfony 8.1 pour l’application ;
- PostgreSQL 18 pour les données ;
- Doctrine ORM 3.6 pour le mapping ;
- Liquibase 5.0.1 comme source de vérité du schéma ;
- Nginx 1.30.4 comme frontal HTTP ;
- Docker Compose pour l’exécution locale et les déploiements ;
- Node.js 22 au minimum, Playwright et Axe pour les tests navigateur et accessibilité.

Les services principaux sont php, nginx, database et liquibase. Les profils de production ajoutent les tâches ponctuelles de maintenance et de sauvegarde. Les images de release sont publiées par digest dans GHCR, signées avec Cosign et accompagnées d’un SBOM et d’une provenance.

## 5. Données et migrations

Liquibase est l’unique source de vérité du schéma. Un changeset appliqué n’est jamais modifié ; toute évolution utilise un nouveau fichier Vxxx. Les principales familles de données sont :

- utilisateurs, identités d’authentification et règles de rôles ;
- thématiques, inscriptions, repas et permanences ;
- rooming, chambres et disponibilités ;
- imports, audit, historique statistique anonyme et suivi des purges.

Doctrine doit rester cohérent avec le schéma Liquibase et cette cohérence est vérifiée par la CI.

## 6. Sécurité

L’application utilise des comptes nominatifs, des mots de passe hachés, des protections CSRF, une limitation des tentatives de connexion et des cookies de session sécurisés en production. La politique de contenu limite les ressources aux origines attendues. Les journaux Symfony sont écrits sur la sortie standard au format structuré et un filtre masque les secrets usuels.

Les conteneurs de production fonctionnent avec système de fichiers en lecture seule lorsque possible, suppression des capacités Linux, interdiction de gagner des privilèges, espaces temporaires dédiés, limites de ressources et contrôles de santé.

Les secrets sont fournis hors du dépôt. Ils doivent être distincts par environnement et par rôle PostgreSQL.

## 7. Confidentialité et conservation

Les particularités alimentaires sont facultatives et servent à adapter le self et les repas aux besoins déclarés. Leur traitement repose sur le consentement de la personne, matérialisé par le choix de renseigner ces champs ; lorsqu’elles révèlent une information de santé, le consentement est explicite au regard de cette finalité. La personne peut le retirer en supprimant les informations de son profil.

Le responsable du traitement n’est pas encore formellement identifié. La politique publiée l’indique sans attribuer ce rôle à une entité non confirmée et fournit le contact opérationnel contact@neitsab.net. Ce point doit être régularisé dès qu’une décision organisationnelle est prise.

Règles techniques actuelles :

- purge à partir du 10 octobre des inscriptions entièrement comprises entre le 1er septembre et le 31 août précédent ;
- conservation des inscriptions qui chevauchent deux campagnes afin de préserver la continuité de l’accueil ; elles nécessitent une suppression manuelle ou la suppression du compte associé ;
- conservation sans durée limite de statistiques strictement anonymes ;
- effacement immédiat des particularités alimentaires lors de la désactivation, puis suppression du compte et de ses données après trente jours ;
- sauvegardes chiffrées et supprimées après sept jours ;
- journaux Docker limités à cinq fichiers de 10 Mio par service, avec une durée dépendant de l’activité.

La conservation des inscriptions chevauchant deux campagnes est une limite connue : elle doit rester visible dans la documentation et faire l’objet d’une revue périodique.

## 8. Intégration, publication et déploiement

GitHub Actions contrôle les titres de pull requests, Docker Compose, Composer, Liquibase, Doctrine, PHPStan, le style, PHPUnit, les assets, l’accessibilité, les parcours E2E, les secrets et les vulnérabilités. Un contrôle de production couvre aussi les rôles PostgreSQL, la sauvegarde-restauration et le durcissement des conteneurs.

Release Please prépare les versions à partir des titres Conventional Commits. La fusion de la pull request de release crée le tag et la GitHub Release, publie les images signées puis déclenche la recette. Le passage en production est manuel depuis le dépôt d’infrastructure.

Le proxy inverse, TLS, les secrets, la planification des sauvegardes et maintenances et la supervision sont gérés hors de ce dépôt.

## 9. Exploitation

Le dépôt fournit les commandes ponctuelles de sauvegarde, restauration, migration, maintenance et contrôle de santé. Leur planification régulière appartient à l’infrastructure et doit être vérifiée séparément. Toute mise à jour de production doit commencer par une sauvegarde chiffrée vérifiée et conserver une version antérieure permettant le retour arrière.

Les dossiers de référence sont maintenus hors du dépôt public dans l’espace Documentation dédié :

- DAT.md : architecture technique ;
- DIN.md : installation et mise en service ;
- DEX.md : exploitation courante, incidents et contrôles.

Ils ne doivent contenir aucune valeur secrète.

## 10. Points à surveiller

- formaliser l’identité et l’adresse du responsable du traitement ;
- vérifier et documenter que le recueil du consentement alimentaire reste libre, spécifique, éclairé, explicite et démontrable ;
- contrôler périodiquement les inscriptions qui chevauchent deux campagnes ;
- vérifier dans l’infrastructure la planification effective des sauvegardes, maintenances et sondes ;
- maintenir la cohérence entre README, contexte projet, politique de confidentialité, conditions d’utilisation, DAT, DIN, DEX et comportement réel ;
- documenter toute activation future d’une API ou d’une identité externe avant sa mise en production.
