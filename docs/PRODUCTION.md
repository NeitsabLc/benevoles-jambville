# Production sur `web01`

Ce guide décrit le déploiement de Bénévoles Jambville sous
`/srv/docker/benevole/`, derrière le Traefik central de `proxy01`. Il ne
concerne pas la stack Campement, qui conserve son propre projet Compose, son
réseau et ses volumes.

L'URL de production existante est
`https://benevoles-jambville.neitsab.net`. La migration vers `web01` conserve
strictement ce nom, les données et, pour la première bascule, les mêmes digests
d'images que la production source.

## État effectivement livré le 13 août 2026

La migration de `campement` (`192.168.2.4`) vers la VM Proxmox `web01`
(`192.168.2.18`) a été réalisée avec la release `v1.2.1`, commit
`d55aa5e8e91d9fdceaca9813d49b890a80deeef3`. Les fichiers `.env`,
`.env.release`, `app/.env` et `app/.env.local` ainsi que les données PostgreSQL
proviennent de l'ancienne production. Seules les adresses propres aux nouveaux
hôtes ont changé : backend `192.168.2.18` et proxy de confiance
`192.168.2.6`.

La route Traefik est versionnée dans le dépôt `homelab-traefik`. L'ancienne
stack reste arrêtée mais intacte pendant la période de retour arrière. Les
améliorations décrites dans la section suivante appartiennent à la prochaine
release tant qu'une nouvelle image GHCR n'a pas été publiée et livrée.

### Changements préparés pour la prochaine release

Le dépôt de travail contient, sans qu'ils soient encore présents dans les
images `v1.2.1`, les healthchecks PHP-FPM et Nginx, l'attente de leur état sain
par `make release-up`, la sortie des logs Symfony sur `stderr`, le contrôle CI
du bind PostgreSQL local, le scan Trivy des secrets et la commande
`release-maintenance-now`. Une nouvelle publication GHCR par digest est
nécessaire avant leur déploiement.

## Architecture retenue

```text
Internet
  -> Freebox : TCP 80/443
  -> proxy01 / Traefik : 192.168.2.6
  -> web01 : 192.168.2.18:8081 (autorisé seulement depuis proxy01)
  -> Nginx :8080
  -> PHP 8.4-FPM / Symfony 8.1 :9000
  -> PostgreSQL 18 (réseau Docker privé et 127.0.0.1:5434 pour l'administration locale)
```

Services Compose :

- `nginx` est le seul service publié sur l'adresse LAN de `web01` ;
- `php` exécute Symfony et PHP-FPM sans privilèges root ;
- `database` conserve les données dans le volume nommé
  `benevole_jambville_donnees_postgresql` ;
- `liquibase`, activé à la demande avec le profil `outils`, est l'unique moteur
  de migration ;
- `backup` crée immédiatement une sauvegarde chiffrée au démarrage, puis une
  sauvegarde toutes les 24 heures, avec une rétention locale de sept jours ;
- `maintenance` exécute quotidiennement les désactivations et purges métier.

Il n'existe ni Redis, ni file de messages, ni worker asynchrone, ni stockage
d'uploads durable. Les CSV importés sont traités temporairement. Les caches,
sessions et logs applicatifs sont éphémères. Dans `v1.2.1`, Symfony écrit ses
logs dans le tmpfs du conteneur ; la prochaine release les enverra sur `stderr`
pour collecte et rotation par Docker.

## Fichiers sensibles

Les fichiers suivants restent exclusivement sur `web01` et sont ignorés par
Git ainsi que par le contexte de construction Docker :

- `.env` : paramètres Compose et mots de passe PostgreSQL ;
- `.env.release` : références GHCR immuables par digest ;
- `app/.env` et `app/.env.local` : secret Symfony, transport SMTP et éventuels
  surcharges locales ;
- `/srv/backups/benevole-jambville/` : dumps PostgreSQL chiffrés ;
- `var/migration/` : éventuel dump de migration temporaire.

Ne jamais placer la clé privée `age` de restauration sur `web01`. Conserver
cette clé et sa phrase de récupération dans un coffre distinct, puis tester sa
restauration régulièrement depuis un environnement isolé.

## Préparation de `web01`

Prérequis : Docker Engine, le plugin Docker Compose, Git, `make`, UFW,
`iptables-nft`, le client `cosign` récent pour la vérification des signatures
Sigstore, et un accès en lecture aux images privées
GHCR.

```bash
sudo install -d -m 0750 -o <compte-deploiement> -g <groupe-deploiement> /srv/docker/benevole
git clone --branch main --single-branch \
  https://github.com/NeitsabLc/benevoles-jambville.git \
  /srv/docker/benevole
cd /srv/docker/benevole

# Pour une migration à l'identique, se placer d'abord sur le commit source.
git switch --detach d55aa5e8e91d9fdceaca9813d49b890a80deeef3

cp deploy/web01/compose.env.example .env
cp deploy/web01/app.env.example app/.env
cp .env.release.example .env.release
chmod 0600 .env .env.release
chown <compte-deploiement>:www-data app/.env
chmod 0640 app/.env

sudo install -d -m 0700 -o 70 -g 70 /srv/backups/benevole-jambville
sudo install -d -m 0700 -o <compte-deploiement> -g <groupe-deploiement> \
  /srv/docker/benevole/var/migration
```

Lors d'une migration, préférer le transfert direct des quatre fichiers source
`.env`, `.env.release`, `app/.env` et `app/.env.local`, puis ne modifier que les
adresses d'hôte et de proxy. Les permissions livrées sont `0600` pour les deux
fichiers racine et `0640`, groupe `www-data`, pour les deux fichiers Symfony.

Remplacer les cinq secrets PostgreSQL par cinq valeurs indépendantes, et
`APP_SECRET` par une sixième valeur. Générer par exemple chaque secret avec
`openssl rand -hex 32`, puis le conserver dans le gestionnaire de secrets. Ne
pas copier la sortie dans un ticket, un log ou l'historique du shell.

Renseigner aussi :

- `BACKUP_AGE_RECIPIENT` avec la clé publique `age` ;
- `MAILER_DSN` et `MAILER_FROM` avec le transport SMTP réel ;
- `APP_HOSTNAME=benevoles-jambville.neitsab.net` pour le healthcheck Nginx ;
- `TRUSTED_PROXIES=192.168.2.6` dans la configuration Compose et Symfony ;
- `BACKUP_DIR=/srv/backups/benevole-jambville` ;
- `DEFAULT_URI` et `TRUSTED_HOST_PATTERN` si le domaine diffère ;
- le SHA Git et les cinq références `ghcr.io/...@sha256:...` dans
  `.env.release`, à partir du workflow GitHub validé pour la version choisie.

Les mots de passe sont injectés au runtime et ne sont jamais copiés dans les
images. Les valeurs de construction factices visibles dans le Dockerfile ne
sont utilisées que pour compiler les assets et ne donnent accès à aucun
service.

Authentifier ensuite Docker avec un compte ou token limité à la lecture des
packages :

```bash
docker login ghcr.io
make release-config
make release-verify
make release-pull
```

Ne pas conserver sur le serveur le jeton GHCR plus longtemps que nécessaire.
Installer Cosign depuis une distribution officielle et conserver sa version à
jour : la vérification exige l'identité OIDC exacte du workflow, la branche
`main` et le SHA Git attendu.

## Pare-feu de `web01`

`web01` utilise UFW avec les politiques `deny incoming`, `allow outgoing` et
`deny routed`. SSH est autorisé depuis `192.168.2.0/24` et depuis le poste
WireGuard `192.168.27.65`. Docker utilise `iptables-nft`; les ports publiés par
Docker sont donc filtrés dans `DOCKER-USER`, indépendamment d'UFW.

Sur une VM neuve, ajouter les deux autorisations SSH avant d'activer UFW et
garder la session courante ouverte jusqu'au test d'une seconde connexion :

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw default deny routed
sudo ufw logging low
sudo ufw allow from 192.168.2.0/24 to 192.168.2.18 port 22 proto tcp \
  comment 'SSH depuis LAN'
sudo ufw allow from 192.168.27.65 to 192.168.2.18 port 22 proto tcp \
  comment 'SSH depuis Mac via WireGuard'
sudo ufw --force enable
sudo ufw status verbose
```

La règle applicative effectivement livrée est :

```text
source 192.168.2.6/32 -> destination 192.168.2.18 TCP/8081 : ACCEPT
toute autre source    -> destination 192.168.2.18 TCP/8081 : DROP
```

Le bind Compose sur `192.168.2.18` évite toute écoute sur `0.0.0.0`, mais ne
remplace pas cette restriction source. Le script et son unité systemd sont
versionnés dans `deploy/web01/` et s'installent ainsi :

```bash
sudo install -m 0755 deploy/web01/benevole-docker-firewall \
  /usr/local/sbin/benevole-docker-firewall
sudo install -m 0644 deploy/web01/benevole-docker-firewall.service \
  /etc/systemd/system/benevole-docker-firewall.service
sudo systemctl daemon-reload
sudo systemctl enable --now benevole-docker-firewall.service
```

Le service doit être installé avant le premier démarrage de Nginx afin que le
port ne soit jamais brièvement ouvert à tout le LAN. Il n'affecte ni Portainer
sur `9000/9443`, ni les autres projets Compose.

Avant et après la modification, sauvegarder et relire la configuration active :

```bash
sudo /usr/sbin/nft list ruleset
sudo /usr/sbin/iptables -S DOCKER-USER
sudo /usr/sbin/iptables -S BENEVOLE_8081
```

Les tests attendus, une fois Nginx démarré, sont :

```bash
# Depuis proxy01 : doit répondre en HTTP.
curl --fail --show-error --head http://192.168.2.18:8081/connexion \
  --header 'Host: benevoles-jambville.neitsab.net'

# Depuis un autre poste LAN : doit être refusé ou expirer.
curl --connect-timeout 3 http://192.168.2.18:8081/connexion
```

Ne publier aucune règle PostgreSQL sur l'adresse LAN. Le mapping de production
`127.0.0.1:5434 -> 5432` reste disponible seulement depuis `web01`, comme sur
l'ancienne production.

L'hyperviseur Proxmox peut ajouter un second niveau de filtrage, mais ne remplace
pas UFW ni la chaîne `DOCKER-USER` dans la VM. Le test réseau depuis
`proxy01` constitue la vérification de bout en bout.

## Migration de l'ancienne base

### 1. Gel et dump final de la source

Sur l'ancien serveur, arrêter les composants susceptibles d'écrire, mais garder
PostgreSQL actif :

```bash
cd /srv/benevoles-jambville

dc() {
  docker compose --env-file .env --env-file .env.release \
    -f compose.yaml -f compose.prod.yaml -f compose.release.yaml "$@"
}

dc stop nginx php maintenance backup
make release-backup-now
```

Après le durcissement des rôles, le compte d'amorçage
`benevole_jambville` est `NOLOGIN`. Le dump doit donc utiliser le compte de
sauvegarde en lecture seule et son mot de passe déjà injecté dans le conteneur :

```bash
umask 077
DUMP_FINAL="/tmp/benevole-jambville-final-$(date -u +%Y%m%dT%H%M%SZ).dump"
DUMP_PARTIEL="${DUMP_FINAL}.partial"

if dc exec -T database sh -ec '
  export PGPASSWORD="$POSTGRES_BACKUP_PASSWORD"
  exec pg_dump --host=127.0.0.1 \
    --username="$POSTGRES_BACKUP_USER" \
    --dbname="$POSTGRES_DB" \
    --format=custom --no-owner --no-acl
' > "$DUMP_PARTIEL"
then
  mv "$DUMP_PARTIEL" "$DUMP_FINAL"
else
  rm -f "$DUMP_PARTIEL"
  exit 1
fi

dc exec -T database pg_restore --list < "$DUMP_FINAL" >/dev/null
sha256sum "$DUMP_FINAL" > "${DUMP_FINAL}.sha256"
sha256sum --check "${DUMP_FINAL}.sha256"
```

Copier le dump et sa somme vers le répertoire `var/migration/` protégé de
`web01`. Comme la somme créée sur la source contient un chemin absolu, comparer
les deux empreintes après transfert :

```bash
set -a
. ./.env.release
set +a

SOMME_SOURCE=$(awk '{print $1}' "${DUMP_CIBLE}.sha256")
SOMME_CIBLE=$(sha256sum "$DUMP_CIBLE" | awk '{print $1}')
test "$SOMME_SOURCE" = "$SOMME_CIBLE"

docker run --rm --interactive --entrypoint pg_restore \
  "$BENEVOLE_RELEASE_POSTGRES_IMAGE" --list < "$DUMP_CIBLE" >/dev/null
```

L'option `--interactive` est indispensable pour transmettre le dump à
`pg_restore` dans le conteneur.

### 2. Initialisation de la cible

Depuis `/srv/docker/benevole`, démarrer uniquement PostgreSQL avec l'image de la
release :

```bash
docker compose --env-file .env --env-file .env.release \
  -f compose.yaml -f compose.prod.yaml -f compose.release.yaml \
  up -d --no-build --wait database
```

Vérifier que la base cible est vide avant la restauration. Restaurer le dump
avec le compte d'amorçage, afin que la bascule de rôles puisse ensuite transférer
la propriété des objets au migrateur :

```bash
docker compose --env-file .env --env-file .env.release \
  -f compose.yaml -f compose.prod.yaml -f compose.release.yaml \
  exec -T database sh -ec \
  'export PGPASSWORD="$POSTGRES_PASSWORD"; \
   pg_restore --host=127.0.0.1 \
    --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
    --exit-on-error --single-transaction --no-owner --no-acl' \
  < var/migration/benevole-jambville-final-<horodatage>.dump
```

Ne pas employer `docker compose down --volumes`, `DROP DATABASE` ou `--clean`
sur une cible contenant des données non vérifiées.

### 3. Migrations et durcissement des rôles

Pour la première migration seulement, saisir le mot de passe du compte
d'amorçage sans l'écrire dans la ligne de commande :

```bash
read -r -s -p 'Mot de passe PostgreSQL d’amorçage : ' POSTGRES_BOOTSTRAP_PASSWORD
printf '\n'
export POSTGRES_BOOTSTRAP_PASSWORD
trap 'unset POSTGRES_BOOTSTRAP_PASSWORD' EXIT INT TERM

docker compose --env-file .env --env-file .env.release \
  -f compose.yaml -f compose.prod.yaml -f compose.release.yaml \
  --profile outils run --rm \
  -e LIQUIBASE_COMMAND_USERNAME=benevole_jambville \
  -e LIQUIBASE_COMMAND_PASSWORD="$POSTGRES_BOOTSTRAP_PASSWORD" \
  liquibase validate

docker compose --env-file .env --env-file .env.release \
  -f compose.yaml -f compose.prod.yaml -f compose.release.yaml \
  --profile outils run --rm \
  -e LIQUIBASE_COMMAND_USERNAME=benevole_jambville \
  -e LIQUIBASE_COMMAND_PASSWORD="$POSTGRES_BOOTSTRAP_PASSWORD" \
  liquibase status
```

Lire le résultat avant de continuer. Si `V001` apparaît en attente alors que
le schéma restauré contient déjà les tables applicatives, arrêter la procédure :
il faut réconcilier l'historique Liquibase et le schéma source, sans exécuter
aveuglément `changelog-sync`. Si le statut est cohérent, appliquer les
changesets en attente :

```bash
docker compose --env-file .env --env-file .env.release \
  -f compose.yaml -f compose.prod.yaml -f compose.release.yaml \
  --profile outils run --rm \
  -e LIQUIBASE_COMMAND_USERNAME=benevole_jambville \
  -e LIQUIBASE_COMMAND_PASSWORD="$POSTGRES_BOOTSTRAP_PASSWORD" \
  liquibase update

unset POSTGRES_BOOTSTRAP_PASSWORD
make db-finalize-role-hardening
make release-db-status
```

Le durcissement transfère les objets au rôle migrateur, vérifie les comptes
applicatif, sauvegarde et administration, puis interdit la connexion au compte
d'amorçage. Les migrations ultérieures utilisent exclusivement
`benevole_jambville_migrator` via `make release-db-update`.

Pour la migration du 13 août 2026, la source et la cible ont été comparées sur
les relations `utilisateur`, `inscription`, `journee`, `repas_inscription` et
sur les huit lignes de `public.databasechangelog`. Tout écart doit interrompre
la bascule avant le démarrage de PHP.

Contrôler ensuite le rôle réellement utilisé par Symfony :

```bash
make release-backup-now
make release-up
make db-verify-role-switch
make release-ps
curl --fail --show-error --head http://192.168.2.18:8081/connexion \
  --header 'Host: benevoles-jambville.neitsab.net'
```

Exécuter les parcours fonctionnels essentiels : connexion, consultation du
calendrier, profil, création ou modification contrôlée, email d'activation et
lecture des logs. Après validation, arrêter l'application cible jusqu'à la
fenêtre de bascule ou la laisser inaccessible derrière le pare-feu et Traefik.

### 4. Bascule finale

1. annoncer une fenêtre de maintenance et empêcher toute nouvelle écriture sur
   l'ancienne application ;
2. produire un nouveau dump final et vérifier sa somme ;
3. restaurer ce dump sur une cible remise dans l'état vierge validé pendant la
   répétition, sans toucher à l'ancienne base ;
4. appliquer Liquibase, durcir les rôles, créer puis vérifier une sauvegarde ;
5. démarrer les cinq services de production et exécuter les tests fonctionnels ;
6. ajouter le flux Traefik, puis surveiller les erreurs HTTP, PHP et PostgreSQL ;
7. supprimer par `shred --zero --remove` les dumps et sommes en clair sur les
   deux serveurs après vérification d'une archive Age cible ;
8. garder l'ancienne stack arrêtée mais intacte pendant la période de retour
   arrière.

Une répétition et une bascule finale ne doivent jamais restaurer successivement
deux dumps par-dessus la même base sans nettoyage explicitement contrôlé.
Ne jamais supprimer le volume de l'ancienne production pendant la période de
retour arrière.

## Configuration Traefik sur `proxy01`

Dans le dépôt privé `NeitsabLc/homelab-traefik`, installer comme
`dynamic/benevoles-jambville.yml` la configuration canonique versionnée dans
`deploy/proxy01/benevoles-jambville.yml` :

```yaml
http:
  routers:
    benevoles-jambville-http:
      rule: "Host(`benevoles-jambville.neitsab.net`)"
      entryPoints:
        - web
      middlewares:
        - benevoles-jambville-https
      service: benevoles-jambville

    benevoles-jambville-https:
      rule: "Host(`benevoles-jambville.neitsab.net`)"
      entryPoints:
        - websecure
      service: benevoles-jambville
      tls:
        certResolver: letsencrypt

  middlewares:
    benevoles-jambville-https:
      redirectScheme:
        scheme: https
        permanent: true

  services:
    benevoles-jambville:
      loadBalancer:
        passHostHeader: true
        servers:
          - url: "http://192.168.2.18:8081"
```

Bénévoles Jambville étant une application publique, ne pas lui appliquer le
middleware `ipAllowList` réservé aux interfaces d'administration. Le pare-feu
de `web01` protège le backend ; Traefik reste le seul client autorisé.

Valider sur `proxy01` :

```bash
cd /srv/traefik
git diff --check
docker compose config --quiet
docker logs traefik --since=2m
curl --head --resolve benevoles-jambville.neitsab.net:80:127.0.0.1 \
  http://benevoles-jambville.neitsab.net/
curl --fail --head --resolve benevoles-jambville.neitsab.net:443:127.0.0.1 \
  https://benevoles-jambville.neitsab.net/connexion
```

Traefik surveille le répertoire dynamique ; aucun redémarrage n'est normalement
nécessaire. Ajouter ou vérifier aussi l'enregistrement DNS public vers la
Freebox. Un override Pi-hole vers `192.168.2.6` est facultatif pour conserver le
même chemin depuis le LAN.

Le résultat livré est une redirection HTTP `308`, suivie d'une réponse HTTPS
HTTP/2 `200` avec certificat Let's Encrypt valide. Le fichier doit être commité
et poussé dans le dépôt Traefik après validation ; le chargement dynamique sur
disque ne suffit pas à garantir sa restauration après incident.

## Exploitation courante

```bash
cd /srv/docker/benevole

# État et santé
make release-ps

# Journaux bornés
docker compose -f compose.yaml -f compose.prod.yaml logs \
  --since=30m --tail=200 nginx php database maintenance backup

# Sauvegarde immédiate avant toute modification
make release-backup-now

# Migrations en attente, puis application explicite
make release-db-status
make release-db-update

# Maintenance métier immédiate si nécessaire
make release-maintenance-now

# Arrêt sans suppression des volumes
docker compose --env-file .env --env-file .env.release \
  -f compose.yaml -f compose.prod.yaml -f compose.release.yaml stop
```

Ne jamais ajouter `-v`/`--volumes` à une commande `down` de production.

## Mise à jour

Pour chaque version :

```bash
cd /srv/docker/benevole
git fetch --prune origin
git switch main
git pull --ff-only

# Mettre .env.release à jour avec les cinq nouveaux digests validés.
make release-pull
make release-backup-now
make release-db-status
make release-db-update
make release-up
make release-ps
```

Tester immédiatement la page de connexion et les parcours métier. Les images
sont vérifiées par digest et par signature Sigstore avant téléchargement ;
`release-up` interdit les reconstructions locales et attend les healthchecks.

## Sauvegarde et restauration

Une sauvegarde est valide seulement si elle est lisible, déchiffrable et
restaurable. Copier régulièrement les archives `.dump.age` vers un stockage
hors de `web01` — par exemple un partage sauvegardé du NAS — sans y copier la
clé privée. Surveiller l'âge et la taille du dernier fichier :

```bash
sudo find /srv/backups/benevole-jambville -maxdepth 1 -type f \
  -name '*.dump.age' \
  -printf '%TY-%Tm-%Td %TH:%TM %s %p\n' | sort | tail
```

L'image de sauvegarde utilise BusyBox : son `find` ne prend pas en charge
`-printf`. Pour lister les archives depuis le conteneur, employer
`ls -lht /backups`.

La CI exécute déjà un test automatisé avec une paire de clés éphémère. Pour
vérifier une archive réelle, monter temporairement la clé privée en lecture
seule et saisir le mot de passe du rôle administratif sans l'ajouter au shell :

```bash
read -r -s -p 'Mot de passe PostgreSQL administrateur : ' POSTGRES_ADMIN_PASSWORD
printf '\n'
export POSTGRES_ADMIN_PASSWORD

docker compose --env-file .env --env-file .env.release \
  -f compose.yaml -f compose.prod.yaml -f compose.release.yaml \
  run --rm --no-deps \
  -e BACKUP_AGE_IDENTITY_FILE=/run/age-identity \
  -e POSTGRES_USER=benevole_jambville_admin \
  -e PGPASSWORD="$POSTGRES_ADMIN_PASSWORD" \
  -e RESTORE_DATABASE_NAME=benevole_jambville_restore_check \
  --volume /media/support-securise/age-identity.txt:/run/age-identity:ro \
  --entrypoint /usr/local/bin/benevole-jambville-verify-backup \
  backup /backups/<archive.dump.age>

unset POSTGRES_ADMIN_PASSWORD
```

Effectuer ce contrôle dans une fenêtre planifiée. Le script crée une base
distincte, compare le nombre d'utilisateurs avec la source, puis supprime la
base de contrôle. Ne jamais restaurer un test dans `benevole_jambville` ;
vérifier aussi manuellement plusieurs tables et retirer le support contenant la
clé dès la fin.

## Diagnostic

```bash
docker compose -f compose.yaml -f compose.prod.yaml ps
docker inspect --format '{{json .State.Health}}' \
  benevole_jambville-nginx-1
docker inspect --format '{{json .State.Health}}' \
  benevole_jambville-php-1
docker inspect --format '{{json .State.Health}}' \
  benevole_jambville-database-1
make release-db-status
sudo ss -lntp | grep ':8081'
sudo ss -lntp | grep ':5434'
```

Résultats attendus : Nginx est publié sur `192.168.2.18:8081`, PostgreSQL est
publié uniquement sur `127.0.0.1:5434` vers le port conteneur `5432`, et aucun
port PostgreSQL n'écoute sur l'adresse LAN. Dans `v1.2.1`, seul PostgreSQL
possède le healthcheck effectivement livré ; les healthchecks PHP-FPM et Nginx
décrits plus haut arriveront avec la prochaine release. Pour les erreurs HTTP,
corréler les logs Traefik sur `proxy01`, Nginx/PHP sur `web01`, puis
PostgreSQL. Les processeurs de logs masquent les jetons d'activation, cookies,
mots de passe, DSN et adresses email ; ne pas augmenter le niveau de log en
production sans revue.

## Retour arrière

Le retour arrière réseau le plus sûr consiste à remettre le service Traefik sur
l'ancienne URL, puis à arrêter la nouvelle stack sans supprimer son volume.
Conserver cependant à l'esprit que toute écriture acceptée après la bascule
n'existe pas dans l'ancienne base. Si des écritures ont eu lieu, décider
explicitement de leur reprise avant de rouvrir l'ancienne application.

Pour un simple rollback applicatif sans changement de schéma, remettre dans
`.env.release` les cinq digests précédemment validés, puis exécuter
`make release-pull` et `make release-up`. Si une migration n'est pas compatible
avec l'ancienne image, arrêter la stack, restaurer la sauvegarde pré-migration
dans une base contrôlée, puis seulement relancer les anciens digests. Ne jamais
supposer qu'un rollback Liquibase destructif est sans perte.

## CI/CD

La CI valide Composer et ImportMap, audite Composer et npm, exécute PHPStan, le
style, PHPUnit, Axe et Playwright sur Chromium/Firefox/mobile, puis analyse les
cinq images avec Trivy. Les commits ordinaires de `main` ne publient rien.
Release Please maintient une pull request de version ; sa fusion publie une
GitHub Release qui construit, signe et teste les cinq images, puis promeut les
mêmes digests sans reconstruction.

Le dépôt `homelab-deploy` reçoit ensuite le SHA immuable et déploie
automatiquement la recette sur `web02`. La production reste une promotion
manuelle depuis ce dépôt : elle vérifie la release et l’égalité des digests,
crée une sauvegarde chiffrée avant Liquibase, puis déploie sur `web01` avec le
compte SSH minimal dédié.
