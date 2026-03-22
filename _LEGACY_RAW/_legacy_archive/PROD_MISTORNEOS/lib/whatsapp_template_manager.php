<?php


require_once __DIR__ . '/../config/db.php';

class WhatsAppTemplateManager {
    
    /**
     * Obtiene la plantilla predeterminada
     */
    public static function getDefaultTemplate(): ?array {
        try {
            $stmt = DB::pdo()->prepare("SELECT * FROM whatsapp_templates WHERE is_default = 1 LIMIT 1");
            $stmt->execute();
            return $stmt->fetch() ?: null;
        } catch (Exception $e) {
            error_log("Error obteniendo plantilla predeterminada: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene una plantilla por ID
     */
    public static function getTemplate(int $id): ?array {
        try {
            $stmt = DB::pdo()->prepare("SELECT * FROM whatsapp_templates WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        } catch (Exception $e) {
            error_log("Error obteniendo plantilla: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene todas las plantillas
     */
    public static function getAllTemplates(): array {
        try {
            $stmt = DB::pdo()->query("SELECT * FROM whatsapp_templates ORDER BY is_default DESC, name ASC");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error obteniendo plantillas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Procesa una plantilla reemplazando las variables
     */
    public static function processTemplate(string $template, array $data): string {
        // Formatear fecha a dd-mm-yyyy
        $formatted_date = '';
        if (!empty($data['tournament_date'])) {
            try {
                $date = new DateTime($data['tournament_date']);
                $formatted_date = $date->format('d-m-Y');
            } catch (Exception $e) {
                // Si hay error en el formato, usar la fecha original
                $formatted_date = $data['tournament_date'];
            }
        }
        
        $variables = [
            '{club_delegado}' => $data['club_delegado'] ?? '',
            '{club_name}' => $data['club_name'] ?? '',
            '{organizer_club_name}' => $data['organizer_club_name'] ?? '',
            '{organizer_delegado}' => $data['organizer_delegado'] ?? '',
            '{tournament_name}' => $data['tournament_name'] ?? '',
            '{tournament_date}' => $formatted_date,
            '{login_url}' => $data['login_url'] ?? '',
            '{username}' => $data['username'] ?? '',
            '{password}' => $data['password'] ?? '',
            '{sender_phone}' => $data['sender_phone'] ?? '',
            '{invitation_file_url}' => $data['invitation_file_url'] ?? '',
            '{norms_file_url}' => $data['norms_file_url'] ?? '',
            '{poster_file_url}' => $data['poster_file_url'] ?? ''
        ];
        
        return str_replace(array_keys($variables), array_values($variables), $template);
    }
    
    /**
     * Genera un mensaje usando la plantilla predeterminada
     */
    public static function generateMessage(array $data): string {
        $template = self::getDefaultTemplate();
        
        if (!$template) {
            // Fallback a plantilla hardcodeada si no hay plantilla en BD
            return self::getFallbackTemplate($data);
        }
        
        return self::processTemplate($template['template'], $data);
    }
    
    /**
     * Genera un mensaje usando una plantilla específica
     */
    public static function generateMessageWithTemplate(int $template_id, array $data): string {
        $template = self::getTemplate($template_id);
        
        if (!$template) {
            throw new Exception("Plantilla no encontrada");
        }
        
        return self::processTemplate($template['template'], $data);
    }
    
    /**
     * Plantilla de respaldo si no hay plantillas en BD
     */
    private static function getFallbackTemplate(array $data): string {
        // Formatear fecha a dd-mm-yyyy
        $formatted_date = '';
        if (!empty($data['tournament_date'])) {
            try {
                $date = new DateTime($data['tournament_date']);
                $formatted_date = $date->format('d-m-Y');
            } catch (Exception $e) {
                $formatted_date = $data['tournament_date'];
            }
        }
        
        $message = "?? *INVITACIÓN AL TORNEO*\n\n";
        $message .= "Apreciado *{$data['club_delegado']}*,\n";
        $message .= "*{$data['club_name']}*\n\n";
        $message .= "El *{$data['organizer_club_name']}* le invita al torneo:\n\n";
        $message .= "?? *{$data['tournament_name']}*\n";
        $message .= "?? *Fecha:* {$formatted_date}\n\n";
        $message .= "?? *ACCESO AL SISTEMA:*\n";
        $message .= "• Enlace: {$data['login_url']}\n";
        $message .= "• Usuario: {$data['username']}\n";
        $message .= "• Contraseña: {$data['password']}\n\n";
        
        // Agregar enlaces a archivos si están disponibles
        if (!empty($data['invitation_file_url'])) {
            $message .= "?? *Invitación:* {$data['invitation_file_url']}\n";
        }
        if (!empty($data['norms_file_url'])) {
            $message .= "?? *Normas:* {$data['norms_file_url']}\n";
        }
        if (!empty($data['poster_file_url'])) {
            $message .= "??? *Afiche:* {$data['poster_file_url']}\n";
        }
        
        $message .= "\nSaludos,\n";
        $message .= "*{$data['organizer_delegado']}*";
        
        if (!empty($data['sender_phone'])) {
            $message .= "\n?? {$data['sender_phone']}";
        }
        
        return $message;
    }
    
    /**
     * Valida que una plantilla tenga las variables necesarias
     */
    public static function validateTemplate(string $template): array {
        $errors = [];
        $required_variables = [
            '{club_delegado}',
            '{club_name}',
            '{organizer_club_name}',
            '{tournament_name}',
            '{tournament_date}',
            '{login_url}',
            '{username}',
            '{password}'
        ];
        
        foreach ($required_variables as $variable) {
            if (strpos($template, $variable) === false) {
                $errors[] = "Variable requerida no encontrada: $variable";
            }
        }
        
        return $errors;
    }
    
    /**
     * Obtiene las variables utilizadas en una plantilla
     */
    public static function getUsedVariables(string $template): array {
        $all_variables = [
            '{club_delegado}',
            '{club_name}',
            '{organizer_club_name}',
            '{organizer_delegado}',
            '{tournament_name}',
            '{tournament_date}',
            '{login_url}',
            '{username}',
            '{password}',
            '{sender_phone}',
            '{invitation_file_url}',
            '{norms_file_url}',
            '{poster_file_url}'
        ];
        
        $used = [];
        foreach ($all_variables as $variable) {
            if (strpos($template, $variable) !== false) {
                $used[] = $variable;
            }
        }
        
        return $used;
    }
}
?>
