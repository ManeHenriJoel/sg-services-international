USE sg_services;

ALTER TABLE terrains
  ADD COLUMN reference VARCHAR(32) NULL UNIQUE AFTER id,
  ADD COLUMN quartier VARCHAR(120) NOT NULL DEFAULT '' AFTER zone_commune,
  ADD COLUMN caracteristiques JSON NULL AFTER quartier,
  MODIFY COLUMN statut_juridique VARCHAR(100) NOT NULL DEFAULT 'À vérifier';

UPDATE terrains
SET reference=CONCAT('SG-TR-',LPAD(id,6,'0'))
WHERE reference IS NULL OR reference='';

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