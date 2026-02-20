-- Tabla para almacenar fotos de torneos
CREATE TABLE IF NOT EXISTS tournament_photos (
  id INT NOT NULL AUTO_INCREMENT,
  torneo_id INT NOT NULL,
  ruta_imagen VARCHAR(500) NOT NULL,
  nombre_archivo VARCHAR(255) NOT NULL,
  orden INT NOT NULL DEFAULT 0,
  fecha_subida TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  subido_por INT NULL,
  PRIMARY KEY (id),
  KEY idx_torneo_id (torneo_id),
  KEY idx_orden (orden),
  CONSTRAINT fk_tournament_photos_torneo FOREIGN KEY (torneo_id) REFERENCES tournaments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_tournament_photos_usuario FOREIGN KEY (subido_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






