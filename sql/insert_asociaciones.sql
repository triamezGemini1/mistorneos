-- Plantilla: Asociaciones Estadales (IDs 1-39)
-- Objetivo: reservar/crear los registros de asociaciones en tabla clubes.
-- IMPORTANTE:
-- 1) Completar los nombres antes de ejecutar.
-- 2) Ajustar admin_club_id si en su entorno el usuario admin general no es ID=1.
-- 3) Si desea mantenerlas sin inscripción en línea, deje permite_inscripcion_linea = 0.

START TRANSACTION;

INSERT INTO clubes (
    id,
    nombre,
    admin_club_id,
    entidad,
    indica,
    estatus,
    permite_inscripcion_linea
) VALUES
    (1,  'ASOCIACION ESTADAL 01', 1,  1, 0, 1, 0),
    (2,  'ASOCIACION ESTADAL 02', 1,  2, 0, 1, 0),
    (3,  'ASOCIACION ESTADAL 03', 1,  3, 0, 1, 0),
    (4,  'ASOCIACION ESTADAL 04', 1,  4, 0, 1, 0),
    (5,  'ASOCIACION ESTADAL 05', 1,  5, 0, 1, 0),
    (6,  'ASOCIACION ESTADAL 06', 1,  6, 0, 1, 0),
    (7,  'ASOCIACION ESTADAL 07', 1,  7, 0, 1, 0),
    (8,  'ASOCIACION ESTADAL 08', 1,  8, 0, 1, 0),
    (9,  'ASOCIACION ESTADAL 09', 1,  9, 0, 1, 0),
    (10, 'ASOCIACION ESTADAL 10', 1, 10, 0, 1, 0),
    (11, 'ASOCIACION ESTADAL 11', 1, 11, 0, 1, 0),
    (12, 'ASOCIACION ESTADAL 12', 1, 12, 0, 1, 0),
    (13, 'ASOCIACION ESTADAL 13', 1, 13, 0, 1, 0),
    (14, 'ASOCIACION ESTADAL 14', 1, 14, 0, 1, 0),
    (15, 'ASOCIACION ESTADAL 15', 1, 15, 0, 1, 0),
    (16, 'ASOCIACION ESTADAL 16', 1, 16, 0, 1, 0),
    (17, 'ASOCIACION ESTADAL 17', 1, 17, 0, 1, 0),
    (18, 'ASOCIACION ESTADAL 18', 1, 18, 0, 1, 0),
    (19, 'ASOCIACION ESTADAL 19', 1, 19, 0, 1, 0),
    (20, 'ASOCIACION ESTADAL 20', 1, 20, 0, 1, 0),
    (21, 'ASOCIACION ESTADAL 21', 1, 21, 0, 1, 0),
    (22, 'ASOCIACION ESTADAL 22', 1, 22, 0, 1, 0),
    (23, 'ASOCIACION ESTADAL 23', 1, 23, 0, 1, 0),
    (24, 'ASOCIACION ESTADAL 24', 1, 24, 0, 1, 0),
    (25, 'ASOCIACION ESTADAL 25', 1, 25, 0, 1, 0),
    (26, 'ASOCIACION ESTADAL 26', 1, 26, 0, 1, 0),
    (27, 'ASOCIACION ESTADAL 27', 1, 27, 0, 1, 0),
    (28, 'ASOCIACION ESTADAL 28', 1, 28, 0, 1, 0),
    (29, 'ASOCIACION ESTADAL 29', 1, 29, 0, 1, 0),
    (30, 'ASOCIACION ESTADAL 30', 1, 30, 0, 1, 0),
    (31, 'ASOCIACION ESTADAL 31', 1, 31, 0, 1, 0),
    (32, 'ASOCIACION ESTADAL 32', 1, 32, 0, 1, 0),
    (33, 'ASOCIACION ESTADAL 33', 1, 33, 0, 1, 0),
    (34, 'ASOCIACION ESTADAL 34', 1, 34, 0, 1, 0),
    (35, 'ASOCIACION ESTADAL 35', 1, 35, 0, 1, 0),
    (36, 'ASOCIACION ESTADAL 36', 1, 36, 0, 1, 0),
    (37, 'ASOCIACION ESTADAL 37', 1, 37, 0, 1, 0),
    (38, 'ASOCIACION ESTADAL 38', 1, 38, 0, 1, 0),
    (39, 'ASOCIACION ESTADAL 39', 1, 39, 0, 1, 0);

COMMIT;
