<?php


require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/PlayerCredentialGenerator.php';

// Verificar autenticación y permisos
$user = Auth::user();
if (!$user || !in_array($user['role'], ['admin_general', 'admin_torneo', 'admin_club'], true)) {
    http_response_code(401);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Denegado</title>
        <style>
            body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f5f5f5; }
            .container { text-align: center; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .icon { font-size: 64px; color: #d32f2f; margin-bottom: 20px; }
            h1 { color: #333; margin: 0 0 10px 0; }
            p { color: #666; margin-bottom: 20px; }
            a { display: inline-block; padding: 10px 20px; background: #1976d2; color: white; text-decoration: none; border-radius: 5px; }
            a:hover { background: #1565c0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">??</div>
            <h1>Acceso Denegado</h1>
            <p>No tienes permisos para acceder a esta función.</p>
            <p>Por favor, <a href="../login.php">inicia sesión</a> con una cuenta autorizada.</p>
            <a href="../index.php">Volver al inicio</a>
        </div>
    </body>
    </html>';
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$action = $_GET['action'] ?? 'single';
$registrant_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : null;
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;

try {
    switch ($action) {
        case 'single':
            // Generar credencial individual
            if (!$registrant_id) {
                throw new Exception('ID de jugador no especificado');
            }
            
            $result = PlayerCredentialGenerator::generateCredential($registrant_id);
            
            if ($result['success']) {
                $file_path = __DIR__ . '/../../' . $result['pdf_path'];
                
                if (!file_exists($file_path)) {
                    throw new Exception('Archivo generado no encontrado');
                }
                
                // Descargar el archivo
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="credencial_jugador_' . $registrant_id . '.pdf"');
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
                
                // Opcional: eliminar el archivo después de descargarlo
                // unlink($file_path);
                
                exit;
            } else {
                throw new Exception($result['error']);
            }
            break;
            
        case 'bulk':
            // Generar credenciales múltiples
            if (!$tournament_id) {
                throw new Exception('ID de torneo no especificado');
            }
            
            $result = PlayerCredentialGenerator::generateBulkCredentials($tournament_id, $club_id);
            
            if ($result['success']) {
                // Crear un archivo ZIP con todas las credenciales
                $zip_path = createCredentialsZip($result['credentials'], $tournament_id);
                
                if (file_exists($zip_path)) {
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="credenciales_torneo_' . $tournament_id . '.zip"');
                    header('Content-Length: ' . filesize($zip_path));
                    readfile($zip_path);
                    
                    // Eliminar archivos temporales
                    unlink($zip_path);
                    
                    exit;
                } else {
                    throw new Exception('Error creando archivo ZIP');
                }
            } else {
                throw new Exception($result['error']);
            }
            break;
            
        case 'preview':
            // Vista previa de credencial
            if (!$registrant_id) {
                throw new Exception('ID de jugador no especificado');
            }
            
            $result = PlayerCredentialGenerator::generateCredential($registrant_id);
            
            if ($result['success']) {
                $file_path = __DIR__ . '/../../' . $result['pdf_path'];
                
                if (!file_exists($file_path)) {
                    throw new Exception('Archivo generado no encontrado');
                }
                
                // Mostrar en el navegador (inline)
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="preview_credencial.pdf"');
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
                
                exit;
            } else {
                throw new Exception($result['error']);
            }
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    error_log("Error generando credencial: " . $e->getMessage());
    showErrorPage($e->getMessage());
    exit;
}

/**
 * Muestra una página de error HTML amigable
 */
function showErrorPage(string $error_message): void {
    http_response_code(400);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error al Generar Credencial</title>
        <style>
            body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f5f5f5; padding: 20px; }
            .container { text-align: center; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; }
            .icon { font-size: 64px; margin-bottom: 20px; }
            h1 { color: #d32f2f; margin: 0 0 10px 0; font-size: 24px; }
            .error-message { color: #333; margin: 20px 0; padding: 20px; background: #ffebee; border-left: 4px solid #d32f2f; text-align: left; border-radius: 4px; line-height: 1.6; }
            .instructions { text-align: left; margin: 20px 0; padding: 15px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px; }
            .instructions ol { margin: 10px 0; padding-left: 20px; }
            .instructions li { margin: 5px 0; }
            .btn { display: inline-block; margin: 10px 5px; padding: 12px 24px; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
            .btn-primary { background: #1976d2; }
            .btn-primary:hover { background: #1565c0; }
            .btn-secondary { background: #757575; }
            .btn-secondary:hover { background: #616161; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">??</div>
            <h1>No se puede generar la credencial</h1>
            <div class="error-message">
                ' . nl2br(htmlspecialchars($error_message)) . '
            </div>
            <div class="instructions">
                <strong>?? Pasos para solucionar:</strong>
                <ol>
                    <li>Ir al módulo de <strong>Inscritos</strong></li>
                    <li>Seleccionar el <strong>Torneo</strong></li>
                    <li>Hacer clic en <strong>"Numerar por Club"</strong> para asignar números</li>
                    <li>Confirmar la numeración</li>
                    <li>Volver a generar la credencial</li>
                </ol>
            </div>
            <a href="../../public/index.php?page=registrants" class="btn btn-primary">?? Ir a Inscritos</a>
            <a href="javascript:history.back()" class="btn btn-secondary">? Volver</a>
        </div>
    </body>
    </html>';
}

/**
 * Crea un archivo ZIP con múltiples credenciales
 */
function createCredentialsZip(array $credential_paths, int $tournament_id): string {
    $zip = new ZipArchive();
    $zip_filename = __DIR__ . '/../../upload/credentials/credenciales_torneo_' . $tournament_id . '_' . time() . '.zip';
    
    if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
        throw new Exception('No se pudo crear el archivo ZIP');
    }
    
    foreach ($credential_paths as $index => $credential_path) {
        $full_path = __DIR__ . '/../../' . $credential_path;
        if (file_exists($full_path)) {
            $zip->addFile($full_path, 'credencial_' . ($index + 1) . '.pdf');
        }
    }
    
    $zip->close();
    
    return $zip_filename;
}



