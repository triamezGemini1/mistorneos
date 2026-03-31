<?php
/**
 * Endpoint para verificar si una c�dula ya existe en la tabla registrants
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// Verificar que se haya enviado una c�dula
if (!isset($_GET['cedula']) || empty($_GET['cedula'])) {
    http_response_code(400);
    echo json_encode(['error' => 'C�dula requerida']);
    exit;
}

$cedula = $_GET['cedula'];
$torneo_id = $_GET['torneo'] ?? '';

try {
    // Buscar en la tabla registrants por c�dula
    $query = "SELECT id, cedula, nombre FROM inscripciones WHERE cedula = ?";
    $params = [$cedula];
    
    if ($torneo_id) {
        $query .= " AND torneo_id = ?";
        $params[] = $torneo_id;
    }
    
    $query .= " LIMIT 1";
    
    $stmt = DB::pdo()->prepare($query);
    $stmt->execute($params);
    
    $registrant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($registrant) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'data' => [
                'id' => $registrant['id'],
                'cedula' => $registrant['cedula'],
                'nombre' => $registrant['nombre']
            ],
            'message' => $torneo_id ? 
                'Esta c�dula ya est� inscrita en este torneo' : 
                'Esta c�dula ya est� registrada en el sistema'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => 'C�dula disponible'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>





