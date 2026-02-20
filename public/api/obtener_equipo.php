<?php
/**
 * API: Obtener datos de un equipo con sus jugadores
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/EquiposHelper.php';

header('Content-Type: application/json');

try {
    $equipo_id = (int)($_GET['id'] ?? 0);
    
    if ($equipo_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de equipo inválido']);
        exit;
    }
    
    $pdo = DB::pdo();
    
    // Obtener datos del equipo
    $stmt = $pdo->prepare("
        SELECT e.*, c.nombre as club_nombre
        FROM equipos e
        LEFT JOIN clubes c ON e.id_club = c.id
        WHERE e.id = ?
    ");
    $stmt->execute([$equipo_id]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipo) {
        echo json_encode(['success' => false, 'message' => 'Equipo no encontrado']);
        exit;
    }
    
    // Obtener jugadores del equipo desde inscritos usando codigo_equipo
    $codigo_equipo = $equipo['codigo_equipo'] ?? null;
    
    if (empty($codigo_equipo)) {
        $jugadores = [];
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                i.id as id_inscrito,
                i.id_usuario,
                i.torneo_id,
                i.id_club,
                i.codigo_equipo,
                i.estatus,
                u.cedula,
                u.nombre,
                u.id as usuario_id
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            WHERE i.torneo_id = ? AND i.codigo_equipo = ? AND i.estatus != 'retirado'
            ORDER BY u.nombre ASC
        ");
        $stmt->execute([$equipo['id_torneo'], $codigo_equipo]);
        $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear jugadores para compatibilidad con el formulario
        foreach ($jugadores as &$jugador) {
            $jugador['id'] = $jugador['id_inscrito'];
            $jugador['club_nombre'] = $equipo['club_nombre'] ?? '';
        }
        unset($jugador);
    }
    
    $equipo['jugadores'] = $jugadores;
    
    echo json_encode([
        'success' => true,
        'equipo' => $equipo
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

