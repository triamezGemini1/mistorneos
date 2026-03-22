-- Añade columna puntos_asistencia a clasiranking (punto por asistencia al torneo)
-- Fórmula: ptosrnk = puntos_posicion + (ganados × puntos_por_partida_ganada) + puntos_asistencia
-- Para posiciones 31+: ptosrnk = (ganados × puntos_por_partida_ganada) + puntos_asistencia
-- Ejecutar una sola vez. Si la columna ya existe, ignorar el error.

ALTER TABLE clasiranking 
ADD COLUMN puntos_asistencia INT DEFAULT 1 
COMMENT 'Puntos por asistencia/participación en el torneo';
