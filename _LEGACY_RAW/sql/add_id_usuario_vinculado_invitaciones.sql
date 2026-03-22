-- Reclamación de token: usuario autenticado vinculado a la invitación
-- Ejecutar una sola vez en la BD. Si la columna ya existe, ignorar el error.

ALTER TABLE invitaciones
ADD COLUMN id_usuario_vinculado INT UNSIGNED NULL DEFAULT NULL
COMMENT 'ID del usuario (usuarios.id) que reclamó esta invitación'
AFTER token;

-- Índice opcional (puede fallar si ya existe)
-- CREATE INDEX idx_invitaciones_id_usuario_vinculado ON invitaciones (id_usuario_vinculado);
