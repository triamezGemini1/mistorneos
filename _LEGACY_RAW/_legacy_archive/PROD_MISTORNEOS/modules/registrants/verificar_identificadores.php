<?php
/**
 * Verificar que todos los jugadores tengan identificadores válidos
 * Usado antes de generar credenciales o exportar jugadores
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json');

// Verificar autenticación
try {
    Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado',
        'detalles' => $e->getMessage()
    ]);
    exit;
}

try {
    // Obtener parámetros
    $torneo_id = !empty($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : null;
    $club_id = !empty($_GET['club_id']) ? (int)$_GET['club_id'] : null;
    $club_ids = !empty($_GET['club_ids']) ? $_GET['club_ids'] : [];
    
    // Construir query
    $where = ['1=1']; // Condición base
    $params = [];
    
    if ($torneo_id) {
        $where[] = "r.torneo_id = ?";
        $params[] = $torneo_id;
    }
    
    if ($club_id) {
        $where[] = "r.id_club = ?";
        $params[] = $club_id;
    } elseif (!empty($club_ids) && is_array($club_ids)) {
        $placeholders = str_repeat('?,', count($club_ids) - 1) . '?';
        $where[] = "r.id_club IN ($placeholders)";
        foreach ($club_ids as $cid) {
            $params[] = (int)$cid;
        }
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Contar jugadores
    $stmt = DB::pdo()->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN r.posicion IS NULL OR r.posicion = 0 THEN 1 ELSE 0 END) as sin_identificador,
            SUM(CASE WHEN r.posicion IS NOT NULL AND r.posicion > 0 THEN 1 ELSE 0 END) as con_identificador
        FROM inscritos r
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = (int)$resultado['total'];
    $sin_identificador = (int)$resultado['sin_identificador'];
    $con_identificador = (int)$resultado['con_identificador'];
    
    // Si no hay jugadores
    if ($total === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontraron jugadores con los filtros especificados',
            'total' => 0,
            'sin_identificador' => 0,
            'con_identificador' => 0
        ]);
        exit;
    }
    
    // Responder con resultados
    echo json_encode([
        'success' => true,
        'total' => $total,
        'con_identificador' => $con_identificador,
        'sin_identificador' => $sin_identificador,
        'message' => $sin_identificador > 0 
            ? "Hay jugadores sin identificador" 
            : "Todos los jugadores tienen identificador válido"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al verificar identificadores',
        'detalles' => $e->getMessage()
    ]);
}

