<?php


require_once __DIR__ . '/../config/db.php';

class PDFGenerator {
    
    /**
     * Genera un PDF con la invitación y archivos del torneo
     */
    public static function generateInvitationPDF(int $invitation_id): array {
        try {
            // Obtener datos de la invitación
            $invitation_data = self::getInvitationData($invitation_id);
            if (!$invitation_data) {
                return ['success' => false, 'error' => 'Invitación no encontrada'];
            }
            
            // Obtener archivos del torneo
            $tournament_files = self::getTournamentFiles($invitation_data['torneo_id']);
            
            // Generar contenido HTML
            $html_content = self::generateHTMLContent($invitation_data, $tournament_files);
            
            // Generar PDF usando TCPDF
            $pdf_path = self::createPDF($html_content, $invitation_data);
            
            return [
                'success' => true,
                'pdf_path' => $pdf_path,
                'message' => 'PDF generado correctamente'
            ];
            
        } catch (Exception $e) {
            error_log("Error generando PDF: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error generando PDF: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene los datos completos de una invitación
     */
    private static function getInvitationData(int $invitation_id): ?array {
        $stmt = DB::pdo()->prepare("
            SELECT 
                i.*,
                t.nombre as tournament_name,
                t.fechator as tournament_date,
                t.clase,
                t.modalidad,
                t.costo,
                t.club_responsable,
                t.invitacion as tournament_invitation_file,
                t.normas as tournament_norms_file,
                t.afiche as tournament_poster_file,
                c.nombre as club_name,
                c.delegado as club_delegado,
                c.email as club_email,
                c.telefono as club_telefono,
                c.direccion as club_direccion,
                c.logo as club_logo,
                oc.nombre as organizer_club_name,
                oc.delegado as organizer_delegado,
                oc.telefono as organizer_telefono,
                oc.email as organizer_email,
                oc.direccion as organizer_direccion,
                oc.logo as organizer_logo
            FROM invitations i
            LEFT JOIN tournaments t ON i.torneo_id = t.id
            LEFT JOIN clubes c ON i.club_id = c.id
            LEFT JOIN clubes oc ON t.club_responsable = oc.id
            WHERE i.id = ?
        ");
        $stmt->execute([$invitation_id]);
        return $stmt->fetch();
    }
    
    /**
     * Obtiene los archivos del torneo desde la base de datos
     */
    private static function getTournamentFiles(int $tournament_id): array {
        $files = [];
        
        try {
            // Obtener archivos del torneo desde la base de datos
            $stmt = DB::pdo()->prepare("
                SELECT invitacion, normas, afiche 
                FROM tournaments 
                WHERE id = ?
            ");
            $stmt->execute([$tournament_id]);
            $tournament = $stmt->fetch();
            
            if ($tournament) {
                // Archivo de invitación
                if (!empty($tournament['invitacion'])) {
                    $file_path = __DIR__ . '/../upload/' . $tournament['invitacion'];
                    if (file_exists($file_path)) {
                        $files[] = [
                            'name' => basename($tournament['invitacion']),
                            'path' => 'upload/' . $tournament['invitacion'],
                            'type' => 'invitacion',
                            'extension' => strtolower(pathinfo($tournament['invitacion'], PATHINFO_EXTENSION)),
                            'size' => filesize($file_path)
                        ];
                    }
                }
                
                // Archivo de normas
                if (!empty($tournament['normas'])) {
                    $file_path = __DIR__ . '/../upload/' . $tournament['normas'];
                    if (file_exists($file_path)) {
                        $files[] = [
                            'name' => basename($tournament['normas']),
                            'path' => 'upload/' . $tournament['normas'],
                            'type' => 'normas',
                            'extension' => strtolower(pathinfo($tournament['normas'], PATHINFO_EXTENSION)),
                            'size' => filesize($file_path)
                        ];
                    }
                }
                
                // Archivo de afiche
                if (!empty($tournament['afiche'])) {
                    $file_path = __DIR__ . '/../upload/' . $tournament['afiche'];
                    if (file_exists($file_path)) {
                        $files[] = [
                            'name' => basename($tournament['afiche']),
                            'path' => 'upload/' . $tournament['afiche'],
                            'type' => 'afiche',
                            'extension' => strtolower(pathinfo($tournament['afiche'], PATHINFO_EXTENSION)),
                            'size' => filesize($file_path)
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error obteniendo archivos del torneo: " . $e->getMessage());
        }
        
        return $files;
    }
    
    /**
     * Genera el contenido HTML para el PDF
     */
    private static function generateHTMLContent(array $invitation_data, array $tournament_files): string {
        $login_url = self::buildInvitationUrl($invitation_data['torneo_id'], $invitation_data['club_id']);
        
        // Obtener rutas de logos
        $organizer_logo_path = self::getLogoPath($invitation_data['organizer_logo'] ?? null);
        $invited_logo_path = self::getLogoPath($invitation_data['club_logo'] ?? null);
        
        // Formatear fechas
        $fecha_inicio = date('d/m/Y', strtotime($invitation_data['acceso1']));
        $fecha_fin = date('d/m/Y', strtotime($invitation_data['acceso2']));
        $fecha_torneo = date('d/m/Y', strtotime($invitation_data['tournament_date']));
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invitación al Torneo</title>
    <style>
        @page { 
            size: A4; 
            margin: 12mm; 
        }
        
        body { 
            font-family: "Helvetica Neue", "Helvetica", "Arial", sans-serif;
            margin: 0; 
            padding: 0;
            color: #1a1a1a;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 50%, #e1f5fe 100%);
            position: relative;
            font-size: 10px;
        }
        
        /* Marco decorativo elegante */
        .document-border {
            position: absolute;
            top: 8mm;
            left: 8mm;
            right: 8mm;
            bottom: 8mm;
            border: 2px solid #1565c0;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 3px 15px rgba(21, 101, 192, 0.15);
        }
        
        .content-wrapper {
            position: relative;
            z-index: 1;
            padding: 10px;
        }
        
        /* Header con logo organizador */
        .header-section {
            text-align: center;
            padding: 6px 0 4px 0;
            border-bottom: 2px solid #1565c0;
            margin-bottom: 6px;
            position: relative;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 6px 6px 0 0;
        }
        
        .organizer-logo-container {
            margin-bottom: 4px;
        }
        
        .organizer-logo-container img {
            max-width: 150px;
            max-height: 150px;
            object-fit: contain;
            filter: drop-shadow(0 3px 6px rgba(21, 101, 192, 0.35));
        }
        
        .organizer-name {
            font-size: 15px;
            color: #0d47a1;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-top: 4px;
        }
        
        /* Decoración esquinas - sin superposición */
        .corner-decoration {
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid #1565c0;
            background: white;
        }
        
        .corner-tl { 
            top: 6mm; 
            left: 6mm; 
            border-right: none; 
            border-bottom: none; 
            border-radius: 10px 0 0 0;
        }
        .corner-tr { 
            top: 6mm; 
            right: 6mm; 
            border-left: none; 
            border-bottom: none; 
            border-radius: 0 10px 0 0;
        }
        .corner-bl { 
            bottom: 6mm; 
            left: 6mm; 
            border-right: none; 
            border-top: none; 
            border-radius: 0 0 0 10px;
        }
        .corner-br { 
            bottom: 6mm; 
            right: 6mm; 
            border-left: none; 
            border-top: none; 
            border-radius: 0 0 10px 0;
        }
        
        /* Texto de invitación elegante */
        .invitation-announcement {
            text-align: center;
            font-style: italic;
            font-size: 18px;
            color: #0d47a1;
            margin: 4px 0;
            letter-spacing: 0.3px;
            line-height: 1.3;
            font-weight: 500;
        }
        
        /* Nombre del torneo - destacado */
        .tournament-title {
            text-align: center;
            font-size: 25px;
            font-weight: 900;
            color: #0d47a1;
            margin: 5px auto;
            padding: 8px 10px;
            background: white;
            border: 3px solid #1565c0;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            line-height: 1.3;
            box-shadow: 0 3px 8px rgba(21, 101, 192, 0.3);
            max-width: 80%;
        }
        
        .tournament-date {
            text-align: center;
            font-size: 15px;
            color: #0d47a1;
            margin: -2px 0 6px 0;
            font-style: italic;
            font-weight: 700;
        }
        
        /* Sección club invitado - elegante */
        .invited-club-section {
            background: white;
            padding: 8px;
            margin: 5px auto;
            border: 2px solid #1565c0;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(21, 101, 192, 0.15);
            max-width: 85%;
        }
        
        .club-header {
            display: table;
            width: 100%;
        }
        
        .club-logo-cell {
            display: table-cell;
            width: 100px;
            vertical-align: middle;
            text-align: center;
            padding-right: 10px;
        }
        
        .club-logo-cell img {
            max-width: 90px;
            max-height: 90px;
            object-fit: contain;
            border: 2px solid #1565c0;
            border-radius: 6px;
            padding: 4px;
            background: #f5f5f5;
            box-shadow: 0 2px 5px rgba(21, 101, 192, 0.25);
        }
        
        .club-details {
            display: table-cell;
            vertical-align: middle;
        }
        
        .club-name {
            font-size: 15px;
            font-weight: 800;
            color: #0d47a1;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .club-info-grid {
            display: table;
            width: 100%;
        }
        
        .club-info-column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        
        .club-info-row {
            font-size: 15px;
            margin: 2px 0;
            color: #212121;
            line-height: 1.4;
        }
        
        .club-info-label {
            font-weight: 700;
            color: #1565c0;
            margin-right: 3px;
        }
        
        .club-info-value {
            color: #212121;
            font-weight: 600;
        }
        
        .club-info-column-right .club-info-row {
            text-align: center;
        }
        
        .club-info-column-right .club-info-label {
            margin-right: 0;
            margin-left: 4px;
        }
        
        /* Sección de acceso - diseño profesional */
        .access-section {
            background: #0d47a1;
            padding: 8px;
            margin: 5px auto;
            border-radius: 6px;
            box-shadow: 0 3px 10px rgba(13, 71, 161, 0.4);
            border: 2px solid #1565c0;
            max-width: 85%;
        }
        
        .access-title {
            text-align: center;
            color: white;
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        
        .token-container {
            background: white;
            padding: 6px;
            border-radius: 4px;
            margin: 5px 0;
            border: 2px solid #1976d2;
        }
        
        .token-label {
            font-size: 8px;
            color: #0d47a1;
            font-weight: 800;
            text-align: center;
            display: block;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .token-value {
            font-family: "Consolas", "Monaco", "Courier New", monospace;
            font-size: 12px;
            color: #c62828;
            font-weight: 700;
            text-align: center;
            word-break: break-all;
            line-height: 1.4;
            padding: 5px;
            background: #fff9e6;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            letter-spacing: 0.3px;
        }
        
        .url-container {
            background: white;
            padding: 6px;
            border-radius: 4px;
            border: 2px solid #1976d2;
        }
        
        .url-label {
            font-size: 8px;
            color: #0d47a1;
            font-weight: 800;
            text-align: center;
            display: block;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .url-value {
            font-family: "Consolas", "Monaco", "Courier New", monospace;
            font-size: 8px;
            color: #1976d2;
            text-align: center;
            word-break: break-all;
            line-height: 1.5;
            font-weight: 700;
            letter-spacing: 0.2px;
        }
        
        /* Instrucciones - diseño limpio en dos columnas */
        .instructions-section {
            background: white;
            padding: 8px;
            margin: 5px auto;
            border: 2px solid #1565c0;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(21, 101, 192, 0.1);
            max-width: 85%;
        }
        
        .instructions-title {
            font-size: 18px;
            font-weight: 800;
            color: #0d47a1;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
            border-bottom: 2px solid #1565c0;
            padding-bottom: 4px;
        }
        
        .instructions-columns {
            display: table;
            width: 100%;
        }
        
        .instructions-column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 0 5px;
        }
        
        .instructions-list {
            margin: 0;
            padding-left: 10px;
            font-size: 12.5px;
            color: #212121;
            line-height: 1.5;
        }
        
        .instructions-list li {
            margin: 3px 0;
        }
        
        .instructions-list strong {
            color: #0d47a1;
            font-weight: 800;
        }
        
        /* Información de contacto - elegante */
        .contact-section {
            background: white;
            padding: 8px;
            margin: 5px auto;
            border: 2px solid #1565c0;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(21, 101, 192, 0.1);
            max-width: 85%;
        }
        
        .contact-title {
            font-size: 15px;
            font-weight: 800;
            color: #0d47a1;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #1565c0;
            padding-bottom: 4px;
        }
        
        .contact-name {
            font-size: 15px;
            font-weight: 800;
            color: #0d47a1;
            margin: 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .contact-detail {
            font-size: 15px;
            color: #212121;
            margin: 3px 0;
            font-weight: 600;
        }
        
        .contact-phone {
            font-size: 15px;
            color: #0d47a1;
            margin: 4px 0;
            font-weight: 800;
            letter-spacing: 0.3px;
        }
        
        .contact-organization {
            font-size: 15px;
            color: #1565c0;
            font-style: italic;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 2px solid #1565c0;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        
        /* Archivos adjuntos - compacto y elegante */
        .files-section {
            background: white;
            padding: 6px;
            margin: 5px auto;
            border-radius: 6px;
            border: 2px solid #1565c0;
            box-shadow: 0 1px 4px rgba(21, 101, 192, 0.1);
            max-width: 85%;
        }
        
        .files-title {
            font-size: 9px;
            font-weight: 800;
            color: #0d47a1;
            text-align: center;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 2px solid #1565c0;
            padding-bottom: 3px;
        }
        
        .file-item {
            font-size: 7.5px;
            color: #212121;
            margin: 2px 0;
            padding: 3px 5px;
            background: #f5f5f5;
            border-left: 2px solid #1565c0;
            border-radius: 3px;
            line-height: 1.4;
        }
        
        .file-link {
            color: #1976d2;
            word-break: break-all;
            font-weight: 700;
        }
        
        /* Footer decorativo */
        .footer-decoration {
            text-align: center;
            margin-top: 5px;
            padding: 4px;
            border-top: 2px solid #1565c0;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 0 0 6px 6px;
        }
        
        .footer-text {
            font-size: 12px;
            color: #1565c0;
            font-style: italic;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="document-border">
        <div class="corner-decoration corner-tl"></div>
        <div class="corner-decoration corner-tr"></div>
        <div class="corner-decoration corner-bl"></div>
        <div class="corner-decoration corner-br"></div>
    </div>
    
    <div class="content-wrapper">
        <!-- Header con logo y nombre del organizador -->
        <div class="header-section">
            ' . ($organizer_logo_path ? '
            <div class="organizer-logo-container">
                <img src="' . $organizer_logo_path . '" alt="Logo Organizador" />
            </div>' : '') . '
            <div class="organizer-name">' . htmlspecialchars($invitation_data['organizer_club_name']) . '</div>
        </div>
        
        <!-- Texto de invitación -->
        <div class="invitation-announcement">
            Le invita cordialmente a participar de nuestro magno evento deportivo
        </div>
        
        <!-- Nombre del torneo -->
        <div class="tournament-title">
            ' . htmlspecialchars($invitation_data['tournament_name']) . '
        </div>
        <div class="tournament-date">
            Fecha del evento: ' . $fecha_torneo . '
        </div>
        
        <!-- Información del club invitado -->
        <div class="invited-club-section">
            <div class="club-header">
                ' . ($invited_logo_path ? '
                <div class="club-logo-cell">
                    <img src="' . $invited_logo_path . '" alt="Logo Club" />
                </div>' : '') . '
                <div class="club-details">
                    <div class="club-name">' . htmlspecialchars($invitation_data['club_name']) . '</div>
                    <div class="club-info-grid">
                        <div class="club-info-column">
                            <div class="club-info-row">
                                <span class="club-info-label">Delegado:</span>
                                <span class="club-info-value">' . htmlspecialchars($invitation_data['club_delegado']) . '</span>
                            </div>
                            <div class="club-info-row">
                                <span class="club-info-label">Teléfono:</span>
                                <span class="club-info-value">' . htmlspecialchars($invitation_data['club_telefono'] ?? 'No disponible') . '</span>
                            </div>
                            <div class="club-info-row">
                                <span class="club-info-label">Email:</span>
                                <span class="club-info-value">' . htmlspecialchars($invitation_data['club_email'] ?? 'No disponible') . '</span>
                            </div>
                        </div>
                        <div class="club-info-column club-info-column-right">
                            <div class="club-info-row">
                                <span class="club-info-label">desde</span>
                                <span class="club-info-value">' . $fecha_inicio . '</span>
                                
                            </div>
                            <div class="club-info-row">
                                <span class="club-info-label">hasta</span>
                                <span class="club-info-value">' . $fecha_fin . '</span>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sección de acceso -->
        <div class="access-section">
            <div class="access-title">?? Credenciales de Acceso</div>
            
            <div class="token-container">
                <span class="token-label">Token de Autenticación</span>
                <div class="token-value">' . htmlspecialchars($invitation_data['token']) . '</div>
            </div>
            
            <div class="url-container">
                <span class="url-label">Dirección de Acceso</span>
                <div class="url-value">' . htmlspecialchars($login_url) . '</div>
            </div>
        </div>
        
        <!-- Instrucciones -->
        <div class="instructions-section">
            <div class="instructions-title">?? Instrucciones de Acceso</div>
            <div class="instructions-columns">
                <div class="instructions-column">
                    <ol class="instructions-list">
                        <li>Copie el <strong>Token de Autenticación</strong></li>
                        <li>Acceda a la <strong>Dirección de Acceso</strong></li>
                        <li>Pegue el token en el campo</li>
                    </ol>
                </div>
                <div class="instructions-column">
                    <ol class="instructions-list" start="4">
                        <li>Presione <strong>ENTER</strong> para validar</li>
                        <li>Inscriba a sus <strong>jugadores</strong></li>
                    </ol>
                </div>
            </div>
        </div>
        
        <!-- Información de contacto -->
        <div class="contact-section">
            <div class="contact-title">?? Información de Contacto</div>
            <div class="contact-name">' . htmlspecialchars($invitation_data['organizer_delegado']) . '</div>
            <div class="contact-phone">
                ? ' . htmlspecialchars($invitation_data['organizer_telefono'] ?? 'No disponible') . '
            </div>
            <div class="contact-detail">
                ' . htmlspecialchars($invitation_data['organizer_email'] ?? 'No disponible') . '
            </div>
            <div class="contact-organization">
                Comisión de Dominó del ' . htmlspecialchars($invitation_data['organizer_club_name']) . '
            </div>
        </div>';
        
        
        // Agregar archivos del torneo si existen
        if (!empty($tournament_files)) {
            $html .= '
        
        <!-- Archivos Adjuntos del Torneo -->
        <div class="files-section">
            <div class="files-title">?? Archivos Adjuntos del Torneo</div>';
            
            foreach ($tournament_files as $file) {
                $file_type_label = self::getFileTypeLabel($file['type']);
                $base_url = self::getBaseUrl();
                $file_url = $base_url . '/' . $file['path'];
                
                $html .= '
                <div class="file-item">
                    <strong>' . $file_type_label . ':</strong> ' . htmlspecialchars($file['name']) . 
                    ' (' . self::formatFileSize($file['size']) . ')
                <br><span class="file-link">' . htmlspecialchars($file_url) . '</span>
                </div>';
            }
            
            $html .= '
        </div>';
        }
        
        $html .= '
        
        <!-- Footer -->
        <div class="footer-decoration">
            <div class="footer-text">Este documento es una invitación oficial al torneo - Favor conservarlo hasta la finalización del evento</div>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Construye la URL de invitación
     */
    private static function buildInvitationUrl(int $tournament_id, int $club_id): string {
        $base_url = self::getBaseUrl();
        return $base_url . '/public/simple_invitation_login.php?torneo=' . $tournament_id . '&club=' . $club_id;
    }
    
    /**
     * Obtiene la URL base del sistema
     */
    private static function getBaseUrl(): string {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        return $protocol . '://' . $host . $path;
    }
    
    /**
     * Obtiene la etiqueta del tipo de archivo
     */
    private static function getFileTypeLabel(string $type): string {
        $labels = [
            'invitacion' => 'Invitación',
            'normas' => 'Normas',
            'afiche' => 'Afiche',
            'invitations' => 'Invitación',
            'norms' => 'Normas',
            'posters' => 'Afiche'
        ];
        return $labels[$type] ?? ucfirst($type);
    }
    
    /**
     * Formatea el tamaño del archivo
     */
    private static function formatFileSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Obtiene la ruta absoluta del logo para incluirlo en el PDF
     */
    private static function getLogoPath(?string $logo_relative_path): ?string {
        if (empty($logo_relative_path)) {
            return null;
        }
        
        // Construir ruta absoluta
        $absolute_path = __DIR__ . '/../' . $logo_relative_path;
        
        // Verificar si el archivo existe
        if (!file_exists($absolute_path)) {
            error_log("Logo no encontrado: " . $absolute_path);
            return null;
        }
        
        // Verificar si es una imagen válida
        $extension = strtolower(pathinfo($absolute_path, PATHINFO_EXTENSION));
        $valid_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($extension, $valid_extensions)) {
            error_log("Logo no es una imagen válida: " . $absolute_path);
            return null;
        }
        
        // Retornar ruta absoluta para Dompdf
        return $absolute_path;
    }
    
    /**
     * Crea el PDF usando Dompdf
     */
    private static function createPDF(string $html_content, array $invitation_data): string {
        // Verificar si Dompdf está disponible
        if (!class_exists('Dompdf\Dompdf')) {
            // Intentar cargar Dompdf manualmente
            $dompdf_path = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($dompdf_path)) {
                require_once $dompdf_path;
            } else {
                throw new Exception('Dompdf no está disponible. Instale Dompdf para generar PDFs.');
            }
        }
        
        // Crear directorio de PDFs si no existe
        $pdf_dir = __DIR__ . '/../upload/pdfs/';
        if (!is_dir($pdf_dir)) {
            mkdir($pdf_dir, 0755, true);
        }
        
        // Generar nombre del archivo
        $filename = 'invitacion_' . $invitation_data['torneo_id'] . '_' . $invitation_data['club_id'] . '_' . time() . '.pdf';
        $pdf_path = $pdf_dir . $filename;
        
        // Crear PDF usando Dompdf
        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'chroot' => __DIR__ . '/..'
        ]);
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Guardar PDF
        $output = $dompdf->output();
        file_put_contents($pdf_path, $output);
        
        return 'upload/pdfs/' . $filename;
    }
}
?>
