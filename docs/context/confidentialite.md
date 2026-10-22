# Confidentialité — Bénévoles Jambville

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

## 10. Points à surveiller

- formaliser l’identité et l’adresse du responsable du traitement ;
- vérifier et documenter que le recueil du consentement alimentaire reste libre, spécifique, éclairé, explicite et démontrable ;
- contrôler périodiquement les inscriptions qui chevauchent deux campagnes ;
- vérifier dans l’infrastructure la planification effective des sauvegardes, maintenances et sondes ;
- maintenir la cohérence entre README, contexte projet, politique de confidentialité, conditions d’utilisation, DAT, DIN, DEX et comportement réel ;
- documenter toute activation future d’une API ou d’une identité externe avant sa mise en production.
