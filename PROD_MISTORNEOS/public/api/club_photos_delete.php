<?php
/**
 * API para eliminar fotos de clubes
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json');

// Verificar permisos
Auth::requireRole(['admin_club']);

$pdo = DB::pdo();
$user = Auth::user();

try {
    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    
    $foto_id = isset($input['foto_id']) ? (int)$input['foto_id'] : 0;
    $club_id = isset($input['club_id']) ? (int)$input['club_id'] : 0;
    
    if ($foto_id <= 0 || $club_id <= 0) {
        throw new Exception('Parámetros inválidos');
    }
    
    // Verificar que el club pertenece al admin_club
    if ($user['club_id'] != $club_id) {
        throw new Exception('No tiene permisos para eliminar fotos de este club');
    }
    
    // Obtener información de la foto
    $stmt = $pdo->prepare("SELECT ruta_imagen FROM club_photos WHERE id = ? AND club_id = ?");
    $stmt->execute([$foto_id, $club_id]);
    $foto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$foto) {
        throw new Exception('Foto no encontrada');
    }
    
    // Eliminar archivo físico
    $file_path = __DIR__ . '/../../' . $foto['ruta_imagen'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Eliminar registro de la base de datos
    $stmt = $pdo->prepare("DELETE FROM club_photos WHERE id = ? AND club_id = ?");
    $stmt->execute([$foto_id, $club_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Foto eliminada correctamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}




