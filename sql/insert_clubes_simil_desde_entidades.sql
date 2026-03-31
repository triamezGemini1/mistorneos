-- Genera un símil de clubes (asociaciones) desde la tabla entidad
-- usando IDs 1..39.
--
-- Uso:
--   SOURCE sql/insert_clubes_simil_desde_entidades.sql;
--
-- Notas:
-- - Toma id/nombre desde tabla entidad.
-- - Inserta/actualiza en clubes con esos mismos IDs.
-- - admin_club_id por defecto = 1 (ajustar si aplica en tu BD).

START TRANSACTION;

INSERT INTO clubes (
    id,
    nombre,
    admin_club_id,
    entidad,
    indica,
    estatus,
    permite_inscripcion_linea
)
SELECT
    src.id,
    src.nombre,
    src.admin_club_id,
    src.entidad,
    src.indica,
    src.estatus,
    src.permite_inscripcion_linea
FROM (
    SELECT
        e.id AS id,
        CONCAT('ASOCIACION ', UPPER(TRIM(e.nombre))) AS nombre,
        1 AS admin_club_id,
        e.id AS entidad,
        0 AS indica,
        1 AS estatus,
        0 AS permite_inscripcion_linea
    FROM entidad e
    WHERE e.id BETWEEN 1 AND 39
      AND COALESCE(TRIM(e.nombre), '') <> ''
) AS src
ON DUPLICATE KEY UPDATE
    nombre = src.nombre,
    entidad = src.entidad,
    estatus = src.estatus,
    permite_inscripcion_linea = src.permite_inscripcion_linea;

COMMIT;
