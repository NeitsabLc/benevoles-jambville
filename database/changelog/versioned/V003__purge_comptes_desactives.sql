--liquibase formatted sql

--changeset benevole-jambville:V003
ALTER TABLE benevole_jambville.utilisateur
    ADD COLUMN desactive_le TIMESTAMPTZ;

UPDATE benevole_jambville.utilisateur
SET desactive_le = CURRENT_TIMESTAMP
WHERE NOT actif;

CREATE INDEX idx_utilisateur_purge
    ON benevole_jambville.utilisateur (desactive_le)
    WHERE NOT actif;

--rollback DROP INDEX IF EXISTS benevole_jambville.idx_utilisateur_purge;
--rollback ALTER TABLE benevole_jambville.utilisateur DROP COLUMN IF EXISTS desactive_le;
