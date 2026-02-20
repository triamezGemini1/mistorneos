<?php
/**
 * API Simple para Torneos
 * Facilita el acceso desde JavaScript sin routing complejo
 */


require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

try {
    // Obtener filtros
    $estatus = $_GET['estatus'] ?? null;
    
    // Construir query
    $where = [];
    $params = [];
    
    if ($estatus !== null && $estatus !== '') {
        $where[] = "t.estatus = ?";
        $params[] = (int)$estatus;
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Obtener torneos
    $stmt = DB::pdo()->prepare("
        SELECT t.*, c.nombre as club_responsable_nombre
        FROM tournaments t
        LEFT JOIN clubes c ON t.club_responsable = c.id
        {$where_clause}
        ORDER BY t.fechator DESC
    ");
    $stmt->execute($params);
    $tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $tournaments,
        'total' => count($tournaments)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}














