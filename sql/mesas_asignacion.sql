-- Snapshot de asignación por mesa/ronda (torneos por equipos u otros).
-- tournament_id = id del torneo para el que se generó la ronda (mismo criterio que partiresul.id_torneo).

CREATE TABLE IF NOT EXISTS mesas_asignacion (
  id INT NOT NULL AUTO_INCREMENT,
  tournament_id INT NOT NULL COMMENT 'FK lógica a tournaments.id',
  ronda INT NOT NULL,
  mesa INT NOT NULL,
  secuencia TINYINT NOT NULL,
  id_usuario INT NOT NULL,
  codigo_equipo VARCHAR(32) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_torneo_ronda_mesa_seq (tournament_id, ronda, mesa, secuencia),
  KEY idx_torneo_ronda (tournament_id, ronda)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
