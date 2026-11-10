CREATE DATABASE IF NOT EXISTS sg_services CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sg_services;

CREATE TABLE IF NOT EXISTS utilisateurs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,
  role ENUM('admin','super_admin') NOT NULL DEFAULT 'admin',
  date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS abonnes_alertes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  telephone VARCHAR(60) NOT NULL DEFAULT '',
  date_inscription DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  criteres JSON NULL,
  token_desabonnement CHAR(64) NOT NULL UNIQUE,
  INDEX idx_abonnes_actifs (actif,date_inscription)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS terrains (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(32) NULL UNIQUE,
  titre VARCHAR(180) NOT NULL,
  type_bien ENUM('terrain','maison','lot_loti') NOT NULL DEFAULT 'terrain',
  description TEXT NOT NULL,
  prix DECIMAL(15,2) UNSIGNED NOT NULL,
  superficie DECIMAL(10,2) UNSIGNED NOT NULL,
  statut ENUM('disponible','reserve','vendu') NOT NULL DEFAULT 'disponible',
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  statut_juridique VARCHAR(100) NOT NULL DEFAULT 'À vérifier',
  zone_commune VARCHAR(120) NOT NULL,
  quartier VARCHAR(120) NOT NULL DEFAULT '',
  caracteristiques JSON NULL,
  date_publication TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vues INT UNSIGNED NOT NULL DEFAULT 0,
  INDEX idx_terrains_public (statut, zone_commune, prix, superficie)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documents_terrains (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  terrain_id BIGINT UNSIGNED NOT NULL,
  nom_fichier VARCHAR(180) NOT NULL,
  chemin_fichier VARCHAR(180) NOT NULL,
  taille_fichier BIGINT UNSIGNED NOT NULL,
  date_ajout TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_documents_terrain FOREIGN KEY (terrain_id) REFERENCES terrains(id) ON DELETE CASCADE,
  INDEX idx_documents_terrain (terrain_id,date_ajout)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS photos_terrains (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  terrain_id BIGINT UNSIGNED NOT NULL,
  chemin_fichier VARCHAR(180) NOT NULL,
  type ENUM('photo','video') NOT NULL DEFAULT 'photo',
  CONSTRAINT fk_photos_terrain FOREIGN KEY (terrain_id) REFERENCES terrains(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS soumissions_vendeurs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom_proprietaire VARCHAR(150) NOT NULL,
  contact_whatsapp VARCHAR(60) NOT NULL,
  type_bien ENUM('terrain','maison','lot_loti') NOT NULL,
  description TEXT NOT NULL,
  prix_souhaite DECIMAL(15,2) UNSIGNED NOT NULL,
  fichiers_justificatifs JSON NOT NULL,
  statut_traitement ENUM('nouveau','en_cours','accepte','refuse') NOT NULL DEFAULT 'nouveau',
  date_soumission TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  localisation VARCHAR(180) NOT NULL,
  superficie DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
  terrain_id BIGINT UNSIGNED NULL,
  CONSTRAINT fk_soumissions_terrain FOREIGN KEY (terrain_id) REFERENCES terrains(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demandes_clients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  terrain_id BIGINT UNSIGNED NULL,
  nom VARCHAR(150) NOT NULL,
  contact VARCHAR(80) NOT NULL,
  message TEXT NOT NULL,
  statut ENUM('lu','non_lu') NOT NULL DEFAULT 'non_lu',
  date_demande TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_demandes_terrain FOREIGN KEY (terrain_id) REFERENCES terrains(id) ON DELETE SET NULL,
  INDEX idx_demandes_statut (statut, date_demande)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tentatives_connexion (
  email VARCHAR(190) NOT NULL,
  empreinte_ip CHAR(64) NOT NULL,
  echecs TINYINT UNSIGNED NOT NULL DEFAULT 0,
  derniere_tentative TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (email, empreinte_ip)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parametres (
  cle VARCHAR(80) PRIMARY KEY,
  valeur TEXT NOT NULL,
  date_modification TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS realisations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(160) NOT NULL,
  categorie VARCHAR(120) NOT NULL,
  description VARCHAR(1200) NOT NULL,
  chemin_fichier VARCHAR(180) NOT NULL,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_realisations_actives (actif, date_creation)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS avis_terrains (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  terrain_id BIGINT UNSIGNED NULL,
  realisation_id BIGINT UNSIGNED NULL,
  type_cible ENUM('terrain','service','realisation') NOT NULL DEFAULT 'terrain',
  cible_service VARCHAR(120) NULL,
  nom VARCHAR(120) NOT NULL,
  note TINYINT UNSIGNED NOT NULL,
  commentaire VARCHAR(2000) NOT NULL,
  statut ENUM('en_attente','publie','masque') NOT NULL DEFAULT 'en_attente',
  empreinte_ip CHAR(64) NOT NULL,
  date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_avis_note CHECK (note BETWEEN 1 AND 5),
  CONSTRAINT fk_avis_terrain FOREIGN KEY (terrain_id) REFERENCES terrains(id) ON DELETE SET NULL,
  CONSTRAINT fk_avis_realisation FOREIGN KEY (realisation_id) REFERENCES realisations(id) ON DELETE SET NULL,
  INDEX idx_avis_public (terrain_id, statut, date_creation),
  INDEX idx_avis_service (type_cible, cible_service, statut)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS images_hero (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(140) NOT NULL,
  texte_alternatif VARCHAR(180) NOT NULL,
  chemin_fichier VARCHAR(180) NOT NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hero_actif (actif, position, id)
) ENGINE=InnoDB;

