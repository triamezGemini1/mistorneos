<?php
/**
 * Remover Jugador de un Equipo
 */

session_start();

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/EquiposHelper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = DB::pdo();
    
    $torneo_id = $_SESSION['torneo_id'];
    $club_id = $_SESSION['club_id'];
    
    $jugador_id = (int)($_POST['jugador_id'] ?? 0);
    $equipo_id = (int)($_POST['equipo_id'] ?? 0);
    
    if ($jugador_id <= 0 || $equipo_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Parámetros no válidos']);
        exit;
    }
    
    // Verificar que el equipo pertenece al club
    $stmt = $pdo->prepare("SELECT id, id_torneo, id_club FROM equipos WHERE id = ?");
    $stmt->execute([$equipo_id]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipo) {
        echo json_encode(['success' => false, 'message' => 'Equipo no encontrado']);
        exit;
    }
    
    if ($equipo['id_torneo'] != $torneo_id || $equipo['id_club'] != $club_id) {
        echo json_encode(['success' => false, 'message' => 'No tiene permisos para modificar este equipo']);
        exit;
    }
    
    // Verificar que el jugador pertenece al equipo
    $stmt = $pdo->prepare("SELECT id FROM equipo_jugadores WHERE id = ? AND id_equipo = ?");
    $stmt->execute([$jugador_id, $equipo_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Jugador no encontrado en este equipo']);
        exit;
    }
    
    // Remover jugador (cambiar estatus a inactivo)
    $resultado = EquiposHelper::removerJugador($jugador_id);
    
    echo json_encode($resultado);
    
} catch (PDOException $e) {
    error_log("Error al remover jugador: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos'
    ]);
} catch (Exception $e) {
    error_log("Error al remover jugador: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}









