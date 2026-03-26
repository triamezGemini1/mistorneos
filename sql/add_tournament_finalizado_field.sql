-- Agregar campo finalizado a la tabla tournaments
ALTER TABLE tournaments 
ADD COLUMN finalizado TINYINT(1) DEFAULT 0 COMMENT 'Indica si el torneo está finalizado/cerrado (1 = finalizado, 0 = activo)',
ADD COLUMN fecha_finalizacion DATETIME NULL COMMENT 'Fecha y hora en que se finalizó el torneo';

-- Crear índice para búsquedas rápidas
CREATE INDEX idx_tournaments_finalizado ON tournaments(finalizado);






