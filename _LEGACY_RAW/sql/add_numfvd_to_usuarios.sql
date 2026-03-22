-- Agrega columna numfvd a usuarios (sincronizada desde atletas por cédula)
-- Uso: ejecutar una vez para permitir ordenar/filtrar por numfvd en Inscribir en Sitio.

ALTER TABLE usuarios
  ADD COLUMN numfvd VARCHAR(50) NULL DEFAULT NULL
  COMMENT 'Número FVD del atleta (sincronizado desde tabla atletas por cédula)'
  AFTER entidad;

-- Índices opcionales (ignorar si ya existen)
-- CREATE INDEX idx_usuarios_numfvd ON usuarios(numfvd);
-- CREATE INDEX idx_usuarios_entidad ON usuarios(entidad);
