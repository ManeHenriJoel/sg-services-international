USE sg_services;

ALTER TABLE terrains
  ADD COLUMN type_bien ENUM('terrain','maison','lot_loti') NOT NULL DEFAULT 'terrain' AFTER titre;