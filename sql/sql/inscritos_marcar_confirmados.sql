-- Marcar como confirmados todos los inscritos que no estén retirados.
-- Inscripción en sitio o confirmada por pago/otra vía = confirmado.
-- No se modifica estatus retirado (4 / 'retirado').

-- Columna INT: 1 = confirmado
UPDATE inscritos
SET estatus = 1
WHERE torneo_id > 0
  AND estatus != 4
  AND estatus != 'retirado';
