# Bénévoles Jambville — Contexte et règles de développement

## 1. Objet du projet

L’application Bénévoles Jambville permet de gérer les bénévoles intervenant à
Jambville, leurs profils, leurs inscriptions, leurs repas, leur couchage et la
préparation quotidienne de l’accueil et de l’hôtellerie.

Ce document constitue la référence fonctionnelle et technique du projet. Toute
évolution doit respecter les règles décrites ici ou mettre ce document à jour
dans le même changement.

## 2. Profils et autorisations

Un utilisateur possède exactement un des trois rôles suivants :

| Rôle métier | Valeur technique |
|---|---|
| Équipe pilote | `EQUIPE_PILOTE` |
| Salarié accueil et hôtellerie | `SALARIE_ACCUEIL` |
| Bénévole Jambville | `BENEVOLE` |

Il n’existe pas de cumul de rôles.

### Matrice des droits

| Fonction | Bénévole | Salarié | Équipe pilote |
|---|:---:|:---:|:---:|
| Consulter son profil | Oui | Oui | Oui |
| Modifier ses données personnelles autorisées | Oui | Oui | Oui |
| Modifier la remise de la tenue ou du foulard | Non | Non | Oui |
| Créer sa propre inscription | Oui | Oui | Oui |
| Créer une inscription compagnon | Non | Non | Oui |
| Consulter le calendrier des présences | Oui | Oui | Oui |
| Modifier la permanence et le commentaire d’une journée | Non | Oui | Oui |
| Consulter la synthèse accueil et hôtellerie | Non | Oui | Oui |
| Gérer les thématiques | Non | Non | Oui |
| Gérer les personnes de permanence | Non | Oui | Oui |
| Gérer les utilisateurs, leurs droits et les imports | Non | Non | Oui |

Les autorisations doivent toujours être contrôlées côté serveur. Masquer un
bouton dans l’interface ne constitue jamais une protection suffisante.

## 3. Profil utilisateur

Les informations suivantes sont présentes dans le profil :

- code adhérent, non modifiable ;
- nom, non modifiable ;
- prénom, non modifiable ;
- email, non modifiable ;
- téléphone, modifiable par l’utilisateur ;
- régime végétarien ;
- allergie aux œufs ;
- allergie aux arachides ;
- autre régime ou allergie, sous forme de commentaire ;
- besoin spécifique de couchage, sous forme de commentaire ;
- foulard remis, modifiable uniquement par l’équipe pilote ;
- tenue remise, modifiable uniquement par l’équipe pilote.

Le code adhérent est l’identifiant métier pivot entre l’application, les imports
CSV, la future API adhérents et le futur SSO.

Les informations de régime et d’allergie sont des données sensibles. Elles ne
doivent jamais apparaître dans le calendrier nominatif des présences, les
journaux applicatifs ou les détails du journal d’audit.

## 4. Inscriptions et présences

Les thématiques peuvent être permanentes ou événementielles. Une thématique
événementielle possède une période inclusive et est proposée en tête de liste
quand toute la présence demandée est comprise dans cette période. Lorsqu’elle
est marquée « Thématique exclusive sur cette période », elle remplace tous les
autres choix pour une présence compatible. Une présence qui chevauche même
partiellement une période exclusive doit être entièrement ramenée dans cette
période et utiliser la thématique de l’événement ; sinon l’inscription est
refusée avec un message explicite. Une thématique n’est jamais supprimée
si elle a été utilisée : elle peut être désactivée puis réactivée.
Une thématique événementielle est automatiquement désactivée 24 heures après
la fin de sa dernière journée, soit à minuit le surlendemain de sa date de fin.

### 4.1 Inscription individuelle

Un utilisateur peut uniquement s’inscrire lui-même. Il renseigne :

- une date de début inclusive ;
- une date de fin inclusive ;
- les repas concernés ;
- un couchage en dur ou en tente ;
- une thématique ;
- le nombre d’enfants présents ;
- un commentaire libre.

Un utilisateur peut posséder plusieurs inscriptions sur des périodes
distinctes. Deux inscriptions individuelles actives du même utilisateur ne
peuvent pas se chevaucher. Cette règle est garantie par PostgreSQL en plus de la
validation applicative.

Les repas sont générés automatiquement pour chaque journée comprise dans la
période. Ils sont sélectionnés par défaut. Une modification de la période doit
ajouter ou retirer les repas correspondants sans conserver de repas hors de la
nouvelle période.

Pour un repas sélectionné, l’effectif est :

```text
1 bénévole + nombre d’enfants
```

Les enfants partagent les repas et le type de couchage du parent. Ils ne
possèdent pas de profil individuel dans l’application. Un régime propre à un
enfant peut être précisé dans le commentaire de l’inscription, sans être intégré
automatiquement aux totaux de régimes du profil du parent.

### 4.2 Inscription compagnon

Une inscription compagnon peut uniquement être créée par l’équipe pilote. Elle
contient :

- le nom de l’équipe compagnon ;
- le nombre total de personnes ;
- les dates de début et de fin inclusives ;
- les repas concernés ;
- le couchage en dur ou en tente ;
- un commentaire libre.

L’effectif déclaré représente tout le groupe. Il n’existe pas de décompte
d’enfants distinct pour ce type d’inscription.

Pour un repas sélectionné, l’effectif est égal au nombre de personnes du groupe.

### 4.3 Calendrier des présences

Le calendrier des présences est privé et nécessite une authentification. Il est
consultable par les trois rôles.

Pour chaque journée, il affiche uniquement :

- les nom et prénom des bénévoles présents ;
- leur thématique ;
- les équipes compagnons présentes ;
- la personne de permanence ;
- le commentaire de la journée.

Aucune donnée médicale, alimentaire ou de besoin spécifique n’y est affichée.

L’équipe pilote et les salariés peuvent modifier la permanence et le
commentaire d’une journée. Une saisie simplifiée doit permettre d’appliquer une
permanence et un commentaire à une période complète, soit en remplaçant les
valeurs existantes après confirmation, soit en complétant uniquement les jours
vides.

### 4.4 Synthèse accueil et hôtellerie

Cette synthèse est réservée aux équipes pilotes et aux salariés. Pour une
période sélectionnée, elle présente chaque jour :

- le nombre de petits-déjeuners ;
- le nombre de déjeuners ;
- le nombre de dîners ;
- les personnes et équipes présentes ;
- les personnes et équipes en couchage dur ;
- les personnes et équipes en tente ;
- les régimes alimentaires sous forme de totaux anonymes.

Les régimes ne doivent jamais être associés à une identité dans cette synthèse.
Les enfants augmentent les quantités de repas et de couchage, mais pas les
totaux de régimes enregistrés sur le profil du parent.

## 5. Utilisateurs, import et synchronisation

### 5.1 Import CSV transitoire

Le format attendu est :

```text
code_adherent;nom;prenom;email;telephone;code_fonction;code_structure
```

L’import doit proposer une prévisualisation avant application : créations,
mises à jour, lignes inchangées, erreurs, doublons et changements de rôle.

Le rapprochement se fait exclusivement par code adhérent. Un email ne doit
jamais servir à choisir un compte existant.

Tant que l’authentification locale est utilisée, un nouveau compte reçoit un
lien temporaire lui permettant de choisir son premier mot de passe. Aucun mot
de passe provisoire en clair ne doit être envoyé ou journalisé.

### 5.2 Future synchronisation API

À terme, une synchronisation quotidienne avec l’outil de gestion des adhésions
de l’association remplacera l’import comme source principale des comptes.

L’API fournit au minimum :

- le code adhérent ;
- le nom ;
- le prénom ;
- l’email ;
- le téléphone ;
- le code fonction ;
- le code structure.

Le CSV et l’API doivent utiliser le même pipeline de normalisation, validation,
rapprochement et calcul des rôles.

La synchronisation quotidienne :

1. récupère et valide le jeu complet de données ;
2. crée les nouveaux comptes ;
3. met à jour les données de référence ;
4. recalcule les rôles ;
5. traite les absences de la source ;
6. produit un rapport détaillé et un journal d’audit.

Une défaillance de l’API ou un volume anormalement faible ne doit jamais
désactiver massivement les utilisateurs. Une personne absente est marquée comme
telle puis désactivée après deux synchronisations complètes consécutives où elle
est absente. Elle n’est jamais supprimée physiquement et est automatiquement
réactivée si elle réapparaît.

### 5.3 Calcul des rôles

Le rôle est calculé à partir du duo `code_fonction` et `code_structure` au moyen
des règles de la table `regle_attribution_role`.

- une combinaison reconnue reçoit le rôle configuré ;
- une combinaison inconnue reçoit par défaut le rôle `BENEVOLE` ;
- des codes absents ou invalides sont signalés dans le rapport ;
- les changements vers ou depuis `EQUIPE_PILOTE` sont explicitement mis en
  évidence et audités.

Le calcul doit enregistrer la règle utilisée et sa version afin de permettre
d’expliquer tout changement de droit.

## 6. Future authentification SSO

Le futur SSO autorisera la saisie d’un email ou d’un code adhérent sur son propre
écran. L’application ne traite pas cet identifiant de saisie : elle utilise le
code adhérent certifié transmis dans la réponse signée du SSO.

Le flux cible est :

```text
SSO → code adhérent certifié → utilisateur local actif → session applicative
```

Les comptes auront été créés préalablement par la synchronisation quotidienne.
La première connexion ne crée donc pas de compte.

Le code adhérent est utilisé pour le premier rapprochement. Le couple OIDC
standard `iss` et `sub`, enregistré en base sous les noms `emetteur_sso` et
`sujet_sso`, sécurise ensuite l’identité technique. Un email transmis par le SSO
peut servir au contrôle, mais jamais au rattachement automatique d’un compte.

La connexion est refusée si le code adhérent est absent, inconnu, désactivé ou
incohérent avec une identité SSO déjà enregistrée.

## 7. Architecture technique

Le projet reprend les choix éprouvés de l’application Campement :

- PHP 8.4 ;
- Symfony 8.1 ;
- Doctrine ORM ;
- PostgreSQL 18 ;
- Liquibase ;
- UUID version 7 ;
- Twig, AssetMapper, Turbo et Stimulus ;
- Docker Compose ;
- Nginx et PHP-FPM ;
- PHPUnit.

Le code Symfony se trouve dans `app/`. Les paramètres d’accès locaux sont
définis dans les fichiers d’exemple destinés au développement.

L’application doit rester une application Symfony rendue côté serveur. Une SPA
séparée ne doit pas être introduite sans besoin fonctionnel démontré et décision
explicite.

## 8. Base de données

### 8.1 Langue et nommage

Le schéma, les tables, colonnes, contraintes, index et valeurs métier sont
nommés en français, sans accent et en `snake_case`.

Conventions :

- table au singulier : `utilisateur`, `inscription`, `journee` ;
- clé primaire : `id` en UUID v7, sauf clé naturelle explicitement validée ;
- clé étrangère : suffixe `_id` ;
- horodatages : `cree_le`, `modifie_le` ;
- auteur d’une action : `cree_par_id`, `modifie_par_id` ;
- booléens formulés positivement : `actif`, `selectionne`, `tenue_remise` ;
- valeurs métier stables en majuscules : `BENEVOLE`, `INDIVIDUELLE`, `DINER`.

Les seuls termes anglais admis sont ceux imposés à la frontière d’un protocole
externe. Ils sont traduits avant enregistrement en base.

### 8.2 Tables principales

Le schéma initial contient :

- `utilisateur` ;
- `identite_authentification` ;
- `thematique` ;
- `inscription` ;
- `repas_inscription` ;
- `personne_permanence` ;
- `journee` ;
- `regle_attribution_role` ;
- `synchronisation_adherents` ;
- `journal_audit`.

### 8.3 Responsabilité de Liquibase et Doctrine

Liquibase est l’unique source de vérité du schéma PostgreSQL.

Toutes les créations et évolutions de tables, colonnes, contraintes, index et
données de référence passent par un changeset dans
`database/changelog/versioned/`.

Règles impératives :

- ne jamais modifier un changeset déjà appliqué ;
- créer un nouveau fichier `Vxxx__description.sql` pour chaque évolution ;
- réserver `database/changelog/dev/` aux données locales et de test ;
- fournir un rollback pertinent lorsque cela reste sûr ;
- exécuter `make db-validate` avant chaque livraison ;
- contrôler le SQL avec `make db-sql` pour les changements sensibles.

Doctrine sert au mapping, aux associations, aux repositories et aux requêtes. Il
ne doit jamais modifier le schéma. Ne jamais utiliser :

```bash
php bin/console doctrine:schema:update --force
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Les defaults UUID PostgreSQL restent définis dans Liquibase et ne sont pas
dupliqués dans le mapping Doctrine.

### 8.4 Conservation des données

Un utilisateur est d’abord désactivé, puis supprimé physiquement avec ses
données associées après 30 jours sans réactivation. Les données détaillées
d’une campagne de septembre à août sont agrégées puis supprimées le 10 octobre
suivant. L’historique ne conserve que la date, le nombre de bénévoles par
thématique et le nombre de compagnons, sans identifiant personnel ni lien vers
les données sources.

Les suppressions en cascade ne sont admises que pour des données strictement
dépendantes sans valeur autonome, par exemple les repas d’une inscription.

## 9. Interface et charte graphique

L’interface reprend la charte et les composants du projet Campement :

- bleu SGDF comme couleur principale ;
- vert menthe comme accent ;
- typographie Sarabun ;
- navigation latérale responsive ;
- cartes blanches avec accent supérieur menthe ;
- formulaires utilisables sur mobile ;
- tableaux adaptatifs ou représentations en cartes sur petit écran ;
- messages de succès temporaires et erreurs persistantes.

Chaque écran doit être conçu et vérifié au minimum aux largeurs mobile et
ordinateur. Les contrôles doivent rester accessibles au clavier, posséder des
libellés explicites et présenter un état de focus visible.

Les calendriers et synthèses doivent rester lisibles sans dépendre uniquement de
la couleur.

## 10. Sécurité et confidentialité

- Contrôler les droits dans les contrôleurs, voters ou services métier.
- Protéger toute écriture par un jeton CSRF.
- Hacher les mots de passe avec le composant Symfony prévu à cet effet.
- Stocker uniquement le hash des jetons d’activation et de réinitialisation.
- Limiter la durée de vie des liens temporaires.
- Ne jamais journaliser les mots de passe, jetons bruts ou données médicales.
- Appliquer les durées de conservation validées aux sauvegardes et aux journaux
  sans les exposer dans la documentation versionnée du dépôt.
- Exécuter les purges depuis le service applicatif prévu à cet effet, sans
  dépendre d’une configuration manuelle non versionnée sur l’hôte.
- Normaliser les emails en minuscules sans en faire une identité métier.
- Valider les fichiers CSV, leur encodage, leur taille et leurs en-têtes.
- Échapper les contenus affichés et conserver l’échappement automatique Twig.
- Journaliser les changements de rôle, imports, synchronisations et remises de
  tenue ou de foulard.
- Préférer la désactivation à la suppression afin de préserver l’audit.

## 11. Tests attendus

Toute règle métier significative doit être couverte au niveau approprié.

Priorités de test :

- matrice des droits et interdiction des accès directs non autorisés ;
- modification limitée des champs du profil ;
- inscription uniquement pour soi-même ;
- création compagnon réservée à l’équipe pilote ;
- génération et recalcul des repas selon la période ;
- prise en compte des enfants et des effectifs compagnons ;
- interdiction du chevauchement des inscriptions individuelles ;
- calcul quotidien des synthèses ;
- anonymisation des régimes ;
- application d’une permanence à une période ;
- import CSV, doublons et rapports d’erreurs ;
- calcul du rôle par codes fonction et structure ;
- protections contre une désactivation massive lors d’une anomalie API ;
- rattachement SSO par code adhérent et contrôle `iss`/`sub`.

Les tests fonctionnels doivent vérifier le résultat HTTP et la persistance
réelle, pas uniquement l’existence d’un bouton ou d’un texte.

## 12. Commandes et déroulement d’un changement

Installation :

```bash
make install
```

Commandes courantes :

```bash
make up
make down
make console ARGS="about"
make db-validate
make db-status
make db-sql
make db-update
make test
```

Pour une évolution de schéma :

1. créer un nouveau changeset Liquibase ;
2. valider le changelog ;
3. inspecter le SQL généré ;
4. appliquer le changeset sur une base locale ;
5. mettre à jour les entités Doctrine ;
6. valider le mapping sans demander à Doctrine de modifier le schéma ;
7. exécuter les tests concernés.

Pour une évolution d’interface :

1. réutiliser les composants existants ;
2. vérifier l’affichage ordinateur et mobile ;
3. vérifier les états vide, erreur, succès et chargement ;
4. vérifier la navigation clavier et les libellés accessibles ;
5. vérifier les autorisations côté serveur.

## 13. Principes de maintenance

- Lorsqu’une conversation aboutit à une validation puis à une demande de
  commit, relire systématiquement ce document avant de créer le commit et le
  compléter avec toute nouvelle décision fonctionnelle, règle métier,
  convention technique ou contrainte de développement issue de la conversation.
- La mise à jour de ce document fait partie intégrante du changement à commiter :
  elle doit être incluse dans le même commit que l’évolution concernée. Si la
  conversation ne modifie aucune règle ou décision durable, vérifier malgré tout
  que le document reste exact avant de commiter.
- Préférer des services métier explicites aux règles dispersées dans les
  contrôleurs ou templates.
- Centraliser les calculs de repas, rôles et synthèses afin que le CSV, l’API et
  les écrans utilisent les mêmes règles.
- Ne pas exposer les détails d’intégration externe dans les entités métier.
- Conserver une trace expliquant les décisions automatiques importantes.
- Traiter les synchronisations comme des opérations rejouables et auditées.
- Préserver la compatibilité avec les données existantes à chaque évolution.
- Mettre à jour ce document lorsqu’une décision fonctionnelle ou technique est
  modifiée.
