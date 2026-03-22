-- Crea una organización por cada entidad que aún no tenga ninguna.
-- Nombre: "ASOCIACION DE DOMINO DEL ESTADO " + nombre de la entidad.
-- Las organizaciones se crean INACTIVAS (estatus = 0) hasta asignar un usuario y activar desde el panel.

-- 1) Usuario placeholder (obligatorio en la tabla; las orgs inactivas lo usan hasta tener usuario válido)
SET @admin_id = (SELECT id FROM usuarios WHERE role = 'admin_general' AND status = 0 ORDER BY id ASC LIMIT 1);
SET @admin_id = IFNULL(@admin_id, (SELECT id FROM usuarios ORDER BY id ASC LIMIT 1));

-- 2a) Si la tabla entidad tiene columna "id" (y "nombre"):
INSERT INTO organizaciones (nombre, entidad, admin_user_id, estatus)
SELECT CONCAT('ASOCIACION DE DOMINO DEL ESTADO ', TRIM(e.nombre)), e.id, @admin_id, 0
FROM entidad e
LEFT JOIN organizaciones o ON o.entidad = e.id
WHERE o.id IS NULL AND @admin_id IS NOT NULL;

-- 2b) Si en tu BD la tabla entidad usa "codigo" en lugar de "id", comenta 2a y descomenta lo siguiente:
-- INSERT INTO organizaciones (nombre, entidad, admin_user_id, estatus)
-- SELECT CONCAT('ASOCIACION DE DOMINO DEL ESTADO ', TRIM(e.nombre)), e.codigo, @admin_id, 0
-- FROM entidad e
-- LEFT JOIN organizaciones o ON o.entidad = e.codigo
-- WHERE o.id IS NULL AND @admin_id IS NOT NULL;
