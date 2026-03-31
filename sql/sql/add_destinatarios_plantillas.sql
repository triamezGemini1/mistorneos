-- Añadir columna destinatarios a plantillas_notificaciones
-- 'inscritos' = solo inscritos del torneo; 'todos_usuarios_admin' = todos los usuarios del administrador (clubes supervisados)

-- Ejecutar una sola vez. Si la columna ya existe, ignorar el error.
ALTER TABLE plantillas_notificaciones
ADD COLUMN destinatarios VARCHAR(30) NOT NULL DEFAULT 'inscritos'
COMMENT 'inscritos = inscritos del torneo; todos_usuarios_admin = todos los usuarios del admin'
AFTER categoria;
