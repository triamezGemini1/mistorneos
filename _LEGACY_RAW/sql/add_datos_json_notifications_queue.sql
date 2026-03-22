-- Datos estructurados para notificaciones (tarjeta formateada "nueva ronda")
ALTER TABLE notifications_queue
ADD COLUMN datos_json TEXT NULL COMMENT 'JSON con tipo, ronda, mesa, nombre, stats, urls' AFTER url_destino;
