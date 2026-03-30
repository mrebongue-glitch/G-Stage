-- ================================================================
--  BASE DE DONNÉES : gestiondesstagiaires
--  Système de Gestion des Stagiaires — Cellule Informatique
--  Direction Générale des Douanes du Cameroun
--  Généré le : 28 mars 2026
-- ================================================================

-- ── Création / sélection de la base ─────────────────────────────
CREATE DATABASE IF NOT EXISTS gestiondesstagiaires
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gestiondesstagiaires;

-- ================================================================
--  TABLE : encadreurs
-- ================================================================
CREATE TABLE IF NOT EXISTS encadreurs (
  id                  INT           NOT NULL AUTO_INCREMENT,
  nom                 VARCHAR(150)  NOT NULL,
  role                VARCHAR(150)  NOT NULL,
  telephone           VARCHAR(30)   DEFAULT NULL,
  email               VARCHAR(150)  NOT NULL UNIQUE,
  a_compte            TINYINT(1)    NOT NULL DEFAULT 0
                        COMMENT '0 = sans compte, 1 = avec compte',
  invitation_envoyee  TINYINT(1)    NOT NULL DEFAULT 0,
  created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ================================================================
--  TABLE : stagiaires
-- ================================================================
CREATE TABLE IF NOT EXISTS stagiaires (
  id              INT             NOT NULL AUTO_INCREMENT,
  nom             VARCHAR(150)    NOT NULL,
  email           VARCHAR(150)    NOT NULL UNIQUE,
  etablissement   VARCHAR(200)    NOT NULL,
  filiere         VARCHAR(150)    NOT NULL,
  niveau          VARCHAR(50)     NOT NULL,
  date_debut      DATE            NOT NULL,
  date_fin        DATE            NOT NULL,
  statut          ENUM('actif','termine','abandonne') NOT NULL DEFAULT 'actif',
  created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CHECK (date_fin >= date_debut)
) ENGINE=InnoDB;

-- ================================================================
--  TABLE : modules
-- ================================================================
CREATE TABLE IF NOT EXISTS modules (
  id          INT           NOT NULL AUTO_INCREMENT,
  titre       VARCHAR(150)  NOT NULL,
  description TEXT          NOT NULL,
  duree_jours INT           NOT NULL COMMENT 'Durée en jours',
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ================================================================
--  TABLE : affectations
--  Lien stagiaire ↔ module ↔ encadreur
-- ================================================================
CREATE TABLE IF NOT EXISTS affectations (
  id              INT       NOT NULL AUTO_INCREMENT,
  stagiaire_id    INT       NOT NULL,
  module_id       INT       NOT NULL,
  encadreur_id    INT       NOT NULL,
  date_affectation DATE     NOT NULL,
  statut          ENUM('en-cours','terminee') NOT NULL DEFAULT 'en-cours',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_aff_stagiaire  FOREIGN KEY (stagiaire_id)  REFERENCES stagiaires (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_aff_module     FOREIGN KEY (module_id)     REFERENCES modules    (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_aff_encadreur  FOREIGN KEY (encadreur_id)  REFERENCES encadreurs (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ================================================================
--  TABLE : evaluations
-- ================================================================
CREATE TABLE IF NOT EXISTS evaluations (
  id              INT            NOT NULL AUTO_INCREMENT,
  affectation_id  INT            NOT NULL,
  stagiaire_id    INT            NOT NULL,
  module_id       INT            NOT NULL,
  date_evaluation DATE           NOT NULL,
  note            DECIMAL(4,2)   NOT NULL COMMENT 'Note sur 20',
  appreciation    ENUM('Bien','Insuffisant') NOT NULL
                    COMMENT 'Bien si note >= 10, Insuffisant sinon',
  commentaire     TEXT           DEFAULT NULL,
  created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_eval_affectation  FOREIGN KEY (affectation_id) REFERENCES affectations (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_eval_stagiaire    FOREIGN KEY (stagiaire_id)   REFERENCES stagiaires   (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_eval_module       FOREIGN KEY (module_id)      REFERENCES modules      (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CHECK (note BETWEEN 0 AND 20)
) ENGINE=InnoDB;

-- ================================================================
--  TABLE : presences
-- ================================================================
CREATE TABLE IF NOT EXISTS presences (
  id              INT     NOT NULL AUTO_INCREMENT,
  stagiaire_id    INT     NOT NULL,
  date_presence   DATE    NOT NULL,
  statut          ENUM('present','absent') NOT NULL,
  motif           VARCHAR(255) DEFAULT NULL
                    COMMENT 'Motif d''absence (libre)',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_presence_jour (stagiaire_id, date_presence),
  CONSTRAINT fk_pres_stagiaire FOREIGN KEY (stagiaire_id) REFERENCES stagiaires (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ================================================================
--  TABLE : attestations
--  Journal des attestations générées
-- ================================================================
CREATE TABLE IF NOT EXISTS attestations (
  id              INT           NOT NULL AUTO_INCREMENT,
  stagiaire_id    INT           NOT NULL,
  reference       VARCHAR(50)   NOT NULL UNIQUE
                    COMMENT 'Format : CI/ANNEE/NNN',
  mention         VARCHAR(255)  NOT NULL,
  date_generation DATE          NOT NULL DEFAULT (CURDATE()),
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_attest_stagiaire FOREIGN KEY (stagiaire_id) REFERENCES stagiaires (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;


-- ================================================================
--  TABLE : utilisateurs
--  Comptes de connexion à la plateforme (admin / encadreur)
-- ================================================================
CREATE TABLE IF NOT EXISTS utilisateurs (
  id           INT           NOT NULL AUTO_INCREMENT,
  nom          VARCHAR(150)  NOT NULL,
  identifiant  VARCHAR(80)   NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255)  NOT NULL COMMENT 'Hash bcrypt via password_hash()',
  role         ENUM('admin','encadreur') NOT NULL DEFAULT 'encadreur',
  encadreur_id INT           DEFAULT NULL,
  actif        TINYINT(1)    NOT NULL DEFAULT 1,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_utilisateurs_encadreur (encadreur_id),
  CONSTRAINT fk_utilisateurs_encadreur
    FOREIGN KEY (encadreur_id) REFERENCES encadreurs (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Administrateur par défaut
-- Mot de passe en clair : mother237  (hashé avec PASSWORD_BCRYPT)
INSERT IGNORE INTO utilisateurs (nom, identifiant, mot_de_passe, role) VALUES (
  'EBONGUE MOÏSE ROGER',
  'mrebongue',
  '$2y$10$gb6.5Za8ZHwaLd/q5Q4pJOWiKIExLhKa8PSyhcn2Qcs8FA9mxZYva',
  'admin'
);


-- ================================================================
--  DONNÉES DE RÉFÉRENCE — Encadreurs
-- ================================================================
INSERT INTO encadreurs (id, nom, role, telephone, email, a_compte, invitation_envoyee) VALUES
  (1, 'MBARGA Jean-Paul',    'Chef de section Developpement',    '+237 699 123 456', 'jp.mbarga@douanes.cm', 0, 0),
  (2, 'FOTSO Marie-Claire',  'Ingenieur Reseau',                 '+237 677 234 567', 'mc.fotso@douanes.cm',  0, 0),
  (3, 'NDAM Emmanuel',       'Administrateur Base de Donnees',   '+237 655 345 678', 'e.ndam@douanes.cm',    0, 0);


-- ================================================================
--  DONNÉES DE RÉFÉRENCE — Stagiaires
-- ================================================================
INSERT INTO stagiaires (id, nom, email, etablissement, filiere, niveau, date_debut, date_fin, statut) VALUES
  (1, 'ATANGANA Cecile',   'c.atangana@gmail.com',      'Universite de Yaounde I',                        'Informatique de Gestion', 'BTS 2', '2025-10-01', '2026-01-31', 'termine'),
  (2, 'TCHOUPO Alain',     'alain.tchoupo@gmail.com',   'INSTITUT UNIVERSITAIRE SIANTOU De Yaounde',      'Informatique',             'BTS 2', '2026-01-15', '2026-04-15', 'actif'),
  (3, 'KAMGA Patrick',     'p.kamga@outlook.com',       'ENSPY Yaounde',                                  'Telecommunications',       'BTS 2', '2026-01-20', '2026-04-20', 'actif'),
  (4, 'NGONO Stephanie',   'n.ngono@gmail.com',         'IUT de Douala',                                  'Genie Informatique',       'BTS 2', '2026-02-01', '2026-05-30', 'abandonne');


-- ================================================================
--  DONNÉES DE RÉFÉRENCE — Modules
-- ================================================================
INSERT INTO modules (id, titre, description, duree_jours) VALUES
  (1, 'Comptabilite Douaniere',           'Notions essentielles de comptabilite appliquee aux douanes',                                        10),
  (2, 'Securite Informatique',            'Cybersecurite, protection des donnees et gestion des acces',                                        10),
  (3, 'Reseau et Infrastructure',         'Configuration des reseaux informatiques, maintenance des serveurs et gestion de l''infrastructure',  10),
  (4, 'Systeme d''Information Douanier',  'Utilisation et administration du SYDONIA et autres systemes douaniers',                             15),
  (5, 'Maintenance Informatique',         'Maintenance preventive et corrective du materiel informatique',                                     10),
  (6, 'Developpement Web',                'Conception et developpement d''applications web (HTML, CSS, JavaScript)',                           15),
  (7, 'Base de Donnees',                  'Administration et gestion des bases de donnees (SQL, MySQL)',                                       15);


-- ================================================================
--  DONNÉES — Affectations
-- ================================================================
--  NGONO Stephanie → Base de Donnees → FOTSO Marie-Claire
INSERT INTO affectations (id, stagiaire_id, module_id, encadreur_id, date_affectation, statut) VALUES
  (1, 4, 7, 2, '2026-03-17', 'en-cours'),
--  TCHOUPO Alain → Developpement Web → MBARGA Jean-Paul
  (2, 2, 6, 1, '2026-03-17', 'en-cours'),
--  KAMGA Patrick → Reseau et Infrastructure → NDAM Emmanuel
  (3, 3, 3, 3, '2026-03-17', 'en-cours'),
--  ATANGANA Cecile → Systeme d'Information Douanier → MBARGA Jean-Paul (terminée)
  (4, 1, 4, 1, '2025-10-01', 'terminee');


-- ================================================================
--  DONNÉES — Evaluations
-- ================================================================
INSERT INTO evaluations (id, affectation_id, stagiaire_id, module_id, date_evaluation, note, appreciation, commentaire) VALUES
  (1, 1, 4, 7, '2026-03-17', 15.00, 'Bien',        'Bon etat d''esprit'),
  (2, 2, 2, 6, '2026-03-17',  7.00, 'Insuffisant', 'Peut mieux faire'),
  (3, 4, 1, 4, '2026-01-30', 17.00, 'Bien',        'Excellente performance, tres serieuse');


-- ================================================================
--  DONNÉES — Présences (exemples — semaine du 24 mars 2026)
-- ================================================================
INSERT INTO presences (stagiaire_id, date_presence, statut, motif) VALUES
  -- Lundi 24 mars
  (2, '2026-03-24', 'present',  NULL),
  (3, '2026-03-24', 'present',  NULL),
  -- Mardi 25 mars
  (2, '2026-03-25', 'present',  NULL),
  (3, '2026-03-25', 'absent',   'Convocation administrative'),
  -- Mercredi 26 mars
  (2, '2026-03-26', 'present',  NULL),
  (3, '2026-03-26', 'present',  NULL),
  -- Jeudi 27 mars
  (2, '2026-03-27', 'absent',   'Problème de sante'),
  (3, '2026-03-27', 'present',  NULL),
  -- Vendredi 28 mars
  (2, '2026-03-28', 'present',  NULL),
  (3, '2026-03-28', 'present',  NULL);


-- ================================================================
--  DONNÉES — Attestations (déjà générées)
-- ================================================================
INSERT INTO attestations (stagiaire_id, reference, mention, date_generation) VALUES
  (1, 'CI/2026/001', 'serieux et entiere satisfaction', '2026-02-01');


-- ================================================================
--  VUES UTILES
-- ================================================================

-- Vue : fiche complète d'un stagiaire avec son encadreur courant
CREATE OR REPLACE VIEW v_stagiaires_complet AS
SELECT
  s.id            AS stagiaire_id,
  s.nom           AS stagiaire,
  s.etablissement,
  s.filiere,
  s.niveau,
  s.date_debut,
  s.date_fin,
  s.statut,
  m.titre         AS module_courant,
  e.nom           AS encadreur_courant,
  a.statut        AS statut_affectation
FROM stagiaires s
LEFT JOIN affectations a ON a.stagiaire_id = s.id AND a.statut = 'en-cours'
LEFT JOIN modules      m ON m.id = a.module_id
LEFT JOIN encadreurs   e ON e.id = a.encadreur_id;

-- Vue : tableau de bord — statistiques globales
CREATE OR REPLACE VIEW v_stats_dashboard AS
SELECT
  (SELECT COUNT(*) FROM stagiaires)                                  AS total_stagiaires,
  (SELECT COUNT(*) FROM stagiaires WHERE statut = 'actif')           AS stagiaires_actifs,
  (SELECT COUNT(*) FROM stagiaires WHERE statut = 'termine')         AS stagiaires_termines,
  (SELECT COUNT(*) FROM stagiaires WHERE statut = 'abandonne')       AS stagiaires_abandonnes,
  (SELECT COUNT(*) FROM encadreurs)                                  AS total_encadreurs,
  (SELECT COUNT(*) FROM modules)                                     AS total_modules,
  (SELECT COUNT(*) FROM affectations WHERE statut = 'en-cours')      AS affectations_en_cours,
  (SELECT ROUND(AVG(note), 2) FROM evaluations)                      AS moyenne_generale,
  (SELECT COUNT(*) FROM evaluations WHERE appreciation = 'Bien')     AS evaluations_bien,
  (SELECT COUNT(*) FROM evaluations WHERE appreciation = 'Insuffisant') AS evaluations_insuffisant;

-- Vue : taux de présence par stagiaire
CREATE OR REPLACE VIEW v_taux_presence AS
SELECT
  s.id,
  s.nom,
  COUNT(p.id)                                                    AS jours_enregistres,
  SUM(p.statut = 'present')                                      AS jours_presents,
  SUM(p.statut = 'absent')                                       AS jours_absents,
  ROUND(SUM(p.statut = 'present') * 100.0 / COUNT(p.id), 1)     AS taux_presence_pct
FROM stagiaires s
LEFT JOIN presences p ON p.stagiaire_id = s.id
GROUP BY s.id, s.nom;


-- ================================================================
--  PROCÉDURE : générer une attestation
-- ================================================================
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS generer_attestation(IN p_stagiaire_id INT)
BEGIN
  DECLARE v_annee       INT;
  DECLARE v_num         INT;
  DECLARE v_reference   VARCHAR(50);
  DECLARE v_mention     VARCHAR(255);
  DECLARE v_statut      VARCHAR(20);

  SET v_annee = YEAR(CURDATE());

  -- Numéro séquentiel sur l'année en cours
  SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(reference, '/', -1) AS UNSIGNED)), 0) + 1
    INTO v_num
    FROM attestations
   WHERE YEAR(date_generation) = v_annee;

  SET v_reference = CONCAT('CI/', v_annee, '/', LPAD(v_num, 3, '0'));

  -- Mention selon le statut du stagiaire
  SELECT statut INTO v_statut FROM stagiaires WHERE id = p_stagiaire_id;
  SET v_mention = CASE
    WHEN v_statut = 'termine'   THEN 'serieux et entiere satisfaction'
    WHEN v_statut = 'actif'     THEN 'serieux et engagement'
    ELSE 'application'
  END;

  INSERT INTO attestations (stagiaire_id, reference, mention, date_generation)
    VALUES (p_stagiaire_id, v_reference, v_mention, CURDATE())
    ON DUPLICATE KEY UPDATE
      mention         = v_mention,
      date_generation = CURDATE();

  SELECT a.id, s.nom, a.reference, a.mention, a.date_generation
    FROM attestations a
    JOIN stagiaires s ON s.id = a.stagiaire_id
   WHERE a.stagiaire_id = p_stagiaire_id
     AND a.reference = v_reference;
END $$

DELIMITER ;


-- ================================================================
--  FIN DU SCRIPT
-- ================================================================
