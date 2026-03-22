<?php
/**
 * API para obtener fotos de un torneo (público)
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$pdo = DB::pdo();

try {
    $torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
    
    if ($torneo_id <= 0) {
        throw new Exception('ID de torneo inválido');
    }
    
    // Obtener fotos del torneo
    $stmt = $pdo->prepare("
        SELECT 
            id,
            ruta_imagen,
            titulo,
            descripcion,
            orden,
            fecha_subida
        FROM club_photos
        WHERE torneo_id = ?
        ORDER BY orden ASC, fecha_subida ASC
    ");
    
    $stmt->execute([$torneo_id]);
    $fotos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'fotos' => $fotos,
        'total' => count($fotos)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}



