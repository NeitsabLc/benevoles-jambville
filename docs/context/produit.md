# Produit — Bénévoles Jambville

> Ce que fait Bénévoles Jambville, pour qui, selon quelles règles, et avec quel vocabulaire.
> Référence fonctionnelle : à maintenir avec le code.

## 01 Vue d'ensemble

| Élément | Valeur |
|---|---|
| Objectif | Organiser l'accueil des bénévoles et des équipes compagnons à Jambville |
| Utilisateurs | Bénévoles, salariés d'accueil, équipe pilote |
| Supports | Ordinateur et mobile |
| Hors périmètre | Identité externe (préparée dans les données, non active) ; aucune donnée de santé obligatoire |

L'application couvre les comptes, profils, inscriptions, présences, repas, couchages, transports depuis Meulan,
permanences et affectations de chambres. La version réellement déployée se vérifie dans le dépôt d'infrastructure.

## 02 Utilisateurs et habilitations

| Rôle | Peut | Périmètre de données |
|---|---|---|
| Bénévole | Consulter et modifier son profil, gérer ses inscriptions, consulter le calendrier partagé autorisé | Ses propres données |
| `ROLE_SALARIE_ACCUEIL` | Consulter les synthèses, gérer permanences, chambres et rooming selon ses droits | Données nécessaires à l'accueil |
| `ROLE_EQUIPE_PILOTE` | Droits d'accueil, plus utilisateurs, imports, thématiques, inscriptions compagnons et inscriptions d'autres bénévoles | Toute l'application |
| Administrateur technique | Exploiter l'infrastructure ; données personnelles seulement pour le support, la sécurité et la continuité | Hors application |

Les autorisations sont contrôlées côté serveur : l'interface n'est jamais à elle seule une mesure de contrôle d'accès.

## 03 Règles métier

### Profils et comptes

- Le profil contient l'identité, les coordonnées, les rôles, la tenue, l'hébergement et, de manière facultative,
  les particularités alimentaires.
- La première connexion passe par un jeton limité à sept jours.
- Un compte désactivé peut être réactivé pendant trente jours.

### Inscriptions et accueil

- Une inscription est individuelle ou concerne une équipe compagnons. Elle décrit une période, une thématique,
  les repas, le couchage, les enfants, un commentaire d'accueil et, si besoin, l'heure d'arrivée à Meulan
  pour le transport jusqu'à Jambville.
- Une présence à la journée ne crée pas de besoin de couchage et est exclue du rooming.
- Les synthèses opérationnelles peuvent être nominatives quand l'accueil l'exige ; les totaux alimentaires
  sont toujours présentés sans noms.

### Rooming, calendrier et permanences

- Le rooming répartit les personnes qui dorment sur place dans les chambres disponibles, avec contrôle
  de capacité et prise en compte des enfants.
- Le calendrier consolide présences et permanences ; configurations de chambres et disponibilités sont versionnées en base.

### Administration

- L'équipe pilote gère les utilisateurs, les règles d'attribution de rôles, les thématiques et les imports CSV
  avec prévisualisation.
- L'authentification reste locale ; la structure de données prépare une future identité externe.

## 04 Glossaire

> Un seul nom par notion, à l'écran, dans le code et en base. Bénévoles Jambville code en français (`decisions.md`).

| À l'écran | Dans le code | Table | Définition |
|---|---|---|---|
| Bénévole | `Utilisateur` | `utilisateur` | Personne ayant un compte |
| Inscription | `Inscription` | `inscription` | Venue d'un bénévole ou d'une équipe sur une période |
| Équipe compagnons | `Inscription` (type équipe) | `inscription` | Inscription collective, décrite par un nombre de personnes |
| Thématique | `Thematique` | `thematique` | Motif ou chantier d'une venue |
| Permanence | `PersonnePermanence` | `personne_permanence` | Personne assurant une permanence d'accueil |
| Présence à la journée | `Inscription` sans couchage | `inscription` | Venue sans nuit sur place |
| Rooming | `RoomingService` | — | Répartition des personnes dans les chambres |

| À utiliser | À ne jamais utiliser |
|---|---|
| Bénévole | Membre, adhérent, utilisateur (à l'écran) |
| Inscription | Réservation, séjour |
| Équipe compagnons | Groupe, unité |

## 05 Parcours principaux

1. Équipe pilote → importe ou crée un bénévole → le bénévole reçoit son lien de première connexion.
2. Bénévole → complète son profil → s'inscrit sur une période et une thématique.
3. Salarié d'accueil → consulte les synthèses → prépare repas, transports et permanences.
4. Salarié d'accueil → lance le rooming → affecte les chambres dans la limite des capacités.
