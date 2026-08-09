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
    );

--rollback DELETE FROM benevole_jambville.utilisateur WHERE code_adherent IN ('DEV-BENEVOLE', 'DEV-ACCUEIL', 'DEV-PILOTE');
