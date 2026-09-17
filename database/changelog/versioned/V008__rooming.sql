--liquibase formatted sql

--changeset benevole-jambville:V008
CREATE TABLE benevole_jambville.affectation_rooming (
    id UUID NOT NULL DEFAULT uuidv7(),
    inscription_id UUID NOT NULL,
    date_nuit DATE NOT NULL,
    chambre VARCHAR(20) NOT NULL,
    nombre_places INTEGER NOT NULL,
    modifie_par_id UUID NOT NULL,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_affectation_rooming PRIMARY KEY (id),
    CONSTRAINT fk_affectation_rooming_inscription FOREIGN KEY (inscription_id)
        REFERENCES benevole_jambville.inscription (id) ON DELETE CASCADE,
    CONSTRAINT fk_affectation_rooming_modifie_par FOREIGN KEY (modifie_par_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE RESTRICT,
    CONSTRAINT uq_affectation_rooming UNIQUE (inscription_id, date_nuit),
    CONSTRAINT ck_affectation_rooming_chambre CHECK (
        chambre IN ('BLONDIN', 'SIZAINE', 'PATROUILLE', 'COLIBRI')
    ),
    CONSTRAINT ck_affectation_rooming_places CHECK (nombre_places > 0)
);

CREATE INDEX idx_affectation_rooming_nuit_chambre
    ON benevole_jambville.affectation_rooming (date_nuit, chambre);

COMMENT ON TABLE benevole_jambville.affectation_rooming IS
    'Répartition par nuit des personnes en couchage dur dans les chambres de Jambville';

--rollback DROP TABLE IF EXISTS benevole_jambville.affectation_rooming;
