-- Motor de torneos v1 — ejecutar manualmente (ignorar errores "Duplicate column" si ya aplicó).
-- MySQL 8+, base mistorneos.

ALTER TABLE usuarios
  ADD COLUMN organizacion_workspace_id INT NULL DEFAULT NULL COMMENT 'Organización (tenant) del admin' AFTER club_id;

ALTER TABLE tournaments
  ADD COLUMN slug VARCHAR(150) NULL DEFAULT NULL AFTER nombre;

ALTER TABLE tournaments
  ADD UNIQUE KEY uk_tournaments_slug (slug);

ALTER TABLE tournaments
  ADD COLUMN tipo_torneo ENUM('individual','parejas','equipos') NOT NULL DEFAULT 'individual' AFTER estatus;

ALTER TABLE inscritos
  ADD COLUMN ratificado TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Listo para ronda 1 / pago confirmado' AFTER estatus;

ALTER TABLE inscritos
  ADD COLUMN presente_sitio TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Presente en sede' AFTER ratificado;

ALTER TABLE inscritos
  ADD KEY idx_inscritos_torneo_ratificado (torneo_id, ratificado);
