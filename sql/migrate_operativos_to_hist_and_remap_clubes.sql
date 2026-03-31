-- Migración operativa -> histórica + remapeo de IDs de clubes
-- Requisitos:
-- 1) Crear tablas espejo *_hist con misma estructura e índices
-- 2) Mover datos operativos a histórico
-- 3) Vaciar tablas operativas
-- 4) Remapear IDs de clubes (id = id + 40 en orden descendente)
-- 5) Ajustar AUTO_INCREMENT de clubes al siguiente disponible
-- 6) Todo en una única transacción: si falla la migración, no tocar clubes

START TRANSACTION;

-- Validaciones mínimas de tablas origen requeridas
SET @missing_required = (
    SELECT COUNT(*) FROM (
        SELECT 'partiresul' AS tbl
        UNION ALL SELECT 'inscritos'
        UNION ALL SELECT 'clubes'
    ) r
    LEFT JOIN information_schema.tables t
      ON t.table_schema = DATABASE()
     AND t.table_name = r.tbl
    WHERE t.table_name IS NULL
);

-- Forzar error si faltan tablas críticas
SET @sql = IF(
    @missing_required > 0,
    'SELECT * FROM __faltan_tablas_criticas__',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tabla de torneos legacy (torneos) o moderna (tournaments)
SET @has_torneos = (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'torneos'
);
SET @has_tournaments = (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'tournaments'
);

-- Crear espejo torneos_hist según tabla disponible
SET @sql = IF(
    @has_torneos = 1,
    'CREATE TABLE IF NOT EXISTS torneos_hist LIKE torneos',
    IF(@has_tournaments = 1, 'CREATE TABLE IF NOT EXISTS torneos_hist LIKE tournaments', 'SELECT 1')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Espejos obligatorios
CREATE TABLE IF NOT EXISTS partiresul_hist LIKE partiresul;
CREATE TABLE IF NOT EXISTS inscritos_hist LIKE inscritos;

-- clubes_atletas puede no existir en todas las instalaciones
SET @has_clubes_atletas = (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'clubes_atletas'
);
SET @sql = IF(
    @has_clubes_atletas = 1,
    'CREATE TABLE IF NOT EXISTS clubes_atletas_hist LIKE clubes_atletas',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migrar datos a histórico
SET @sql = IF(
    @has_torneos = 1,
    'INSERT INTO torneos_hist SELECT * FROM torneos',
    IF(@has_tournaments = 1, 'INSERT INTO torneos_hist SELECT * FROM tournaments', 'SELECT 1')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO partiresul_hist SELECT * FROM partiresul;
INSERT INTO inscritos_hist SELECT * FROM inscritos;

SET @sql = IF(
    @has_clubes_atletas = 1,
    'INSERT INTO clubes_atletas_hist SELECT * FROM clubes_atletas',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Vaciar operativas (DELETE para respetar transacción; TRUNCATE hace commit implícito)
DELETE FROM partiresul;
DELETE FROM inscritos;

SET @sql = IF(
    @has_clubes_atletas = 1,
    'DELETE FROM clubes_atletas',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @has_torneos = 1,
    'DELETE FROM torneos',
    IF(@has_tournaments = 1, 'DELETE FROM tournaments', 'SELECT 1')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remapeo de IDs de clubes (orden descendente para evitar colisiones)
UPDATE clubes
SET id = id + 40
ORDER BY id DESC;

-- Ajustar AUTO_INCREMENT al siguiente disponible
SET @next_club_id = (SELECT COALESCE(MAX(id), 0) + 1 FROM clubes);
SET @sql = CONCAT('ALTER TABLE clubes AUTO_INCREMENT = ', @next_club_id);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
