-- =====================================================
-- MIGRACIÓN: Creación de tabla partiresul
-- Fecha: 2025-01-XX
-- Descripción: Crea tabla para control de partidas realizadas en torneos
-- =====================================================
-- NOTA: La base de datos se selecciona automáticamente por la conexión PDO
-- 
-- IMPORTANTE: Este script asume que existen las siguientes tablas:
--   - usuarios (o users, ajustar si es necesario)
--   - torneos (o tournaments, ajustar si es necesario)
-- 
-- Si las tablas tienen nombres diferentes, ajustar las foreign keys antes de ejecutar

-- Eliminar tabla si existe (para recrearla)
DROP TABLE IF EXISTS `partiresul`;

-- Crear tabla partiresul
CREATE TABLE IF NOT EXISTS `partiresul` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_torneo` int UNSIGNED NOT NULL,
  `partida` int NOT NULL COMMENT 'Número de partida',
  `mesa` int NOT NULL COMMENT 'Número de mesa',
  `secuencia` int NOT NULL COMMENT 'Secuencia dentro de la partida',
  `id_usuario` int UNSIGNED NOT NULL COMMENT 'Usuario que jugó',
  `resultado1` int DEFAULT '0' COMMENT 'Primer resultado',
  `resultado2` int DEFAULT '0' COMMENT 'Segundo resultado',
  `efectividad` int DEFAULT '0' COMMENT 'Efectividad de la partida: +puntos normales, -puntos si forfait',
  `ff` tinyint(1) DEFAULT '0' COMMENT 'Forfeit (No presentado)',
  `tarjeta` int DEFAULT '0' COMMENT 'Tarjetas recibidas',
  `sancion` int DEFAULT '0' COMMENT 'Sanciones aplicadas',
  `chancleta` int DEFAULT '0' COMMENT 'Chancletas',
  `zapato` int DEFAULT '0' COMMENT 'Zapatos',
  `fecha_partida` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `registrado_por` int UNSIGNED NOT NULL COMMENT 'Admin que registró el resultado',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `registrado` tinyint(1) DEFAULT '0' COMMENT 'Indica si los resultados de esta fila ya fueron registrados (0=No, 1=Sí)',
  PRIMARY KEY (`id`),
  KEY `registrado_por` (`registrado_por`),
  KEY `idx_torneo` (`id_torneo`),
  KEY `idx_partida` (`partida`),
  KEY `idx_mesa` (`mesa`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_torneo_partida_mesa` (`id_torneo`,`partida`,`mesa`),
  KEY `idx_registrado_torneo_partida_mesa` (`id_torneo`,`partida`,`mesa`,`registrado`),
  KEY `idx_partiresul_efectividad` (`efectividad`),
  KEY `idx_partiresul_torneo_registrado` (`id_torneo`,`registrado`) COMMENT 'Agregación WHERE id_torneo=? AND registrado=1 (Mejora 4)',
  KEY `idx_partiresul_torneo_usuario_partida` (`id_torneo`,`id_usuario`,`partida`) COMMENT 'GROUP BY y eliminación duplicados (Mejora 4)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Resultados de partidas por jugador - Incluye efectividad por forfait';

-- Agregar restricciones de foreign keys
-- IMPORTANTE: partiresul se relaciona con inscritos mediante id_usuario e id_torneo
ALTER TABLE `partiresul`
  ADD CONSTRAINT `partiresul_ibfk_1` FOREIGN KEY (`id_torneo`) REFERENCES `torneos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `partiresul_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `partiresul_ibfk_3` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;

-- Agregar índice compuesto para optimizar joins con inscritos
ALTER TABLE `partiresul`
  ADD INDEX `idx_torneo_usuario` (`id_torneo`, `id_usuario`) COMMENT 'Índice para joins con inscritos';

-- Mejora 4: índices para agregación y duplicados (si no se crearon en el CREATE TABLE)
-- Ejecutar solo si la tabla ya existía sin estos índices:
-- ALTER TABLE partiresul ADD KEY idx_partiresul_torneo_registrado (id_torneo, registrado);
-- ALTER TABLE partiresul ADD KEY idx_partiresul_torneo_usuario_partida (id_torneo, id_usuario, partida);

-- =====================================================
-- RELACIÓN CON INSCRITOS:
-- =====================================================
-- partiresul.id_usuario = inscritos.id_usuario
-- partiresul.id_torneo = inscritos.torneo_id
-- 
-- IMPORTANTE: Antes de insertar en partiresul, verificar que existe
-- un registro en inscritos con el mismo id_usuario e id_torneo
-- =====================================================

-- =====================================================
-- NOTAS IMPORTANTES:
-- =====================================================
-- 1. Esta tabla almacena los resultados de cada partida jugada
-- 2. Cada fila representa un jugador en una partida específica
-- 3. El campo 'registrado' permite controlar si los resultados ya fueron procesados
-- 4. La efectividad puede ser positiva (puntos ganados) o negativa (forfait)
-- 5. Los campos de sanciones (tarjeta, sancion, chancleta, zapato) se registran por partida
-- =====================================================

