-- Actualizar plantilla nueva_ronda: enlace al resumen de jugador (sin "Revisa tu mesa")
-- Ejecutar en instalaciones que ya tienen la plantilla.

UPDATE plantillas_notificaciones
SET cuerpo_mensaje = 'Hola {nombre}, la Ronda {ronda} del torneo {torneo} ya está publicada.

Tus estadísticas: Ganados: {ganados}, Perdidos: {perdidos}, Efectividad: {efectividad}, Puntos: {puntos}.
Mesa asignada: {mesa}. Pareja: {pareja}.

Ver mi resumen de jugador: {url_resumen}'
WHERE nombre_clave = 'nueva_ronda';
