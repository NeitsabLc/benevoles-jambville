# Bénévoles Jambville

Application de gestion des profils, inscriptions, présences et besoins d'accueil des bénévoles de Jambville.

## Socle technique

- PHP 8.4 et Symfony 8.1 ;
- PostgreSQL 18 ;
- Doctrine ORM pour le mapping applicatif ;
- Liquibase comme unique source de vérité du schéma ;
- Twig, AssetMapper, Turbo et Stimulus ;
- Docker Compose et Nginx.

## Démarrage

```bash
make install
```

L'application est ensuite accessible sur <http://localhost:8081>.

## Base de données

Les changements versionnés se trouvent dans `database/changelog/versioned`. Un changeset déjà appliqué ne doit jamais être modifié : toute évolution doit créer un nouveau fichier `Vxxx`.

Commandes utiles :

```bash
make db-validate
make db-status
make db-sql
make db-update
```

Pour charger aussi les comptes et données réservés au développement :

```bash
make db-dev-update
```

Doctrine ne doit jamais créer ni modifier le schéma.

## Envoi des emails

Les nouveaux comptes créés par import CSV reçoivent leur lien de première connexion par SMTP. Configurez ces variables dans `app/.env.local` en adaptant l’hôte, le port et les identifiants :

```dotenv
MAILER_DSN=smtp://utilisateur:mot-de-passe@smtp.exemple.fr:587?encryption=tls&auth_mode=login
MAILER_FROM=no-reply@jambville.sgdf.fr
```

En développement, la valeur par défaut `null://null` neutralise la livraison réelle des emails.
