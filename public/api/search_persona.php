<?php
/**
 * Búsqueda de persona por nacionalidad + cédula. Cuatro bloques separados, cada uno con una acción clara.
 * Usado por: Formulario de Invitación e Inscripción en Sitio.
 *
 * Parámetros: cedula, nacionalidad, torneo_id (opcional; si > 0 se ejecuta bloque INSCRITO).
 *
 * BLOQUE 1 - INSCRITO: Buscar en inscritos. Si existe → accion "ya_inscrito": mensaje, front limpia formulario y foco nacionalidad.
 * BLOQUE 2 - USUARIO: Buscar en usuarios. Si existe → accion "encontrado_usuario": persona con id; front rellena y permite inscribir.
 * BLOQUE 3 - PERSONAS: Buscar en base externa. Si existe → accion "encontrado_persona": persona sin id; front rellena y permite inscribir (al enviar se crea usuario).
 * BLOQUE 4 - NUEVO: No encontrado → accion "nuevo": front mantiene nacionalidad y cédula, limpia resto, foco nombre; al enviar se crea usuario e inscribe.
 */
declare(strict_types=1);

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
    echo json_encode([
        'accion' => 'error',
        'status' => 'error',
        'mensaje' => 'Cédula requerida',
        'error' => 'Cédula requerida'
    ]);
    exit;
}

try {
    $pdo = DB::pdo();

    // ─── BLOQUE 1: INSCRITO. Si está inscrito → una sola acción: ya_inscrito (mensaje; front limpia y foco nacionalidad). ───
    if ($torneo_id > 0) {
        error_log("search_persona.php - BLOQUE INSCRITO: Buscando en inscritos (torneo_id=" . $torneo_id . ", nac=" . $nacionalidad . ", cedula=" . $cedula . ")");
        try {
            $stmt = $pdo->prepare("
                SELECT id FROM inscritos
                WHERE torneo_id = ? AND nacionalidad = ? AND cedula = ?
                LIMIT 1
            ");
            $stmt->execute([$torneo_id, $nacionalidad, $cedula]);
            $row = $stmt->fetch();
            if ($row) {
                error_log("search_persona.php - BLOQUE INSCRITO: YA_INSCRITO (id=" . ($row['id'] ?? '') . ")");
                echo json_encode([
                    'accion' => 'ya_inscrito',
                    'status' => 'ya_inscrito',
                    'mensaje' => 'El jugador ya está en este torneo. Puede ingresar otra cédula.',
                    'encontrado' => false
                ]);
                exit;
            }
            error_log("search_persona.php - BLOQUE INSCRITO: no encontrado, continuar a BLOQUE USUARIO");
        } catch (Throwable $e) {
            error_log("search_persona.php - BLOQUE INSCRITO excepcion: " . $e->getMessage());
        }
    } else {
        error_log("search_persona.php - BLOQUE INSCRITO omitido (torneo_id=0), continuar a BLOQUE USUARIO");
    }

    // ─── BLOQUE 2: USUARIO. Si existe en usuarios → una sola acción: encontrado_usuario (persona con id; front rellena y permite inscribir). ───
    error_log("search_persona.php - BLOQUE USUARIO: Buscando en usuarios (cedula variantes)");
    $cedula_variantes = array_unique([$cedula, $nacionalidad . $cedula]);
    foreach ($cedula_variantes as $c) {
        if ($c === '') continue;
        try {
            $stmt = $pdo->prepare("SELECT id, nacionalidad, nombre, sexo, fechnac, celular, email FROM usuarios WHERE cedula = ? LIMIT 1");
            $stmt->execute([$c]);
            $persona = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($persona) {
                error_log("search_persona.php - BLOQUE USUARIO: ENCONTRADO (" . ($persona['nombre'] ?? '') . ")");
                $fechnac = $persona['fechnac'] ?? '';
                if ($fechnac && preg_match('/^\d{4}-\d{2}-\d{2}/', $fechnac) === false && strtotime($fechnac) !== false) {
                    $fechnac = date('Y-m-d', strtotime($fechnac));
                }
                $celular = $persona['celular'] ?? '';
                echo json_encode([
                    'accion' => 'encontrado_usuario',
                    'status' => 'encontrado',
                    'encontrado' => true,
                    'fuente' => 'usuarios',
                    'existe_en_usuarios' => true,
                    'mensaje' => 'Datos encontrados en la plataforma. Revise y pulse Inscribir.',
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
        } catch (Throwable $e) {
            error_log("search_persona.php - BLOQUE USUARIO excepcion: " . $e->getMessage());
        }
    }
    error_log("search_persona.php - BLOQUE USUARIO: no encontrado, continuar a BLOQUE PERSONAS");

    // ─── BLOQUE 3: PERSONAS. Si existe en base externa → una sola acción: encontrado_persona (persona sin id; front rellena; al enviar se crea usuario e inscribe). ───
    if (file_exists(__DIR__ . '/../../config/persona_database.php')) {
        error_log("search_persona.php - BLOQUE PERSONAS: Buscando en base externa");
        require_once __DIR__ . '/../../config/persona_database.php';
        try {
            $database = new PersonaDatabase();
            $result = $database->searchPersonaById($nacionalidad, $cedula);

            if (isset($result['encontrado']) && $result['encontrado'] && isset($result['persona'])) {
                error_log("search_persona.php - BLOQUE PERSONAS: ENCONTRADO en base externa");
                $p = $result['persona'];
                $cel = $p['celular'] ?? $p['telefono'] ?? '';
                $fechnac = $p['fechnac'] ?? '';
                if ($fechnac && preg_match('/^\d{4}-\d{2}-\d{2}/', $fechnac) === false && strtotime($fechnac) !== false) {
                    $fechnac = date('Y-m-d', strtotime($fechnac));
                }
                echo json_encode([
                    'accion' => 'encontrado_persona',
                    'status' => 'encontrado',
                    'encontrado' => true,
                    'fuente' => 'externa',
                    'existe_en_usuarios' => false,
                    'mensaje' => 'Datos encontrados en base externa. Revise y pulse Inscribir (se creará usuario al inscribir).',
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
                error_log("search_persona.php - BLOQUE PERSONAS: ENCONTRADO (formato data)");
                $d = $result['data'];
                $cel = $d['telefono'] ?? $d['celular'] ?? '';
                $fechnac = $d['fechnac'] ?? '';
                if ($fechnac && preg_match('/^\d{4}-\d{2}-\d{2}/', $fechnac) === false && strtotime($fechnac) !== false) {
                    $fechnac = date('Y-m-d', strtotime($fechnac));
                }
                echo json_encode([
                    'accion' => 'encontrado_persona',
                    'status' => 'encontrado',
                    'encontrado' => true,
                    'fuente' => 'externa',
                    'existe_en_usuarios' => false,
                    'mensaje' => 'Datos encontrados en base externa. Revise y pulse Inscribir (se creará usuario al inscribir).',
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
            error_log("search_persona.php - BLOQUE PERSONAS: no encontrado");
        } catch (Throwable $e) {
            error_log("search_persona.php - BLOQUE PERSONAS excepcion: " . $e->getMessage());
        }
    } else {
        error_log("search_persona.php - BLOQUE PERSONAS omitido (sin config), continuar a BLOQUE NUEVO");
    }

    // ─── BLOQUE 4: NUEVO. No encontrado en ninguno → una sola acción: nuevo (front mantiene nacionalidad y cédula, limpia resto, foco nombre; al enviar se crea usuario e inscribe). ───
    error_log("search_persona.php - BLOQUE NUEVO: no encontrado en inscritos, usuarios ni personas");
    echo json_encode([
        'accion' => 'nuevo',
        'status' => 'no_encontrado',
        'encontrado' => false,
        'mensaje' => 'No encontrado. Complete nombre y el resto de datos; al pulsar Inscribir se creará el usuario y se inscribirá en el torneo.'
    ]);

} catch (Throwable $e) {
    error_log("search_persona.php - Error general: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'accion' => 'error',
        'status' => 'error',
        'encontrado' => false,
        'mensaje' => 'Error interno del servidor.',
        'error' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
