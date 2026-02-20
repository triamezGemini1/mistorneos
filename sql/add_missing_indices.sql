-- Añadir índices faltantes para mejorar rendimiento
-- usuarios.club_id (para consultas de afiliados por club/organización)
CREATE INDEX IF NOT EXISTS idx_usuarios_club_id ON usuarios(club_id);

-- notifications_queue índices para consultas de notificaciones
CREATE INDEX IF NOT EXISTS idx_notifications_queue_usuario ON notifications_queue(usuario_id, canal, estado);
