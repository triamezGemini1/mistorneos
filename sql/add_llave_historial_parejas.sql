-- Añade columna llave (id_menor-id_mayor) a historial_parejas
-- Regla: siempre guardar como id_menor-id_mayor. Una sola consulta: WHERE torneo_id = ? AND llave = '123-456'
-- Ejecutar después de create_historial_parejas.sql (una sola vez)

-- Si la columna ya existe, comentar la línea siguiente
ALTER TABLE historial_parejas ADD COLUMN llave VARCHAR(32) NULL AFTER jugador_2_id;

-- Normalizar: jugador_1_id = menor, jugador_2_id = mayor
UPDATE historial_parejas 
SET jugador_1_id = LEAST(jugador_1_id, jugador_2_id),
    jugador_2_id = GREATEST(jugador_1_id, jugador_2_id)
WHERE jugador_1_id > jugador_2_id;

-- Rellenar llave (id_menor-id_mayor)
UPDATE historial_parejas 
SET llave = CONCAT(jugador_1_id, '-', jugador_2_id)
WHERE llave IS NULL OR llave = '';

-- Índice para búsqueda rápida por torneo + llave
CREATE INDEX idx_torneo_llave ON historial_parejas (torneo_id, llave);
