-- Índices recomendados para action=inscribir_sitio (evitar full table scan sobre tablas grandes).
-- Ejecutar en BD de producción si las tablas tienen muchos registros (p. ej. 32M en personas/atletas).
-- Comprobar antes: SHOW INDEX FROM usuarios; SHOW INDEX FROM inscritos; SHOW INDEX FROM clubes;
-- En MySQL 5.7 omitir "IF NOT EXISTS" o ejecutar cada CREATE solo si el índice no existe.

-- Usuarios: filtros por role, status, club_id, entidad (usa estos campos en WHERE)
CREATE INDEX IF NOT EXISTS idx_usuarios_role_status ON usuarios (role, status);
CREATE INDEX IF NOT EXISTS idx_usuarios_club_id ON usuarios (club_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_entidad ON usuarios (entidad);

-- Inscritos: filtro por torneo y estatus
CREATE INDEX IF NOT EXISTS idx_inscritos_torneo_estatus ON inscritos (torneo_id, estatus);

-- Clubes: filtro por estatus
CREATE INDEX IF NOT EXISTS idx_clubes_estatus ON clubes (estatus);
