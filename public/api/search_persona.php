<?php
/**
 * Endpoint para buscar persona por nacionalidad + cédula
 * Para inscripción en línea: busca PRIMERO en usuarios, luego BD externa, luego tablas locales
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
            $stmt = DB::pdo()->prepare("SELECT nombre, sexo, fechnac, celular, email FROM usuarios WHERE cedula = ? LIMIT 1");
            $stmt->execute([$c]);
            $persona = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($persona) {
                error_log("search_persona.php - Encontrado en usuarios: " . $persona['nombre']);
                echo json_encode([
                    'encontrado' => true,
                    'fuente' => 'usuarios',
                    'usuario_registrado' => true,
                    'persona' => [
                        'nombre' => $persona['nombre'] ?? '',
                        'sexo' => $persona['sexo'] ?? '',
                        'fechnac' => $persona['fechnac'] ?? '',
                        'celular' => $persona['celular'] ?? '',
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
                // Formato: ['encontrado' => true, 'persona' => [...]]
                error_log("search_persona.php - Encontrado en base externa (formato 1): " . ($result['persona']['nombre'] ?? 'N/A'));
                echo json_encode([
                    'encontrado' => true,
                    'fuente' => 'externa',
                    'persona' => [
                        'nombre' => $result['persona']['nombre'] ?? '',
                        'sexo' => $result['persona']['sexo'] ?? '',
                        'fechnac' => $result['persona']['fechnac'] ?? '',
                        'celular' => $result['persona']['celular'] ?? $result['persona']['telefono'] ?? ''
                    ]
                ]);
                exit;
            } elseif (isset($result['success']) && $result['success'] && isset($result['data'])) {
                // Formato: ['success' => true, 'data' => [...]]
                error_log("search_persona.php - Encontrado en base externa (formato 2): " . ($result['data']['nombre'] ?? 'N/A'));
                echo json_encode([
                    'encontrado' => true,
                    'fuente' => 'externa',
                    'persona' => [
                        'nombre' => $result['data']['nombre'] ?? '',
                        'sexo' => $result['data']['sexo'] ?? '',
                        'fechnac' => $result['data']['fechnac'] ?? '',
                        'celular' => $result['data']['telefono'] ?? $result['data']['celular'] ?? ''
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
    
    // 3. Buscar en tabla inscripciones (usuarios ya buscado en paso 1)
    error_log("search_persona.php - Paso 3: Buscando en inscripciones");
    try {
        $stmt = DB::pdo()->prepare("
            SELECT nombre, sexo, fechnac, celular 
            FROM inscripciones 
            WHERE cedula = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$cedula]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($persona) {
            error_log("search_persona.php - Encontrado en inscripciones: " . $persona['nombre']);
            echo json_encode([
                'encontrado' => true,
                'fuente' => 'inscripciones',
                'persona' => [
                    'nombre' => $persona['nombre'] ?? '',
                    'sexo' => $persona['sexo'] ?? '',
                    'fechnac' => $persona['fechnac'] ?? '',
                    'celular' => $persona['celular'] ?? ''
                ]
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("search_persona.php - Error buscando en inscripciones: " . $e->getMessage());
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
