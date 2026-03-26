<?php


require_once __DIR__ . '/../config/db.php';

/**
 * Generador de credenciales para jugadores
 * Genera PDFs en formato tarjeta de cr�dito (8cm x 5cm)
 */
class PlayerCredentialGenerator {
    
    /**
     * Genera credencial PDF para un jugador espec�fico
     */
    public static function generateCredential(int $registrant_id): array {
        try {
            // Obtener datos del jugador
            $player_data = self::getPlayerData($registrant_id);
            if (!$player_data) {
                return ['success' => false, 'error' => 'Jugador no encontrado'];
            }
            
            // Validar que el jugador tenga un identificador asignado
            $identificador = $player_data['identificador'] ?? 0;
            if (empty($identificador) || $identificador == 0) {
                return [
                    'success' => false, 
                    'error' => 'IDENTIFICADOR INV�LIDO. El jugador no tiene un n�mero asignado. Por favor, genere los identificadores del torneo antes de crear las credenciales. Puede hacerlo desde el bot�n "Numerar por Club" en el m�dulo de inscritos.'
                ];
            }
            
            // Generar contenido HTML
            $html_content = self::generateCredentialHTML($player_data);
            
            // Generar PDF
            $pdf_path = self::createCredentialPDF($html_content, $player_data);
            
            return [
                'success' => true,
                'pdf_path' => $pdf_path,
                'message' => 'Credencial generada correctamente'
            ];
            
        } catch (Exception $e) {
            error_log("Error generando credencial: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error generando credencial: ' . $e->getMessage()];
        }
    }
    
    /**
     * Genera credenciales para m�ltiples jugadores de un torneo
     */
    public static function generateBulkCredentials(int $tournament_id, ?int $club_id = null): array {
        try {
            $credentials = [];
            $errors = [];
            
            // Obtener todos los jugadores del torneo (opcionalmente filtrado por club)
            $query = "SELECT id FROM inscripciones WHERE torneo_id = ?";
            $params = [$tournament_id];
            
            if ($club_id !== null) {
                $query .= " AND club_id = ?";
                $params[] = $club_id;
            }
            
            $stmt = DB::pdo()->prepare($query);
            $stmt->execute($params);
            $players = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($players as $player_id) {
                $result = self::generateCredential($player_id);
                if ($result['success']) {
                    $credentials[] = $result['pdf_path'];
                } else {
                    $errors[] = "Jugador ID $player_id: " . $result['error'];
                }
            }
            
            return [
                'success' => count($credentials) > 0,
                'credentials' => $credentials,
                'total' => count($credentials),
                'errors' => $errors,
                'message' => count($credentials) . ' credenciales generadas'
            ];
            
        } catch (Exception $e) {
            error_log("Error generando credenciales en masa: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Obtiene todos los datos necesarios para la credencial
     */
    private static function getPlayerData(int $registrant_id): ?array {
        $stmt = DB::pdo()->prepare("
            SELECT 
                r.*,
                t.nombre as tournament_name,
                t.fechator as tournament_date,
                t.club_responsable as organizer_club_id,
                c.nombre as club_name,
                c.logo as club_logo,
                oc.nombre as organizer_club_name,
                oc.logo as organizer_logo
            FROM inscripciones r
            LEFT JOIN tournaments t ON r.torneo_id = t.id
            LEFT JOIN clubes c ON r.club_id = c.id
            LEFT JOIN clubes oc ON t.club_responsable = oc.id
            WHERE r.id = ?
        ");
        $stmt->execute([$registrant_id]);
        return $stmt->fetch();
    }
    
    /**
     * Genera el HTML para la credencial en formato tarjeta
     */
    private static function generateCredentialHTML(array $player_data): string {
        // Formatear datos - convertir a string antes de htmlspecialchars
        $player_name = htmlspecialchars((string)$player_data['nombre']);
        $player_id = htmlspecialchars((string)$player_data['cedula']);
        $tournament_name = htmlspecialchars((string)$player_data['tournament_name']);
        $club_name = htmlspecialchars((string)$player_data['club_name']);
        
        // Manejar identificador: si es 0 o vac�o, mostrar "N/A"
        $identificador_value = $player_data['identificador'] ?? 0;
        if (empty($identificador_value) || $identificador_value == 0) {
            $identificador = 'N/A';
        } else {
            $identificador = htmlspecialchars((string)$identificador_value);
        }
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: 14cm 10cm;
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            width: 14cm;
            height: 10cm;
            font-family: "Arial", "Helvetica", sans-serif;
            background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
            position: relative;
            overflow: hidden;
        }
        
        .card-border {
            position: absolute;
            top: 5mm;
            left: 5mm;
            right: 5mm;
            bottom: 5mm;
            border: 3px solid white;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .content {
            position: relative;
            z-index: 1;
            padding: 5mm 10mm 10mm 10mm;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .tournament-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            color: #0d47a1;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4mm 3mm;
            background: linear-gradient(90deg, #e3f2fd, #bbdefb, #e3f2fd);
            border-radius: 8px;
            border: 2px solid #1565c0;
            margin-bottom: 3mm;
            line-height: 1.3;
        }
        
        .player-line {
            text-align: center;
            margin-bottom: 2.5mm;
            padding: 4mm;
            background: #f8f9fa;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
        }
        
        .player-cedula {
            font-size: 14pt;
            color: #666;
            font-weight: 600;
            margin-bottom: 3mm;
        }
        
        .player-name {
            font-size: 13pt;
            font-weight: bold;
            color: #212121;
            line-height: 1.3;
        }
        
        .club-line {
            text-align: center;
            font-size: 11pt;
            color: #1565c0;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3mm;
            padding: 3mm;
        }
        
        .identifier-line {
            text-align: center;
            margin-bottom: 3mm;
            padding: 6mm;
            background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);
            border-radius: 8px;
            border: 3px solid #1565c0;
        }
        
        .identifier-label {
            font-size: 10pt;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 3mm;
            letter-spacing: 1px;
        }
        
        .identifier-number {
            font-size: 16pt;
            font-weight: bold;
            color: #0d47a1;
            letter-spacing: 2px;
        }
        
        .footer-website {
            text-align: center;
            font-size: 10pt;
            color: #1565c0;
            font-weight: 600;
            padding: 2mm;
            border-top: 2px solid #1565c0;
            background: linear-gradient(90deg, rgba(227, 242, 253, 0.3), rgba(255, 255, 255, 0.5), rgba(227, 242, 253, 0.3));
            border-radius: 6px;
        }
        
        .corner {
            position: absolute;
            width: 14mm;
            height: 14mm;
            border: 3px solid rgba(255, 255, 255, 0.4);
        }
        
        .corner-tl {
            top: 0;
            left: 0;
            border-right: none;
            border-bottom: none;
            border-radius: 12px 0 0 0;
        }
        
        .corner-tr {
            top: 0;
            right: 0;
            border-left: none;
            border-bottom: none;
            border-radius: 0 12px 0 0;
        }
        
        .corner-bl {
            bottom: 0;
            left: 0;
            border-right: none;
            border-top: none;
            border-radius: 0 0 0 12px;
        }
        
        .corner-br {
            bottom: 0;
            right: 0;
            border-left: none;
            border-top: none;
            border-radius: 0 0 12px 0;
        }
    </style>
</head>
<body>
    <div class="corner corner-tl"></div>
    <div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div>
    <div class="corner corner-br"></div>
    
    <div class="card-border">
        <div class="content">
            <div class="tournament-title">' . $tournament_name . '</div>
            
            <div class="player-line">
                <div class="player-cedula">C.I.: ' . $player_id . '</div>
                <div class="player-name">' . $player_name . '</div>
            </div>
            
            <div class="club-line">' . $club_name . '</div>
            
            <div class="identifier-line">
                <div class="identifier-label">Identificador</div>
                <div class="identifier-number">' . $identificador . '</div>
            </div>
            
            <div class="footer-website">www.laestaciondeldomino.com</div>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Crea el PDF de la credencial
     */
    private static function createCredentialPDF(string $html_content, array $player_data): string {
        // Verificar si Dompdf est� disponible
        if (!class_exists('Dompdf\Dompdf')) {
            $dompdf_path = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($dompdf_path)) {
                require_once $dompdf_path;
            } else {
                throw new Exception('Dompdf no est� disponible. Instale Dompdf para generar PDFs.');
            }
        }
        
        // Crear directorio de credenciales si no existe
        $credentials_dir = __DIR__ . '/../upload/credentials/';
        if (!is_dir($credentials_dir)) {
            mkdir($credentials_dir, 0755, true);
        }
        
        // Generar nombre del archivo
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $player_data['nombre']);
        $filename = 'credencial_' . $player_data['torneo_id'] . '_' . $player_data['identificador'] . '_' . $safe_name . '_' . time() . '.pdf';
        $pdf_path = $credentials_dir . $filename;
        
        // Crear PDF usando Dompdf
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', __DIR__ . '/..');
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html_content);
        
        // Tama�o: 14cm x 10cm
        // 1cm = 28.3465 puntos
        // 14cm = 396.85 puntos
        // 10cm = 283.46 puntos
        $dompdf->setPaper([0, 0, 396.85, 283.46], 'landscape');
        
        $dompdf->render();
        
        // Guardar PDF
        $output = $dompdf->output();
        file_put_contents($pdf_path, $output);
        
        return 'upload/credentials/' . $filename;
    }
    
    /**
     * Obtiene la ruta absoluta de un logo
     */
    private static function getLogoPath(?string $logo_relative_path): ?string {
        if (empty($logo_relative_path)) {
            return null;
        }
        
        $absolute_path = __DIR__ . '/../' . $logo_relative_path;
        
        if (!file_exists($absolute_path)) {
            error_log("Logo no encontrado: " . $absolute_path);
            return null;
        }
        
        $extension = strtolower(pathinfo($absolute_path, PATHINFO_EXTENSION));
        $valid_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($extension, $valid_extensions)) {
            return null;
        }
        
        return $absolute_path;
    }
    
    /**
     * Obtiene la ruta del logo de La Estaci�n del Domin�
     */
    private static function getEstacionLogoPath(): ?string {
        // Buscar el logo en varias ubicaciones posibles
        $possible_paths = [
            __DIR__ . '/../upload/logos/estacion_domino.png',
            __DIR__ . '/../upload/logos/estacion_domino.jpg',
            __DIR__ . '/../upload/logos/la_estacion.png',
            __DIR__ . '/../upload/logos/la_estacion.jpg',
            __DIR__ . '/../public/assets/img/estacion_domino.png',
            __DIR__ . '/../public/assets/img/estacion_domino.jpg',
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Descarga directamente la credencial al navegador
     */
    public static function downloadCredential(int $registrant_id): void {
        $result = self::generateCredential($registrant_id);
        
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode($result);
            exit;
        }
        
        $file_path = __DIR__ . '/../' . $result['pdf_path'];
        
        if (!file_exists($file_path)) {
            http_response_code(404);
            echo json_encode(['error' => 'Archivo no encontrado']);
            exit;
        }
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="credencial_jugador.pdf"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }
}


