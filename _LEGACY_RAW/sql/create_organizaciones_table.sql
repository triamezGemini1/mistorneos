-- Crear tabla de organizaciones
-- Las organizaciones son entidades superiores (federaciones, asociaciones) que crean los admin_club
-- Los clubes pertenecen a organizaciones, no directamente a usuarios

CREATE TABLE IF NOT EXISTS organizaciones (
  id INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(255) NOT NULL,
  direccion VARCHAR(255) NULL,
  responsable VARCHAR(100) NULL COMMENT 'Nombre del responsable/presidente',
  telefono VARCHAR(50) NULL,
  email VARCHAR(100) NULL,
  entidad INT NOT NULL DEFAULT 0 COMMENT 'Código de entidad geográfica (estado/región)',
  admin_user_id INT NOT NULL COMMENT 'Usuario admin_club que registró/gestiona esta organización',
  logo VARCHAR(255) NULL,
  estatus TINYINT NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_user_id (admin_user_id),
  KEY idx_entidad (entidad),
  KEY idx_estatus (estatus),
  CONSTRAINT fk_organizaciones_admin FOREIGN KEY (admin_user_id) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar campo organizacion_id a la tabla clubes
ALTER TABLE clubes 
ADD COLUMN IF NOT EXISTS organizacion_id INT NULL COMMENT 'Organización a la que pertenece el club'
AFTER admin_club_id;

-- Crear índice para búsquedas por organización
CREATE INDEX IF NOT EXISTS idx_clubes_organizacion ON clubes(organizacion_id);

-- Agregar foreign key (opcional, puede omitirse si hay datos legacy)
-- ALTER TABLE clubes 
-- ADD CONSTRAINT fk_clubes_organizacion FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Agregar campo organizacion_id a la tabla tournaments
ALTER TABLE tournaments 
ADD COLUMN IF NOT EXISTS organizacion_id INT NULL COMMENT 'Organización que organiza el torneo'
AFTER club_responsable;

-- Crear índice para búsquedas por organización en torneos
CREATE INDEX IF NOT EXISTS idx_tournaments_organizacion ON tournaments(organizacion_id);

-- Nota: La columna 'entidad' en tournaments puede mantenerse para compatibilidad o eliminarse después
