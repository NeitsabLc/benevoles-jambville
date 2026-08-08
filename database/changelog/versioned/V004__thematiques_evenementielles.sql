--liquibase formatted sql

--changeset benevole-jambville:V004
--comment: Ajout des périodes et de l'exclusivité aux thématiques événementielles
ALTER TABLE benevole_jambville.thematique
    ADD COLUMN date_debut_evenement DATE,
    ADD COLUMN date_fin_evenement DATE,
    ADD COLUMN exclusive_sur_periode BOOLEAN NOT NULL DEFAULT FALSE,
    ADD CONSTRAINT ck_thematique_periode_evenement CHECK (
        (date_debut_evenement IS NULL AND date_fin_evenement IS NULL AND NOT exclusive_sur_periode)
        OR (date_debut_evenement IS NOT NULL AND date_fin_evenement IS NOT NULL AND date_fin_evenement >= date_debut_evenement)
    );

CREATE INDEX idx_thematique_periode_evenement
    ON benevole_jambville.thematique (date_debut_evenement, date_fin_evenement)
    WHERE actif AND date_debut_evenement IS NOT NULL;

--rollback DROP INDEX benevole_jambville.idx_thematique_periode_evenement; ALTER TABLE benevole_jambville.thematique DROP CONSTRAINT ck_thematique_periode_evenement, DROP COLUMN exclusive_sur_periode, DROP COLUMN date_fin_evenement, DROP COLUMN date_debut_evenement;
