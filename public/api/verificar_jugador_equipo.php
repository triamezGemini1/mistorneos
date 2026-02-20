<?php
/**
 * API: Verificar disponibilidad de jugador para equipo
 * public/api/ - Acceso web
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $pdo = DB::pdo();
    
    $torneoId = (int)($_REQUEST['torneo_id'] ?? $_REQUEST['id_torneo'] ?? 0);
    $cedula = trim($_REQUEST['cedula'] ?? '');
    $idUsuario = (int)($_REQUEST['id_usuario'] ?? $_REQUEST['carnet'] ?? 0);
    $equipoIdActual = (int)($_REQUEST['equipo_id'] ?? $_REQUEST['id_equipo'] ?? 0);
    
    if ($torneoId <= 0) {
        echo json_encode(['disponible' => false, 'mensaje' => 'Debe especificar el ID del torneo', 'equipo_actual' => null, 'codigo_equipo' => null, 'jugador' => null]);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, modalidad, nombre FROM tournaments WHERE id = ?");
    $stmt->execute([$torneoId]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$torneo) {
        echo json_encode(['disponible' => false, 'mensaje' => 'Torneo no encontrado', 'equipo_actual' => null, 'codigo_equipo' => null, 'jugador' => null]);
        exit;
    }
    
    if ((int)$torneo['modalidad'] !== 3) {
        echo json_encode(['disponible' => false, 'mensaje' => 'Este torneo no es modalidad equipos', 'equipo_actual' => null, 'codigo_equipo' => null, 'jugador' => null]);
        exit;
    }
    
    if (empty($cedula) && $idUsuario <= 0) {
        echo json_encode(['disponible' => false, 'mensaje' => 'Debe proporcionar cédula o ID de usuario', 'equipo_actual' => null, 'codigo_equipo' => null, 'jugador' => null]);
        exit;
    }
    
    $cedulaLimpia = preg_replace('/^[VEJP]/i', '', $cedula);
    $jugadorData = null;
    $cedulaBusqueda = $cedulaLimpia;
    
    if ($idUsuario > 0) {
        $stmt = $pdo->prepare("SELECT id, cedula, nombre FROM usuarios WHERE id = ?");
        $stmt->execute([$idUsuario]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($usuario) {
            $cedulaBusqueda = $usuario['cedula'];
            $jugadorData = ['id' => $usuario['id'], 'cedula' => $usuario['cedula'], 'nombre' => $usuario['nombre'], 'origen' => 'usuarios'];
        }
    }
    
    if (!empty($cedulaBusqueda) && !$jugadorData) {
        $stmt = $pdo->prepare("SELECT id, cedula, nombre FROM usuarios WHERE cedula = ?");
        $stmt->execute([$cedulaBusqueda]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($usuario) {
            $jugadorData = ['id' => $usuario['id'], 'cedula' => $usuario['cedula'], 'nombre' => $usuario['nombre'], 'origen' => 'usuarios'];
        } else {
            $stmt = $pdo->prepare("SELECT id, cedula, nombre FROM inscripciones WHERE cedula = ? AND torneo_id = ? LIMIT 1");
            $stmt->execute([$cedulaBusqueda, $torneoId]);
            $inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($inscripcion) {
                $jugadorData = ['id' => $inscripcion['id'], 'cedula' => $inscripcion['cedula'], 'nombre' => $inscripcion['nombre'], 'origen' => 'inscripciones'];
            }
        }
    }
    
    $sql = "SELECT e.id AS equipo_id, e.nombre_equipo, e.codigo_equipo, ej.posicion_equipo, ej.es_capitan FROM equipo_jugadores ej INNER JOIN equipos e ON ej.id_equipo = e.id WHERE e.id_torneo = ? AND ej.cedula = ? AND ej.estatus = 1 AND e.estatus = 0";
    $params = [$torneoId, $cedulaBusqueda];
    if ($equipoIdActual > 0) {
        $sql .= " AND e.id != ?";
        $params[] = $equipoIdActual;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $equipoExistente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($equipoExistente) {
        echo json_encode([
            'disponible' => false,
            'mensaje' => "El jugador ya está inscrito en el equipo '{$equipoExistente['nombre_equipo']}' (Código: {$equipoExistente['codigo_equipo']})",
            'equipo_actual' => $equipoExistente['nombre_equipo'],
            'codigo_equipo' => $equipoExistente['codigo_equipo'],
            'equipo_id' => (int)$equipoExistente['equipo_id'],
            'posicion_en_equipo' => (int)$equipoExistente['posicion_equipo'],
            'es_capitan' => (bool)$equipoExistente['es_capitan'],
            'jugador' => $jugadorData
        ]);
    } else {
        echo json_encode([
            'disponible' => true,
            'mensaje' => $jugadorData ? 'Jugador disponible para inscribir en equipo' : 'Cédula disponible (jugador no encontrado en sistema)',
            'equipo_actual' => null,
            'codigo_equipo' => null,
            'jugador' => $jugadorData
        ]);
    }
    
} catch (PDOException $e) {
    error_log("API verificar_jugador_equipo ERROR: " . $e->getMessage());
    $msg = strpos($e->getMessage(), "doesn't exist") !== false ? 'Las tablas de equipos no han sido creadas.' : 'Error al verificar disponibilidad';
    echo json_encode(['disponible' => false, 'mensaje' => $msg, 'equipo_actual' => null, 'codigo_equipo' => null, 'jugador' => null]);
} catch (Exception $e) {
    error_log("API verificar_jugador_equipo ERROR: " . $e->getMessage());
    echo json_encode(['disponible' => false, 'mensaje' => 'Error inesperado', 'equipo_actual' => null, 'codigo_equipo' => null, 'jugador' => null]);
}
