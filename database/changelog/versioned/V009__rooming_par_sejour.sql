--liquibase formatted sql

--changeset benevole-jambville:V009
--comment: Une chambre est affectée à une inscription pour toute sa période de présence
WITH affectations_classees AS (
    SELECT id,
           ROW_NUMBER() OVER (
               PARTITION BY inscription_id
               ORDER BY modifie_le DESC, cree_le DESC, id DESC
           ) AS rang
    FROM benevole_jambville.affectation_rooming
)
DELETE FROM benevole_jambville.affectation_rooming
WHERE id IN (
    SELECT id
    FROM affectations_classees
    WHERE rang > 1
);

DROP INDEX benevole_jambville.idx_affectation_rooming_nuit_chambre;

ALTER TABLE benevole_jambville.affectation_rooming
    DROP CONSTRAINT uq_affectation_rooming,
    DROP COLUMN date_nuit,
    ADD CONSTRAINT uq_affectation_rooming_inscription UNIQUE (inscription_id);

CREATE INDEX idx_affectation_rooming_chambre
    ON benevole_jambville.affectation_rooming (chambre);

COMMENT ON TABLE benevole_jambville.affectation_rooming IS
    'Chambre affectée à une inscription en couchage dur pour toute sa période de présence';

--rollback DROP INDEX benevole_jambville.idx_affectation_rooming_chambre;
--rollback ALTER TABLE benevole_jambville.affectation_rooming DROP CONSTRAINT uq_affectation_rooming_inscription;
--rollback ALTER TABLE benevole_jambville.affectation_rooming ADD COLUMN date_nuit DATE;
--rollback UPDATE benevole_jambville.affectation_rooming a SET date_nuit = i.date_debut FROM benevole_jambville.inscription i WHERE i.id = a.inscription_id;
--rollback ALTER TABLE benevole_jambville.affectation_rooming ALTER COLUMN date_nuit SET NOT NULL, ADD CONSTRAINT uq_affectation_rooming UNIQUE (inscription_id, date_nuit);
--rollback CREATE INDEX idx_affectation_rooming_nuit_chambre ON benevole_jambville.affectation_rooming (date_nuit, chambre);
--rollback COMMENT ON TABLE benevole_jambville.affectation_rooming IS 'Répartition par nuit des personnes en couchage dur dans les chambres de Jambville';
