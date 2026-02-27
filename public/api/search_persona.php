<?php
/**
 * Búsqueda de persona por nacionalidad + cédula. Jerarquía estricta de descarte.
 * Usado por: Formulario de Invitación e Inscripción en Sitio (mismo backend).
 *
 * Parámetros obligatorios desde frontend: cedula, nacionalidad.
 * Parámetro crítico para NIVEL 1: torneo_id (GET o POST). Si no se envía, no se valida "ya inscrito".
 *
 * NIVEL 1 (prioridad): inscritos → si existe: { "status": "ya_inscrito", "mensaje": "..." }. STOP.
 * NIVEL 2: usuarios → si existe: datos + existe_en_usuarios: true.
 * NIVEL 3: base externa → si existe: datos + fuente: "externa".
 * NIVEL 4: no encontrado → { "status": "no_encontrado" } (registro manual).
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$input = array_merge($_GET, $_POST);
$cedula_raw = isset($input['cedula']) ? trim((string) $input['cedula']) : '';
$cedula = preg_replace('/^[VEJP]/i', '', $cedula_raw);
$cedula = preg_replace('/\D/', '', $cedula);
$nacionalidad = isset($input['nacionalidad']) ? strtoupper(trim((string) $input['nacionalidad'])) : 'V';
if (!in_array($nacionalidad, ['V', 'E', 'J', 'P'], true)) {
    $nacionalidad = 'V';
}
$torneo_id = isset($input['torneo_id']) ? (int) $input['torneo_id'] : (isset($input['torneo']) ? (int) $input['torneo'] : 0);

error_log("search_persona.php - ENTRADA: nacionalidad=" . $nacionalidad . ", cedula=" . $cedula . ", torneo_id=" . $torneo_id);

if ($cedula === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'encontrado' => false, 'error' => 'Cédula requerida']);
    exit;
}

try {
    $pdo = DB::pdo();

    // ─── PASO 1: Buscar en tabla inscritos (solo si torneo_id > 0). Si existe → ya_inscrito, STOP. ───
    if ($torneo_id > 0) {
        error_log("search_persona.php - PASO 1: Buscando en INSCRITOS (torneo_id=" . $torneo_id . ", nac=" . $nacionalidad . ", cedula=" . $cedula . ")");
        try {
            $stmtInscrito = $pdo->prepare("
                SELECT id FROM inscritos
                WHERE torneo_id = ? AND nacionalidad = ? AND cedula = ?
                LIMIT 1
            ");
            $stmtInscrito->execute([$torneo_id, $nacionalidad, $cedula]);
            $rowInscrito = $stmtInscrito->fetch();
            if ($rowInscrito) {
                error_log("search_persona.php - PASO 1 resultado: YA_INSCRITO (id=" . ($rowInscrito['id'] ?? '') . ")");
                echo json_encode([
                    'status' => 'ya_inscrito',
                    'mensaje' => 'El jugador ya está inscrito en este torneo',
                    'encontrado' => false
                ]);
                exit;
            }
            error_log("search_persona.php - PASO 1 resultado: no encontrado en inscritos, continuar a PASO 2");
        } catch (Throwable $e) {
            error_log("search_persona.php - PASO 1 excepcion: " . $e->getMessage());
            if (strpos($e->getMessage(), 'nacionalidad') !== false || strpos($e->getMessage(), 'cedula') !== false) {
                error_log("search_persona.php - Tabla inscritos sin columnas nacionalidad/cedula. Continuar a PASO 2.");
            }
        }
    } else {
        error_log("search_persona.php - PASO 1 OMITIDO: torneo_id=0 (no se envio desde frontend). Continuar a PASO 2.");
    }

    // ─── PASO 2: Buscar en tabla usuarios. Si existe → retornar datos + existe_en_usuarios: true. ───
    error_log("search_persona.php - PASO 2: Buscando en USUARIOS (cedula variantes)");
    $cedula_variantes = array_unique([$cedula, $nacionalidad . $cedula]);
    foreach ($cedula_variantes as $c) {
        if ($c === '') continue;
        try {
            $stmt = $pdo->prepare("SELECT id, nacionalidad, nombre, sexo, fechnac, celular, email FROM usuarios WHERE cedula = ? LIMIT 1");
            $stmt->execute([$c]);
            $persona = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($persona) {
                error_log("search_persona.php - PASO 2 resultado: ENCONTRADO en usuarios (" . ($persona['nombre'] ?? '') . ")");
                $fechnac = $persona['fechnac'] ?? '';
                if ($fechnac && preg_match('/^\d{4}-\d{2}-\d{2}/', $fechnac) === false && strtotime($fechnac) !== false) {
                    $fechnac = date('Y-m-d', strtotime($fechnac));
                }
                $celular = $persona['celular'] ?? '';
                echo json_encode([
                    'status' => 'encontrado',
                    'encontrado' => true,
                    'existe_en_usuarios' => true,
                    'fuente' => 'usuarios',
                    'usuario_registrado' => true,
                    'persona' => [
                        'id' => (int) ($persona['id'] ?? 0),
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
            error_log("search_persona.php - PASO 2 excepcion: " . $e->getMessage());
        }
    }
    error_log("search_persona.php - PASO 2 resultado: no encontrado en usuarios, continuar a PASO 3");

    // ─── PASO 3: Buscar en base de datos externa (personas). Si existe → datos + fuente: "externa". ───
    if (file_exists(__DIR__ . '/../../config/persona_database.php')) {
        error_log("search_persona.php - PASO 3: Buscando en BASE PERSONAS (externa)");
        require_once __DIR__ . '/../../config/persona_database.php';
        try {
            $database = new PersonaDatabase();
            $result = $database->searchPersonaById($nacionalidad, $cedula);

            if (isset($result['encontrado']) && $result['encontrado'] && isset($result['persona'])) {
                error_log("search_persona.php - PASO 3 resultado: ENCONTRADO en base externa");
                $p = $result['persona'];
                $cel = $p['celular'] ?? $p['telefono'] ?? '';
                $fechnac = $p['fechnac'] ?? '';
                if ($fechnac && preg_match('/^\d{4}-\d{2}-\d{2}/', $fechnac) === false && strtotime($fechnac) !== false) {
                    $fechnac = date('Y-m-d', strtotime($fechnac));
                }
                echo json_encode([
                    'status' => 'encontrado',
                    'encontrado' => true,
                    'existe_en_usuarios' => false,
                    'fuente' => 'externa',
                    'persona' => [
                        'nacionalidad' => $p['nacionalidad'] ?? $nacionalidad,
                        'nombre' => $p['nombre'] ?? '',
                        'sexo' => $p['sexo'] ?? '',
                        'fechnac' => $fechnac,
                        'celular' => $cel,
                        'telefono' => $cel,
                        'email' => $p['email'] ?? ''
                    ]
                ]);
                exit;
            }
            if (isset($result['success']) && $result['success'] && isset($result['data'])) {
                error_log("search_persona.php - PASO 3 resultado: ENCONTRADO en base externa (formato data)");
                $d = $result['data'];
                $cel = $d['telefono'] ?? $d['celular'] ?? '';
                $fechnac = $d['fechnac'] ?? '';
                if ($fechnac && preg_match('/^\d{4}-\d{2}-\d{2}/', $fechnac) === false && strtotime($fechnac) !== false) {
                    $fechnac = date('Y-m-d', strtotime($fechnac));
                }
                echo json_encode([
                    'status' => 'encontrado',
                    'encontrado' => true,
                    'existe_en_usuarios' => false,
                    'fuente' => 'externa',
                    'persona' => [
                        'nacionalidad' => $d['nacionalidad'] ?? $nacionalidad,
                        'nombre' => $d['nombre'] ?? '',
                        'sexo' => $d['sexo'] ?? '',
                        'fechnac' => $fechnac,
                        'celular' => $cel,
                        'telefono' => $cel,
                        'email' => $d['email'] ?? ''
                    ]
                ]);
                exit;
            }
            error_log("search_persona.php - PASO 3 resultado: no encontrado en base externa");
        } catch (Exception $e) {
            error_log("search_persona.php - PASO 3 excepcion: " . $e->getMessage());
        }
    } else {
        error_log("search_persona.php - PASO 3 OMITIDO: no existe config persona_database.php");
    }

    // ─── PASO 4: No encontrado en ninguno → registro manual. ───
    error_log("search_persona.php - PASO 4: NO_ENCONTRADO, devolver registro manual");
    echo json_encode([
        'status' => 'no_encontrado',
        'encontrado' => false,
        'mensaje' => 'Persona no encontrada. Complete los datos manualmente.'
    ]);

} catch (Exception $e) {
    error_log("search_persona.php - Error general: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'encontrado' => false,
        'error' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
