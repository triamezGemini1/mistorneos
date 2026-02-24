<?php
/**
 * Endpoint para buscar persona por nacionalidad + cédula.
 * Usado por el formulario de inscripción por invitación.
 *
 * Orden de búsqueda (el cliente debe validar inscritos antes de llamar a este endpoint):
 * 1. [Cliente] Validar en `inscritos` (check_cedula.php) → si existe, abortar con "Jugador ya registrado".
 * 2. Buscar en tabla `usuarios` → si existe, retornar: sexo, fecha_nacimiento, telefono, email, nacionalidad.
 * 3. Si no en usuarios: consulta externa (API/Legacy) como último recurso.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// Log de parámetros recibidos
error_log("search_persona.php - GET params: " . print_r($_GET, true));

// Verificar que se haya enviado nacionalidad y cédula
if (!isset($_GET['cedula']) || empty($_GET['cedula'])) {
    http_response_code(400);
    echo json_encode(['encontrado' => false, 'error' => 'Cédula requerida']);
    exit;
}

$cedula = trim($_GET['cedula']);
$nacionalidad = isset($_GET['nacionalidad']) ? trim($_GET['nacionalidad']) : 'V';

// Validar nacionalidad
if (!in_array($nacionalidad, ['V', 'E', 'J', 'P'])) {
    $nacionalidad = 'V';
}

error_log("search_persona.php - Buscando: Nacionalidad=$nacionalidad, Cédula=$cedula");

try {
    // 1. PRIMERO buscar en usuarios (prioridad para inscripción en línea)
    error_log("search_persona.php - Paso 1: Buscando en tabla usuarios");
    $cedula_variantes = [preg_replace('/^[VEJP]/i', '', $cedula), $cedula];
    if (!empty($nacionalidad) && !preg_match('/^[VEJP]/i', $cedula)) {
        $cedula_variantes[] = strtoupper(substr($nacionalidad, 0, 1)) . preg_replace('/^[VEJP]/i', '', $cedula);
    }
    foreach (array_unique($cedula_variantes) as $c) {
        if (empty($c)) continue;
        try {
            $stmt = DB::pdo()->prepare("SELECT nacionalidad, nombre, sexo, fechnac, celular, email FROM usuarios WHERE cedula = ? LIMIT 1");
            $stmt->execute([$c]);
            $persona = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($persona) {
                error_log("search_persona.php - Encontrado en usuarios: " . $persona['nombre']);
                $fechnac = $persona['fechnac'] ?? '';
                if ($fechnac && preg_match('/^\d{4}-\d{2}-\d{2}/', $fechnac) === false && (strtotime($fechnac) !== false)) {
                    $fechnac = date('Y-m-d', strtotime($fechnac));
                }
                $celular = $persona['celular'] ?? '';
                echo json_encode([
                    'encontrado' => true,
                    'fuente' => 'usuarios',
                    'usuario_registrado' => true,
                    'persona' => [
                        'nacionalidad' => $persona['nacionalidad'] ?? 'V',
                        'nombre' => $persona['nombre'] ?? '',
                        'sexo' => $persona['sexo'] ?? '',
                        'fechnac' => $fechnac,
                        'celular' => $celular,
                        'telefono' => $celular,
                        'email' => $persona['email'] ?? ''
                    ]
                ]);
                exit;
            }
        } catch (Exception $e) {
            error_log("search_persona.php - Error buscando en usuarios: " . $e->getMessage());
        }
    }

    // 2. Buscar en base de datos externa (BD personas, tabla dbo_persona - IDUsuario sin prefijo V/E)
    error_log("search_persona.php - Paso 2: Buscando en base de datos externa");
    $cedula_externa = preg_replace('/^[VEJP]/i', '', $cedula);
    $cedula_externa = $cedula_externa ?: $cedula;
    
    if (file_exists(__DIR__ . '/../../config/persona_database.php')) {
        require_once __DIR__ . '/../../config/persona_database.php';
        
        try {
            $database = new PersonaDatabase();
            $result = $database->searchPersonaById($nacionalidad, $cedula_externa);
            
            error_log("search_persona.php - Resultado base externa: " . print_r($result, true));
            
            // Verificar diferentes formatos de respuesta
            if (isset($result['encontrado']) && $result['encontrado'] && isset($result['persona'])) {
                $cel = $result['persona']['celular'] ?? $result['persona']['telefono'] ?? '';
                $fechnac = $result['persona']['fechnac'] ?? '';
                if ($fechnac && preg_match('/^\d{4}-\d{2}-\d{2}/', $fechnac) === false && strtotime($fechnac) !== false) {
                    $fechnac = date('Y-m-d', strtotime($fechnac));
                }
                echo json_encode([
                    'encontrado' => true,
                    'fuente' => 'externa',
                    'persona' => [
                        'nacionalidad' => $result['persona']['nacionalidad'] ?? $nacionalidad,
                        'nombre' => $result['persona']['nombre'] ?? '',
                        'sexo' => $result['persona']['sexo'] ?? '',
                        'fechnac' => $fechnac,
                        'celular' => $cel,
                        'telefono' => $cel,
                        'email' => $result['persona']['email'] ?? ''
                    ]
                ]);
                exit;
            } elseif (isset($result['success']) && $result['success'] && isset($result['data'])) {
                $cel = $result['data']['telefono'] ?? $result['data']['celular'] ?? '';
                $fechnac = $result['data']['fechnac'] ?? '';
                if ($fechnac && preg_match('/^\d{4}-\d{2}-\d{2}/', $fechnac) === false && strtotime($fechnac) !== false) {
                    $fechnac = date('Y-m-d', strtotime($fechnac));
                }
                echo json_encode([
                    'encontrado' => true,
                    'fuente' => 'externa',
                    'persona' => [
                        'nacionalidad' => $result['data']['nacionalidad'] ?? $nacionalidad,
                        'nombre' => $result['data']['nombre'] ?? '',
                        'sexo' => $result['data']['sexo'] ?? '',
                        'fechnac' => $fechnac,
                        'celular' => $cel,
                        'telefono' => $cel,
                        'email' => $result['data']['email'] ?? ''
                    ]
                ]);
                exit;
            }
        } catch (Exception $e) {
            error_log("search_persona.php - Error en búsqueda externa: " . $e->getMessage());
        }
    } else {
        error_log("search_persona.php - No existe persona_database.php");
    }

    // 4. No se encontró en ninguna parte
    error_log("search_persona.php - No encontrado");
    echo json_encode([
        'encontrado' => false,
        'mensaje' => 'Persona no encontrada con ' . $nacionalidad . $cedula
    ]);
    
} catch (Exception $e) {
    error_log("search_persona.php - Error general: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'encontrado' => false,
        'error' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
