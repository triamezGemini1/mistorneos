<?php
/**
 * Guardar Equipo con sus 4 Jugadores
 * Procesa el formulario de creación de equipo
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
    $invitacion_id = $_SESSION['invitacion_id'];
    
    // Obtener datos del formulario
    $nombre_equipo = trim($_POST['nombre_equipo'] ?? '');
    
    // Validar nombre del equipo
    if (empty($nombre_equipo) || strlen($nombre_equipo) < 3) {
        echo json_encode([
            'success' => false,
            'message' => 'El nombre del equipo debe tener al menos 3 caracteres'
        ]);
        exit;
    }
    
    // Recopilar datos de los 4 jugadores
    $jugadores = [];
    $cedulas_usadas = [];
    
    for ($i = 1; $i <= 4; $i++) {
        $cedula = trim($_POST["cedula_$i"] ?? '');
        $nombre = trim($_POST["nombre_$i"] ?? '');
        $sexo = trim($_POST["sexo_$i"] ?? '');
        $es_capitan = (int)($_POST["es_capitan_$i"] ?? 0);
        
        // Limpiar cédula
        $cedula = preg_replace('/^[VEJP]/i', '', $cedula);
        
        // Validar datos requeridos
        if (empty($cedula)) {
            echo json_encode([
                'success' => false,
                'message' => "Debe ingresar la cédula del jugador $i"
            ]);
            exit;
        }
        
        if (empty($nombre)) {
            echo json_encode([
                'success' => false,
                'message' => "Debe ingresar el nombre del jugador $i"
            ]);
            exit;
        }
        
        if (empty($sexo)) {
            echo json_encode([
                'success' => false,
                'message' => "Debe seleccionar el sexo del jugador $i"
            ]);
            exit;
        }
        
        // Verificar cédulas duplicadas en el formulario
        if (in_array($cedula, $cedulas_usadas)) {
            echo json_encode([
                'success' => false,
                'message' => "La cédula $cedula está duplicada en el formulario"
            ]);
            exit;
        }
        $cedulas_usadas[] = $cedula;
        
        // Verificar que el jugador no esté en otro equipo (última validación)
        $verificacion = EquiposHelper::verificarDisponibilidadJugador($torneo_id, $cedula);
        
        if (!$verificacion['disponible']) {
            echo json_encode([
                'success' => false,
                'message' => "Jugador $i ($cedula): " . $verificacion['message']
            ]);
            exit;
        }
        
        $jugadores[] = [
            'cedula' => $cedula,
            'nombre' => strtoupper($nombre),
            'sexo' => strtoupper($sexo),
            'es_capitan' => $es_capitan == 1
        ];
    }
    
    // Verificar que tengamos 4 jugadores
    if (count($jugadores) !== 4) {
        echo json_encode([
            'success' => false,
            'message' => 'Se requieren exactamente 4 jugadores'
        ]);
        exit;
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // 1. Crear el equipo
        $resultado = EquiposHelper::crearEquipo($torneo_id, $club_id, $nombre_equipo, $invitacion_id);
        
        if (!$resultado['success']) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => $resultado['message']
            ]);
            exit;
        }
        
        $equipo_id = $resultado['id'];
        $codigo_equipo = $resultado['codigo'];
        
        // 2. Inscribir cada jugador en la tabla inscripciones (si no existe)
        // y luego agregarlo al equipo
        $jugadores_agregados = 0;
        $errores = [];
        
        foreach ($jugadores as $index => $jugador) {
            $posicion = $index + 1;
            
            // Verificar si el jugador ya existe en inscripciones para este torneo
            $stmt = $pdo->prepare("
                SELECT id FROM inscripciones 
                WHERE cedula = ? AND torneo_id = ?
            ");
            $stmt->execute([$jugador['cedula'], $torneo_id]);
            $inscripcionExistente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $id_inscripcion = null;
            
            if ($inscripcionExistente) {
                $id_inscripcion = $inscripcionExistente['id'];
            } else {
                // Insertar en inscripciones
                $sexoNumerico = strtoupper($jugador['sexo']) === 'F' ? 2 : 1;
                
                $stmt = $pdo->prepare("
                    INSERT INTO inscripciones 
                    (cedula, nombre, sexo, club_id, torneo_id, identificador, estatus, categ)
                    VALUES (?, ?, ?, ?, ?, 0, 1, 0)
                ");
                $stmt->execute([
                    $jugador['cedula'],
                    $jugador['nombre'],
                    $sexoNumerico,
                    $club_id,
                    $torneo_id
                ]);
                
                $id_inscripcion = $pdo->lastInsertId();
            }
            
            // Agregar al equipo
            $resultadoJugador = EquiposHelper::agregarJugador(
                $equipo_id,
                $jugador['cedula'],
                $jugador['nombre'],
                $posicion,
                $jugador['es_capitan'],
                null, // id_inscrito (tabla inscritos)
                $id_inscripcion // id_inscripcion (tabla inscripciones)
            );
            
            if ($resultadoJugador['success']) {
                $jugadores_agregados++;
            } else {
                $errores[] = "Jugador $posicion: " . $resultadoJugador['message'];
            }
        }
        
        // Verificar que se agregaron todos los jugadores
        if ($jugadores_agregados < 4) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Solo se pudieron agregar ' . $jugadores_agregados . ' de 4 jugadores. ' . implode('; ', $errores)
            ]);
            exit;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Equipo '$nombre_equipo' creado exitosamente con código $codigo_equipo",
            'equipo_id' => $equipo_id,
            'codigo_equipo' => $codigo_equipo,
            'jugadores_agregados' => $jugadores_agregados
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Error al guardar equipo: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error al guardar equipo: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}









