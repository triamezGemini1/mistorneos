-- Registrar id de usuario en directorio de clubes (para invitaciones futuras)
-- Ejecutar una sola vez. Si la columna ya existe, ignorar el error.

ALTER TABLE directorio_clubes
ADD COLUMN id_usuario INT UNSIGNED NULL DEFAULT NULL
COMMENT 'ID del usuario (usuarios.id) que gestiona este club en invitaciones'
AFTER email;
