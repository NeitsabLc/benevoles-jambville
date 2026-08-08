--liquibase formatted sql

--changeset benevole-jambville:V002
--comment: Ajout des thématiques initiales du calendrier des présences
INSERT INTO benevole_jambville.thematique (id, nom, ordre_affichage) VALUES
    ('019d0000-0000-7000-8000-000000000001', 'Accueil', 10),
    ('019d0000-0000-7000-8000-000000000002', 'Chantier', 20),
    ('019d0000-0000-7000-8000-000000000003', 'Audiovisuel', 30),
    ('019d0000-0000-7000-8000-000000000004', 'Technique infra', 40),
    ('019d0000-0000-7000-8000-000000000005', 'Abeille', 50),
    ('019d0000-0000-7000-8000-000000000006', 'Scout Market', 60),
    ('019d0000-0000-7000-8000-000000000007', 'Au service', 70);

--rollback DELETE FROM benevole_jambville.thematique WHERE id IN ('019d0000-0000-7000-8000-000000000001', '019d0000-0000-7000-8000-000000000002', '019d0000-0000-7000-8000-000000000003', '019d0000-0000-7000-8000-000000000004', '019d0000-0000-7000-8000-000000000005', '019d0000-0000-7000-8000-000000000006', '019d0000-0000-7000-8000-000000000007');
