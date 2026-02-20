<?php
/**
 * API: Buscar jugador inscrito en un torneo por cédula
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json');

try {
    $cedula = $_GET['cedula'] ?? '';
    $torneo_id = (int)($_GET['torneo_id'] ?? 0);
    
    if (empty($cedula) || $torneo_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Cédula y torneo_id son requeridos']);
        exit;
    }
    
    $pdo = DB::pdo();
    
    // Buscar jugador en usuarios (afiliados)
    $stmt = $pdo->prepare("
        SELECT u.id as id_usuario, u.nombre, u.cedula, u.sexo,
               u.club_id as club_id, c.nombre as club_nombre,
               ins.id as id_inscrito, ins.codigo_equipo
        FROM usuarios u
        LEFT JOIN clubes c ON u.club_id = c.id
        LEFT JOIN inscritos ins ON ins.id_usuario = u.id AND ins.torneo_id = ? AND ins.estatus != 'retirado'
        WHERE u.cedula = ? 
          AND u.role = 'usuario'
          AND u.status = 0
        LIMIT 1
    ");
    $stmt->execute([$torneo_id, $cedula]);
    $jugador = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($jugador) {
        // Verificar si ya está en un equipo (tiene codigo_equipo)
        if (!empty($jugador['codigo_equipo'])) {
            echo json_encode([
                'success' => false, 
                'message' => 'Este jugador ya está asignado a un equipo (código: ' . $jugador['codigo_equipo'] . ')',
                'jugador' => $jugador
            ]);
            exit;
        }
        
        // Agregar campo id para compatibilidad
        $jugador['id'] = $jugador['id_inscrito'] ?? null;
        
        echo json_encode([
            'success' => true,
            'jugador' => $jugador
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Jugador no encontrado en los afiliados disponibles'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

