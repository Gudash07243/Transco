-- ============================================================
--  TRANCO - Schéma de base de données
-- ============================================================

CREATE DATABASE IF NOT EXISTS transco_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE transco_db;

-- --------------------------------------------------------
--  Table UTILISATEUR
-- --------------------------------------------------------
CREATE TABLE UTILISATEUR (
    id_user INT(11) NOT NULL AUTO_INCREMENT,
    email VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('ADMIN', 'CONTROLEUR', 'CLIENT') NOT NULL,
    PRIMARY KEY (id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
--  Table CLIENT
-- --------------------------------------------------------
CREATE TABLE CLIENT (
    id_client INT(11) NOT NULL AUTO_INCREMENT,
    nom VARCHAR(50) NOT NULL,
    postnom VARCHAR(50) NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    id_user INT(11) NOT NULL,
    PRIMARY KEY (id_client),
    UNIQUE (id_user),
    CONSTRAINT fk_client_user FOREIGN KEY (id_user) REFERENCES UTILISATEUR(id_user) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
--  Table BUS
-- --------------------------------------------------------
CREATE TABLE BUS (
    id_bus INT(11) NOT NULL AUTO_INCREMENT,
    plaque_bus VARCHAR(20) NOT NULL UNIQUE,
    capacite INT(11) NOT NULL,
    PRIMARY KEY (id_bus)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
--  Table LIGNE
-- --------------------------------------------------------
CREATE TABLE LIGNE (
    id_ligne INT(11) NOT NULL AUTO_INCREMENT,
    ville_depart VARCHAR(100) NOT NULL,
    ville_destination VARCHAR(100) NOT NULL,
    PRIMARY KEY (id_ligne)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
--  Table VOYAGE
-- --------------------------------------------------------
CREATE TABLE VOYAGE (
    id_voyage INT(11) NOT NULL AUTO_INCREMENT,
    date_depart DATE NOT NULL,
    heure_date TIME NOT NULL,
    prix_billet DECIMAL(10,2) NOT NULL,
    places_disponibles INT(11) NOT NULL,
    id_bus INT(11) NOT NULL,
    id_ligne INT(11) NOT NULL,
    PRIMARY KEY (id_voyage),
    CONSTRAINT fk_voyage_bus FOREIGN KEY (id_bus) REFERENCES BUS(id_bus),
    CONSTRAINT fk_voyage_ligne FOREIGN KEY (id_ligne) REFERENCES LIGNE(id_ligne)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
--  Table COMMANDE
-- --------------------------------------------------------
CREATE TABLE COMMANDE (
    id_commande INT(11) NOT NULL AUTO_INCREMENT,
    date_commande DATETIME DEFAULT CURRENT_TIMESTAMP,
    montant_total DECIMAL(10,2) NOT NULL,
    id_client INT(11) NOT NULL,
    PRIMARY KEY (id_commande),
    CONSTRAINT fk_commande_client FOREIGN KEY (id_client) REFERENCES CLIENT(id_client)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
--  Table BILLET
-- --------------------------------------------------------
CREATE TABLE BILLET (
    id_billet INT(11) NOT NULL AUTO_INCREMENT,
    numero_siege INT(11) NOT NULL,
    code_qr VARCHAR(255) NOT NULL UNIQUE,
    statut_billet ENUM('VALIDE', 'UTILISE', 'ANNULE') DEFAULT 'VALIDE',
    id_commande INT(11) NOT NULL,
    id_voyage INT(11) NOT NULL,
    PRIMARY KEY (id_billet),
    CONSTRAINT fk_billet_commande FOREIGN KEY (id_commande) REFERENCES COMMANDE(id_commande) ON DELETE CASCADE,
    CONSTRAINT fk_billet_voyage FOREIGN KEY (id_voyage) REFERENCES VOYAGE(id_voyage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  Données de démonstration
-- ============================================================

-- Compte ADMIN (mot de passe : Admin@2024)
INSERT INTO UTILISATEUR (email, mot_de_passe, role) VALUES
('admin@tranco.com', '$2y$10$gVB0uOj0F.ZTW6MC0u7r1.pmjVzd..dSy.H2nv6U1M2doTrgZR1u2', 'ADMIN');
-- Note: remplacez le hash par password_hash('Admin@2024', PASSWORD_DEFAULT) en PHP

-- Bus de démonstration
INSERT INTO BUS (plaque_bus, capacite) VALUES
('KIN-001-A', 50),
('KIN-002-B', 45),
('KIN-003-C', 60);

-- Lignes de démonstration
INSERT INTO LIGNE (ville_depart, ville_destination) VALUES
('Kinshasa', 'Lubumbashi'),
('Kinshasa', 'Matadi'),
('Lubumbashi', 'Kolwezi'),
('Kinshasa', 'Kikwit');

-- Voyages de démonstration
INSERT INTO VOYAGE (date_depart, heure_date, prix_billet, places_disponibles, id_bus, id_ligne) VALUES
('2024-12-20', '06:00:00', 150.00, 50, 1, 1),
('2024-12-20', '08:00:00', 80.00,  45, 2, 2),
('2024-12-21', '07:00:00', 120.00, 60, 3, 3),
('2024-12-22', '06:30:00', 95.00,  50, 1, 4);
