USE sg_services;

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