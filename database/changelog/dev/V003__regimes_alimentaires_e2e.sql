--liquibase formatted sql

--changeset benevole-jambville-dev:V003 context:dev
--comment: Régimes alimentaires de démonstration adaptés aux choix proposés dans l'interface
UPDATE benevole_jambville.utilisateur
SET sans_gluten = TRUE
WHERE code_adherent = 'DEV-BENEVOLE-2';

UPDATE benevole_jambville.utilisateur
SET sans_lactose = TRUE,
    allergie_arachide = FALSE,
    regime_autre = NULL
WHERE code_adherent = 'DEV-BENEVOLE-3';

UPDATE benevole_jambville.inscription
SET nombre_sans_lactose = nombre_allergie_oeuf,
    nombre_allergie_oeuf = 0,
    nombre_allergie_arachide = 0
WHERE id = '019cc100-0000-7000-8000-000000000203';

--rollback UPDATE benevole_jambville.inscription SET nombre_allergie_oeuf = nombre_sans_lactose, nombre_sans_lactose = 0 WHERE id = '019cc100-0000-7000-8000-000000000203'; UPDATE benevole_jambville.utilisateur SET sans_gluten = FALSE WHERE code_adherent = 'DEV-BENEVOLE-2'; UPDATE benevole_jambville.utilisateur SET sans_lactose = FALSE, allergie_arachide = TRUE, regime_autre = 'Sans lactose' WHERE code_adherent = 'DEV-BENEVOLE-3';
