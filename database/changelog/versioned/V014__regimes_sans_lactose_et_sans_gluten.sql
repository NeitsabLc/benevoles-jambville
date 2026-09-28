--liquibase formatted sql

--changeset benevole-jambville:V014
ALTER TABLE benevole_jambville.utilisateur
    ADD COLUMN sans_lactose BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN sans_gluten BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE benevole_jambville.inscription
    ADD COLUMN nombre_sans_lactose INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN nombre_sans_gluten INTEGER NOT NULL DEFAULT 0,
    ADD CONSTRAINT ck_inscription_effectifs_nouveaux_regimes CHECK (
        nombre_sans_lactose >= 0
        AND nombre_sans_gluten >= 0
        AND (
            (type = 'INDIVIDUELLE'
                AND nombre_sans_lactose = 0
                AND nombre_sans_gluten = 0)
            OR (type = 'COMPAGNON'
                AND nombre_sans_lactose <= nombre_personnes
                AND nombre_sans_gluten <= nombre_personnes)
        )
    );

UPDATE benevole_jambville.utilisateur
SET regime_autre = NULLIF(CONCAT_WS(E'\n',
    NULLIF(BTRIM(regime_autre), ''),
    CASE WHEN allergie_oeuf THEN 'Allergie aux œufs' END,
    CASE WHEN allergie_arachide THEN 'Allergie aux arachides' END
), '')
WHERE allergie_oeuf OR allergie_arachide;

UPDATE benevole_jambville.inscription
SET commentaire = NULLIF(CONCAT_WS(E'\n',
    NULLIF(BTRIM(commentaire), ''),
    CASE WHEN nombre_allergie_oeuf > 0 THEN 'Ancienne déclaration — allergie aux œufs : ' || nombre_allergie_oeuf END,
    CASE WHEN nombre_allergie_arachide > 0 THEN 'Ancienne déclaration — allergie aux arachides : ' || nombre_allergie_arachide END
), '')
WHERE nombre_allergie_oeuf > 0 OR nombre_allergie_arachide > 0;

UPDATE benevole_jambville.utilisateur
SET allergie_oeuf = FALSE,
    allergie_arachide = FALSE
WHERE allergie_oeuf OR allergie_arachide;

UPDATE benevole_jambville.inscription
SET nombre_allergie_oeuf = 0,
    nombre_allergie_arachide = 0
WHERE nombre_allergie_oeuf > 0 OR nombre_allergie_arachide > 0;

COMMENT ON COLUMN benevole_jambville.utilisateur.allergie_oeuf IS
    'Colonne historique conservée vide pour compatibilité avec les données de développement V001';
COMMENT ON COLUMN benevole_jambville.utilisateur.allergie_arachide IS
    'Colonne historique conservée vide pour compatibilité avec les données de développement V001';
COMMENT ON COLUMN benevole_jambville.inscription.nombre_allergie_oeuf IS
    'Colonne historique conservée vide pour compatibilité avec les données de développement V001';
COMMENT ON COLUMN benevole_jambville.inscription.nombre_allergie_arachide IS
    'Colonne historique conservée vide pour compatibilité avec les données de développement V001';

--rollback ALTER TABLE benevole_jambville.inscription DROP CONSTRAINT ck_inscription_effectifs_nouveaux_regimes, DROP COLUMN nombre_sans_gluten, DROP COLUMN nombre_sans_lactose;
--rollback ALTER TABLE benevole_jambville.utilisateur DROP COLUMN sans_gluten, DROP COLUMN sans_lactose;
