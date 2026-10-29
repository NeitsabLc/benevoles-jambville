# Confidentialité — Bénévoles Jambville

> Données personnelles traitées, utilité, durée de conservation et mesures de protection.
> Base de l'analyse d'impact (PIA) au sens du RGPD, exigée par l'accueil SGDF (critère 2.33).

## 01 Règles communes

- **Confidentialité dès la conception** : aucune donnée personnelle n'est ajoutée sans utilité écrite ici,
  durée de conservation et mécanisme de suppression, dès le premier développement.
- **Minimisation** : collecter le strict nécessaire ; préférer un nombre ou un agrégat à une donnée nominative.
- **Suppression dès la fin de l'usage** : chaque donnée a une purge automatique, testée, déclenchée
  au plus tôt (fin de campagne, désactivation, expiration) ; l'anonymisation remplace la suppression
  seulement quand une trace est nécessaire.
- **Cloisonnement** : une donnée n'est visible que dans le périmètre de son propriétaire ;
  tout accès est vérifié côté serveur et couvert par un test.
- Aucune donnée personnelle dans les journaux, les dépôts, les tickets ou les jeux de test.

## 02 Finalité

Organiser l'accueil des bénévoles et des équipes compagnons à Jambville : inscriptions, repas, couchages,
transports, permanences et rooming.

## 03 Données personnelles

| Données | Personnes | Utilité | Conservation | Purge |
|---|---|---|---|---|
| Nom, prénom, e-mail, téléphone | Bénévoles | Compte, contact, accueil | Jusqu'à 30 jours après la désactivation du compte | `app:comptes:purger-desactives` |
| Code adhérent, fonction, structure, rôle | Bénévoles | Attribution automatique des rôles | Avec le compte | `app:comptes:purger-desactives` |
| Particularités alimentaires (végétarien, sans lactose, sans gluten, autre) | Bénévoles | Adapter le self et les repas | Effacées dès la désactivation ; retrait possible à tout moment depuis le profil | Désactivation du compte |
| Tenue, foulard, besoin de couchage | Bénévoles | Logistique d'accueil | Avec le compte | `app:comptes:purger-desactives` |
| Empreinte du mot de passe | Bénévoles | Authentification | Avec le compte | `app:comptes:purger-desactives` |
| Jeton de première connexion | Bénévoles | Activation du compte | Sept jours au plus, effacé à l'usage | Expiration |
| Inscriptions (période, repas, couchage, enfants, commentaire, heure d'arrivée à Meulan) | Bénévoles, équipes compagnons | Organisation de l'accueil | Jusqu'au 10 octobre suivant la campagne (1er septembre – 31 août) | `app:donnees:purger-campagne-precedente` |
| Nom des personnes de permanence | Personnes de permanence | Planning d'accueil | Un an après la fin de la permanence | À mettre en place (`etat.md`) |

## 04 Données non personnelles

- Statistiques strictement anonymes : conservées sans limite de durée.
- Totaux alimentaires : toujours sans noms.

## 05 Mesures de protection

- Base légale des particularités alimentaires : consentement, matérialisé par le choix de renseigner ces champs ;
  explicite lorsqu'elles révèlent une information de santé, et retirable en supprimant l'information du profil.
- Purges lancées par le service `maintenance` (`docker/php/maintenance.sh`).
- Accès par rôles ; synthèses nominatives limitées aux besoins de l'accueil.
- Mots de passe hachés ; données sensibles masquées dans les journaux (`Logging/SensitiveDataProcessor.php`).
- Rôles PostgreSQL séparés ; l'application ne peut pas modifier le schéma.
- Sauvegardes chiffrées, supprimées après sept jours.
- Journaux Docker limités à cinq fichiers de 10 Mio par service.

## 06 Droits des personnes

| Droit | Comment l'exercer |
|---|---|
| Accès, rectification | Profil du bénévole ; sinon demande à l'équipe pilote |
| Retrait du consentement alimentaire | Suppression des informations depuis le profil |
| Effacement | Désactivation du compte, puis suppression après trente jours |
| Contact | contact@neitsab.net, en attendant la désignation du responsable de traitement |

## 07 Points à surveiller

- Formaliser l'identité et l'adresse du responsable du traitement.
- Vérifier et documenter que le consentement alimentaire reste libre, spécifique, éclairé, explicite et démontrable.
- Inscriptions qui chevauchent deux campagnes : conservées pour la continuité de l'accueil ; elles exigent une
  suppression manuelle ou celle du compte. Limite connue, à revoir périodiquement.
- Vérifier la planification effective des sauvegardes, purges et sondes.
- Maintenir la cohérence entre README, contexte, politique de confidentialité, conditions d'utilisation et comportement réel.
- Documenter toute activation future d'une API ou d'une identité externe avant sa mise en production.
