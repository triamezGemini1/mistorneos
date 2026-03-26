<?php
/**
 * API: Búsqueda expedita para Inscripción en Sitio.
 * Solo tabla usuarios. Orden: 1) número solo, 2) V+cedula, 3) E+cedula. Si no hay resultado: no encontrado.
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$cedula = trim($_GET['cedula'] ?? '');
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

/**
 * Extrae solo dígitos de la cédula para búsqueda expedita.
 * Orden de búsqueda: 1) número solo, 2) V+number, 3) E+number.
 */
function cedulaSoloNumeros($cedula) {
    return preg_replace('/\D/', '', trim($cedula));
}

try {
    $pdo = DB::pdo();

    // Búsqueda por ID de usuario (rápida, un solo SELECT por PK)
    if ($user_id > 0) {
        $stmt = $pdo->prepare("SELECT id, username, nombre, cedula, club_id, email, celular FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'existe_usuario' => true,
                    'usuario_existente' => [
                        'id' => (int)$row['id'],
                        'username' => $row['username'],
                        'nombre' => $row['nombre'],
                        'cedula' => $row['cedula'] ?? '',
                        'email' => $row['email'] ?? '',
                        'celular' => $row['celular'] ?? '',
                        'club_id' => $row['club_id'] ? (int)$row['club_id'] : null
                    ]
                ]
            ]);
            exit;
        }
        echo json_encode(['success' => true, 'data' => ['existe_usuario' => false, 'usuario_existente' => null]]);
        exit;
    }

    if ($cedula === '') {
        echo json_encode(['success' => false, 'error' => 'Cédula o ID requerido']);
        exit;
    }

    $solo_numeros = cedulaSoloNumeros($cedula);
    if ($solo_numeros === '') {
        echo json_encode(['success' => false, 'error' => 'Cédula inválida']);
        exit;
    }

    // Búsqueda expedita: 1) número solo, 2) V+cedula, 3) E+cedula (cada uno un SELECT por índice)
    $sql = "SELECT id, username, nombre, cedula, club_id, email, celular FROM usuarios WHERE cedula = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);

    $row = null;
    $valores = [$solo_numeros, 'V' . $solo_numeros, 'E' . $solo_numeros];
    foreach ($valores as $valor) {
        $stmt->execute([$valor]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            break;
        }
    }

    if ($row) {
        echo json_encode([
            'success' => true,
            'data' => [
                'existe_usuario' => true,
                'usuario_existente' => [
                    'id' => (int)$row['id'],
                    'username' => $row['username'],
                    'nombre' => $row['nombre'],
                    'cedula' => $row['cedula'] ?? '',
                    'email' => $row['email'] ?? '',
                    'celular' => $row['celular'] ?? '',
                    'club_id' => $row['club_id'] ? (int)$row['club_id'] : null
                ]
            ]
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'existe_usuario' => false,
            'usuario_existente' => null,
            'mensaje' => 'No encontrado.'
        ]
    ]);

} catch (Exception $e) {
    error_log("search_usuario_inscripcion_sitio.php - Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al buscar']);
}
