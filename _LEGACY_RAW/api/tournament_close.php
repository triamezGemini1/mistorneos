<?php
/**
 * API para cerrar/finalizar un torneo
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

header('Content-Type: application/json');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar permisos
Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

// Validar CSRF
CSRF::validate();

// Obtener datos
$torneo_id = isset($_POST['torneo_id']) ? (int)$_POST['torneo_id'] : 0;
$confirmar = isset($_POST['confirmar']) && $_POST['confirmar'] === 'true';

if ($torneo_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de torneo inválido']);
    exit;
}

// Verificar acceso al torneo
if (!Auth::canAccessTournament($torneo_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para acceder a este torneo']);
    exit;
}

try {
    $pdo = DB::pdo();
    
    // Verificar si el campo finalizado existe
    $stmt = $pdo->query("SHOW COLUMNS FROM tournaments LIKE 'finalizado'");
    $campo_existe = $stmt->rowCount() > 0;
    
    if (!$campo_existe) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'El campo finalizado no existe en la tabla. Ejecute el script de migración.']);
        exit;
    }
    
    // Obtener información del torneo
    $stmt = $pdo->prepare("SELECT id, nombre, finalizado FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$torneo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Torneo no encontrado']);
        exit;
    }
    
    // Si ya está finalizado, no hacer nada
    if ($torneo['finalizado'] == 1) {
        echo json_encode(['success' => true, 'message' => 'El torneo ya está finalizado', 'already_closed' => true]);
        exit;
    }
    
    // Si no se confirma, retornar información para mostrar confirmación
    if (!$confirmar) {
        echo json_encode([
            'success' => true,
            'needs_confirmation' => true,
            'message' => '¿Está seguro de que desea finalizar este torneo? Esta acción no se puede deshacer.',
            'torneo_nombre' => $torneo['nombre']
        ]);
        exit;
    }
    
    // Cerrar el torneo
    $pdo->beginTransaction();
    
    try {
        // Verificar si el campo fecha_finalizacion existe
        $stmt = $pdo->query("SHOW COLUMNS FROM tournaments LIKE 'fecha_finalizacion'");
        $fecha_existe = $stmt->rowCount() > 0;
        
        if ($fecha_existe) {
            $stmt = $pdo->prepare("
                UPDATE tournaments 
                SET finalizado = 1, fecha_finalizacion = NOW() 
                WHERE id = ?
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE tournaments 
                SET finalizado = 1 
                WHERE id = ?
            ");
        }
        
        $stmt->execute([$torneo_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Torneo finalizado exitosamente',
            'torneo_nombre' => $torneo['nombre']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error al cerrar torneo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cerrar el torneo: ' . $e->getMessage()]);
}






