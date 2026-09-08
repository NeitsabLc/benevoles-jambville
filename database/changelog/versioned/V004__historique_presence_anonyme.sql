--liquibase formatted sql

--changeset benevole-jambville:V004
CREATE TABLE benevole_jambville.historique_presence_anonyme (
    date_journee DATE NOT NULL,
    thematique VARCHAR(120),
    nombre_benevoles INTEGER NOT NULL DEFAULT 0,
    nombre_compagnons INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT uq_historique_presence_anonyme UNIQUE NULLS NOT DISTINCT (date_journee, thematique),
    CONSTRAINT ck_historique_presence_anonyme_compteurs CHECK (nombre_benevoles >= 0 AND nombre_compagnons >= 0),
    CONSTRAINT ck_historique_presence_anonyme_contenu CHECK (
        (thematique IS NOT NULL AND nombre_benevoles > 0 AND nombre_compagnons = 0)
        OR (thematique IS NULL AND nombre_benevoles = 0 AND nombre_compagnons > 0)
    )
);

COMMENT ON TABLE benevole_jambville.historique_presence_anonyme IS
    'Statistiques journalières sans identifiant personnel ni lien vers les données sources';

--rollback DROP TABLE IF EXISTS benevole_jambville.historique_presence_anonyme;
