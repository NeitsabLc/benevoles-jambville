--liquibase formatted sql

--changeset benevole-jambville-dev:V004 context:dev
--comment: Weekend de démonstration des 3 et 4 octobre 2026 avec 28 bénévoles
UPDATE benevole_jambville.inscription
SET date_fin = DATE '2026-10-02'
WHERE id = '019cc100-0000-7000-8000-000000000203'
  AND date_fin = DATE '2026-10-03';

DELETE FROM benevole_jambville.repas_inscription
WHERE inscription_id = '019cc100-0000-7000-8000-000000000203'
  AND date_repas = DATE '2026-10-03';

WITH benevoles(prenom, ordre) AS (
    VALUES
        ('Alice', 1), ('Amine', 2), ('Anaïs', 3), ('Arthur', 4),
        ('Camille', 5), ('Chloé', 6), ('Clément', 7), ('Élodie', 8),
        ('Emma', 9), ('Gabriel', 10), ('Hugo', 11), ('Inès', 12),
        ('Jade', 13), ('Jules', 14), ('Léa', 15), ('Léo', 16),
        ('Louise', 17), ('Lucas', 18), ('Manon', 19), ('Maël', 20),
        ('Nina', 21), ('Noah', 22), ('Océane', 23), ('Paul', 24),
        ('Romane', 25), ('Sacha', 26), ('Théo', 27), ('Zoé', 28)
)
INSERT INTO benevole_jambville.utilisateur (
    id, code_adherent, nom, prenom, email, role, source_role, mot_de_passe,
    vegetarien, sans_lactose, sans_gluten, besoin_couchage
)
SELECT
    ('019cc400-0000-7000-8000-' || LPAD(ordre::TEXT, 12, '0'))::UUID,
    'DEV-WEEKEND-' || LPAD(ordre::TEXT, 2, '0'),
    'Weekend',
    prenom,
    'weekend.' || LPAD(ordre::TEXT, 2, '0') || '@jambville.test',
    'BENEVOLE',
    'MANUEL',
    '$2y$13$9Oq11qmNObFSNXtSOtg/Lew4UU9vHpAMD4oNWdYf0aYaYEKtDzbv.',
    0 = ordre % 4,
    0 = ordre % 5,
    0 = ordre % 7,
    CASE WHEN 0 = ordre % 9 THEN 'Lit proche des sanitaires' END
FROM benevoles;

WITH benevoles(ordre) AS (
    SELECT generate_series(1, 28)
)
INSERT INTO benevole_jambville.inscription (
    id, type, utilisateur_id, thematique_id, date_debut, date_fin, type_couchage,
    nombre_enfants, heure_transport_meulan, commentaire, cree_par_id, modifie_par_id
)
SELECT
    ('019cc400-0000-7001-8000-' || LPAD(ordre::TEXT, 12, '0'))::UUID,
    'INDIVIDUELLE',
    ('019cc400-0000-7000-8000-' || LPAD(ordre::TEXT, 12, '0'))::UUID,
    (SELECT id FROM benevole_jambville.thematique WHERE nom = 'Accueil'),
    DATE '2026-10-03',
    DATE '2026-10-04',
    CASE WHEN ordre <= 20 THEN 'DUR' ELSE 'TENTE' END,
    0,
    CASE ordre
        WHEN 1 THEN TIME '08:15'
        WHEN 2 THEN TIME '09:00'
        WHEN 3 THEN TIME '09:45'
        WHEN 4 THEN TIME '10:30'
    END,
    CASE WHEN 0 = ordre % 6 THEN 'Présence du weekend de démonstration' END,
    '019cc100-0000-7000-8000-000000000003',
    '019cc100-0000-7000-8000-000000000003'
FROM benevoles;

INSERT INTO benevole_jambville.repas_inscription (inscription_id, date_repas, type_repas)
SELECT
    inscription.id,
    jours.date_repas,
    types.type_repas
FROM benevole_jambville.inscription AS inscription
CROSS JOIN (VALUES (DATE '2026-10-03'), (DATE '2026-10-04')) AS jours(date_repas)
CROSS JOIN (VALUES ('PETIT_DEJEUNER'), ('DEJEUNER'), ('DINER')) AS types(type_repas)
WHERE inscription.id::TEXT LIKE '019cc400-0000-7001-8000-%';

--rollback DELETE FROM benevole_jambville.inscription WHERE id::TEXT LIKE '019cc400-0000-7001-8000-%'; DELETE FROM benevole_jambville.utilisateur WHERE id::TEXT LIKE '019cc400-0000-7000-8000-%'; UPDATE benevole_jambville.inscription SET date_fin = DATE '2026-10-03' WHERE id = '019cc100-0000-7000-8000-000000000203' AND date_fin = DATE '2026-10-02'; INSERT INTO benevole_jambville.repas_inscription (inscription_id, date_repas, type_repas) SELECT '019cc100-0000-7000-8000-000000000203', DATE '2026-10-03', type_repas FROM (VALUES ('PETIT_DEJEUNER'), ('DEJEUNER'), ('DINER')) AS types(type_repas) ON CONFLICT DO NOTHING;
