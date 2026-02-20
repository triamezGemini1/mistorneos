-- =====================================================
-- MIGRACIÓN: Reestructuración de tabla inscripciones
-- Fecha: 2025-01-XX
-- Descripción: Agrega nuevos campos a la tabla inscripciones existente
-- =====================================================
-- NOTA: La base de datos se selecciona automáticamente por la conexión PDO
-- 
-- IMPORTANTE: Este script MODIFICA la tabla inscripciones existente
-- No elimina datos, solo agrega nuevas columnas

-- Paso 1: Respaldar estructura actual (opcional)
-- CREATE TABLE IF NOT EXISTS `inscripciones_backup` AS 
-- SELECT * FROM `inscripciones` WHERE 1=0;

-- Paso 2: Agregar nuevas columnas si no existen
-- Nota: Usar IF NOT EXISTS no está disponible en ALTER TABLE, 
-- por lo que se manejarán errores de columna duplicada

-- Agregar id_usuario si no existe (para referencia a usuarios)
-- ALTER TABLE `inscripciones` 
-- ADD COLUMN `id_usuario` int UNSIGNED NULL COMMENT 'Referencia a tabla users' AFTER `id`;

-- Agregar id_club si no existe (renombrar club_id si es necesario)
-- ALTER TABLE `inscripciones` 
-- ADD COLUMN `id_club` int UNSIGNED NULL COMMENT 'Club al que pertenece el inscrito' AFTER `id_usuario`;

-- Agregar posicion
ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `posicion` int DEFAULT '0' COMMENT 'Posición en la clasificación del torneo' AFTER `estatus`;

-- Modificar estatus a INT si es ENUM o TINYINT
-- Primero convertir valores existentes si es necesario
ALTER TABLE `inscripciones` 
MODIFY COLUMN `estatus` int DEFAULT '0' COMMENT 'Estatus: 0=pendiente, 1=confirmado, 2=solvente, 3=no_solvente, 4=retirado';

-- Agregar campos de estadísticas
ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `ganados` int DEFAULT '0' COMMENT 'Partidas ganadas' AFTER `estatus`;

ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `perdidos` int DEFAULT '0' COMMENT 'Partidas perdidas' AFTER `ganados`;

ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `efectividad` int DEFAULT '0' COMMENT 'La efectividad es un valor diferencial int' AFTER `perdidos`;

ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `puntos` int DEFAULT '0' COMMENT 'Puntos acumulados en el torneo' AFTER `efectividad`;

ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `ptosrnk` int UNSIGNED DEFAULT '0' COMMENT 'Puntos de ranking: puntos por posición + (partidas ganadas × puntos por partida ganada)' AFTER `puntos`;

-- Agregar campos de sanciones
ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `sancion` int DEFAULT '0' COMMENT 'Código de sanción' AFTER `ptosrnk`;

ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `chancletas` int DEFAULT '0' COMMENT 'Contador de chancletas' AFTER `sancion`;

ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `zapatos` int DEFAULT '0' COMMENT 'Contador de zapatos' AFTER `chancletas`;

ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `tarjeta` int DEFAULT '0' COMMENT 'Número de tarjetas' AFTER `zapatos`;

-- Agregar fecha_inscripcion si no existe (puede ser created_at)
ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `fecha_inscripcion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de inscripción' AFTER `tarjeta`;

-- Agregar inscrito_por
ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `inscrito_por` int UNSIGNED DEFAULT NULL COMMENT 'ID del usuario que hizo la inscripción' AFTER `fecha_inscripcion`;

-- Agregar notas
ALTER TABLE `inscripciones` 
ADD COLUMN IF NOT EXISTS `notas` text COLLATE utf8mb4_unicode_ci COMMENT 'Observaciones' AFTER `inscrito_por`;

-- Paso 3: Agregar índices si no existen
ALTER TABLE `inscripciones` 
ADD INDEX IF NOT EXISTS `idx_posicion` (`posicion`);

ALTER TABLE `inscripciones` 
ADD INDEX IF NOT EXISTS `idx_puntos` (`puntos`);

ALTER TABLE `inscripciones` 
ADD INDEX IF NOT EXISTS `idx_ptosrnk` (`ptosrnk`);

ALTER TABLE `inscripciones` 
ADD INDEX IF NOT EXISTS `idx_estatus` (`estatus`);

-- Agregar índice compuesto único si no existe (id_usuario, torneo_id)
-- ALTER TABLE `inscripciones` 
-- ADD UNIQUE INDEX IF NOT EXISTS `unique_inscripcion` (`id_usuario`, `torneo_id`);

-- Paso 4: Agregar foreign keys si las tablas referenciadas existen
-- (Comentado porque puede que las tablas usuarios/torneos no existan aún)
-- ALTER TABLE `inscripciones`
--   ADD CONSTRAINT `inscripciones_ibfk_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
--   ADD CONSTRAINT `inscripciones_ibfk_torneo` FOREIGN KEY (`torneo_id`) REFERENCES `torneos` (`id`) ON DELETE CASCADE,
--   ADD CONSTRAINT `inscripciones_ibfk_club` FOREIGN KEY (`id_club`) REFERENCES `clubes` (`id`) ON DELETE SET NULL,
--   ADD CONSTRAINT `inscripciones_ibfk_inscrito_por` FOREIGN KEY (`inscrito_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

-- =====================================================
-- NOTAS IMPORTANTES:
-- =====================================================
-- 1. Este script MODIFICA la tabla inscripciones existente
-- 2. No elimina columnas existentes, solo agrega nuevas
-- 3. El campo estatus se convierte a INT si era ENUM o TINYINT
-- 4. Los campos existentes (cedula, nombre, sexo, etc.) se mantienen
-- 5. Las foreign keys están comentadas - descomentar cuando las tablas referenciadas existan
-- =====================================================

