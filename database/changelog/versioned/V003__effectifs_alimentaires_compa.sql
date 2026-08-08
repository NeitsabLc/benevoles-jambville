--liquibase formatted sql

--changeset benevole-jambville:V003
--comment: Ajout des effectifs alimentaires déclarés pour les équipes compas
ALTER TABLE benevole_jambville.inscription
    ADD COLUMN nombre_vegetariens INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN nombre_allergie_oeuf INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN nombre_allergie_arachide INTEGER NOT NULL DEFAULT 0;

ALTER TABLE benevole_jambville.inscription
    ADD CONSTRAINT ck_inscription_effectifs_alimentaires CHECK (
        nombre_vegetariens >= 0
        AND nombre_allergie_oeuf >= 0
        AND nombre_allergie_arachide >= 0
        AND (type = 'COMPAGNON' OR (
            nombre_vegetariens = 0
            AND nombre_allergie_oeuf = 0
            AND nombre_allergie_arachide = 0
        ))
    );

--rollback ALTER TABLE benevole_jambville.inscription DROP CONSTRAINT ck_inscription_effectifs_alimentaires; ALTER TABLE benevole_jambville.inscription DROP COLUMN nombre_vegetariens, DROP COLUMN nombre_allergie_oeuf, DROP COLUMN nombre_allergie_arachide;
