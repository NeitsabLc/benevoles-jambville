--liquibase formatted sql

--changeset benevole-jambville:V013
--comment: Besoin de transport depuis Meulan à l'arrivée d'une inscription
ALTER TABLE benevole_jambville.inscription
    ADD COLUMN heure_transport_meulan TIME WITHOUT TIME ZONE;

--rollback ALTER TABLE benevole_jambville.inscription DROP COLUMN IF EXISTS heure_transport_meulan;
