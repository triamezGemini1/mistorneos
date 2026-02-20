<?php
/**
 * API: Verificar disponibilidad de jugador para equipo
 * 
 * Verifica en tiempo real si un jugador puede ser inscrito en un equipo:
 * - Que no esté ya registrado en otro equipo del mismo torneo
 * - Busca por cédula, carnet (id_usuario) o número de identificación
 * 
 * Parámetros GET/POST:
 * - torneo_id: ID del torneo (requerido)
 * - cedula: Número de cédula (opcional)
 * - id_usuario: ID del usuario/carnet (opcional)
 * - equipo_id: ID del equipo actual (opcional, para excluir de la validación)
 * 
 * Respuesta JSON:
 * {
 *   "disponible": true/false,
 *   "mensaje": "string",
 *   "equipo_actual": "nombre del equipo" o null,
 *   "codigo_equipo": "código" o null,
 *   "jugador": { datos del jugador si se encontró }
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Permitir CORS si es necesario
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = DB::pdo();
    
    // Obtener parámetros (soporta GET y POST)
    $torneoId = (int)($_REQUEST['torneo_id'] ?? $_REQUEST['id_torneo'] ?? 0);
    $cedula = trim($_REQUEST['cedula'] ?? '');
    $idUsuario = (int)($_REQUEST['id_usuario'] ?? $_REQUEST['carnet'] ?? 0);
    $equipoIdActual = (int)($_REQUEST['equipo_id'] ?? $_REQUEST['id_equipo'] ?? 0);
    
    // Validar torneo
    if ($torneoId <= 0) {
        echo json_encode([
            'disponible' => false,
            'mensaje' => 'Debe especificar el ID del torneo',
            'equipo_actual' => null,
            'codigo_equipo' => null,
            'jugador' => null
        ]);
        exit;
    }
    
    // Verificar que el torneo sea modalidad equipos
    $stmt = $pdo->prepare("SELECT id, modalidad, nombre FROM tournaments WHERE id = ?");
    $stmt->execute([$torneoId]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$torneo) {
        echo json_encode([
            'disponible' => false,
            'mensaje' => 'Torneo no encontrado',
            'equipo_actual' => null,
            'codigo_equipo' => null,
            'jugador' => null
        ]);
        exit;
    }
    
    if ((int)$torneo['modalidad'] !== 3) {
        echo json_encode([
            'disponible' => false,
            'mensaje' => 'Este torneo no es modalidad equipos',
            'equipo_actual' => null,
            'codigo_equipo' => null,
            'jugador' => null
        ]);
        exit;
    }
    
    // Debe proporcionar cédula o id_usuario
    if (empty($cedula) && $idUsuario <= 0) {
        echo json_encode([
            'disponible' => false,
            'mensaje' => 'Debe proporcionar cédula o ID de usuario',
            'equipo_actual' => null,
            'codigo_equipo' => null,
            'jugador' => null
        ]);
        exit;
    }
    
    // Limpiar cédula (remover letra de nacionalidad si viene)
    $cedulaLimpia = preg_replace('/^[VEJP]/i', '', $cedula);
    
    // Datos del jugador encontrado
    $jugadorData = null;
    $cedulaBusqueda = $cedulaLimpia;
    
    // Si se proporciona id_usuario, obtener la cédula del usuario
    if ($idUsuario > 0) {
        $stmt = $pdo->prepare("SELECT id, cedula, nombre FROM usuarios WHERE id = ?");
        $stmt->execute([$idUsuario]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            $cedulaBusqueda = $usuario['cedula'];
            $jugadorData = [
                'id' => $usuario['id'],
                'cedula' => $usuario['cedula'],
                'nombre' => $usuario['nombre'],
                'origen' => 'usuarios'
            ];
        }
    }
    
    // Si tenemos cédula pero no datos de jugador, buscar en las tablas
    if (!empty($cedulaBusqueda) && !$jugadorData) {
        // Primero buscar en usuarios
        $stmt = $pdo->prepare("SELECT id, cedula, nombre FROM usuarios WHERE cedula = ?");
        $stmt->execute([$cedulaBusqueda]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            $jugadorData = [
                'id' => $usuario['id'],
                'cedula' => $usuario['cedula'],
                'nombre' => $usuario['nombre'],
                'origen' => 'usuarios'
            ];
        } else {
            // Buscar en inscripciones (sistema de invitaciones)
            $stmt = $pdo->prepare("
                SELECT id, cedula, nombre 
                FROM inscripciones 
                WHERE cedula = ? AND torneo_id = ?
                LIMIT 1
            ");
            $stmt->execute([$cedulaBusqueda, $torneoId]);
            $inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($inscripcion) {
                $jugadorData = [
                    'id' => $inscripcion['id'],
                    'cedula' => $inscripcion['cedula'],
                    'nombre' => $inscripcion['nombre'],
                    'origen' => 'inscripciones'
                ];
            }
        }
    }
    
    // Verificar si el jugador ya está en algún equipo de este torneo
    $sql = "
        SELECT 
            e.id AS equipo_id,
            e.nombre_equipo,
            e.codigo_equipo,
            ej.posicion_equipo,
            ej.es_capitan
        FROM equipo_jugadores ej
        INNER JOIN equipos e ON ej.id_equipo = e.id
        WHERE e.id_torneo = ? 
          AND ej.cedula = ?
          AND ej.estatus = 1
          AND e.estatus = 0
    ";
    
    $params = [$torneoId, $cedulaBusqueda];
    
    // Si estamos editando un equipo, excluirlo de la validación
    if ($equipoIdActual > 0) {
        $sql .= " AND e.id != ?";
        $params[] = $equipoIdActual;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $equipoExistente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($equipoExistente) {
        // El jugador YA está en otro equipo
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
        // El jugador está disponible
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
    
    // Verificar si las tablas existen
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode([
            'disponible' => false,
            'mensaje' => 'Las tablas de equipos no han sido creadas. Ejecute la migración primero.',
            'equipo_actual' => null,
            'codigo_equipo' => null,
            'jugador' => null,
            'error_tecnico' => 'Tablas equipos/equipo_jugadores no existen'
        ]);
    } else {
        echo json_encode([
            'disponible' => false,
            'mensaje' => 'Error al verificar disponibilidad',
            'equipo_actual' => null,
            'codigo_equipo' => null,
            'jugador' => null
        ]);
    }
} catch (Exception $e) {
    error_log("API verificar_jugador_equipo ERROR: " . $e->getMessage());
    echo json_encode([
        'disponible' => false,
        'mensaje' => 'Error inesperado',
        'equipo_actual' => null,
        'codigo_equipo' => null,
        'jugador' => null
    ]);
}









