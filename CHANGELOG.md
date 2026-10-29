# Historique des versions

## [1.6.2](https://github.com/NeitsabLc/benevoles-jambville/compare/v1.6.1...v1.6.2) (2026-10-29)


### Corrections

* **deps:** corriger CVE-2026-45070 en mettant symfony/mime à jour ([#83](https://github.com/NeitsabLc/benevoles-jambville/issues/83)) ([fe0346d](https://github.com/NeitsabLc/benevoles-jambville/commit/fe0346db70525ba8378d94c51bd03808608119aa))


### Documentation

* aligner les fichiers de contexte sur les gabarits communs ([#82](https://github.com/NeitsabLc/benevoles-jambville/issues/82)) ([0637093](https://github.com/NeitsabLc/benevoles-jambville/commit/06370933cdc2738be9f8710f2f4b1d81474d3b81))
* partager les consignes des assistants et le contexte du projet ([#76](https://github.com/NeitsabLc/benevoles-jambville/issues/76)) ([7da3af5](https://github.com/NeitsabLc/benevoles-jambville/commit/7da3af54e91a3c85181dce212535f91f883efa5e))
* structurer AGENTS.md, architecture, infra et design system ([#79](https://github.com/NeitsabLc/benevoles-jambville/issues/79)) ([26ec83b](https://github.com/NeitsabLc/benevoles-jambville/commit/26ec83bfc806104cc375362fda61cbd015705e69))

## [1.6.1](https://github.com/NeitsabLc/benevoles-jambville/compare/v1.6.0...v1.6.1) (2026-10-21)


### Corrections

* **deps:** corriger Symfony, les images et les tests Dependabot ([#72](https://github.com/NeitsabLc/benevoles-jambville/issues/72)) ([09f5ee1](https://github.com/NeitsabLc/benevoles-jambville/commit/09f5ee1a9e13420c10022fe799e8d846f73bdd90))
* **security:** préparer les images PHP 8.4.27 ([#75](https://github.com/NeitsabLc/benevoles-jambville/issues/75)) ([de919ff](https://github.com/NeitsabLc/benevoles-jambville/commit/de919ff825cfd954f24fb008c4a42f779b478a37))


### Dépendances

* **deps:** bump github/codeql-action/upload-sarif ([#74](https://github.com/NeitsabLc/benevoles-jambville/issues/74)) ([4211bd2](https://github.com/NeitsabLc/benevoles-jambville/commit/4211bd26e71fa28cc3445f213f250cba7e568448))

## [1.6.0](https://github.com/NeitsabLc/benevoles-jambville/compare/v1.5.1...v1.6.0) (2026-09-30)


### Fonctionnalités

* **rooming:** exclure les présences à la journée ([#57](https://github.com/NeitsabLc/benevoles-jambville/issues/57)) ([549c4fb](https://github.com/NeitsabLc/benevoles-jambville/commit/549c4fbd48b3382ff50ef0d8d7afe01e3807f89b))


### Corrections

* actualiser la documentation et sécuriser Liquibase ([#66](https://github.com/NeitsabLc/benevoles-jambville/issues/66)) ([4bb3eba](https://github.com/NeitsabLc/benevoles-jambville/commit/4bb3ebaf923d28b5ff8bd47105cf5a8ce69cf2f1))


### Refactorisation

* renforcer la sûreté des types ([#61](https://github.com/NeitsabLc/benevoles-jambville/issues/61)) ([4d61bbb](https://github.com/NeitsabLc/benevoles-jambville/commit/4d61bbbd79c4b9db62cc08e2c46a7381aaa19d62))
* séparer la configuration du rooming ([#64](https://github.com/NeitsabLc/benevoles-jambville/issues/64)) ([7481dea](https://github.com/NeitsabLc/benevoles-jambville/commit/7481dea92532db7a9dc6899cfe3154e8b4a08ba4))


### Documentation

* formaliser la revue par le mainteneur unique ([#62](https://github.com/NeitsabLc/benevoles-jambville/issues/62)) ([c1e6ceb](https://github.com/NeitsabLc/benevoles-jambville/commit/c1e6ceb0f64be07a777797ba2c2a2b367b7965c6))

## [1.5.1](https://github.com/NeitsabLc/benevoles-jambville/compare/v1.5.0...v1.5.1) (2026-09-28)


### Corrections

* embarquer les migrations Liquibase ([#59](https://github.com/NeitsabLc/benevoles-jambville/issues/59)) ([19f17f5](https://github.com/NeitsabLc/benevoles-jambville/commit/19f17f54f7e683bc14947bb96e9d1b331b0e4ed7))

## [1.5.0](https://github.com/NeitsabLc/benevoles-jambville/compare/v1.4.0...v1.5.0) (2026-09-28)


### Fonctionnalités

* enrichir les inscriptions et la synthèse ([#56](https://github.com/NeitsabLc/benevoles-jambville/issues/56)) ([99a7b30](https://github.com/NeitsabLc/benevoles-jambville/commit/99a7b3011e8e93571f3e4ff77e5bc55790da18c8))


### Corrections

* **ci:** propager l'environnement et fiabiliser Betterleaks ([#55](https://github.com/NeitsabLc/benevoles-jambville/issues/55)) ([35a1e18](https://github.com/NeitsabLc/benevoles-jambville/commit/35a1e18121f101a8f22c5fd4c42ae3ed93f50ca9))
* **deps:** mettre à jour doctrine/doctrine-bundle en 3.3.2 ([#52](https://github.com/NeitsabLc/benevoles-jambville/issues/52)) ([77c7b05](https://github.com/NeitsabLc/benevoles-jambville/commit/77c7b051f3ad0617f29089e2c07361fc8f43a50c))


### Dépendances

* **deps-dev:** bump symfony/maker-bundle ([#51](https://github.com/NeitsabLc/benevoles-jambville/issues/51)) ([f056dc3](https://github.com/NeitsabLc/benevoles-jambville/commit/f056dc36dde702f86fed762c16af3918842c98c1))
* **deps-dev:** bump the tests-navigateur group across 1 directory with 2 updates ([#50](https://github.com/NeitsabLc/benevoles-jambville/issues/50)) ([9a16c3a](https://github.com/NeitsabLc/benevoles-jambville/commit/9a16c3adb031adf04e2750f98bdc0c2307369859))
* **deps:** bump the actions-github group across 1 directory with 2 updates ([#54](https://github.com/NeitsabLc/benevoles-jambville/issues/54)) ([c5c518a](https://github.com/NeitsabLc/benevoles-jambville/commit/c5c518a26e27c3dc8a2a4db6c16e9a71638026b0))

## [1.4.0](https://github.com/NeitsabLc/benevoles-jambville/compare/v1.3.3...v1.4.0) (2026-09-22)

### Fonctionnalités

* administrer le rooming et les disponibilités des chambres ([3708dbc](https://github.com/NeitsabLc/benevoles-jambville/commit/3708dbcf3db58c5d0649713f2a3bc082d82a1f14))
* afficher le prochain séjour sur le compte bénévole ([880ed3d](https://github.com/NeitsabLc/benevoles-jambville/commit/880ed3dc515aa6de0c554edf08a18a96de815cbd))

## [1.3.3](https://github.com/NeitsabLc/benevoles-jambville/compare/v1.3.2...v1.3.3) (2026-09-10)

### Corrections

* **ci:** aligner Cosign sur la version des serveurs ([14ee8d4](https://github.com/NeitsabLc/benevoles-jambville/commit/14ee8d4e01113460b38ad8c8dcc9b4cd29b04a99))
* **ci:** créer un contexte TLS pour Buildx ([6ef86de](https://github.com/NeitsabLc/benevoles-jambville/commit/6ef86ded6f766718558be56c7fd7b9dcd6d9208b))
* **ci:** joindre le service DinD pendant les smoke tests ([41d42db](https://github.com/NeitsabLc/benevoles-jambville/commit/41d42db6c1affa1ce6c0eb78664e76f88d6cea07))
* **ci:** partager les fichiers temporaires avec DinD ([289ebec](https://github.com/NeitsabLc/benevoles-jambville/commit/289ebecc26e6cbaa98dd7a4de31bc4a5aef66089))
* **ci:** utiliser un hôte HTTP autorisé dans les smoke tests ([cd47ed9](https://github.com/NeitsabLc/benevoles-jambville/commit/cd47ed9f00ad0fd06ba271b17e36fd4f71a9bb01))
