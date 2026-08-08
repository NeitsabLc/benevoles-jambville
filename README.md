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

Doctrine ne doit jamais créer ni modifier le schéma.
