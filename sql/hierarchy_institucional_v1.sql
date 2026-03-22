-- Fase 3: jerarquía institucional (entidad → organización → club → torneo)
-- MySQL 8+ / MariaDB 10.3+. Ejecutar manualmente; omita líneas que fallen por "Duplicate column".
-- Antes de NOT NULL, revise filas huérfanas en clubes/tournaments.

-- ---------------------------------------------------------------------------
-- 1) Catálogo de entidades (Miranda, etc.)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS entidades (
  id INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(160) NOT NULL,
  logo VARCHAR(255) NULL DEFAULT NULL,
  estatus TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO entidades (id, nombre)
SELECT 1, 'Entidad por defecto'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM entidades WHERE id = 1);

-- ---------------------------------------------------------------------------
-- 2) Organizaciones: enlace a entidad
-- ---------------------------------------------------------------------------
ALTER TABLE organizaciones
  ADD COLUMN entidad_id INT NULL DEFAULT NULL COMMENT 'FK entidades' AFTER id;

UPDATE organizaciones SET entidad_id = 1 WHERE entidad_id IS NULL;

-- Si existe columna legacy `entidad` (INT), copiar valores > 0:
-- UPDATE organizaciones SET entidad_id = entidad WHERE entidad IS NOT NULL AND entidad > 0 AND (entidad_id IS NULL OR entidad_id = 1);

ALTER TABLE organizaciones
  MODIFY entidad_id INT NOT NULL;

-- Opcional (descomente si no rompe datos):
-- ALTER TABLE organizaciones ADD CONSTRAINT fk_organizaciones_entidad FOREIGN KEY (entidad_id) REFERENCES entidades(id) ON UPDATE CASCADE ON DELETE RESTRICT;

-- ---------------------------------------------------------------------------
-- 3) Clubes: entidad_id + organizacion_id obligatorios
-- ---------------------------------------------------------------------------
ALTER TABLE clubes
  ADD COLUMN entidad_id INT NULL DEFAULT NULL COMMENT 'FK entidades' AFTER id;

-- Rellenar organizacion_id ausente desde una organización existente (ajuste en producción si aplica):
UPDATE clubes c
  CROSS JOIN (SELECT id FROM organizaciones ORDER BY id ASC LIMIT 1) first_org
  SET c.organizacion_id = first_org.id
WHERE (c.organizacion_id IS NULL OR c.organizacion_id = 0);

UPDATE clubes c
  INNER JOIN organizaciones o ON o.id = c.organizacion_id
  SET c.entidad_id = o.entidad_id
WHERE c.entidad_id IS NULL;

UPDATE clubes SET entidad_id = 1 WHERE entidad_id IS NULL OR entidad_id = 0;

ALTER TABLE clubes
  MODIFY entidad_id INT NOT NULL;

ALTER TABLE clubes
  MODIFY organizacion_id INT NOT NULL;

-- Opcional:
-- ALTER TABLE clubes ADD CONSTRAINT fk_clubes_entidad FOREIGN KEY (entidad_id) REFERENCES entidades(id) ON UPDATE CASCADE ON DELETE RESTRICT;
-- ALTER TABLE clubes ADD CONSTRAINT fk_clubes_organizacion FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON UPDATE CASCADE ON DELETE RESTRICT;

-- ---------------------------------------------------------------------------
-- 4) Torneos: organizacion_id + entidad_id (ADN del torneo)
-- ---------------------------------------------------------------------------
ALTER TABLE tournaments
  ADD COLUMN organizacion_id INT NULL DEFAULT NULL COMMENT 'Organizadora (tenant)' AFTER club_responsable;

ALTER TABLE tournaments
  ADD COLUMN entidad_id INT NULL DEFAULT NULL COMMENT 'Entidad federativa' AFTER organizacion_id;

-- Heredar de club_responsable (legacy = id organización en muchos despliegues)
UPDATE tournaments
  SET organizacion_id = club_responsable
WHERE (organizacion_id IS NULL OR organizacion_id = 0)
  AND club_responsable IS NOT NULL
  AND club_responsable > 0;

-- Torneos sin organización: asignar primera organización (revisar en producción)
UPDATE tournaments t
  CROSS JOIN (SELECT id FROM organizaciones ORDER BY id ASC LIMIT 1) first_org
  SET t.organizacion_id = first_org.id
WHERE t.organizacion_id IS NULL OR t.organizacion_id = 0;

UPDATE tournaments t
  INNER JOIN organizaciones o ON o.id = t.organizacion_id
  SET t.entidad_id = o.entidad_id
WHERE t.entidad_id IS NULL;

UPDATE tournaments SET entidad_id = 1 WHERE entidad_id IS NULL OR entidad_id = 0;

ALTER TABLE tournaments
  MODIFY organizacion_id INT NOT NULL;

ALTER TABLE tournaments
  MODIFY entidad_id INT NOT NULL;

CREATE INDEX idx_tournaments_organizacion ON tournaments (organizacion_id);

-- Opcional:
-- ALTER TABLE tournaments ADD CONSTRAINT fk_tournaments_entidad FOREIGN KEY (entidad_id) REFERENCES entidades(id) ON UPDATE CASCADE ON DELETE RESTRICT;
-- ALTER TABLE tournaments ADD CONSTRAINT fk_tournaments_organizacion FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON UPDATE CASCADE ON DELETE RESTRICT;
