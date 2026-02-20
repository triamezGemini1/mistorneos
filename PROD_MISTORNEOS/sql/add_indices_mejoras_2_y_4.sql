-- =====================================================
-- Índices Mejora 2 (inscritos) y Mejora 4 (partiresul)
-- Procedimiento de asignación de rondas - optimización
-- Ejecutar en BD existente si no se usa scripts/add_missing_indices.php
-- =====================================================

-- Mejora 2: inscritos - conteos por torneo/estatus y ORDER BY clasificación
-- (omitir si ya existen)
ALTER TABLE `inscritos` ADD KEY `idx_inscritos_torneo_estatus` (`torneo_id`, `estatus`);
ALTER TABLE `inscritos` ADD KEY `idx_inscritos_clasificacion` (`torneo_id`, `posicion`, `ganados`, `efectividad`, `puntos`);

-- Mejora 4: partiresul - agregación y duplicados
-- (omitir si ya existen; fallan con "Duplicate key name" si el índice existe)
ALTER TABLE `partiresul` ADD KEY `idx_partiresul_torneo_registrado` (`id_torneo`, `registrado`);
ALTER TABLE `partiresul` ADD KEY `idx_partiresul_torneo_usuario_partida` (`id_torneo`, `id_usuario`, `partida`);
