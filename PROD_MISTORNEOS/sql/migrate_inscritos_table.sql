-- =====================================================
-- MIGRACIÓN: Reestructuración de tabla inscripciones
-- Fecha: 2025-01-XX
-- Descripción: Reestructura tabla inscripciones con estructura mejorada
-- =====================================================
-- NOTA: La base de datos se selecciona automáticamente por la conexión PDO
-- 
-- IMPORTANTE: Este script asume que existen las siguientes tablas:
--   - usuarios (o users, ajustar si es necesario)
--   - torneos (o tournaments, ajustar si es necesario)
--   - clubes
-- 
-- Si las tablas tienen nombres diferentes, ajustar las foreign keys antes de ejecutar

-- Paso 1: Crear tabla temporal para respaldar datos existentes (si aplica)
CREATE TABLE IF NOT EXISTS `inscripciones_backup` AS 
SELECT * FROM `inscripciones` WHERE 1=0;

-- Paso 2: Respaldar datos existentes si la tabla ya existe
-- (No eliminamos la tabla, la modificamos)

-- Paso 3: Agregar nuevas columnas si no existen (ALTER TABLE en lugar de CREATE)
-- Primero verificamos si necesitamos agregar columnas nuevas
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_usuario` int UNSIGNED NOT NULL COMMENT 'Referencia a tabla users',
  `torneo_id` int UNSIGNED NOT NULL COMMENT 'Referencia a tabla tournaments',
  `id_club` int UNSIGNED DEFAULT NULL COMMENT 'Club al que pertenece el inscrito',
  `posicion` int DEFAULT '0' COMMENT 'Posición en la clasificación del torneo',
  `estatus` int DEFAULT '0' COMMENT 'Estatus: 0=pendiente, 1=confirmado, 2=solvente, 3=no_solvente, 4=retirado',
  `ganados` int DEFAULT '0' COMMENT 'Partidas ganadas',
  `perdidos` int DEFAULT '0' COMMENT 'Partidas perdidas',
  `efectividad` int DEFAULT '0' COMMENT 'La efectividad es un valor diferencial int',
  `puntos` int DEFAULT '0' COMMENT 'Puntos acumulados en el torneo',
  `ptosrnk` int UNSIGNED DEFAULT '0' COMMENT 'Puntos de ranking: puntos por posición + (partidas ganadas × puntos por partida ganada)',
  `sancion` int DEFAULT '0' COMMENT 'Código de sanción',
  `chancletas` int DEFAULT '0' COMMENT 'Contador de chancletas',
  `zapatos` int DEFAULT '0' COMMENT 'Contador de zapatos',
  `tarjeta` int DEFAULT '0' COMMENT 'Número de tarjetas',
  `fecha_inscripcion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `inscrito_por` int UNSIGNED DEFAULT NULL COMMENT 'ID del usuario que hizo la inscripción',
  `notas` text COLLATE utf8mb4_unicode_ci COMMENT 'Observaciones',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inscripcion` (`id_usuario`,`torneo_id`) COMMENT 'Un usuario solo puede inscribirse una vez por torneo',
  KEY `inscrito_por` (`inscrito_por`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_torneo` (`torneo_id`),
  KEY `idx_club` (`id_club`),
  KEY `idx_estatus` (`estatus`),
  KEY `idx_puntos` (`puntos`),
  KEY `idx_posicion` (`posicion`),
  KEY `idx_ptosrnk` (`ptosrnk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paso 4: Agregar restricciones de foreign keys
-- IMPORTANTE: Verificar que las tablas 'usuarios' y 'torneos' existan
-- Si el sistema usa 'users' y 'tournaments', ajustar las referencias antes de ejecutar
ALTER TABLE `inscritos`
  ADD CONSTRAINT `inscritos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscritos_ibfk_2` FOREIGN KEY (`torneo_id`) REFERENCES `torneos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscritos_ibfk_3` FOREIGN KEY (`id_club`) REFERENCES `clubes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inscritos_ibfk_4` FOREIGN KEY (`inscrito_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

-- =====================================================
-- NOTAS IMPORTANTES:
-- =====================================================
-- 1. Esta migración crea una tabla completamente nueva
-- 2. Los datos de la tabla antigua 'inscripciones' NO se migran automáticamente
-- 3. Se requiere un script adicional para migrar datos existentes
-- 4. Después de esta migración, actualizar todo el código PHP que usa 'inscripciones'
-- 5. La tabla 'inscripciones' puede mantenerse temporalmente para referencia
-- =====================================================

