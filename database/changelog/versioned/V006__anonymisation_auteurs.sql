--liquibase formatted sql

--changeset benevole-jambville:V006
--comment: Conserver les données métier lors de la purge du compte qui les a créées ou modifiées
ALTER TABLE benevole_jambville.inscription
    DROP CONSTRAINT fk_inscription_cree_par,
    DROP CONSTRAINT fk_inscription_modifie_par,
    ALTER COLUMN cree_par_id DROP NOT NULL,
    ALTER COLUMN modifie_par_id DROP NOT NULL,
    ADD CONSTRAINT fk_inscription_cree_par FOREIGN KEY (cree_par_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_inscription_modifie_par FOREIGN KEY (modifie_par_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE SET NULL;

ALTER TABLE benevole_jambville.journee
    DROP CONSTRAINT fk_journee_modifie_par,
    ALTER COLUMN modifie_par_id DROP NOT NULL,
    ADD CONSTRAINT fk_journee_modifie_par FOREIGN KEY (modifie_par_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE SET NULL;
