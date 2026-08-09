--liquibase formatted sql

--changeset benevole-jambville:V001
CREATE EXTENSION IF NOT EXISTS btree_gist;

CREATE SCHEMA IF NOT EXISTS benevole_jambville AUTHORIZATION benevole_jambville;

COMMENT ON SCHEMA benevole_jambville IS
    'Schéma principal de l’application de gestion des bénévoles de Jambville';

ALTER ROLE benevole_jambville
    IN DATABASE benevole_jambville
    SET search_path TO benevole_jambville, public;

CREATE TABLE benevole_jambville.utilisateur (
    id UUID NOT NULL DEFAULT uuidv7(),
    code_adherent VARCHAR(50) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(180) NOT NULL,
    telephone VARCHAR(30),
    code_fonction VARCHAR(50),
    code_structure VARCHAR(50),
    role VARCHAR(30) NOT NULL DEFAULT 'BENEVOLE',
    source_role VARCHAR(20) NOT NULL DEFAULT 'MANUEL',
    role_calcule_le TIMESTAMPTZ,
    version_regle_role VARCHAR(30),
    mot_de_passe VARCHAR(255),
    changement_mot_de_passe_requis BOOLEAN NOT NULL DEFAULT FALSE,
    jeton_activation VARCHAR(64),
    expiration_jeton_activation TIMESTAMPTZ,
    vegetarien BOOLEAN NOT NULL DEFAULT FALSE,
    allergie_oeuf BOOLEAN NOT NULL DEFAULT FALSE,
    allergie_arachide BOOLEAN NOT NULL DEFAULT FALSE,
    regime_autre TEXT,
    besoin_couchage TEXT,
    foulard_remis BOOLEAN NOT NULL DEFAULT FALSE,
    tenue_remise BOOLEAN NOT NULL DEFAULT FALSE,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    telephone_modifie_localement BOOLEAN NOT NULL DEFAULT FALSE,
    derniere_synchronisation_le TIMESTAMPTZ,
    present_derniere_synchronisation BOOLEAN,
    absent_depuis TIMESTAMPTZ,
    desactive_par_synchronisation_le TIMESTAMPTZ,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_utilisateur PRIMARY KEY (id),
    CONSTRAINT uq_utilisateur_code_adherent UNIQUE (code_adherent),
    CONSTRAINT ck_utilisateur_role CHECK (role IN ('EQUIPE_PILOTE', 'SALARIE_ACCUEIL', 'BENEVOLE')),
    CONSTRAINT ck_utilisateur_source_role CHECK (source_role IN ('MANUEL', 'CSV', 'API')),
    CONSTRAINT ck_utilisateur_activation CHECK (
        (jeton_activation IS NULL AND expiration_jeton_activation IS NULL)
        OR (jeton_activation IS NOT NULL AND expiration_jeton_activation IS NOT NULL)
    )
);

CREATE UNIQUE INDEX uq_utilisateur_email_normalise
    ON benevole_jambville.utilisateur (LOWER(email));
CREATE INDEX idx_utilisateur_nom_prenom
    ON benevole_jambville.utilisateur (nom, prenom);
CREATE INDEX idx_utilisateur_codes_role
    ON benevole_jambville.utilisateur (code_fonction, code_structure);

CREATE TABLE benevole_jambville.identite_authentification (
    id UUID NOT NULL DEFAULT uuidv7(),
    utilisateur_id UUID NOT NULL,
    fournisseur VARCHAR(50) NOT NULL,
    emetteur_sso VARCHAR(255) NOT NULL,
    sujet_sso VARCHAR(255) NOT NULL,
    code_adherent_constate VARCHAR(50) NOT NULL,
    email_constate VARCHAR(180),
    derniere_connexion_le TIMESTAMPTZ,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_identite_authentification PRIMARY KEY (id),
    CONSTRAINT fk_identite_authentification_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE CASCADE,
    CONSTRAINT uq_identite_authentification_sso UNIQUE (emetteur_sso, sujet_sso),
    CONSTRAINT uq_identite_authentification_fournisseur_utilisateur UNIQUE (fournisseur, utilisateur_id)
);

CREATE INDEX idx_identite_authentification_utilisateur
    ON benevole_jambville.identite_authentification (utilisateur_id);

CREATE TABLE benevole_jambville.thematique (
    id UUID NOT NULL DEFAULT uuidv7(),
    nom VARCHAR(120) NOT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    ordre_affichage INTEGER NOT NULL DEFAULT 0,
    date_debut_evenement DATE,
    date_fin_evenement DATE,
    exclusive_sur_periode BOOLEAN NOT NULL DEFAULT FALSE,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_thematique PRIMARY KEY (id),
    CONSTRAINT ck_thematique_ordre CHECK (ordre_affichage >= 0),
    CONSTRAINT ck_thematique_periode_evenement CHECK (
        (date_debut_evenement IS NULL AND date_fin_evenement IS NULL AND NOT exclusive_sur_periode)
        OR (date_debut_evenement IS NOT NULL AND date_fin_evenement IS NOT NULL AND date_fin_evenement >= date_debut_evenement)
    )
);

CREATE UNIQUE INDEX uq_thematique_nom_normalise
    ON benevole_jambville.thematique (LOWER(nom));
CREATE INDEX idx_thematique_periode_evenement
    ON benevole_jambville.thematique (date_debut_evenement, date_fin_evenement)
    WHERE actif AND date_debut_evenement IS NOT NULL;

CREATE TABLE benevole_jambville.inscription (
    id UUID NOT NULL DEFAULT uuidv7(),
    type VARCHAR(20) NOT NULL,
    utilisateur_id UUID,
    thematique_id UUID,
    nom_equipe_compa VARCHAR(150),
    nombre_personnes INTEGER,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    type_couchage VARCHAR(10) NOT NULL,
    nombre_enfants INTEGER NOT NULL DEFAULT 0,
    nombre_vegetariens INTEGER NOT NULL DEFAULT 0,
    nombre_allergie_oeuf INTEGER NOT NULL DEFAULT 0,
    nombre_allergie_arachide INTEGER NOT NULL DEFAULT 0,
    commentaire TEXT,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    cree_par_id UUID NOT NULL,
    modifie_par_id UUID NOT NULL,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_inscription PRIMARY KEY (id),
    CONSTRAINT fk_inscription_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE RESTRICT,
    CONSTRAINT fk_inscription_thematique FOREIGN KEY (thematique_id)
        REFERENCES benevole_jambville.thematique (id) ON DELETE RESTRICT,
    CONSTRAINT fk_inscription_cree_par FOREIGN KEY (cree_par_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE RESTRICT,
    CONSTRAINT fk_inscription_modifie_par FOREIGN KEY (modifie_par_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE RESTRICT,
    CONSTRAINT ck_inscription_type CHECK (type IN ('INDIVIDUELLE', 'COMPAGNON')),
    CONSTRAINT ck_inscription_couchage CHECK (type_couchage IN ('DUR', 'TENTE')),
    CONSTRAINT ck_inscription_dates CHECK (date_fin >= date_debut),
    CONSTRAINT ck_inscription_enfants CHECK (nombre_enfants >= 0),
    CONSTRAINT ck_inscription_effectifs_alimentaires CHECK (
        nombre_vegetariens >= 0
        AND nombre_allergie_oeuf >= 0
        AND nombre_allergie_arachide >= 0
        AND (
            (type = 'INDIVIDUELLE'
                AND nombre_vegetariens = 0
                AND nombre_allergie_oeuf = 0
                AND nombre_allergie_arachide = 0)
            OR (type = 'COMPAGNON'
                AND nombre_vegetariens <= nombre_personnes
                AND nombre_allergie_oeuf <= nombre_personnes
                AND nombre_allergie_arachide <= nombre_personnes)
        )
    ),
    CONSTRAINT ck_inscription_contenu CHECK (
        (type = 'INDIVIDUELLE'
            AND utilisateur_id IS NOT NULL
            AND thematique_id IS NOT NULL
            AND nom_equipe_compa IS NULL
            AND nombre_personnes IS NULL)
        OR
        (type = 'COMPAGNON'
            AND utilisateur_id IS NULL
            AND thematique_id IS NULL
            AND nom_equipe_compa IS NOT NULL
            AND BTRIM(nom_equipe_compa) <> ''
            AND nombre_personnes > 0
            AND nombre_enfants = 0)
    )
);

ALTER TABLE benevole_jambville.inscription
    ADD CONSTRAINT ex_inscription_individuelle_sans_chevauchement
    EXCLUDE USING gist (
        utilisateur_id WITH =,
        daterange(date_debut, date_fin, '[]') WITH &&
    )
    WHERE (type = 'INDIVIDUELLE' AND actif);

CREATE INDEX idx_inscription_periode
    ON benevole_jambville.inscription (date_debut, date_fin);
CREATE INDEX idx_inscription_utilisateur
    ON benevole_jambville.inscription (utilisateur_id);
CREATE INDEX idx_inscription_thematique
    ON benevole_jambville.inscription (thematique_id);

CREATE TABLE benevole_jambville.repas_inscription (
    id UUID NOT NULL DEFAULT uuidv7(),
    inscription_id UUID NOT NULL,
    date_repas DATE NOT NULL,
    type_repas VARCHAR(20) NOT NULL,
    selectionne BOOLEAN NOT NULL DEFAULT TRUE,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_repas_inscription PRIMARY KEY (id),
    CONSTRAINT fk_repas_inscription_inscription FOREIGN KEY (inscription_id)
        REFERENCES benevole_jambville.inscription (id) ON DELETE CASCADE,
    CONSTRAINT uq_repas_inscription UNIQUE (inscription_id, date_repas, type_repas),
    CONSTRAINT ck_repas_inscription_type CHECK (
        type_repas IN ('PETIT_DEJEUNER', 'DEJEUNER', 'DINER')
    )
);

CREATE INDEX idx_repas_inscription_synthese
    ON benevole_jambville.repas_inscription (date_repas, type_repas)
    WHERE selectionne;

CREATE TABLE benevole_jambville.personne_permanence (
    id UUID NOT NULL DEFAULT uuidv7(),
    nom VARCHAR(150) NOT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    ordre_affichage INTEGER NOT NULL DEFAULT 0,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_personne_permanence PRIMARY KEY (id),
    CONSTRAINT ck_personne_permanence_ordre CHECK (ordre_affichage >= 0)
);

CREATE UNIQUE INDEX uq_personne_permanence_nom_normalise
    ON benevole_jambville.personne_permanence (LOWER(nom));

CREATE TABLE benevole_jambville.journee (
    date_journee DATE NOT NULL,
    personne_permanence_id UUID,
    commentaire TEXT,
    modifie_par_id UUID NOT NULL,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_journee PRIMARY KEY (date_journee),
    CONSTRAINT fk_journee_personne_permanence FOREIGN KEY (personne_permanence_id)
        REFERENCES benevole_jambville.personne_permanence (id) ON DELETE RESTRICT,
    CONSTRAINT fk_journee_modifie_par FOREIGN KEY (modifie_par_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE RESTRICT,
    CONSTRAINT ck_journee_contenu CHECK (
        personne_permanence_id IS NOT NULL OR NULLIF(BTRIM(commentaire), '') IS NOT NULL
    )
);

CREATE TABLE benevole_jambville.regle_attribution_role (
    id UUID NOT NULL DEFAULT uuidv7(),
    code_fonction VARCHAR(50) NOT NULL,
    code_structure VARCHAR(50) NOT NULL,
    role_attribue VARCHAR(30) NOT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    commentaire TEXT,
    version VARCHAR(30) NOT NULL,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_regle_attribution_role PRIMARY KEY (id),
    CONSTRAINT uq_regle_attribution_role UNIQUE (code_fonction, code_structure),
    CONSTRAINT ck_regle_attribution_role CHECK (
        role_attribue IN ('EQUIPE_PILOTE', 'SALARIE_ACCUEIL', 'BENEVOLE')
    )
);

CREATE TABLE benevole_jambville.synchronisation_adherents (
    id UUID NOT NULL DEFAULT uuidv7(),
    source VARCHAR(10) NOT NULL,
    statut VARCHAR(30) NOT NULL,
    declenchee_par_id UUID,
    commencee_le TIMESTAMPTZ,
    terminee_le TIMESTAMPTZ,
    nombre_lignes INTEGER NOT NULL DEFAULT 0,
    nombre_creations INTEGER NOT NULL DEFAULT 0,
    nombre_mises_a_jour INTEGER NOT NULL DEFAULT 0,
    nombre_desactivations INTEGER NOT NULL DEFAULT 0,
    nombre_changements_role INTEGER NOT NULL DEFAULT 0,
    nombre_erreurs INTEGER NOT NULL DEFAULT 0,
    rapport JSONB NOT NULL DEFAULT '{}'::jsonb,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_synchronisation_adherents PRIMARY KEY (id),
    CONSTRAINT fk_synchronisation_adherents_utilisateur FOREIGN KEY (declenchee_par_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE SET NULL,
    CONSTRAINT ck_synchronisation_adherents_source CHECK (source IN ('CSV', 'API')),
    CONSTRAINT ck_synchronisation_adherents_statut CHECK (
        statut IN ('EN_PREPARATION', 'EN_COURS', 'TERMINEE', 'TERMINEE_AVEC_ERREURS', 'ECHEC')
    ),
    CONSTRAINT ck_synchronisation_adherents_compteurs CHECK (
        nombre_lignes >= 0
        AND nombre_creations >= 0
        AND nombre_mises_a_jour >= 0
        AND nombre_desactivations >= 0
        AND nombre_changements_role >= 0
        AND nombre_erreurs >= 0
    ),
    CONSTRAINT ck_synchronisation_adherents_dates CHECK (
        terminee_le IS NULL OR commencee_le IS NULL OR terminee_le >= commencee_le
    )
);

CREATE INDEX idx_synchronisation_adherents_date
    ON benevole_jambville.synchronisation_adherents (cree_le DESC);

CREATE TABLE benevole_jambville.journal_audit (
    id UUID NOT NULL DEFAULT uuidv7(),
    utilisateur_id UUID,
    action VARCHAR(100) NOT NULL,
    type_objet VARCHAR(100) NOT NULL,
    objet_id UUID,
    details JSONB NOT NULL DEFAULT '{}'::jsonb,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_journal_audit PRIMARY KEY (id),
    CONSTRAINT fk_journal_audit_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES benevole_jambville.utilisateur (id) ON DELETE SET NULL
);

CREATE INDEX idx_journal_audit_objet
    ON benevole_jambville.journal_audit (type_objet, objet_id);
CREATE INDEX idx_journal_audit_date
    ON benevole_jambville.journal_audit (cree_le DESC);

--rollback DROP SCHEMA IF EXISTS benevole_jambville CASCADE;
--rollback DROP EXTENSION IF EXISTS btree_gist;
