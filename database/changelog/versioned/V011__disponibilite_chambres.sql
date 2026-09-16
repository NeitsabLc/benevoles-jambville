--liquibase formatted sql

--changeset benevole-jambville:V011
--comment: Configure la disponibilité permanente ou par périodes des chambres fixes
CREATE TABLE benevole_jambville.configuration_chambre_rooming (
    chambre VARCHAR(20) PRIMARY KEY,
    disponible_permanence BOOLEAN NOT NULL DEFAULT TRUE,
    modifie_par_id UUID,
    modifie_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_configuration_chambre_modifie_par FOREIGN KEY (modifie_par_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE SET NULL,
    CONSTRAINT ck_configuration_chambre_code CHECK (
        chambre IN ('BLONDIN', 'SIZAINE', 'PATROUILLE', 'COLIBRI')
    )
);

CREATE TABLE benevole_jambville.disponibilite_chambre_rooming (
    id UUID NOT NULL DEFAULT uuidv7(),
    chambre VARCHAR(20) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    modifie_par_id UUID,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_disponibilite_chambre PRIMARY KEY (id),
    CONSTRAINT fk_disponibilite_chambre_configuration FOREIGN KEY (chambre)
        REFERENCES benevole_jambville.configuration_chambre_rooming (chambre) ON DELETE CASCADE,
    CONSTRAINT fk_disponibilite_chambre_modifie_par FOREIGN KEY (modifie_par_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE SET NULL,
    CONSTRAINT ck_disponibilite_chambre_periode CHECK (date_fin >= date_debut),
    CONSTRAINT ex_disponibilite_chambre_periode EXCLUDE USING gist (
        chambre WITH =,
        daterange(date_debut, date_fin, '[]') WITH &&
    )
);

INSERT INTO benevole_jambville.configuration_chambre_rooming (chambre) VALUES
    ('BLONDIN'),
    ('SIZAINE'),
    ('PATROUILLE'),
    ('COLIBRI');

CREATE INDEX idx_disponibilite_chambre_dates
    ON benevole_jambville.disponibilite_chambre_rooming (chambre, date_debut, date_fin);

COMMENT ON TABLE benevole_jambville.configuration_chambre_rooming IS
    'Mode de disponibilité des chambres fixes du centre';
COMMENT ON TABLE benevole_jambville.disponibilite_chambre_rooming IS
    'Périodes inclusives pendant lesquelles une chambre non permanente peut être planifiée';

--rollback DROP TABLE IF EXISTS benevole_jambville.disponibilite_chambre_rooming; DROP TABLE IF EXISTS benevole_jambville.configuration_chambre_rooming;
