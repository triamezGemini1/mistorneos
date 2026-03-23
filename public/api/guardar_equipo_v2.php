<?php
/**
 * API: Guardar/Crear equipo (v2 — mismo nombre de archivo nuevo evita OPcache obsoleto en guardar_equipo.php)
 */
ob_start();
require_once __DIR__ . '/../../config/session_start_early.php';

function sendJsonError($message, $errorType = 'ERROR', $details = []) {
    ob_clean();
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    error_log("API Error [$errorType]: $message");
    if (!empty($details)) {
        error_log("Detalles: " . json_encode($details));
    }
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error_type' => $errorType,
        'details' => $details
    ]);
    exit;
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    sendJsonError("Error PHP: $errstr", 'PHP_ERROR', [
        'file' => basename($errfile),
        'line' => $errline,
        'severity' => $errno
    ]);
}, E_ALL & ~E_DEPRECATED & ~E_STRICT);

set_exception_handler(function($exception) {
    sendJsonError("Excepción no capturada: " . $exception->getMessage(), 'EXCEPTION', [
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine(),
        'class' => get_class($exception)
    ]);
});

try {
    $requiredFiles = [
        'bootstrap' => __DIR__ . '/../../config/bootstrap.php',
        'db' => __DIR__ . '/../../config/db_config.php',
        'auth' => __DIR__ . '/../../config/auth.php',
        'csrf' => __DIR__ . '/../../config/csrf.php',
        'EquiposHelper' => __DIR__ . '/../../lib/EquiposHelper.php'
    ];
    foreach ($requiredFiles as $name => $file) {
        if (!file_exists($file)) {
            sendJsonError("Archivo requerido no encontrado: $name", 'FILE_NOT_FOUND', ['file' => $file]);
        }
        require_once $file;
    }
} catch (Throwable $e) {
    sendJsonError("Error al cargar archivos requeridos: " . $e->getMessage(), 'REQUIRE_ERROR', [
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

if (ob_get_level() > 0) {
    ob_clean();
} else {
    ob_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

error_log("=== INICIO GUARDAR EQUIPO V2 (opcache-safe) ===");
error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
error_log("Content-Type recibido: " . $contentType);

$input = $_POST;
if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
}
error_log("POST/input recibido: " . json_encode($input, JSON_UNESCAPED_UNICODE));

try {
    if (class_exists('CSRF')) {
        $tokenRecibido = $input['csrf_token'] ?? '';
        $tokenSesion = $_SESSION['csrf_token'] ?? '';
        if (!$tokenRecibido || !$tokenSesion || !hash_equals($tokenSesion, $tokenRecibido)) {
            error_log("CSRF inválido o sesión sin token - token_recibido=" . (strlen($tokenRecibido) ? 'presente' : 'vacio') . ", token_sesion=" . (strlen($tokenSesion) ? 'presente' : 'vacio'));
            if (empty($tokenSesion)) {
                CSRF::token();
            }
            echo json_encode([
                'success' => false,
                'message' => 'El token de seguridad ha expirado o no coincide. Recarga la página (F5) y vuelve a intentar guardar el equipo.',
                'error_type' => 'CSRF_INVALID'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        error_log("CSRF validado correctamente");
    }

    $torneo_id = (int)($input['torneo_id'] ?? 0);
    $equipo_id = (int)($input['equipo_id'] ?? 0);
    $nombre_equipo = trim($input['nombre_equipo'] ?? '');
    $club_id = (int)($input['club_id'] ?? 0);
    $jugadores = $input['jugadores'] ?? [];
    if (is_string($jugadores)) {
        $jugadores = json_decode($jugadores, true);
        if (!is_array($jugadores)) {
            $jugadores = [];
        }
    }

    error_log("PASO 1: Datos extraídos - torneo_id=$torneo_id, equipo_id=$equipo_id, nombre_equipo=$nombre_equipo, club_id=$club_id");
    error_log("PASO 1: Jugadores recibidos: " . count($jugadores) . " jugadores");

    if ($torneo_id <= 0 || empty($nombre_equipo) || $club_id <= 0) {
        error_log("ERROR: Datos incompletos - torneo_id=$torneo_id, nombre_equipo='$nombre_equipo', club_id=$club_id");
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $user = Auth::user();
    $creado_por = Auth::id() ?: null;
    error_log("PASO 2: Usuario autenticado - user_id=$creado_por");

    $pdo = DB::pdo();
    $pdo->beginTransaction();
    error_log("PASO 3: Transacción iniciada");

    try {
        error_log("PASO 4: Verificando si es equipo nuevo o existente (equipo_id=$equipo_id)");
        if ($equipo_id > 0) {
            error_log("PASO 4A: Actualizando equipo existente id=$equipo_id");
            $stmt = $pdo->prepare("
                UPDATE equipos 
                SET nombre_equipo = UPPER(?), id_club = ?
                WHERE id = ? AND id_torneo = ?
            ");
            $stmt->execute([$nombre_equipo, $club_id, $equipo_id, $torneo_id]);
            error_log("PASO 4A: Equipo actualizado, filas afectadas: " . $stmt->rowCount());

            $stmt = $pdo->prepare("SELECT codigo_equipo FROM equipos WHERE id = ?");
            $stmt->execute([$equipo_id]);
            $codigo_equipo = $stmt->fetchColumn() ?: null;
            error_log("PASO 4A: Código de equipo obtenido: " . ($codigo_equipo ?? 'NULL'));

            if (empty($codigo_equipo)) {
                error_log("ERROR: No se encontró el código del equipo existente");
                throw new Exception("No se encontró el código del equipo existente");
            }

            $stmt = $pdo->prepare("UPDATE inscritos SET codigo_equipo = '' WHERE torneo_id = ? AND codigo_equipo = ?");
            $stmt->execute([$torneo_id, $codigo_equipo]);
            error_log("PASO 4A: Jugadores anteriores limpiados, filas afectadas: " . $stmt->rowCount());
        } else {
            error_log("PASO 4B: Creando nuevo equipo");
            $result = EquiposHelper::crearEquipo($torneo_id, $club_id, $nombre_equipo, $creado_por);
            error_log("PASO 4B: Resultado de crearEquipo: " . json_encode($result, JSON_UNESCAPED_UNICODE));

            if (!$result['success']) {
                error_log("ERROR: Falló creación de equipo - " . $result['message']);
                throw new Exception($result['message']);
            }

            $equipo_id = $result['id'];
            $codigo_equipo = $result['codigo'] ?? null;
            error_log("PASO 4B: Equipo creado - equipo_id=$equipo_id, codigo_equipo=" . ($codigo_equipo ?? 'NULL'));

            if (empty($codigo_equipo)) {
                error_log("ERROR: No se pudo generar el código del equipo");
                throw new Exception("No se pudo generar el código del equipo");
            }
        }

        error_log("PASO 5: Iniciando procesamiento de jugadores (total: " . count($jugadores) . ")");
        $jugador_numero = 0;
        foreach ($jugadores as $jugador_data) {
            $jugador_numero++;
            error_log("PASO 5.$jugador_numero: Procesando jugador - " . json_encode($jugador_data, JSON_UNESCAPED_UNICODE));

            if (empty($jugador_data['cedula']) || empty($jugador_data['nombre'])) {
                error_log("PASO 5.$jugador_numero: Jugador saltado - falta cédula o nombre");
                continue;
            }

            $cedula = trim($jugador_data['cedula']);
            $nombre = trim($jugador_data['nombre']);
            $id_usuario = (int)($jugador_data['id_usuario'] ?? 0);
            $id_inscrito = (int)($jugador_data['id_inscrito'] ?? 0);

            if ($id_usuario <= 0) {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ? LIMIT 1");
                $stmt->execute([$cedula]);
                $rowUser = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($rowUser && isset($rowUser['id'])) {
                    $id_usuario = (int)$rowUser['id'];
                }
            }

            if ($id_usuario <= 0) {
                throw new Exception("No se pudo determinar el ID de usuario para la cédula $cedula");
            }

            if ($id_inscrito > 0) {
                $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE id = ? AND id_usuario = ? AND torneo_id = ? LIMIT 1");
                $stmt->execute([$id_inscrito, $id_usuario, $torneo_id]);
                if (!$stmt->fetch()) {
                    $id_inscrito = 0;
                }
            }

            if ($id_inscrito <= 0) {
                $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE id_usuario = ? AND torneo_id = ? LIMIT 1");
                $stmt->execute([$id_usuario, $torneo_id]);
                $rowInscrito = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($rowInscrito && isset($rowInscrito['id'])) {
                    $id_inscrito = (int)$rowInscrito['id'];
                } else {
                    require_once __DIR__ . '/../../lib/InscritosHelper.php';
                    require_once __DIR__ . '/../../lib/UserActivationHelper.php';
                    $id_inscrito = InscritosHelper::insertarInscrito($pdo, [
                        'id_usuario' => $id_usuario,
                        'torneo_id' => $torneo_id,
                        'id_club' => $club_id,
                        'codigo_equipo' => $codigo_equipo,
                        'estatus' => 1,
                        'inscrito_por' => $creado_por,
                        'numero' => 0
                    ]);
                    UserActivationHelper::activateUser($pdo, $id_usuario);
                }
            }

            $stmt = $pdo->prepare("
                UPDATE inscritos 
                SET id_club = ?, codigo_equipo = ?, estatus = 1
                WHERE id = ?
            ");
            $stmt->execute([$club_id, $codigo_equipo, $id_inscrito]);
        }
        error_log("PASO 5: Todos los jugadores procesados correctamente");

        $pdo->commit();
        error_log("=== ÉXITO: Equipo guardado correctamente (V2) ===");
        echo json_encode([
            'success' => true,
            'message' => $equipo_id > 0 ? 'Equipo actualizado exitosamente' : 'Equipo creado exitosamente',
            'equipo_id' => $equipo_id
        ]);
    } catch (Throwable $e) {
        error_log("ERROR en transacción: " . $e->getMessage());
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
} catch (Throwable $e) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    error_log("=== ERROR FINAL V2: " . $e->getMessage() . " ===");
    $errorMessage = $e->getMessage();
    if ($e instanceof PDOException && isset($e->errorInfo)) {
        $errorMessage .= " (SQL: " . ($e->errorInfo[1] ?? 'N/A') . ")";
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el equipo: ' . $errorMessage,
        'error_type' => get_class($e),
        'error_file' => basename($e->getFile()),
        'error_line' => $e->getLine()
    ]);
    exit;
}
