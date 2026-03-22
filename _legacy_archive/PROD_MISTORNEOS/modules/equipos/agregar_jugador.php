<?php
/**
 * Agregar Jugador a Equipo Existente
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
    
    // Obtener datos
    $equipo_id = (int)($_POST['equipo_id'] ?? 0);
    $cedula = trim($_POST['cedula'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');
    $posicion = (int)($_POST['posicion'] ?? 0);
    $es_capitan = (int)($_POST['es_capitan'] ?? 0);
    
    // Validaciones básicas
    if ($equipo_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de equipo no válido']);
        exit;
    }
    
    if (empty($cedula) || empty($nombre) || empty($sexo)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
        exit;
    }
    
    if ($posicion < 1 || $posicion > 4) {
        echo json_encode(['success' => false, 'message' => 'Posición no válida']);
        exit;
    }
    
    // Limpiar cédula
    $cedula = preg_replace('/^[VEJP]/i', '', $cedula);
    
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
    
    // Verificar disponibilidad del jugador
    $verificacion = EquiposHelper::verificarDisponibilidadJugador($torneo_id, $cedula, null, $equipo_id);
    
    if (!$verificacion['disponible']) {
        echo json_encode(['success' => false, 'message' => $verificacion['message']]);
        exit;
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // Verificar/crear inscripción
        $stmt = $pdo->prepare("SELECT id FROM inscripciones WHERE cedula = ? AND torneo_id = ?");
        $stmt->execute([$cedula, $torneo_id]);
        $inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $id_inscripcion = null;
        
        if ($inscripcion) {
            $id_inscripcion = $inscripcion['id'];
        } else {
            // Crear inscripción
            $sexoNumerico = strtoupper($sexo) === 'F' ? 2 : 1;
            
            $stmt = $pdo->prepare("
                INSERT INTO inscripciones 
                (cedula, nombre, sexo, club_id, torneo_id, identificador, estatus, categ)
                VALUES (?, ?, ?, ?, ?, 0, 1, 0)
            ");
            $stmt->execute([
                $cedula,
                strtoupper($nombre),
                $sexoNumerico,
                $club_id,
                $torneo_id
            ]);
            
            $id_inscripcion = $pdo->lastInsertId();
        }
        
        // Agregar al equipo
        $resultado = EquiposHelper::agregarJugador(
            $equipo_id,
            $cedula,
            strtoupper($nombre),
            $posicion,
            $es_capitan == 1,
            null,
            $id_inscripcion
        );
        
        if (!$resultado['success']) {
            $pdo->rollBack();
            echo json_encode($resultado);
            exit;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Jugador agregado exitosamente',
            'jugador_id' => $resultado['id']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Error al agregar jugador: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos'
    ]);
} catch (Exception $e) {
    error_log("Error al agregar jugador: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}









