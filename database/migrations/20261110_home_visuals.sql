USE sg_services;

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

ALTER TABLE avis_terrains
  ADD COLUMN realisation_id BIGINT UNSIGNED NULL AFTER terrain_id,
  MODIFY COLUMN type_cible ENUM('terrain','service','realisation') NOT NULL DEFAULT 'terrain',
  ADD CONSTRAINT fk_avis_realisation FOREIGN KEY (realisation_id) REFERENCES realisations(id) ON DELETE SET NULL,
  ADD INDEX idx_avis_realisation (realisation_id, statut);