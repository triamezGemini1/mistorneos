-- Índices recomendados para TTFB de inscribir_equipo_sitio / torneo_gestion
-- Revisar nombres de tablas/columnas en su motor (MySQL/MariaDB vs SQL Server).
-- Ejecutar en ventana de mantenimiento; CREATE INDEX es online en muchos motores.

-- ========== inscritos ==========
-- Usado en: LEFT JOIN inscritos ON id_usuario + torneo_id; WHERE torneo_id + codigo_equipo IN (...)
-- Evita full scan al filtrar por torneo y unir a usuarios.
CREATE INDEX idx_inscritos_torneo_usuario ON inscritos (torneo_id, id_usuario);
CREATE INDEX idx_inscritos_torneo_codigo ON inscritos (torneo_id, codigo_equipo);
-- Si estatus se filtra siempre con torneo:
-- CREATE INDEX idx_inscritos_torneo_estatus ON inscritos (torneo_id, estatus);

-- ========== usuarios ==========
-- Listado por club (admin club): WHERE club_id IN (...) AND role = ...
CREATE INDEX idx_usuarios_club_role ON usuarios (club_id, role);
-- Búsqueda por cédula (API buscar_jugador_inscripcion)
CREATE INDEX idx_usuarios_cedula ON usuarios (cedula);

-- ========== equipos ==========
-- WHERE id_torneo = ?
CREATE INDEX idx_equipos_id_torneo ON equipos (id_torneo);

-- ========== partiresul (¿partiresul / partida?) ==========
-- Vista inscribir_equipo_sitio.php: MAX(partida) WHERE id_torneo = ?
CREATE INDEX idx_partiresul_torneo_mesa ON partiresul (id_torneo, mesa);

-- ========== persona (si aplica y 32M filas) ==========
-- Solo si las inscripciones o búsquedas leen persona por id_torneo / equipo (ajustar a su esquema real).
-- CREATE INDEX idx_persona_... ON persona (...);

-- Verificar planes:
-- EXPLAIN SELECT ... (misma SQL que en obtenerDatosInscribirEquipoSitio);
