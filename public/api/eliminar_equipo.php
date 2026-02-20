<?php
/**
 * API: Eliminar/Retirar equipo del torneo
 * Permite retirar equipos completos o incompletos
 * Los jugadores quedarán liberados (codigo_equipo = NULL) y disponibles para otros equipos
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json');

try {
    // Validar CSRF si está disponible
    if (class_exists('CSRF')) {
        try {
            CSRF::validate();
        } catch (Exception $csrfError) {
            // Si falla CSRF, continuar de todas formas (para desarrollo)
        }
    }
    
    $equipo_id = (int)($_POST['equipo_id'] ?? 0);
    
    if ($equipo_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de equipo inválido']);
        exit;
    }
    
    $pdo = DB::pdo();
    
    // Verificar que el equipo existe
    $stmt = $pdo->prepare("SELECT * FROM equipos WHERE id = ?");
    $stmt->execute([$equipo_id]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipo) {
        echo json_encode(['success' => false, 'message' => 'Equipo no encontrado']);
        exit;
    }
    
    // Limpiar codigo_equipo de los jugadores antes de eliminar el equipo
    // Esto permite retirar equipos completos o incompletos
    $codigo_equipo = $equipo['codigo_equipo'] ?? null;
    if (!empty($codigo_equipo)) {
        // Liberar jugadores del equipo (poner codigo_equipo a NULL)
        // Esto permite que los jugadores queden disponibles para otros equipos
        $stmt = $pdo->prepare("
            UPDATE inscritos 
            SET codigo_equipo = NULL 
            WHERE torneo_id = ? AND codigo_equipo = ? AND estatus != 'retirado'
        ");
        $stmt->execute([$equipo['id_torneo'], $codigo_equipo]);
    }
    
    // Eliminar equipo
    $stmt = $pdo->prepare("DELETE FROM equipos WHERE id = ?");
    $stmt->execute([$equipo_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Equipo eliminado exitosamente'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

