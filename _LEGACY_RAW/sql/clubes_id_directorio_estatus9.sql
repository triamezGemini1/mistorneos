-- Unificación directorio → clubes: imagen del directorio en tabla clubes.
-- Estatus 9 = procede del directorio (pendiente de que el club acepte invitación y complete datos).
-- Ejecutar una sola vez. Si id_directorio_club ya existe, omitir el ALTER.

ALTER TABLE clubes
ADD COLUMN id_directorio_club INT NULL DEFAULT NULL COMMENT 'ID en directorio_clubes si el club se creó desde invitación por directorio'
AFTER organizacion_id;

ALTER TABLE clubes
ADD UNIQUE INDEX idx_clubes_id_directorio (id_directorio_club);

-- Estatus 9 ya es válido (estatus TINYINT). Uso documentado:
-- 0 = inactivo, 1 = activo, 9 = procede del directorio (pendiente aceptación/completar datos al loguearse).
