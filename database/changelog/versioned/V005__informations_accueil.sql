--liquibase formatted sql

--changeset benevole-jambville:V005
--comment: Suivi de la saisie des informations pratiques après la première authentification
ALTER TABLE benevole_jambville.utilisateur
    ADD COLUMN informations_accueil_completees BOOLEAN NOT NULL DEFAULT TRUE;

--rollback ALTER TABLE benevole_jambville.utilisateur DROP COLUMN IF EXISTS informations_accueil_completees;
