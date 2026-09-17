--liquibase formatted sql

--changeset benevole-jambville:V010
--comment: Matérialise les affectations par nuit afin de permettre les exceptions ponctuelles
ALTER TABLE benevole_jambville.affectation_rooming
    ADD COLUMN date_nuit DATE;

UPDATE benevole_jambville.affectation_rooming a
SET date_nuit = i.date_debut
FROM benevole_jambville.inscription i
WHERE i.id = a.inscription_id;

DROP INDEX benevole_jambville.idx_affectation_rooming_chambre;

ALTER TABLE benevole_jambville.affectation_rooming
    DROP CONSTRAINT uq_affectation_rooming_inscription;

INSERT INTO benevole_jambville.affectation_rooming (
    inscription_id,
    date_nuit,
    chambre,
    nombre_places,
    modifie_par_id,
    cree_le,
    modifie_le
)
SELECT
    a.inscription_id,
    dates.date_nuit::date,
    a.chambre,
    a.nombre_places,
    a.modifie_par_id,
    a.cree_le,
    a.modifie_le
FROM benevole_jambville.affectation_rooming a
INNER JOIN benevole_jambville.inscription i ON i.id = a.inscription_id
CROSS JOIN LATERAL generate_series(
    i.date_debut + 1,
    i.date_fin,
    INTERVAL '1 day'
) AS dates(date_nuit)
WHERE a.date_nuit = i.date_debut;

ALTER TABLE benevole_jambville.affectation_rooming
    ALTER COLUMN date_nuit SET NOT NULL,
    ADD CONSTRAINT uq_affectation_rooming UNIQUE (inscription_id, date_nuit);

CREATE INDEX idx_affectation_rooming_nuit_chambre
    ON benevole_jambville.affectation_rooming (date_nuit, chambre);

COMMENT ON TABLE benevole_jambville.affectation_rooming IS
    'Répartition par nuit des personnes en couchage dur, gérée par séjour avec exceptions ponctuelles';

--rollback WITH affectations_classees AS (SELECT id, ROW_NUMBER() OVER (PARTITION BY inscription_id ORDER BY date_nuit, id) AS rang FROM benevole_jambville.affectation_rooming) DELETE FROM benevole_jambville.affectation_rooming WHERE id IN (SELECT id FROM affectations_classees WHERE rang > 1);
--rollback DROP INDEX benevole_jambville.idx_affectation_rooming_nuit_chambre;
--rollback ALTER TABLE benevole_jambville.affectation_rooming DROP CONSTRAINT uq_affectation_rooming, DROP COLUMN date_nuit, ADD CONSTRAINT uq_affectation_rooming_inscription UNIQUE (inscription_id);
--rollback CREATE INDEX idx_affectation_rooming_chambre ON benevole_jambville.affectation_rooming (chambre);
--rollback COMMENT ON TABLE benevole_jambville.affectation_rooming IS 'Chambre affectée à une inscription en couchage dur pour toute sa période de présence';
