# Métier — Bénévoles Jambville

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
