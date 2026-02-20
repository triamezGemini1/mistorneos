-- =====================================================
-- MIGRACIÓN: Creación de tabla inscritos
-- Fecha: 2025-01-XX
-- Descripción: Crea tabla inscritos para gestionar inscripciones de torneos
-- Esta tabla se relaciona con partiresul durante todo el proceso del torneo
-- =====================================================
-- NOTA: La base de datos se selecciona automáticamente por la conexión PDO
-- 
-- IMPORTANTE: Este script asume que existen las siguientes tablas:
--   - usuarios (o users, ajustar si es necesario)
--   - torneos (o tournaments, ajustar si es necesario)
--   - clubes
-- 
-- Si las tablas tienen nombres diferentes, ajustar las foreign keys antes de ejecutar

-- Eliminar tabla si existe (para recrearla con estructura correcta)
DROP TABLE IF EXISTS `inscritos`;

-- Crear tabla inscritos con estructura completa
CREATE TABLE IF NOT EXISTS `inscritos` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_usuario` int UNSIGNED NOT NULL COMMENT 'Referencia a tabla usuarios - Usado también en partiresul',
  `torneo_id` int UNSIGNED NOT NULL COMMENT 'Referencia a tabla torneos - Usado también en partiresul',
  `id_club` int UNSIGNED DEFAULT NULL COMMENT 'Club al que pertenece el inscrito',
  `posicion` int DEFAULT '0' COMMENT 'Posición en la clasificación del torneo',
  `estatus` int DEFAULT '0' COMMENT 'Estatus: 0=pendiente, 1=confirmado, 2=solvente, 3=no_solvente, 4=retirado',
  `ganados` int DEFAULT '0' COMMENT 'Partidas ganadas (se actualiza desde partiresul)',
  `perdidos` int DEFAULT '0' COMMENT 'Partidas perdidas (se actualiza desde partiresul)',
  `efectividad` int DEFAULT '0' COMMENT 'Efectividad total: suma de efectividad de partiresul',
  `puntos` int DEFAULT '0' COMMENT 'Puntos acumulados en el torneo',
  `ptosrnk` int UNSIGNED DEFAULT '0' COMMENT 'Puntos de ranking: puntos por posición + (partidas ganadas × puntos por partida ganada)',
  `sancion` int DEFAULT '0' COMMENT 'Total de sanciones (suma de partiresul.sancion)',
  `chancletas` int DEFAULT '0' COMMENT 'Total de chancletas (suma de partiresul.chancleta)',
  `zapatos` int DEFAULT '0' COMMENT 'Total de zapatos (suma de partiresul.zapato)',
  `tarjeta` int DEFAULT '0' COMMENT 'Total de tarjetas (suma de partiresul.tarjeta)',
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
  KEY `idx_ptosrnk` (`ptosrnk`),
  KEY `idx_torneo_usuario` (`torneo_id`, `id_usuario`) COMMENT 'Índice compuesto para joins con partiresul'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inscripciones de usuarios en torneos - Se relaciona con partiresul';

-- Agregar restricciones de foreign keys
-- IMPORTANTE: Verificar que las tablas 'usuarios' y 'torneos' existan
-- Si el sistema usa 'users' y 'tournaments', ajustar las referencias antes de ejecutar
ALTER TABLE `inscritos`
  ADD CONSTRAINT `inscritos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscritos_ibfk_2` FOREIGN KEY (`torneo_id`) REFERENCES `torneos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscritos_ibfk_3` FOREIGN KEY (`id_club`) REFERENCES `clubes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inscritos_ibfk_4` FOREIGN KEY (`inscrito_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

-- =====================================================
-- RELACIÓN CON PARTIRESUL:
-- =====================================================
-- partiresul.id_usuario = inscritos.id_usuario
-- partiresul.id_torneo = inscritos.torneo_id
-- 
-- Los campos que se actualizan desde partiresul:
-- - ganados: COUNT de partidas donde resultado1 > resultado2 o resultado2 > resultado1
-- - perdidos: COUNT de partidas donde se perdió
-- - efectividad: SUM(partiresul.efectividad) WHERE registrado = 1
-- - sancion: SUM(partiresul.sancion) WHERE registrado = 1
-- - chancletas: SUM(partiresul.chancleta) WHERE registrado = 1
-- - zapatos: SUM(partiresul.zapato) WHERE registrado = 1
-- - tarjeta: SUM(partiresul.tarjeta) WHERE registrado = 1
-- =====================================================

