\set ON_ERROR_STOP on

BEGIN;

DELETE FROM benevole_jambville.inscription
WHERE cree_par_id IN (
    SELECT id FROM benevole_jambville.utilisateur WHERE code_adherent LIKE 'E2E-%'
)
OR modifie_par_id IN (
    SELECT id FROM benevole_jambville.utilisateur WHERE code_adherent LIKE 'E2E-%'
);

DELETE FROM benevole_jambville.journee
WHERE date_journee BETWEEN DATE '2094-01-01' AND DATE '2094-12-31';

DELETE FROM benevole_jambville.personne_permanence
WHERE nom LIKE 'Permanence E2E%';

DELETE FROM benevole_jambville.thematique
WHERE nom LIKE 'Parcours E2E%';

DELETE FROM benevole_jambville.utilisateur
WHERE code_adherent LIKE 'E2E-%';

INSERT INTO benevole_jambville.utilisateur (
    id, code_adherent, nom, prenom, email, role, source_role, mot_de_passe,
    changement_mot_de_passe_requis, jeton_activation,
    expiration_jeton_activation, informations_accueil_completees
) VALUES
    (
        '019dd100-0000-7000-8000-000000000401', 'E2E-BENEVOLE', 'Parcours', 'Élodie',
        'e2e.benevole@jambville.test', 'BENEVOLE', 'MANUEL',
        '$2y$13$9Oq11qmNObFSNXtSOtg/Lew4UU9vHpAMD4oNWdYf0aYaYEKtDzbv.',
        FALSE, NULL, NULL, TRUE
    ),
    (
        '019dd100-0000-7000-8000-000000000402', 'E2E-ACCUEIL', 'Parcours', 'Arthur',
        'e2e.accueil@jambville.test', 'SALARIE_ACCUEIL', 'MANUEL',
        '$2y$13$9Oq11qmNObFSNXtSOtg/Lew4UU9vHpAMD4oNWdYf0aYaYEKtDzbv.',
        FALSE, NULL, NULL, TRUE
    ),
    (
        '019dd100-0000-7000-8000-000000000403', 'E2E-PILOTE', 'Parcours', 'Paul',
        'e2e.pilote@jambville.test', 'EQUIPE_PILOTE', 'MANUEL',
        '$2y$13$9Oq11qmNObFSNXtSOtg/Lew4UU9vHpAMD4oNWdYf0aYaYEKtDzbv.',
        FALSE, NULL, NULL, TRUE
    ),
    (
        '019dd100-0000-7000-8000-000000000404', 'E2E-SESSION', 'Session', 'Sonia',
        'e2e.session@jambville.test', 'BENEVOLE', 'MANUEL',
        '$2y$13$9Oq11qmNObFSNXtSOtg/Lew4UU9vHpAMD4oNWdYf0aYaYEKtDzbv.',
        FALSE, NULL, NULL, TRUE
    ),
    (
        '019dd100-0000-7000-8000-000000000405', 'E2E-ACTIVATION', 'Activation', 'Alice',
        'e2e.activation@jambville.test', 'BENEVOLE', 'MANUEL', NULL,
        TRUE, '60ac37c4a47a7edbebfdbc87b9afef05676f9152c3b81660d5c2ec3447f02d1a',
        '2099-12-31 23:59:59+00', FALSE
    );

COMMIT;
