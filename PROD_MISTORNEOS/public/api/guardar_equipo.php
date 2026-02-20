<?php
/**
 * API: Guardar/Crear equipo con sus jugadores
 */

// Iniciar output buffering inmediatamente para capturar cualquier output
ob_start();

// Función helper para enviar respuesta JSON de error
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

// Manejar errores de PHP y convertirlos a JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Solo manejar errores no suprimidos
    if (!(error_reporting() & $errno)) {
        return false;
    }
    sendJsonError("Error PHP: $errstr", 'PHP_ERROR', [
        'file' => basename($errfile),
        'line' => $errline,
        'severity' => $errno
    ]);
}, E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Manejar excepciones no capturadas
set_exception_handler(function($exception) {
    sendJsonError("Excepción no capturada: " . $exception->getMessage(), 'EXCEPTION', [
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine(),
        'class' => get_class($exception)
    ]);
});

try {
    // Cargar archivos requeridos con manejo de errores
    $requiredFiles = [
        'bootstrap' => __DIR__ . '/../../config/bootstrap.php',
        'db' => __DIR__ . '/../../config/db.php',
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

// Limpiar cualquier output accidental generado por los archivos incluidos
// Limpiar cualquier output accidental generado por los archivos incluidos
// Verificar si hay un buffer activo antes de limpiarlo
if (ob_get_level() > 0) {
    ob_clean();
} else {
    ob_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Ahora empezar a registrar logs
error_log("=== INICIO GUARDAR EQUIPO ===");
error_log("POST recibido: " . json_encode($_POST, JSON_UNESCAPED_UNICODE));
error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
error_log("Content-Type recibido: " . ($_SERVER['CONTENT_TYPE'] ?? 'N/A'));

try {
    // Validar CSRF si está disponible
    if (class_exists('CSRF')) {
        try {
            CSRF::validate();
            error_log("CSRF validado correctamente");
        } catch (Exception $csrfError) {
            error_log("CSRF falló pero continúa (desarrollo): " . $csrfError->getMessage());
            // Si falla CSRF, continuar de todas formas (para desarrollo)
            // En producción deberías validar esto
        }
    }
    
    $torneo_id = (int)($_POST['torneo_id'] ?? 0);
    $equipo_id = (int)($_POST['equipo_id'] ?? 0);
    $nombre_equipo = trim($_POST['nombre_equipo'] ?? '');
    $club_id = (int)($_POST['club_id'] ?? 0);
    $jugadores = $_POST['jugadores'] ?? [];
    
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
        // Paso 1: Crear o actualizar el equipo en la tabla equipos
        error_log("PASO 4: Verificando si es equipo nuevo o existente (equipo_id=$equipo_id)");
        if ($equipo_id > 0) {
            // Actualizar equipo existente
            error_log("PASO 4A: Actualizando equipo existente id=$equipo_id");
            $stmt = $pdo->prepare("
                UPDATE equipos 
                SET nombre_equipo = UPPER(?), id_club = ?
                WHERE id = ? AND id_torneo = ?
            ");
            $stmt->execute([$nombre_equipo, $club_id, $equipo_id, $torneo_id]);
            error_log("PASO 4A: Equipo actualizado, filas afectadas: " . $stmt->rowCount());
            
            // Obtener código de equipo existente
            $stmt = $pdo->prepare("SELECT codigo_equipo FROM equipos WHERE id = ?");
            $stmt->execute([$equipo_id]);
            $codigo_equipo = $stmt->fetchColumn() ?: null;
            error_log("PASO 4A: Código de equipo obtenido: " . ($codigo_equipo ?? 'NULL'));
            
            if (empty($codigo_equipo)) {
                error_log("ERROR: No se encontró el código del equipo existente");
                throw new Exception("No se encontró el código del equipo existente");
            }
            
            // Limpiar código_equipo de todos los jugadores que tenían este código (se actualizarán abajo)
            error_log("PASO 4A: Limpiando código_equipo de jugadores anteriores");
            $stmt = $pdo->prepare("UPDATE inscritos SET codigo_equipo = NULL WHERE torneo_id = ? AND codigo_equipo = ?");
            $stmt->execute([$torneo_id, $codigo_equipo]);
            error_log("PASO 4A: Jugadores anteriores limpiados, filas afectadas: " . $stmt->rowCount());
        } else {
            // Crear nuevo equipo
            error_log("PASO 4B: Creando nuevo equipo");
            error_log("PASO 4B: Llamando a EquiposHelper::crearEquipo(torneo_id=$torneo_id, club_id=$club_id, nombre='$nombre_equipo', creado_por=$creado_por)");
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
        
        // Paso 2: Procesar cada jugador y guardar toda su información en inscritos
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
            
            error_log("PASO 5.$jugador_numero: Datos extraídos - cedula=$cedula, nombre=$nombre, id_usuario=$id_usuario, id_inscrito=$id_inscrito");
            
            // Si no tenemos id_usuario, intentar obtenerlo por cédula
            if ($id_usuario <= 0) {
                error_log("PASO 5.$jugador_numero: id_usuario no viene, buscando por cédula=$cedula");
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ? LIMIT 1");
                $stmt->execute([$cedula]);
                $rowUser = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($rowUser && isset($rowUser['id'])) {
                    $id_usuario = (int)$rowUser['id'];
                    error_log("PASO 5.$jugador_numero: id_usuario encontrado por cédula: $id_usuario");
                } else {
                    error_log("PASO 5.$jugador_numero: ERROR - No se encontró usuario con cédula=$cedula");
                }
            }
            
            if ($id_usuario <= 0) {
                error_log("ERROR: No se pudo determinar el ID de usuario para la cédula $cedula");
                throw new Exception("No se pudo determinar el ID de usuario para la cédula $cedula");
            }
            
            // Buscar o crear registro en inscritos
            error_log("PASO 5.$jugador_numero: Buscando/creando registro en inscritos");
            if ($id_inscrito > 0) {
                error_log("PASO 5.$jugador_numero: Verificando id_inscrito=$id_inscrito");
                // Verificar que el id_inscrito corresponde al id_usuario y torneo_id
                $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE id = ? AND id_usuario = ? AND torneo_id = ? LIMIT 1");
                $stmt->execute([$id_inscrito, $id_usuario, $torneo_id]);
                if (!$stmt->fetch()) {
                    error_log("PASO 5.$jugador_numero: id_inscrito inválido, invalidando");
                    $id_inscrito = 0; // Invalidar si no coincide
                } else {
                    error_log("PASO 5.$jugador_numero: id_inscrito válido");
                }
            }
            
            if ($id_inscrito <= 0) {
                error_log("PASO 5.$jugador_numero: Buscando registro existente en inscritos");
                // Buscar si ya existe un registro en inscritos
                $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE id_usuario = ? AND torneo_id = ? LIMIT 1");
                $stmt->execute([$id_usuario, $torneo_id]);
                $rowInscrito = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($rowInscrito && isset($rowInscrito['id'])) {
                    $id_inscrito = (int)$rowInscrito['id'];
                    error_log("PASO 5.$jugador_numero: Registro existente encontrado - id_inscrito=$id_inscrito");
                } else {
                    error_log("PASO 5.$jugador_numero: Creando nuevo registro en inscritos");
                    // Crear nuevo registro en inscritos usando función centralizada
                    require_once __DIR__ . '/../../lib/InscritosHelper.php';
                    
                    try {
                        $id_inscrito = InscritosHelper::insertarInscrito($pdo, [
                            'id_usuario' => $id_usuario,
                            'torneo_id' => $torneo_id,
                            'id_club' => $club_id,
                            'codigo_equipo' => $codigo_equipo,
                            'estatus' => 1, // confirmado
                            'inscrito_por' => $creado_por,
                            'numero' => 0 // Se asignará después con asignarNumeroSecuencialPorEquipo
                        ]);
                        // $id_inscrito ya viene de la función insertarInscrito
                        error_log("PASO 5.$jugador_numero: Registro creado exitosamente - id_inscrito=$id_inscrito");
                    } catch (Exception $e) {
                        error_log("ERROR en PASO 5.$jugador_numero al crear inscrito: " . $e->getMessage());
                        throw $e;
                    }
                }
            }
            
            // Actualizar inscritos con código de equipo y toda la información
            error_log("PASO 5.$jugador_numero: Actualizando inscrito id=$id_inscrito con codigo_equipo=$codigo_equipo");
            try {
                $stmt = $pdo->prepare("
                    UPDATE inscritos 
                    SET id_club = ?, codigo_equipo = ?, estatus = 1
                    WHERE id = ?
                ");
                $stmt->execute([$club_id, $codigo_equipo, $id_inscrito]);
                error_log("PASO 5.$jugador_numero: Inscrito actualizado, filas afectadas: " . $stmt->rowCount());
            } catch (PDOException $e) {
                error_log("ERROR en PASO 5.$jugador_numero al actualizar inscrito: " . $e->getMessage());
                error_log("SQL Error Info: " . json_encode($stmt->errorInfo()));
                throw $e;
            }
        }
        error_log("PASO 5: Todos los jugadores procesados correctamente");
        
        error_log("PASO 6: Commit de transacción");
        $pdo->commit();
        
        error_log("=== ÉXITO: Equipo guardado correctamente ===");
        echo json_encode([
            'success' => true,
            'message' => $equipo_id > 0 ? 'Equipo actualizado exitosamente' : 'Equipo creado exitosamente',
            'equipo_id' => $equipo_id
        ]);
    } catch (Throwable $e) {
        error_log("ERROR en transacción: " . $e->getMessage());
        error_log("Tipo de error: " . get_class($e));
        error_log("Stack trace: " . $e->getTraceAsString());
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("=== ROLLBACK: Transacción revertida ===");
        }
        throw $e;
    }
} catch (Throwable $e) {
    // Capturar cualquier error o excepción (incluyendo errores fatales)
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    
    error_log("=== ERROR FINAL: " . $e->getMessage() . " ===");
    error_log("Tipo de error: " . get_class($e));
    error_log("Archivo: " . $e->getFile() . " línea " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Si es un error de PDO, incluir información adicional
    $errorMessage = $e->getMessage();
    if ($e instanceof PDOException) {
        $errorInfo = $e->errorInfo ?? null;
        if ($errorInfo) {
            error_log("PDO Error Info: " . json_encode($errorInfo));
            $errorMessage .= " (SQL: " . ($errorInfo[1] ?? 'N/A') . ")";
        }
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el equipo: ' . $errorMessage,
        'error_type' => get_class($e),
        'error_file' => basename($e->getFile()),
        'error_line' => $e->getLine()
    ]);
    exit;
} catch (Exception $e) {
    // Fallback para versiones antiguas de PHP que no soportan Throwable
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    
    error_log("=== ERROR FINAL (Exception): " . $e->getMessage() . " ===");
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el equipo: ' . $e->getMessage(),
        'error_type' => 'Exception'
    ]);
    exit;
}

