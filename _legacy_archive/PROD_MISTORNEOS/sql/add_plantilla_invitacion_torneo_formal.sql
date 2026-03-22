-- Nueva plantilla de invitación formal a torneo
-- Formato: Organización + logo, saludo formal, invitación con lugar/fecha, enlace inscripción
-- Variables: {organizacion_nombre}, {url_logo}, {tratamiento}, {nombre}, {torneo}, {lugar_torneo}, {fecha_torneo}, {url_inscripcion}

INSERT INTO plantillas_notificaciones (nombre_clave, titulo_visual, cuerpo_mensaje, categoria) VALUES
('invitacion_torneo_formal', 'Invitación Formal a Torneo', 
'{organizacion_nombre}

{tratamiento} {nombre},

Le invitamos cordialmente a participar de nuestro torneo {torneo}, que se estará realizando en {lugar_torneo}, el día {fecha_torneo}.

Si lo desea puede inscribirse en línea siguiendo las instrucciones en el siguiente enlace:
{url_inscripcion}

Contamos con tu presencia.', 
'torneo')
ON DUPLICATE KEY UPDATE 
  titulo_visual = VALUES(titulo_visual),
  cuerpo_mensaje = VALUES(cuerpo_mensaje),
  categoria = VALUES(categoria);
