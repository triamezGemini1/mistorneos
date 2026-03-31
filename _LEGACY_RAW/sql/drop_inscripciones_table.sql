-- Eliminar tabla inscripciones si existe (el flujo de invitación usa usuarios + inscritos).
-- Ejecutar solo si creaste inscripciones antes y quieres unificar en usuarios/inscritos.

DROP TABLE IF EXISTS `inscripciones`;
