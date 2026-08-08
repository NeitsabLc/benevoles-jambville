--liquibase formatted sql

--changeset benevole-jambville-dev:V001 context:dev
--comment: Comptes de démonstration pour tester la première connexion en développement
INSERT INTO benevole_jambville.utilisateur (
    id,
    code_adherent,
    nom,
    prenom,
    email,
    role,
    source_role,
    mot_de_passe,
    changement_mot_de_passe_requis,
    jeton_activation,
    expiration_jeton_activation
) VALUES
    (
        '019cc100-0000-7000-8000-000000000001',
        'DEV-BENEVOLE',
        'Bénévole',
        'Camille',
        'benevole@jambville.test',
        'BENEVOLE',
        'MANUEL',
        NULL,
        TRUE,
        '08ff40543cbe10db2b0a2f0ebdbe15f4a5de63611f727b06d6dcf89d67cfa2fe',
        '2099-12-31 23:59:59+01'
    ),
    (
        '019cc100-0000-7000-8000-000000000002',
        'DEV-ACCUEIL',
        'Accueil',
        'Sasha',
        'accueil@jambville.test',
        'SALARIE_ACCUEIL',
        'MANUEL',
        NULL,
        TRUE,
        '09b33e946b23555f5b33408598ea63b343bc6b4aea1fdb99dcd0de408063cc63',
        '2099-12-31 23:59:59+01'
    ),
    (
        '019cc100-0000-7000-8000-000000000003',
        'DEV-PILOTE',
        'Pilote',
        'Dominique',
        'pilote@jambville.test',
        'EQUIPE_PILOTE',
        'MANUEL',
        NULL,
        TRUE,
        '4867e86e65f9bee1db60f906154a84df507ce8e4725657c1f9206f922195bf01',
        '2099-12-31 23:59:59+01'
    );

--rollback DELETE FROM benevole_jambville.utilisateur WHERE code_adherent IN ('DEV-BENEVOLE', 'DEV-ACCUEIL', 'DEV-PILOTE');
