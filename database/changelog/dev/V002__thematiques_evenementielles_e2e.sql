--liquibase formatted sql

--changeset benevole-jambville-dev:V002 context:dev
--comment: Thématiques événementielles déterministes pour les scénarios E2E
INSERT INTO benevole_jambville.thematique (
    id,
    nom,
    ordre_affichage,
    date_debut_evenement,
    date_fin_evenement,
    exclusive_sur_periode
) VALUES
    (
        '019cc100-0000-7000-8000-000000000301',
        'Événement E2E standard',
        5,
        '2091-07-10',
        '2091-07-12',
        FALSE
    ),
    (
        '019cc100-0000-7000-8000-000000000302',
        'Événement E2E exclusif',
        5,
        '2091-08-20',
        '2091-08-22',
        TRUE
    );

--rollback DELETE FROM benevole_jambville.thematique WHERE id IN ('019cc100-0000-7000-8000-000000000301', '019cc100-0000-7000-8000-000000000302');
