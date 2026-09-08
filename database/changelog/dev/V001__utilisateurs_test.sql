--liquibase formatted sql

--changeset benevole-jambville-dev:V001 context:dev
--comment: Comptes de démonstration avec un mot de passe commun réservé au développement
INSERT INTO benevole_jambville.utilisateur (
    id,
    code_adherent,
    nom,
    prenom,
    email,
    role,
    source_role,
    mot_de_passe
) VALUES
    (
        '019cc100-0000-7000-8000-000000000001',
        'DEV-BENEVOLE',
        'Bénévole',
        'Camille',
        'benevole@jambville.test',
        'BENEVOLE',
        'MANUEL',
        '$2y$13$9Oq11qmNObFSNXtSOtg/Lew4UU9vHpAMD4oNWdYf0aYaYEKtDzbv.'
    ),
    (
        '019cc100-0000-7000-8000-000000000002',
        'DEV-ACCUEIL',
        'Accueil',
        'Sasha',
        'accueil@jambville.test',
        'SALARIE_ACCUEIL',
        'MANUEL',
        '$2y$13$9Oq11qmNObFSNXtSOtg/Lew4UU9vHpAMD4oNWdYf0aYaYEKtDzbv.'
    ),
    (
        '019cc100-0000-7000-8000-000000000003',
        'DEV-PILOTE',
        'Pilote',
        'Dominique',
        'pilote@jambville.test',
        'EQUIPE_PILOTE',
        'MANUEL',
        '$2y$13$9Oq11qmNObFSNXtSOtg/Lew4UU9vHpAMD4oNWdYf0aYaYEKtDzbv.'
    ),
    (
        '019cc100-0000-7000-8000-000000000004',
        'DEV-BENEVOLE-2',
        'Martin',
        'Lou',
        'lou.martin@jambville.test',
        'BENEVOLE',
        'MANUEL',
        '$2y$13$9Oq11qmNObFSNXtSOtg/Lew4UU9vHpAMD4oNWdYf0aYaYEKtDzbv.'
    ),
    (
        '019cc100-0000-7000-8000-000000000005',
        'DEV-BENEVOLE-3',
        'Bernard',
        'Noa',
        'noa.bernard@jambville.test',
        'BENEVOLE',
        'MANUEL',
        '$2y$13$9Oq11qmNObFSNXtSOtg/Lew4UU9vHpAMD4oNWdYf0aYaYEKtDzbv.'
    );

UPDATE benevole_jambville.utilisateur
SET telephone = '06 12 34 56 78', vegetarien = TRUE, besoin_couchage = 'Lit en dur si possible'
WHERE code_adherent = 'DEV-BENEVOLE-2';

UPDATE benevole_jambville.utilisateur
SET telephone = '07 11 22 33 44', allergie_arachide = TRUE, regime_autre = 'Sans lactose'
WHERE code_adherent = 'DEV-BENEVOLE-3';

INSERT INTO benevole_jambville.personne_permanence (id, nom, ordre_affichage) VALUES
    ('019cc100-0000-7000-8000-000000000101', 'Alex de permanence', 10),
    ('019cc100-0000-7000-8000-000000000102', 'Charlie de permanence', 20);

INSERT INTO benevole_jambville.journee (date_journee, personne_permanence_id, commentaire, modifie_par_id) VALUES
    (CURRENT_DATE, '019cc100-0000-7000-8000-000000000101', 'Accueil des bénévoles à 9 h', '019cc100-0000-7000-8000-000000000003'),
    (CURRENT_DATE + 1, '019cc100-0000-7000-8000-000000000102', NULL, '019cc100-0000-7000-8000-000000000003');

INSERT INTO benevole_jambville.inscription (
    id, type, utilisateur_id, thematique_id, date_debut, date_fin, type_couchage,
    nombre_enfants, commentaire, cree_par_id, modifie_par_id
)
SELECT
    '019cc100-0000-7000-8000-000000000201', 'INDIVIDUELLE',
    '019cc100-0000-7000-8000-000000000001', id,
    CURRENT_DATE, CURRENT_DATE + 2, 'DUR', 0, 'Présence de démonstration',
    '019cc100-0000-7000-8000-000000000001', '019cc100-0000-7000-8000-000000000001'
FROM benevole_jambville.thematique WHERE nom = 'Chantier';

INSERT INTO benevole_jambville.inscription (
    id, type, utilisateur_id, thematique_id, date_debut, date_fin, type_couchage,
    nombre_enfants, commentaire, cree_par_id, modifie_par_id
)
SELECT
    '019cc100-0000-7000-8000-000000000202', 'INDIVIDUELLE',
    '019cc100-0000-7000-8000-000000000004', id,
    CURRENT_DATE + 1, CURRENT_DATE + 4, 'DUR', 1, NULL,
    '019cc100-0000-7000-8000-000000000003', '019cc100-0000-7000-8000-000000000003'
FROM benevole_jambville.thematique WHERE nom = 'Accueil';

INSERT INTO benevole_jambville.inscription (
    id, type, nom_equipe_compa, nombre_personnes, date_debut, date_fin, type_couchage,
    nombre_enfants, nombre_vegetariens, nombre_allergie_oeuf, nombre_allergie_arachide,
    commentaire, cree_par_id, modifie_par_id
) VALUES (
    '019cc100-0000-7000-8000-000000000203', 'COMPAGNON', 'Compas de démonstration', 6,
    CURRENT_DATE + 3, CURRENT_DATE + 5, 'TENTE', 0, 2, 1, 0,
    'Arrivée prévue en fin d’après-midi',
    '019cc100-0000-7000-8000-000000000003', '019cc100-0000-7000-8000-000000000003'
);

INSERT INTO benevole_jambville.repas_inscription (inscription_id, date_repas, type_repas)
SELECT inscription.id, jours.date_repas::date, types.type_repas
FROM benevole_jambville.inscription
CROSS JOIN LATERAL generate_series(inscription.date_debut, inscription.date_fin, INTERVAL '1 day') AS jours(date_repas)
CROSS JOIN (VALUES ('PETIT_DEJEUNER'), ('DEJEUNER'), ('DINER')) AS types(type_repas)
WHERE inscription.id IN (
    '019cc100-0000-7000-8000-000000000201',
    '019cc100-0000-7000-8000-000000000202',
    '019cc100-0000-7000-8000-000000000203'
);

--rollback DELETE FROM benevole_jambville.inscription WHERE id IN ('019cc100-0000-7000-8000-000000000201', '019cc100-0000-7000-8000-000000000202', '019cc100-0000-7000-8000-000000000203'); DELETE FROM benevole_jambville.journee WHERE date_journee IN (CURRENT_DATE, CURRENT_DATE + 1); DELETE FROM benevole_jambville.personne_permanence WHERE id IN ('019cc100-0000-7000-8000-000000000101', '019cc100-0000-7000-8000-000000000102'); DELETE FROM benevole_jambville.utilisateur WHERE code_adherent LIKE 'DEV-%';
