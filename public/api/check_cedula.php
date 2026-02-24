<?php
/**
 * Endpoint para verificar si una cédula ya está inscrita en el torneo (tabla inscritos + usuarios).
 * Si está inscrito: retorna exists=true y mensaje "Jugador ya registrado".
 * Si no está: retorna exists=false para que el cliente pueda buscar en usuarios y personas.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['cedula']) || trim($_GET['cedula']) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cédula requerida']);
    exit;
}

$cedula_raw = trim($_GET['cedula']);
$cedula_solo_numeros = preg_replace('/\D/', '', $cedula_raw);
$torneo_id = isset($_GET['torneo']) ? (int)$_GET['torneo'] : 0;
$nacionalidad = isset($_GET['nacionalidad']) && in_array($_GET['nacionalidad'], ['V', 'E', 'J', 'P']) ? $_GET['nacionalidad'] : null;

try {
    $cedula = preg_replace('/\D/', '', $cedula_raw);
    if ($cedula === '') {
        echo json_encode(['success' => true, 'exists' => false, 'message' => 'Cédula disponible']);
        exit;
    }
    $params = [];
    $query = "
        SELECT i.id, i.torneo_id, i.id_club, u.id as id_usuario, u.cedula, u.nacionalidad, u.nombre, u.sexo, u.fechnac, u.celular, u.email
        FROM inscritos i
        JOIN usuarios u ON i.id_usuario = u.id
        WHERE (u.cedula = ? OR u.cedula = CONCAT(?, ?))
    ";
    $params = [$cedula, $nacionalidad ?? 'V', $cedula];
    if ($nacionalidad !== null) {
        $query .= " AND u.nacionalidad = ?";
        $params[] = $nacionalidad;
    }
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
                'id_usuario' => (int)$registrant['id_usuario'],
                'cedula' => $registrant['cedula'],
                'nacionalidad' => $registrant['nacionalidad'] ?? 'V',
                'nombre' => $registrant['nombre'],
                'sexo' => $registrant['sexo'] ?? '',
                'fechnac' => $registrant['fechnac'] ?? '',
                'celular' => $registrant['celular'] ?? '',
                'telefono' => $registrant['celular'] ?? '',
                'email' => $registrant['email'] ?? ''
            ],
            'message' => $torneo_id > 0
                ? 'Jugador ya registrado en este torneo'
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





