-- Tabla para almacenar plantillas de mensajes de WhatsApp
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    template TEXT NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
);

-- Insertar plantilla por defecto
INSERT INTO whatsapp_templates (name, template, is_default) VALUES 
('default', '🏆 *INVITACIÓN AL TORNEO*

Apreciado *{club_delegado}*,
*{club_name}*

El *{organizer_club_name}* le invita al torneo:

🎯 *{tournament_name}*
📅 *Fecha:* {tournament_date}

🔐 *ACCESO AL SISTEMA:*
• Enlace: {login_url}
• Usuario: {username}
• Contraseña: {password}

Saludos,
*{organizer_delegado}*
Comisión de Dominó - {organizer_club_name}
📞 {sender_phone}', TRUE)
ON DUPLICATE KEY UPDATE template = VALUES(template);









