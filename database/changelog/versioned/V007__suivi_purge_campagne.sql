--liquibase formatted sql

--changeset benevole-jambville:V007
CREATE TABLE benevole_jambville.purge_campagne (
    date_debut DATE PRIMARY KEY,
    date_fin DATE NOT NULL UNIQUE,
    executee_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ck_purge_campagne_periode CHECK (date_fin >= date_debut)
);

COMMENT ON TABLE benevole_jambville.purge_campagne IS
    'État technique empêchant de rejouer une purge annuelle déjà terminée';

--rollback DROP TABLE IF EXISTS benevole_jambville.purge_campagne;
