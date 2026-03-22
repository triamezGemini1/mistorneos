-- Agregar columna para marcar eventos masivos
ALTER TABLE tournaments 
ADD COLUMN es_evento_masivo TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Evento masivo con inscripción pública, 0 = Torneo normal' AFTER estatus;

-- Agregar índice para búsquedas rápidas
CREATE INDEX idx_es_evento_masivo ON tournaments(es_evento_masivo, fechator);

