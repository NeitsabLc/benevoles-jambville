--liquibase formatted sql

--changeset benevole-jambville:V015
ALTER TABLE benevole_jambville.inscription
    DROP CONSTRAINT ck_inscription_couchage,
    ADD CONSTRAINT ck_inscription_couchage CHECK (type_couchage IN ('AUCUN', 'DUR', 'TENTE'));

DELETE FROM benevole_jambville.affectation_rooming a
USING benevole_jambville.inscription i
WHERE i.id = a.inscription_id
  AND i.date_debut = i.date_fin;

UPDATE benevole_jambville.inscription
SET type_couchage = 'AUCUN'
WHERE date_debut = date_fin;

COMMENT ON COLUMN benevole_jambville.inscription.type_couchage IS
    'Type de couchage demandé, ou AUCUN pour une présence limitée à une journée';

--rollback UPDATE benevole_jambville.inscription SET type_couchage = 'DUR' WHERE type_couchage = 'AUCUN'; ALTER TABLE benevole_jambville.inscription DROP CONSTRAINT ck_inscription_couchage, ADD CONSTRAINT ck_inscription_couchage CHECK (type_couchage IN ('DUR', 'TENTE')); COMMENT ON COLUMN benevole_jambville.inscription.type_couchage IS NULL;
