--liquibase formatted sql

--changeset benevole-jambville-dev:R-thematiques-evenementielles-e2e runAlways:true context:dev
--comment: Réinitialisation déterministe des thématiques réservées aux scénarios E2E
UPDATE benevole_jambville.thematique
SET actif = TRUE,
    nom = CASE id
        WHEN '019cc100-0000-7000-8000-000000000301' THEN 'Événement E2E standard'
        ELSE 'Événement E2E exclusif'
    END,
    date_debut_evenement = CASE id
        WHEN '019cc100-0000-7000-8000-000000000301' THEN DATE '2091-07-10'
        ELSE DATE '2091-08-20'
    END,
    date_fin_evenement = CASE id
        WHEN '019cc100-0000-7000-8000-000000000301' THEN DATE '2091-07-12'
        ELSE DATE '2091-08-22'
    END,
    exclusive_sur_periode = id = '019cc100-0000-7000-8000-000000000302'
WHERE id IN (
    '019cc100-0000-7000-8000-000000000301',
    '019cc100-0000-7000-8000-000000000302'
);

--rollback SELECT 1;
