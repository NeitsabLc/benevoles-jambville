# Prompt Codex — migration de Campement

Copier le texte suivant dans la tâche Codex ouverte sur le dépôt Campement.

```text
Je souhaite réinstaller/migrer l'application Campement de l'ancien serveur
`campement` vers le nouveau serveur Proxmox `web01`, en reproduisant le
fonctionnement actuel de production sans changer l'URL, les données, les
secrets ni les règles de sécurité.

Contexte connu :
- ancien serveur applicatif : `campement`, IP `192.168.2.4` ;
- nouveau serveur : `web01`, Debian 13, IP `192.168.2.18` ;
- nouveau reverse proxy Traefik : `proxy01`, IP `192.168.2.6` ;
- ancien proxy : `192.168.2.8` ;
- Docker 29.7.2 et Docker Compose 5.4.0 sur `web01` ;
- Portainer est déjà installé sur `web01` ;
- UFW est actif sur `web01` avec `deny incoming`, `allow outgoing` et
  `deny routed` ;
- SSH est autorisé depuis `192.168.2.0/24` et `192.168.27.65` ;
- Docker utilise `iptables-nft` et la chaîne `DOCKER-USER` ;
- Bénévoles Jambville est déjà installé sur `web01` et possède sa propre règle
  persistante : ne pas la modifier ;
- répertoire cible envisagé pour Campement : `/srv/docker/campement` ;
- Traefik charge ses routes dynamiques depuis le dépôt `/srv/traefik` sur
  `proxy01`.

Objectif :
1. lire intégralement la documentation locale du projet, notamment les
   documents DAT/DIN/DEX sous `.local`, avant toute action ;
2. auditer le dépôt et l'ancienne production pour identifier la version, le
   tag, le commit, les images et digests, les fichiers de configuration et
   permissions, l'URL historique, les ports, volumes, données, migrations,
   sauvegardes, règles de pare-feu et route Traefik ;
3. préparer `web01` avec la même version et les mêmes secrets, en ne modifiant
   que les paramètres dépendant de l'hôte ;
4. reproduire le filtrage Docker existant dans une chaîne dédiée, sans copier
   les IP de conteneurs ou bridges éphémères et sans perturber Portainer ni
   Bénévoles Jambville ;
5. autoriser le port backend Campement uniquement depuis `proxy01` ;
6. transférer et vérifier les données avec une somme SHA-256 ;
7. démarrer uniquement la base, restaurer, valider les migrations, puis
   démarrer l'application ;
8. tester le backend depuis `proxy01`, vérifier son refus depuis un autre poste
   LAN, puis créer la route Traefik avec la même URL ;
9. tester HTTP, HTTPS, le certificat, les en-têtes et le rendu visuel ;
10. conserver l'ancienne installation arrêtée mais intacte pour le retour
    arrière ;
11. supprimer les dumps en clair après validation tout en conservant les
    sauvegardes chiffrées.

Mode de travail impératif :
- guide-moi commande par commande, avec un seul bloc d'étape à la fois, puis
  attends que je colle sa sortie ;
- commence par des commandes en lecture seule ;
- n'affiche jamais le contenu des secrets ;
- ne crée aucun commit et ne pousse rien sans mon autorisation explicite ;
- ne lance aucune commande destructive (`down --volumes`, suppression de
  volume, `DROP DATABASE`, écrasement de configuration) ;
- vérifie chaque précondition avant toute mutation ;
- ne suppose ni l'URL, ni le port, ni les volumes : retrouve-les dans la
  documentation et l'installation actuelle ;
- sépare clairement les commandes destinées à `campement`, `web01` et
  `proxy01`.

Commence par lire la documentation locale et auditer le dépôt, puis donne-moi
uniquement la première série de vérifications en lecture seule.
```
