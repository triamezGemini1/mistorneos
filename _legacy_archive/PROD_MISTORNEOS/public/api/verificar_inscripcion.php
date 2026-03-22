<?php
/**
 * Endpoint para verificar si un usuario ya está inscrito en un evento
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// Validar parámetros
$cedula = trim($_GET['cedula'] ?? '');
$nacionalidad = trim($_GET['nacionalidad'] ?? 'V');
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;

if (empty($cedula) || $torneo_id <= 0) {
    http_response_code(400);
    echo json_encode(['inscrito' => false, 'error' => 'Parámetros requeridos: cedula y torneo_id']);
    exit;
}

// Normalizar cédula: probar con y sin prefijo de nacionalidad
$cedula_variantes = [preg_replace('/^[VEJP]/i', '', $cedula), $cedula];
if (!empty($nacionalidad) && !preg_match('/^[VEJP]/i', $cedula)) {
    $cedula_variantes[] = strtoupper(substr($nacionalidad, 0, 1)) . $cedula;
}

try {
    $pdo = DB::pdo();
    
    // 1. Buscar PRIMERO en usuarios (prioridad para inscripción en línea)
    $usuario = null;
    foreach (array_unique($cedula_variantes) as $c) {
        if (empty($c)) continue;
        $stmt = $pdo->prepare("SELECT id, nombre, email, celular, sexo FROM usuarios WHERE cedula = ? LIMIT 1");
        $stmt->execute([$c]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($usuario) break;
    }
    
    if (!$usuario) {
        // Usuario no registrado: requiere completar registro antes de inscribirse
        echo json_encode([
            'inscrito' => false,
            'usuario_existe' => false,
            'requiere_registro' => true,
            'mensaje' => 'No estás registrado en el sistema. Debes completar el formulario de registro para continuar con la inscripción.'
        ]);
        exit;
    }
    
    $id_usuario = $usuario['id'];
    
    // 2. Verificar si ya está inscrito en el torneo
    $stmt = $pdo->prepare("
        SELECT id FROM inscritos 
        WHERE torneo_id = ? AND id_usuario = ?
        LIMIT 1
    ");
    $stmt->execute([$torneo_id, $id_usuario]);
    $inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($inscripcion) {
        echo json_encode([
            'inscrito' => true,
            'usuario_existe' => true,
            'mensaje' => 'Ya estás inscrito en este evento'
        ]);
    } else {
        // Usuario registrado y no inscrito: retornar datos para llenar formulario
        echo json_encode([
            'inscrito' => false,
            'usuario_existe' => true,
            'mensaje' => 'Usuario encontrado. Puede proceder con la inscripción.',
            'usuario' => [
                'nombre' => $usuario['nombre'] ?? '',
                'email' => $usuario['email'] ?? '',
                'celular' => $usuario['celular'] ?? '',
                'sexo' => $usuario['sexo'] ?? ''
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("verificar_inscripcion.php - Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'inscrito' => false,
        'error' => 'Error interno del servidor'
    ]);
}

