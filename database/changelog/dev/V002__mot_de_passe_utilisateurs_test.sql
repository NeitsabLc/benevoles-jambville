--liquibase formatted sql

--changeset benevole-jambville-dev:V002 context:dev
--comment: Mot de passe commun aux trois comptes de démonstration
UPDATE benevole_jambville.utilisateur
SET mot_de_passe = '$2y$13$9Oq11qmNObFSNXtSOtg/Lew4UU9vHpAMD4oNWdYf0aYaYEKtDzbv.',
    changement_mot_de_passe_requis = FALSE,
    jeton_activation = NULL,
    expiration_jeton_activation = NULL,
    modifie_le = CURRENT_TIMESTAMP
WHERE code_adherent IN ('DEV-BENEVOLE', 'DEV-ACCUEIL', 'DEV-PILOTE');

--rollback UPDATE benevole_jambville.utilisateur SET mot_de_passe = NULL, changement_mot_de_passe_requis = TRUE, jeton_activation = CASE code_adherent WHEN 'DEV-BENEVOLE' THEN '08ff40543cbe10db2b0a2f0ebdbe15f4a5de63611f727b06d6dcf89d67cfa2fe' WHEN 'DEV-ACCUEIL' THEN '09b33e946b23555f5b33408598ea63b343bc6b4aea1fdb99dcd0de408063cc63' WHEN 'DEV-PILOTE' THEN '4867e86e65f9bee1db60f906154a84df507ce8e4725657c1f9206f922195bf01' END, expiration_jeton_activation = '2099-12-31 23:59:59+01', modifie_le = CURRENT_TIMESTAMP WHERE code_adherent IN ('DEV-BENEVOLE', 'DEV-ACCUEIL', 'DEV-PILOTE');
