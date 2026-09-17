--liquibase formatted sql

--changeset benevole-jambville:V012
--comment: Rend le référentiel des chambres administrable et associe chaque chambre à un bâtiment
CREATE TABLE benevole_jambville.chambre_rooming (
    code VARCHAR(50) PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    capacite SMALLINT NOT NULL,
    batiment VARCHAR(50) NOT NULL,
    ordre_affichage INTEGER NOT NULL,
    cree_par_id UUID,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_chambre_rooming_cree_par FOREIGN KEY (cree_par_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE SET NULL,
    CONSTRAINT ck_chambre_rooming_capacite CHECK (capacite BETWEEN 1 AND 100),
    CONSTRAINT ck_chambre_rooming_batiment CHECK (batiment IN (
        'Logement bénévole',
        'Château',
        'Orangerie',
        'Grande Ferme',
        'St Thomas',
        'Petite ferme',
        'Saint Louis'
    )),
    CONSTRAINT uq_chambre_rooming_ordre UNIQUE (ordre_affichage)
);

CREATE UNIQUE INDEX uq_chambre_rooming_nom_batiment
    ON benevole_jambville.chambre_rooming (batiment, LOWER(nom));

INSERT INTO benevole_jambville.chambre_rooming (code, nom, capacite, batiment, ordre_affichage) VALUES
    ('BLONDIN', 'Blondin', 3, 'Logement bénévole', 10),
    ('SIZAINE', 'Sizaine', 6, 'Logement bénévole', 20),
    ('PATROUILLE', 'Patrouille', 6, 'Logement bénévole', 30),
    ('COLIBRI', 'Colibri', 1, 'Logement bénévole', 40);

ALTER TABLE benevole_jambville.affectation_rooming
    DROP CONSTRAINT ck_affectation_rooming_chambre,
    ALTER COLUMN chambre TYPE VARCHAR(50),
    ADD CONSTRAINT fk_affectation_rooming_chambre FOREIGN KEY (chambre)
        REFERENCES benevole_jambville.chambre_rooming (code) ON DELETE RESTRICT;

ALTER TABLE benevole_jambville.disponibilite_chambre_rooming
    DROP CONSTRAINT fk_disponibilite_chambre_configuration;

ALTER TABLE benevole_jambville.configuration_chambre_rooming
    DROP CONSTRAINT ck_configuration_chambre_code,
    ALTER COLUMN chambre TYPE VARCHAR(50),
    ADD CONSTRAINT fk_configuration_chambre_referentiel FOREIGN KEY (chambre)
        REFERENCES benevole_jambville.chambre_rooming (code) ON DELETE CASCADE;

ALTER TABLE benevole_jambville.disponibilite_chambre_rooming
    ALTER COLUMN chambre TYPE VARCHAR(50),
    ADD CONSTRAINT fk_disponibilite_chambre_configuration FOREIGN KEY (chambre)
        REFERENCES benevole_jambville.configuration_chambre_rooming (chambre) ON DELETE CASCADE;

COMMENT ON TABLE benevole_jambville.chambre_rooming IS
    'Référentiel administrable des chambres et de leur bâtiment';

--rollback ALTER TABLE benevole_jambville.disponibilite_chambre_rooming DROP CONSTRAINT fk_disponibilite_chambre_configuration;
--rollback ALTER TABLE benevole_jambville.configuration_chambre_rooming DROP CONSTRAINT fk_configuration_chambre_referentiel;
--rollback ALTER TABLE benevole_jambville.affectation_rooming DROP CONSTRAINT fk_affectation_rooming_chambre;
--rollback DELETE FROM benevole_jambville.affectation_rooming WHERE chambre NOT IN ('BLONDIN', 'SIZAINE', 'PATROUILLE', 'COLIBRI');
--rollback DELETE FROM benevole_jambville.configuration_chambre_rooming WHERE chambre NOT IN ('BLONDIN', 'SIZAINE', 'PATROUILLE', 'COLIBRI');
--rollback ALTER TABLE benevole_jambville.disponibilite_chambre_rooming ALTER COLUMN chambre TYPE VARCHAR(20), ADD CONSTRAINT fk_disponibilite_chambre_configuration FOREIGN KEY (chambre) REFERENCES benevole_jambville.configuration_chambre_rooming (chambre) ON DELETE CASCADE;
--rollback ALTER TABLE benevole_jambville.configuration_chambre_rooming ALTER COLUMN chambre TYPE VARCHAR(20), ADD CONSTRAINT ck_configuration_chambre_code CHECK (chambre IN ('BLONDIN', 'SIZAINE', 'PATROUILLE', 'COLIBRI'));
--rollback ALTER TABLE benevole_jambville.affectation_rooming ALTER COLUMN chambre TYPE VARCHAR(20), ADD CONSTRAINT ck_affectation_rooming_chambre CHECK (chambre IN ('BLONDIN', 'SIZAINE', 'PATROUILLE', 'COLIBRI'));
--rollback DROP TABLE benevole_jambville.chambre_rooming;
