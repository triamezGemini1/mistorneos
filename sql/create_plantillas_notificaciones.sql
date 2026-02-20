-- Plantillas de notificaciones (mensajes preestablecidos editables desde panel)
-- Ejecutar una vez en la base de datos.

CREATE TABLE IF NOT EXISTS plantillas_notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_clave VARCHAR(50) NOT NULL COMMENT 'Ej: nueva_ronda, resultados, recordatorio_pago',
    titulo_visual VARCHAR(100) NOT NULL COMMENT 'Ej: Aviso de Nueva Ronda',
    cuerpo_mensaje TEXT NOT NULL COMMENT 'Texto con variables: {nombre}, {ronda}, {torneo}, etc.',
    categoria ENUM('torneo', 'afiliacion', 'general') DEFAULT 'general',
    destinatarios VARCHAR(30) NOT NULL DEFAULT 'inscritos' COMMENT 'inscritos = inscritos del torneo; todos_usuarios_admin = todos los usuarios del admin',
    UNIQUE KEY uk_nombre_clave (nombre_clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plantillas iniciales (solo si la tabla está vacía)
INSERT IGNORE INTO plantillas_notificaciones (nombre_clave, titulo_visual, cuerpo_mensaje, categoria) VALUES
('nueva_ronda', 'Nueva Ronda Disponible', 'Hola {nombre}, la Ronda {ronda} del torneo {torneo} ya está publicada.

Tus estadísticas: Ganados: {ganados}, Perdidos: {perdidos}, Efectividad: {efectividad}, Puntos: {puntos}.
Mesa asignada: {mesa}. Pareja: {pareja}.

Ver mi resumen de jugador: {url_resumen}', 'torneo'),
('resultados', 'Resultados Actualizados', 'Hola {nombre}, los resultados de la Ronda {ronda} del torneo {torneo} ya están disponibles en el sistema.', 'torneo'),
('recordatorio_pago', 'Recordatorio de Pago', 'Estimado/a {nombre}, le recordamos que tiene pendiente el pago correspondiente al torneo {torneo}.', 'torneo'),
('invitacion_torneo', 'Invitación a Torneo', 'Estimado/a {nombre}, usted está invitado a participar en el torneo {torneo}, que se realizará el día {fecha_torneo} a la hora {hora_torneo}. Nos gustaría contar con su presencia.', 'torneo'),
('inicio_torneo', 'Aviso de Inicio de Torneo', 'Estimado/a {nombre}, su identificador en el sistema es {id_usuario}. Usted está inscrito en el torneo {torneo}, que comenzará el día {fecha_torneo} a la hora {hora_torneo}. Agradecemos su puntualidad.', 'torneo');

-- Actualizar plantilla nueva_ronda para incluir estadísticas y mesa/pareja (para instalaciones que ya la tenían)
UPDATE plantillas_notificaciones
SET cuerpo_mensaje = 'Hola {nombre}, la Ronda {ronda} del torneo {torneo} ya está publicada.

Tus estadísticas: Ganados: {ganados}, Perdidos: {perdidos}, Efectividad: {efectividad}, Puntos: {puntos}.
Mesa asignada: {mesa}. Pareja: {pareja}.

Ver mi resumen de jugador: {url_resumen}'
WHERE nombre_clave = 'nueva_ronda';
