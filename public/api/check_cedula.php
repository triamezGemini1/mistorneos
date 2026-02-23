<?php
/**
 * Endpoint para verificar si una cédula ya está inscrita en el torneo (tabla inscritos + usuarios).
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['cedula']) || trim($_GET['cedula']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Cédula requerida']);
    exit;
}

$cedula = preg_replace('/\D/', '', trim($_GET['cedula']));
$torneo_id = isset($_GET['torneo']) ? (int)$_GET['torneo'] : 0;

try {
    $query = "
        SELECT i.id, u.cedula, u.nombre
        FROM inscritos i
        JOIN usuarios u ON i.id_usuario = u.id
        WHERE u.cedula = ?
    ";
    $params = [$cedula];
    if ($torneo_id > 0) {
        $query .= " AND i.torneo_id = ?";
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
                'id' => (int)$registrant['id'],
                'cedula' => $registrant['cedula'],
                'nombre' => $registrant['nombre']
            ],
            'message' => $torneo_id > 0
                ? 'Esta cédula ya está inscrita en este torneo'
                : 'Esta cédula ya está registrada en el sistema'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => 'Cédula disponible'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}





