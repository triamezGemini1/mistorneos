-- Asignación de mesas a operadores por torneo y ronda
-- Un operador (usuario con role=operador) puede atender varias mesas; cada mesa se asigna a un operador.
CREATE TABLE IF NOT EXISTS operador_mesa_asignacion (
  torneo_id INT NOT NULL,
  ronda INT NOT NULL,
  mesa_numero INT NOT NULL,
  user_id_operador INT NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (torneo_id, ronda, mesa_numero),
  KEY idx_operador (user_id_operador),
  CONSTRAINT fk_oma_torneo FOREIGN KEY (torneo_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_oma_operador FOREIGN KEY (user_id_operador) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
