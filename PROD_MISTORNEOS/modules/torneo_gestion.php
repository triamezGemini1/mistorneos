<?php
/**
 * Módulo de Gestión Completa de Torneos
 * Integra funcionalidades de:
 * - AdminTorneoController: Dashboard y gestión básica
 * - RondasController: Gestión de rondas, cuadrícula
 * - TorneoGestionController: Panel avanzado, resultados, posiciones, resumen individual, hojas de anotación
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../lib/InscritosHelper.php';
require_once __DIR__ . '/../config/MesaAsignacionService.php';

$current_user = Auth::user();
$user_role = $current_user['role'] ?? '';
$user_id = Auth::id();

// Jugadores (usuario) solo pueden ver resumen_individual (el propio) y posiciones
if ($user_role === 'usuario') {
    $action = $_GET['action'] ?? '';
    $torneo_id = (int)($_GET['torneo_id'] ?? 0);
    $inscrito_id = (int)($_GET['inscrito_id'] ?? 0);
    $allowed = ($torneo_id > 0 && in_array($action, ['resumen_individual', 'posiciones']));
    if ($allowed && $action === 'resumen_individual') {
        $allowed = ($inscrito_id > 0 && $inscrito_id === $user_id);
    }
    if (!$allowed) {
        require_once __DIR__ . '/../lib/app_helpers.php';
        header('Location: ' . rtrim(AppHelpers::getBaseUrl(), '/') . '/public/user_portal.php');
        exit;
    }
} else {
    Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);
}

$current_user = Auth::user();
$user_role = $current_user['role'];
$user_id = Auth::id();
$is_admin_general = Auth::isAdminGeneral();
$is_admin_torneo = Auth::isAdminTorneo();
$is_admin_club = Auth::isAdminClub();

// Función auxiliar para determinar la URL base según el contexto
function getBaseUrl() {
    $script = basename($_SERVER['PHP_SELF'] ?? '');
    if ($script === 'panel_torneo.php') return 'panel_torneo.php';
    if ($script === 'admin_torneo.php') return 'admin_torneo.php';
    return 'index.php?page=torneo_gestion';
}

// Función auxiliar para construir URLs de redirección
function buildRedirectUrl($action, $params = []) {
    $base = getBaseUrl();
    $url = $base;
    
    $usa_script_simple = ($base === 'admin_torneo.php' || $base === 'panel_torneo.php');
    if ($usa_script_simple) {
        $url .= '?action=' . $action;
        foreach ($params as $key => $value) {
            $url .= '&' . $key . '=' . urlencode($value);
        }
    } else {
        $url .= '&action=' . $action;
        foreach ($params as $key => $value) {
            $url .= '&' . $key . '=' . urlencode($value);
        }
    }
    
    return $url;
}

/**
 * Verifica si existe la columna 'locked' en la tabla tournaments
 */
function tournamentsLockedColumnExists(): bool {
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tournaments' AND COLUMN_NAME = 'locked'");
        $stmt->execute();
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Asegura que exista la columna 'locked' en tournaments
 */
function ensureTournamentsLockedColumn(): void {
    if (!tournamentsLockedColumnExists()) {
        try {
            $pdo = DB::pdo();
            $pdo->exec("ALTER TABLE tournaments ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 0, ADD INDEX idx_tournaments_locked (locked)");
        } catch (Exception $e) {
            // Ignorar si falla (podría no tener permisos); el flujo continuará sin lock persistente
        }
    }
}

/**
 * Retorna si el torneo está cerrado (locked)
 */
function isTorneoLocked(int $torneoId): bool {
    try {
        if (!tournamentsLockedColumnExists()) {
            return false;
        }
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT locked FROM tournaments WHERE id = ?");
        $stmt->execute([$torneoId]);
        $locked = $stmt->fetchColumn();
        return (int)$locked === 1;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verifica si existe la columna 'correcciones_cierre_at' en tournaments
 */
function tournamentsCorreccionesCierreColumnExists(): bool {
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tournaments' AND COLUMN_NAME = 'correcciones_cierre_at'");
        $stmt->execute();
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Asegura que exista la columna correcciones_cierre_at (fija al guardar última mesa; no se resetea con correcciones)
 */
function ensureTournamentsCorreccionesCierreColumn(): void {
    if (!tournamentsCorreccionesCierreColumnExists()) {
        try {
            $pdo = DB::pdo();
            $pdo->exec("ALTER TABLE tournaments ADD COLUMN correcciones_cierre_at DATETIME NULL COMMENT 'Cierre de correcciones 20 min después de completar última mesa'");
        } catch (Exception $e) {
            // Ignorar si falla
        }
    }
}

// Obtener acción y parámetros
$action = $_GET['action'] ?? 'index';
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : null;
$ronda = isset($_GET['ronda']) ? (int)$_GET['ronda'] : null;
$mesa = isset($_GET['mesa']) ? (int)$_GET['mesa'] : null;
$inscrito_id = isset($_GET['inscrito_id']) ? (int)$_GET['inscrito_id'] : null;

// Manejar acciones POST - DEBE estar antes de cualquier output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? $action;
    
    // Verificar CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';
    if (!$csrf_token || !$session_token || !hash_equals($session_token, $csrf_token)) {
        $_SESSION['error'] = 'Token de seguridad inválido. Por favor, recarga la página e intenta nuevamente.';
        // Si hay torneo_id en POST, redirigir al panel; de lo contrario, al índice
        $redirect_torneo_id = (int)($_POST['torneo_id'] ?? 0);
        if ($redirect_torneo_id > 0) {
            header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $redirect_torneo_id]));
        } else {
            header('Location: ' . buildRedirectUrl('index'));
        }
        exit;
    }
    
    // Bloquear acciones de modificación si el torneo está cerrado
    $torneo_id_check = (int)($_POST['torneo_id'] ?? 0);
    if ($torneo_id_check && isTorneoLocked($torneo_id_check) && ($post_action !== 'cerrar_torneo')) {
        $_SESSION['error'] = 'Este torneo está cerrado y no admite modificaciones.';
        header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id_check]));
        exit;
    }
    
    switch ($post_action) {
        case 'generar_ronda':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            generarRonda($torneo_id, $user_id, $is_admin_general);
            break;
            
        case 'eliminar_ultima_ronda':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            eliminarUltimaRonda($torneo_id, $user_id, $is_admin_general);
            break;
            
        case 'guardar_resultados':
            guardarResultados($user_id, $is_admin_general);
            break;
            
        case 'guardar_mesa_adicional':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            $ronda = (int)($_POST['ronda'] ?? 0);
            guardarMesaAdicional($torneo_id, $ronda, $user_id, $is_admin_general);
            break;
            
        case 'actualizar_estadisticas':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            actualizarEstadisticasManual($torneo_id, $user_id, $is_admin_general);
            break;

        case 'recalcular_bye':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            recalcularBye($torneo_id, $user_id, $is_admin_general);
            break;
            
        case 'ejecutar_reasignacion':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            $ronda = (int)($_POST['ronda'] ?? 0);
            $mesa = (int)($_POST['mesa'] ?? 0);
            ejecutarReasignacion($torneo_id, $ronda, $mesa, $user_id, $is_admin_general);
            break;
        
        case 'cerrar_torneo':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            ensureTournamentsLockedColumn();
            try {
                if ($torneo_id > 0 && tournamentsLockedColumnExists()) {
                    $stmt = DB::pdo()->prepare("UPDATE tournaments SET locked = 1 WHERE id = ?");
                    $stmt->execute([$torneo_id]);
                    $_SESSION['success'] = 'Torneo cerrado definitivamente. No se podrán realizar más cambios.';
                } else {
                    $_SESSION['error'] = 'No fue posible cerrar el torneo (estructura no disponible).';
                }
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error al cerrar el torneo: ' . $e->getMessage();
            }
            header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
            exit;

        case 'enviar_notificacion_torneo':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            enviarNotificacionTorneo($torneo_id, $user_id, $is_admin_general);
            break;

        case 'guardar_asignacion_mesas_operador':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            $ronda = (int)($_POST['ronda'] ?? 0);
            guardarAsignacionMesasOperador($torneo_id, $ronda, $user_id, $is_admin_general);
            break;

        case 'verificar_acta_aprobar':
            verificarActaAprobar($user_id, $is_admin_general);
            break;

        case 'verificar_acta_rechazar':
            verificarActaRechazar($user_id, $is_admin_general);
            break;

        case 'cambiar_estatus_inscrito':
            $inscripcion_id = (int)($_POST['inscripcion_id'] ?? 0);
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            $nuevo_estatus = (int)($_POST['estatus'] ?? 0);
            if ($inscripcion_id <= 0 || $torneo_id <= 0 || !InscritosHelper::isValidEstatus($nuevo_estatus)) {
                $_SESSION['error'] = 'Parámetros inválidos para cambiar estatus.';
                header('Location: ' . buildRedirectUrl('inscripciones', ['torneo_id' => $torneo_id]));
                exit;
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE id = ? AND torneo_id = ?");
            $stmt->execute([$inscripcion_id, $torneo_id]);
            if (!$stmt->fetch()) {
                $_SESSION['error'] = 'Inscripción no encontrada.';
                header('Location: ' . buildRedirectUrl('inscripciones', ['torneo_id' => $torneo_id]));
                exit;
            }
            // Guardar como entero (columna INT); si fuera ENUM usar InscritosHelper::ESTATUS_MAP[$nuevo_estatus]
            $stmt = $pdo->prepare("UPDATE inscritos SET estatus = ? WHERE id = ? AND torneo_id = ?");
            $stmt->execute([$nuevo_estatus, $inscripcion_id, $torneo_id]);
            $_SESSION['success'] = 'Estatus del inscrito actualizado.';
            header('Location: ' . buildRedirectUrl('inscripciones', ['torneo_id' => $torneo_id]));
            exit;

        default:
            $_SESSION['error'] = 'Acción POST no válida';
            // Si hay torneo_id en POST, redirigir al panel; de lo contrario, al índice
            $redirect_torneo_id = (int)($_POST['torneo_id'] ?? 0);
            if ($redirect_torneo_id > 0) {
                header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $redirect_torneo_id]));
            } else {
                header('Location: ' . buildRedirectUrl('index'));
            }
            exit;
    }
}

// Determinar qué vista mostrar
$view_file = null;
$view_data = [];
$error_message = null;

try {
    switch ($action) {
        case 'index':
            $filtro_torneos = isset($_GET['filtro']) && in_array($_GET['filtro'], ['realizados', 'en_proceso', 'por_realizar'], true) ? $_GET['filtro'] : null;
            $torneos = obtenerTorneosGestion($user_id, $is_admin_general, $filtro_torneos);
            if ($is_admin_general && !empty($torneos)) {
                $club_ids = array_unique(array_filter(array_column($torneos, 'club_responsable')));
                $entidad_map = [];
                try {
                    $cols = DB::pdo()->query("SHOW COLUMNS FROM entidad")->fetchAll(PDO::FETCH_ASSOC);
                    $codeCol = $nameCol = null;
                    foreach ($cols as $c) {
                        $f = strtolower($c['Field'] ?? '');
                        if (in_array($f, ['codigo', 'cod_entidad', 'id', 'code'])) $codeCol = $f;
                        if (in_array($f, ['nombre', 'descripcion', 'entidad', 'nombre_entidad'])) $nameCol = $f;
                    }
                    if ($codeCol && $nameCol) {
                        $entidad_map = DB::pdo()->query("SELECT {$codeCol} AS codigo, {$nameCol} AS nombre FROM entidad ORDER BY {$nameCol}")->fetchAll(PDO::FETCH_KEY_PAIR);
                    }
                } catch (Exception $e) { /* ignore */ }
                if (!empty($club_ids)) {
                    $placeholders = implode(',', array_fill(0, count($club_ids), '?'));
                    $stmt_ent = DB::pdo()->prepare("SELECT club_id, entidad FROM usuarios WHERE role = 'admin_club' AND club_id IN ($placeholders)");
                    $stmt_ent->execute(array_values($club_ids));
                    $club_to_entidad = [];
                    foreach ($stmt_ent->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $club_to_entidad[(int)$row['club_id']] = (int)($row['entidad'] ?? 0);
                    }
                    foreach ($torneos as &$t) {
                        $ent = $club_to_entidad[(int)($t['club_responsable'] ?? 0)] ?? 0;
                        $t['entidad_nombre'] = $ent > 0 ? ($entidad_map[$ent] ?? 'Entidad ' . $ent) : 'Sin entidad';
                    }
                    unset($t);
                    usort($torneos, function ($a, $b) {
                        $na = $a['entidad_nombre'] ?? '';
                        $nb = $b['entidad_nombre'] ?? '';
                        $c = strcmp($na, $nb);
                        if ($c !== 0) return $c;
                        return strcmp($a['fechator'] ?? '', $b['fechator'] ?? '');
                    });
                }
            }
            $use_standalone = (basename($_SERVER['PHP_SELF'] ?? '') === 'admin_torneo.php');
            $view_file = $use_standalone ? __DIR__ . '/gestion_torneos/index-moderno.php' : __DIR__ . '/gestion_torneos/index.php';
            $view_data = ['torneos' => $torneos, 'filtro_torneos' => $filtro_torneos, 'is_admin_general' => $is_admin_general];
            break;
            
        case 'dashboard':
            // Redirigir 'dashboard' a 'panel' si hay torneo_id, de lo contrario a 'index'
            if ($torneo_id > 0) {
                header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
                exit;
            } else {
                header('Location: ' . buildRedirectUrl('index'));
                exit;
            }
            break;
            
        case 'panel':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            // Usar siempre panel-moderno.php (común para todos los tipos de torneo)
            // La vista se adapta dinámicamente según la modalidad del torneo
            $view_file = __DIR__ . '/gestion_torneos/panel-moderno.php';
            // Obtener datos según modalidad (obtenerDatosPanel ahora incluye datos de equipos si corresponde)
            $view_data = obtenerDatosPanel($torneo_id);
            // Asegurar que $torneo esté en $view_data (obtenerDatosPanel ya lo incluye, pero por si acaso)
            if (!isset($view_data['torneo']) || !$view_data['torneo']) {
                $view_data['torneo'] = $torneo;
            }
            // También asegurar que torneo_id esté disponible
            $view_data['torneo_id'] = $torneo_id;
            break;
            
        case 'panel_equipos':
            // Redirigir panel_equipos a panel (ahora es común para todos los tipos)
            // Este caso se mantiene solo para compatibilidad con enlaces antiguos
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            // Redirigir al panel común
            header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
            exit;
            break;
            
        case 'cronometro':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            $view_file = __DIR__ . '/gestion_torneos/cronometro.php';
            $view_data = ['torneo' => $torneo, 'torneo_id' => $torneo_id];
            $use_cronometro_standalone = true;
            break;
            
        case 'gestionar_inscripciones_equipos':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/gestionar_inscripciones_equipos.php';
            $view_data = obtenerDatosGestionarInscripcionesEquipos($torneo_id);
            break;
            
        case 'inscribir_equipo_sitio':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/inscribir_equipo_sitio.php';
            $view_data = obtenerDatosInscribirEquipoSitio($torneo_id);
            break;
            
        case 'mesas':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/mesas.php';
            $view_data = obtenerDatosMesas($torneo_id, $ronda, $user_id, $user_role);
            break;

        case 'asignar_mesas_operador':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/asignar_mesas_operador.php';
            $view_data = obtenerDatosAsignarMesasOperador($torneo_id, $ronda);
            break;
            
        case 'rondas':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/rondas.php';
            $view_data = obtenerDatosRondas($torneo_id);
            break;
            
        case 'posiciones':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/posiciones.php';
            $view_data = obtenerDatosPosiciones($torneo_id);
            break;

        case 'galeria_fotos':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            // Reutilizamos la vista de administración de torneo para mantener funcionalidades y estilos
            $view_file = __DIR__ . '/tournament_admin/galeria_fotos.php';
            $view_data = ['torneo' => $torneo, 'torneo_id' => $torneo_id];
            break;
            
        case 'inscripciones':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/inscripciones.php';
            $view_data = obtenerDatosInscripciones($torneo_id);
            break;

        case 'notificaciones':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/notificaciones_torneo.php';
            $view_data = obtenerDatosNotificacionesTorneo($torneo_id);
            break;

        case 'equipos':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/equipos.php';
            $view_data = obtenerDatosEquiposAdmin($torneo_id);
            break;
            
        case 'inscribir_sitio':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/inscribir-sitio.php';
            $view_data = obtenerDatosInscribirSitio($torneo_id, $user_id, $is_admin_general);
            break;
            
        case 'registrar_resultados':
        case 'registrar_resultados_v2':
            if (!$torneo_id) {
                throw new Exception('Debe especificar torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            // Si no se especifica ronda, ir a la última ronda para comenzar a ingresar
            if (!$ronda || $ronda <= 0) {
                $pdo = DB::pdo();
                $stmt = $pdo->prepare("SELECT MAX(partida) FROM partiresul WHERE id_torneo = ?");
                $stmt->execute([$torneo_id]);
                $ultima_ronda = (int)$stmt->fetchColumn();
                if ($ultima_ronda <= 0) {
                    throw new Exception('No hay rondas generadas para este torneo. Genere rondas primero.');
                }
                $ronda = $ultima_ronda;
                $mesa = isset($_GET['mesa']) ? (int)$_GET['mesa'] : 0;
                header('Location: ' . buildRedirectUrl($action, [
                    'torneo_id' => $torneo_id,
                    'ronda' => $ronda,
                    'mesa' => $mesa
                ]));
                exit;
            }
            $view_file = __DIR__ . '/gestion_torneos/registrar-resultados-v2.php';
            $view_data = obtenerDatosRegistroResultados($torneo_id, $ronda, $mesa ?? 0, $user_id, $user_role);
            break;
            
        case 'cuadricula':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/cuadricula.php';
            $view_data = obtenerDatosCuadricula($torneo_id, $ronda);
            break;
            
        case 'hojas_anotacion':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/hojas-anotacion.php';
            $view_data = obtenerDatosHojasAnotacion($torneo_id, $ronda);
            break;
            
        case 'resumen_individual':
            if (!$torneo_id || !$inscrito_id) {
                throw new Exception('Debe especificar torneo e inscrito');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/resumen-individual.php';
            $view_data = obtenerDatosResumenIndividual($torneo_id, $inscrito_id);
            break;
            
        case 'reasignar_mesa':
            if (!$torneo_id || !$ronda || !$mesa) {
                throw new Exception('Debe especificar torneo, ronda y mesa');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            $datos = obtenerDatosReasignarMesa($torneo_id, $ronda, $mesa);
            if (empty($datos['jugadores']) || count($datos['jugadores']) != 4) {
                throw new Exception('La mesa debe tener exactamente 4 jugadores para reasignar');
            }
            $view_file = __DIR__ . '/gestion_torneos/reasignar-mesa.php';
            $view_data = array_merge(['torneo' => $torneo], $datos);
            break;
            
        case 'agregar_mesa':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/agregar-mesa.php';
            $view_data = obtenerDatosAgregarMesa($torneo_id, $ronda);
            break;
            
        case 'agregar_mesa':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $view_file = __DIR__ . '/gestion_torneos/agregar-mesa.php';
            $view_data = obtenerDatosAgregarMesa($torneo_id, $ronda);
            break;
            
        case 'verificar_mesa':
            // Endpoint AJAX
            if (!$torneo_id || !$ronda || !$mesa) {
                header('Content-Type: application/json');
                echo json_encode(['existe' => false]);
                exit;
            }
            header('Content-Type: application/json');
            echo json_encode(['existe' => verificarMesaExiste($torneo_id, $ronda, $mesa)]);
            exit;
            
        case 'podio':
        case 'podios':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            // Detectar modalidad: si es equipos (modalidad = 3), mostrar podios de equipos
            $es_modalidad_equipos = isset($torneo['modalidad']) && (int)$torneo['modalidad'] === 3;
            if ($es_modalidad_equipos) {
                $view_file = __DIR__ . '/tournament_admin/podios_equipos.php';
            } else {
                $view_file = __DIR__ . '/tournament_admin/podios.php';
            }
            $view_data = ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()];
            break;
            
        case 'podios_equipos':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            $view_file = __DIR__ . '/tournament_admin/podios_equipos.php';
            $view_data = ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()];
            break;
            
        case 'equipos_detalle':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            $view_file = __DIR__ . '/tournament_admin/equipos_detalle.php';
            $view_data = ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()];
            break;
            
        case 'resultados_por_club':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            $view_file = __DIR__ . '/tournament_admin/resultados_por_club.php';
            $view_data = ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()];
            break;
            
        case 'resultados_equipos_resumido':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            if ((int)($torneo['modalidad'] ?? 0) !== 3) {
                throw new Exception('Este reporte solo está disponible para torneos por equipos');
            }
            $view_file = __DIR__ . '/tournament_admin/resultados_equipos_resumido.php';
            $view_data = ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()];
            break;
            
        case 'resultados_equipos_detallado':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            if ((int)($torneo['modalidad'] ?? 0) !== 3) {
                throw new Exception('Este reporte solo está disponible para torneos por equipos');
            }
            $view_file = __DIR__ . '/tournament_admin/resultados_equipos_detallado.php';
            $view_data = ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()];
            break;
            
        case 'resultados_general':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            if ((int)($torneo['modalidad'] ?? 0) !== 3) {
                throw new Exception('Este reporte solo está disponible para torneos por equipos');
            }
            $view_file = __DIR__ . '/tournament_admin/resultados_general.php';
            $view_data = ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()];
            break;

        case 'verificar_actas':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            $view_file = __DIR__ . '/tournament_admin/verificar_actas_lista.php';
            $view_data = obtenerDatosVerificarActasLista($torneo_id);
            $view_data['torneo'] = $torneo;
            $view_data['torneo_id'] = $torneo_id;
            break;

        case 'verificar_acta':
            if (!$torneo_id || !$ronda || !$mesa) {
                throw new Exception('Debe especificar torneo, ronda y mesa');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            $view_file = __DIR__ . '/tournament_admin/verificar_acta.php';
            $view_data = obtenerDatosVerificarActa($torneo_id, $ronda, $mesa);
            if (!$view_data) {
                throw new Exception('Acta no encontrada o ya verificada');
            }
            $view_data['torneo'] = $torneo;
            $view_data['torneo_id'] = $torneo_id;
            $view_data['ronda'] = $ronda;
            $view_data['mesa'] = $mesa;
            break;

        case 'verificar_resultados':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            $view_file = __DIR__ . '/tournament_admin/views/verificar_resultados.php';
            $view_data = obtenerDatosVerificarActasLista($torneo_id);
            $view_data['torneo'] = $torneo;
            $view_data['torneo_id'] = $torneo_id;
            $view_data['jugadores'] = [];
            $view_data['torneo_finalizado'] = isTorneoLocked($torneo_id);
            $view_data['is_admin_general'] = $is_admin_general;
            $view_data['can_edit'] = !$view_data['torneo_finalizado'] || $is_admin_general;
            $ronda_vr = (int)($_GET['ronda'] ?? $_REQUEST['ronda'] ?? 0);
            $mesa_vr = (int)($_GET['mesa'] ?? $_REQUEST['mesa'] ?? 0);
            if ($ronda_vr > 0 && $mesa_vr > 0) {
                $acta_data = obtenerDatosVerificarActa($torneo_id, $ronda_vr, $mesa_vr);
                if ($acta_data) {
                    $view_data['jugadores'] = $acta_data['jugadores'];
                    $view_data['ronda'] = $ronda_vr;
                    $view_data['mesa'] = $mesa_vr;
                }
            }
            break;

        case 'verificar_actas_index':
            $view_file = __DIR__ . '/tournament_admin/verificar_actas_index.php';
            $view_data = obtenerTorneosConActasPendientes($user_id, $is_admin_general);
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
    
    // Enriquecer torneo con datos de organización si faltan (para panel_torneo header)
    if (isset($view_data['torneo']) && !empty($view_data['torneo']['club_responsable']) && empty($view_data['torneo']['organizacion_logo'])) {
        try {
            $stmt = DB::pdo()->prepare("SELECT nombre, logo FROM organizaciones WHERE id = ?");
            $stmt->execute([$view_data['torneo']['club_responsable']]);
            $org = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($org) {
                $view_data['torneo']['organizacion_nombre'] = $org['nombre'] ?? 'N/A';
                $view_data['torneo']['organizacion_logo'] = !empty($org['logo']) ? $org['logo'] : null;
            }
        } catch (Exception $e) { /* ignorar */ }
    }
    
    // Cronómetro: página aparte sin layout (pantalla dedicada)
    if (!empty($use_cronometro_standalone) && $view_file && file_exists($view_file)) {
        extract($view_data);
        include $view_file;
        exit;
    }
    
    // Si se invoca desde panel_torneo.php, no renderizar aquí; panel_torneo lo hará con un solo contenedor
    $is_panel_standalone_page = (basename($_SERVER['PHP_SELF'] ?? '') === 'panel_torneo.php');
    if ($is_panel_standalone_page) {
        return; // panel_torneo.php hace el render
    }
    
    // Determinar si usar layout independiente o layout normal
    $use_standalone_layout = (basename($_SERVER['PHP_SELF']) === 'admin_torneo.php');
    
    if ($use_standalone_layout) {
        // Usar layout independiente para admin_torneo.php
        ob_start();
        if ($view_file && file_exists($view_file)) {
            extract($view_data);
            include $view_file;
        } else {
            throw new Exception('Vista no encontrada: ' . basename($view_file));
        }
        $content = ob_get_clean();
        
        // Asegurar que $torneo y $torneo_id estén disponibles para el layout
        if (!isset($torneo) && isset($view_data['torneo'])) {
            $torneo = $view_data['torneo'];
        }
        if (!isset($torneo_id) && isset($torneo['id'])) {
            $torneo_id = $torneo['id'];
        } elseif (!isset($torneo_id)) {
            $torneo_id = (int)($_GET['torneo_id'] ?? $_REQUEST['torneo_id'] ?? 0);
        }
        
        // Obtener acción actual
        $action = $_GET['action'] ?? $_REQUEST['action'] ?? '';
        
        $page_title = $page_title ?? 'Administrador de Torneos';
        include __DIR__ . '/../public/includes/admin_torneo_layout.php';
    } else {
        // Usar layout normal (incluido desde index.php)
        if ($view_file && file_exists($view_file)) {
            extract($view_data);
            include $view_file;
        } else {
            throw new Exception('Vista no encontrada: ' . basename($view_file));
        }
    }
    
} catch (Exception $e) {
    $use_standalone_layout = (basename($_SERVER['PHP_SELF']) === 'admin_torneo.php');
    
    if ($use_standalone_layout) {
        // Mostrar error en layout independiente
        ob_start();
        $error_message = $e->getMessage();
        $view_file = __DIR__ . '/gestion_torneos/index.php';
        $view_data = ['torneos' => [], 'error_message' => $error_message];
        extract($view_data);
        include $view_file;
        $content = ob_get_clean();
        
        $page_title = 'Error - Administrador de Torneos';
        include __DIR__ . '/../public/includes/admin_torneo_layout.php';
    } else {
        // Mostrar error en layout normal
        $error_message = $e->getMessage();
        $view_file = __DIR__ . '/gestion_torneos/index.php';
        $view_data = ['torneos' => [], 'error_message' => $error_message];
        extract($view_data);
        include $view_file;
    }
}

// =================================================================
// FUNCIONES AUXILIARES
// =================================================================

/**
 * Obtiene torneos disponibles para gestión, opcionalmente filtrados por categoría.
 * Categorías: realizados (cerrados), en_proceso (en curso), por_realizar (futuros).
 *
 * @param int $user_id
 * @param bool $is_admin_general
 * @param string|null $filtro 'realizados' | 'en_proceso' | 'por_realizar' | null (todos)
 * @return array
 */
function obtenerTorneosGestion($user_id, $is_admin_general, $filtro = null) {
    $pdo = DB::pdo();
    
    $tournament_filter = Auth::getTournamentFilterForRole('t');
    $where_clause = !empty($tournament_filter['where']) ? "WHERE " . $tournament_filter['where'] : "";
    $params = $tournament_filter['params'];
    
    $sql = "SELECT t.*, o.nombre as organizacion_nombre,
            (SELECT COUNT(*) FROM inscritos WHERE torneo_id = t.id) as total_inscritos,
            (SELECT COUNT(*) FROM inscritos WHERE torneo_id = t.id AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO . ") as inscritos_confirmados
            FROM tournaments t
            LEFT JOIN organizaciones o ON t.club_responsable = o.id
            $where_clause
            ORDER BY t.fechator DESC, t.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $torneos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hoy = date('Y-m-d');
    
    foreach ($torneos as &$torneo) {
        $rondas_generadas = obtenerRondasGeneradas($torneo['id']);
        $torneo['rondas_generadas'] = count($rondas_generadas);
        $ultima_ronda = !empty($rondas_generadas) ? max(array_column($rondas_generadas, 'num_ronda')) : 0;
        $torneo['ultima_ronda'] = $ultima_ronda;
        $torneo['ronda_actual'] = $ultima_ronda;
        $torneo['proxima_ronda'] = $ultima_ronda + 1;
        $torneo['rondas_totales'] = $torneo['rondas'] ?? 0;
        $torneo['rondas_faltantes'] = max(0, ($torneo['rondas_totales'] ?? 0) - $ultima_ronda);
        $torneo['porcentaje_progreso'] = ($torneo['rondas_totales'] > 0) ? round(($ultima_ronda / $torneo['rondas_totales']) * 100) : 0;

        $locked = (int)($torneo['locked'] ?? 0) === 1;
        $fecha = $torneo['fechator'] ?? null;
        $fecha_ok = $fecha ? (strtotime($fecha) <= strtotime($hoy)) : false;

        if ($locked) {
            $torneo['categoria'] = 'realizados';
        } elseif ($fecha_ok || $ultima_ronda > 0) {
            $torneo['categoria'] = 'en_proceso';
        } else {
            $torneo['categoria'] = 'por_realizar';
        }
    }
    unset($torneo);

    if ($filtro !== null && in_array($filtro, ['realizados', 'en_proceso', 'por_realizar'], true)) {
        $torneos = array_values(array_filter($torneos, function ($t) use ($filtro) {
            return ($t['categoria'] ?? '') === $filtro;
        }));
        if ($filtro === 'por_realizar') {
            usort($torneos, function ($a, $b) {
                $fa = $a['fechator'] ?? '';
                $fb = $b['fechator'] ?? '';
                return strcmp($fa, $fb);
            });
        }
    }

    return $torneos;
}

/**
 * Obtiene datos de un torneo
 */
function obtenerTorneo($torneo_id, $user_id, $is_admin_general) {
    $pdo = DB::pdo();
    
    // Obtener torneo (la tabla clubes NO tiene admin_id, se relaciona vía usuarios.club_id)
    $sql = "SELECT t.*, c.nombre as club_nombre
            FROM tournaments t
            LEFT JOIN clubes c ON t.club_responsable = c.id
            WHERE t.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar permisos usando Auth::canAccessTournament
    if ($torneo && !Auth::canAccessTournament($torneo_id)) {
        return null; // Sin permisos
    }
    
    return $torneo;
}

/**
 * Verifica permisos sobre un torneo
 */
function verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general) {
    // Usar Auth::canAccessTournament que ya maneja todos los roles correctamente
    if (!Auth::canAccessTournament($torneo_id)) {
        throw new Exception('No tiene permisos para acceder a este torneo');
    }
    return obtenerTorneo($torneo_id, $user_id, $is_admin_general);
}

/**
 * Obtiene rondas generadas de un torneo
 */
function obtenerRondasGeneradas($torneo_id) {
    $pdo = DB::pdo();
    
    $sql = "SELECT 
                partida as num_ronda,
                COUNT(DISTINCT mesa) as total_mesas,
                COUNT(*) as total_jugadores,
                COUNT(CASE WHEN mesa = 0 THEN 1 END) as jugadores_bye,
                MAX(fecha_partida) as fecha_generacion
            FROM partiresul
            WHERE id_torneo = ?
            GROUP BY partida
            ORDER BY partida ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene datos para el panel de control
 */
function obtenerDatosPanel($torneo_id) {
    $pdo = DB::pdo();
    ensureTournamentsCorreccionesCierreColumn();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $rondas_generadas = obtenerRondasGeneradas($torneo_id);
    $ultima_ronda = !empty($rondas_generadas) ? max(array_column($rondas_generadas, 'num_ronda')) : 0;
    $proxima_ronda = $ultima_ronda + 1;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE torneo_id = ?");
    $stmt->execute([$torneo_id]);
    $total_inscritos = $stmt->fetchColumn();
    
    // Filtro: excluir retirados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO);
    $stmt->execute([$torneo_id]);
    $inscritos_confirmados = $stmt->fetchColumn();
    
    // Estadísticas adicionales
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM partiresul WHERE id_torneo = ? AND registrado = 1");
    $stmt->execute([$torneo_id]);
    $total_partidas = $stmt->fetchColumn();
    
    $puede_generar = true;
    $mesas_incompletas = 0;
    $total_mesas_ronda = 0;
    $ultima_ronda_tiene_resultados = false;
    if ($ultima_ronda > 0) {
        $mesas_incompletas = contarMesasIncompletas($torneo_id, $ultima_ronda);
        $puede_generar = $mesas_incompletas === 0;
        
        // Contar total de mesas de la última ronda
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT mesa) FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa > 0");
        $stmt->execute([$torneo_id, $ultima_ronda]);
        $total_mesas_ronda = $stmt->fetchColumn();
        
        // Si la última ronda tiene resultados de MESAS registrados (no solo estructura ni BYE)
        $mesaServicePanel = new MesaAsignacionService();
        $ultima_ronda_tiene_resultados = $mesaServicePanel->rondaTieneResultadosEnMesas($torneo_id, $ultima_ronda);
    }
    
    // Correcciones: cierre fijado al guardar última mesa (no se resetea con correcciones)
    $ultima_actualizacion_resultados = null;
    $correcciones_cierre_at = isset($torneo['correcciones_cierre_at']) ? $torneo['correcciones_cierre_at'] : null;
    if (empty($correcciones_cierre_at) || $correcciones_cierre_at === '0000-00-00 00:00:00') {
        $correcciones_cierre_at = null;
    }
    
    // Obtener información de la organización (club_responsable = org_id)
    $organizacion_nombre = 'N/A';
    $organizacion_logo = null;
    if (!empty($torneo['club_responsable'])) {
        $stmt = $pdo->prepare("SELECT nombre, logo FROM organizaciones WHERE id = ?");
        $stmt->execute([$torneo['club_responsable']]);
        $org = $stmt->fetch(PDO::FETCH_ASSOC);
        $organizacion_nombre = $org['nombre'] ?? 'N/A';
        $organizacion_logo = !empty($org['logo']) ? $org['logo'] : null;
    }
    $torneo['organizacion_nombre'] = $organizacion_nombre;
    $torneo['organizacion_logo'] = $organizacion_logo;
    
    // Actas pendientes de verificación (origen QR)
    $actas_pendientes_count = 0;
    try {
        $cols_pr = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('estatus', $cols_pr)) {
            $has_origen = in_array('origen_dato', $cols_pr);
            $sql = "
                SELECT COUNT(DISTINCT CONCAT(partida,'-',mesa))
                FROM partiresul
                WHERE id_torneo = ? AND mesa > 0 AND estatus = 'pendiente_verificacion'"
                . ($has_origen ? " AND origen_dato = 'qr'" : "") . "
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$torneo_id]);
            $actas_pendientes_count = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) { /* ignorar */ }

    // Auditoría: mesas Verificadas (QR con foto) vs Digitadas (por admin)
    $mesas_verificadas_count = 0;
    $mesas_digitadas_count = 0;
    try {
        $cols_pr = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_COLUMN);
        $has_origen = in_array('origen_dato', $cols_pr);
        if ($has_origen) {
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT CONCAT(partida,'-',mesa))
                FROM partiresul
                WHERE id_torneo = ? AND mesa > 0 AND registrado = 1 AND origen_dato = 'qr'
            ");
            $stmt->execute([$torneo_id]);
            $mesas_verificadas_count = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT CONCAT(partida,'-',mesa))
                FROM partiresul
                WHERE id_torneo = ? AND mesa > 0 AND registrado = 1 AND origen_dato = 'admin'
            ");
            $stmt->execute([$torneo_id]);
            $mesas_digitadas_count = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) { /* ignorar */ }

    // Estadísticas por modalidad
    $total_equipos = 0;
    $total_jugadores_inscritos = 0;
    if ((int)$torneo['modalidad'] === 3) {
        // Modalidad equipos: obtener estadísticas de equipos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipos WHERE id_torneo = ?");
        $stmt->execute([$torneo_id]);
        $total_equipos = (int)$stmt->fetchColumn();
        
        // Total de jugadores inscritos en equipos (con codigo_equipo)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND codigo_equipo IS NOT NULL AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO);
        $stmt->execute([$torneo_id]);
        $total_jugadores_inscritos = (int)$stmt->fetchColumn();
    }
    
    return [
        'torneo' => $torneo,
        'rondas' => $rondas_generadas,
        'rondas_generadas' => $rondas_generadas,
        'ultimaRonda' => $ultima_ronda,
        'ultima_ronda' => $ultima_ronda,
        'proximaRonda' => $proxima_ronda,
        'proxima_ronda' => $proxima_ronda,
        'totalInscritos' => $total_inscritos,
        'total_inscritos' => $total_inscritos,
        'inscritos_confirmados' => $inscritos_confirmados,
        'total_equipos' => $total_equipos,
        'total_jugadores_inscritos' => $total_jugadores_inscritos, // Para modalidad equipos
        'puedeGenerarRonda' => $puede_generar,
        'puede_generar_ronda' => $puede_generar,
        'mesasIncompletas' => $mesas_incompletas,
        'mesas_incompletas' => $mesas_incompletas,
        'ultima_ronda_tiene_resultados' => $ultima_ronda_tiene_resultados,
        'ultima_actualizacion_resultados' => $ultima_actualizacion_resultados,
        'correcciones_cierre_at' => $correcciones_cierre_at,
        'estadisticas' => [
            'confirmados' => $inscritos_confirmados,
            'solventes' => 0,
            'total_partidas' => $total_partidas,
            'mesas_ronda' => $total_mesas_ronda,
            'total_equipos' => $total_equipos,
            'total_jugadores_inscritos' => $total_jugadores_inscritos
        ],
        'actas_pendientes_count' => $actas_pendientes_count,
        'mesas_verificadas_count' => $mesas_verificadas_count,
        'mesas_digitadas_count' => $mesas_digitadas_count
    ];
}

/**
 * Obtiene datos de mesas de una ronda.
 * Si el usuario es operador, solo se devuelven las mesas de su ámbito (asignadas a él).
 */
function obtenerDatosMesas($torneo_id, $ronda, $user_id = 0, $user_role = '') {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener todas las mesas de la ronda
    $sql = "SELECT DISTINCT pr.mesa as numero,
                MAX(pr.registrado) as registrado,
                COUNT(DISTINCT pr.id_usuario) as total_jugadores
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
            GROUP BY pr.mesa
            ORDER BY pr.mesa ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda]);
    $todasLasMesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener jugadores agrupados por mesa
    $sql = "SELECT 
                pr.*,
                u.nombre as nombre_completo,
                u.nombre,
                u.sexo,
                c.nombre as club_nombre
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            LEFT JOIN inscritos i ON i.id_usuario = u.id AND i.torneo_id = pr.id_torneo
            LEFT JOIN clubes c ON i.id_club = c.id
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
            ORDER BY pr.mesa ASC, pr.secuencia ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por mesa con estructura completa
    $mesas = [];
    foreach ($resultados as $resultado) {
        $numMesa = (int)$resultado['mesa'];
        if (!isset($mesas[$numMesa])) {
            $mesas[$numMesa] = [
                'mesa' => $numMesa,
                'numero' => $numMesa,
                'registrado' => $resultado['registrado'] ?? 0,
                'tiene_resultados' => ($resultado['registrado'] ?? 0) > 0,
                'jugadores' => []
            ];
        }
        $mesas[$numMesa]['jugadores'][] = $resultado;
    }
    
    // Operador: limitar a sus mesas asignadas (ámbito)
    $mesas_operador = obtenerMesasAsignadasOperador($torneo_id, $ronda, $user_id, $user_role);
    if ($mesas_operador !== null && !empty($mesas_operador)) {
        $set_operador = array_flip($mesas_operador);
        $mesas = array_intersect_key($mesas, $set_operador);
        $mesas = array_values($mesas);
    } elseif ($mesas_operador !== null && empty($mesas_operador)) {
        $mesas = [];
    } else {
        $mesas = array_values($mesas);
    }
    
    // Obtener total de rondas
    $stmt = $pdo->prepare("SELECT rondas FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $totalRondas = $stmt->fetchColumn() ?? 0;
    
    return [
        'torneo' => $torneo,
        'ronda' => $ronda,
        'mesas' => $mesas,
        'totalRondas' => $totalRondas,
        'es_operador_ambito' => $mesas_operador !== null,
    ];
}

/**
 * Obtiene datos de rondas
 */
function obtenerDatosRondas($torneo_id) {
    $pdo = DB::pdo();
    
    $torneo = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $torneo->execute([$torneo_id]);
    $torneo = $torneo->fetch(PDO::FETCH_ASSOC);
    
    $rondas_generadas = obtenerRondasGeneradas($torneo_id);
    $ultima_ronda = !empty($rondas_generadas) ? max(array_column($rondas_generadas, 'num_ronda')) : 0;
    $proxima_ronda = $ultima_ronda + 1;
    
    return [
        'torneo' => $torneo,
        'rondas_generadas' => $rondas_generadas,
        'proxima_ronda' => $proxima_ronda
    ];
}

/**
 * Obtiene datos de posiciones.
 * Procedencia: las estadísticas (ganados, perdidos, efectividad, puntos) y la posición
 * se leen de la tabla inscritos, que debe estar sincronizada con partiresul.
 * Se llama a actualizarEstadisticasInscritos() al cargar el reporte.
 * Tarjeta: se toma la de mayor severidad en el torneo desde partiresul (MAX), con
 * fallback a inscritos.tarjeta; valores 0=ninguna, 1=amarilla, 3=roja, 4=negra.
 */
function obtenerDatosPosiciones($torneo_id) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$torneo) {
        return ['torneo' => null, 'posiciones' => [], 'es_modalidad_equipos' => false];
    }
    
    $es_modalidad_equipos = (int)($torneo['modalidad'] ?? 0) === 3;
    
    // Actualizar estadísticas de inscritos desde partiresul y recalcular posiciones (incluye equipos si aplica)
    try {
        actualizarEstadisticasInscritos($torneo_id);
    } catch (Exception $e) {
        error_log("obtenerDatosPosiciones: Error al actualizar estadísticas para torneo $torneo_id: " . $e->getMessage());
        // Continuar mostrando lo que haya en inscritos
    }
    
    // Obtener TODOS los jugadores individuales con estadísticas completas (ya actualizadas en inscritos)
    $sql = "SELECT 
                i.*,
                u.nombre as nombre_completo,
                u.username,
                u.sexo,
                c.nombre as club_nombre,
                c.nombre as nombre_club,
                e.nombre_equipo,
                e.codigo_equipo as codigo_equipo_from_equipos,
                (
                    SELECT COUNT(DISTINCT pr1.partida, pr1.mesa)
                    FROM `partiresul` pr1
                    LEFT JOIN `partiresul` pr_oponente ON pr1.id_torneo = pr_oponente.id_torneo 
                        AND pr1.partida = pr_oponente.partida 
                        AND pr1.mesa = pr_oponente.mesa
                        AND pr_oponente.id_usuario != pr1.id_usuario
                        AND (
                            (pr1.secuencia IN (1, 2) AND pr_oponente.secuencia IN (3, 4)) OR
                            (pr1.secuencia IN (3, 4) AND pr_oponente.secuencia IN (1, 2))
                        )
                    LEFT JOIN `partiresul` pr_compañero ON pr1.id_torneo = pr_compañero.id_torneo 
                        AND pr1.partida = pr_compañero.partida 
                        AND pr1.mesa = pr_compañero.mesa
                        AND pr_compañero.id_usuario != pr1.id_usuario
                        AND (
                            (pr1.secuencia IN (1, 2) AND pr_compañero.secuencia IN (1, 2) AND pr_compañero.secuencia != pr1.secuencia) OR
                            (pr1.secuencia IN (3, 4) AND pr_compañero.secuencia IN (3, 4) AND pr_compañero.secuencia != pr1.secuencia)
                        )
                    WHERE pr1.id_usuario = i.id_usuario
                        AND pr1.id_torneo = ?
                        AND pr1.registrado = 1
                        AND pr1.ff = 0
                        AND pr1.resultado1 = 200
                        AND pr1.efectividad = 100
                        AND pr1.resultado1 > pr1.resultado2
                        AND (
                            -- Caso 1: Oponente tiene forfait
                            pr_oponente.ff = 1 OR
                            -- Caso 2: Compañero tiene forfait (el jugador ganó por forfait de su compañero)
                            pr_compañero.ff = 1
                        )
                ) as ganadas_por_forfait,
                (
                    SELECT COUNT(*)
                    FROM partiresul pbye
                    WHERE pbye.id_usuario = i.id_usuario
                        AND pbye.id_torneo = ?
                        AND pbye.registrado = 1
                        AND pbye.mesa = 0
                        AND pbye.resultado1 > pbye.resultado2
                ) as partidas_bye,
                COALESCE(
                    (SELECT MAX(pr.tarjeta) FROM partiresul pr
                     WHERE pr.id_torneo = i.torneo_id AND pr.id_usuario = i.id_usuario AND pr.registrado = 1),
                    i.tarjeta,
                    0
                ) AS tarjeta
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            LEFT JOIN clubes c ON i.id_club = c.id
            LEFT JOIN equipos e ON i.torneo_id = e.id_torneo AND i.codigo_equipo = e.codigo_equipo AND e.estatus = 0
            WHERE i.torneo_id = ?
            ORDER BY (CASE WHEN (i.estatus = 1 OR i.estatus = 'confirmado') THEN 0 ELSE 1 END) ASC,
                     i.posicion ASC, i.ganados DESC, i.efectividad DESC, i.puntos DESC, i.id ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $torneo_id, $torneo_id]);
    $posiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Asegurar que todos los jugadores tengan el nombre del equipo si tienen codigo_equipo
    foreach ($posiciones as &$pos) {
        if (empty($pos['nombre_equipo']) && !empty($pos['codigo_equipo'])) {
            // Si no tiene nombre_equipo pero tiene codigo_equipo, construir uno
            $pos['nombre_equipo'] = 'Equipo ' . $pos['codigo_equipo'];
        }
    }
    unset($pos);
    
    return [
        'torneo' => $torneo,
        'posiciones' => $posiciones,
        'es_modalidad_equipos' => $es_modalidad_equipos
    ];
}

/**
 * Obtiene datos de inscripciones de un torneo
 */
function obtenerDatosInscripciones($torneo_id) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT t.*, COALESCE(o.nombre, c.nombre) AS club_nombre
            FROM tournaments t
            LEFT JOIN organizaciones o ON t.club_responsable = o.id
            LEFT JOIN clubes c ON t.club_responsable = c.id
            WHERE t.id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si el torneo ha iniciado (tiene rondas generadas)
    $rondas_generadas = obtenerRondasGeneradas($torneo_id);
    $torneo_iniciado = !empty($rondas_generadas);
    // Confirmar/Retirar: permitido mientras el torneo no esté cerrado (locked)
    $torneo_cerrado = (int)($torneo['locked'] ?? 0) === 1;
    $puede_confirmar_retirar = !$torneo_cerrado;
    
    // Obtener TODOS los inscritos del torneo (cualquier estatus) para confirmar o retirar
    $sql = "SELECT 
                i.*,
                u.nombre as nombre_completo,
                u.username,
                u.sexo,
                c.nombre as nombre_club,
                c.id as club_id
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            LEFT JOIN clubes c ON i.id_club = c.id
            WHERE i.torneo_id = ?
            ORDER BY c.nombre ASC, u.nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id]);
    $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas
    $total_inscritos = count($inscritos);
    $confirmados = 0;
    $hombres = 0;
    $mujeres = 0;
    
    foreach ($inscritos as $inscrito) {
        if (InscritosHelper::esConfirmado($inscrito['estatus'])) {
            $confirmados++;
        }
        if ($inscrito['sexo'] == 1 || strtoupper($inscrito['sexo']) === 'M') {
            $hombres++;
        } elseif ($inscrito['sexo'] == 2 || strtoupper($inscrito['sexo']) === 'F') {
            $mujeres++;
        }
    }
    
    // Resumen por club
    $resumen_clubes = [];
    foreach ($inscritos as $inscrito) {
        $club_id = $inscrito['club_id'] ?? 0;
        $club_nombre = $inscrito['nombre_club'] ?? 'Sin Club';
        
        if (!isset($resumen_clubes[$club_id])) {
            $resumen_clubes[$club_id] = [
                'id' => $club_id,
                'nombre' => $club_nombre,
                'total' => 0,
                'hombres' => 0,
                'mujeres' => 0
            ];
        }
        
        $resumen_clubes[$club_id]['total']++;
        if ($inscrito['sexo'] == 1 || strtoupper($inscrito['sexo']) === 'M') {
            $resumen_clubes[$club_id]['hombres']++;
        } elseif ($inscrito['sexo'] == 2 || strtoupper($inscrito['sexo']) === 'F') {
            $resumen_clubes[$club_id]['mujeres']++;
        }
    }
    
    return [
        'torneo' => $torneo,
        'inscritos' => $inscritos,
        'total_inscritos' => $total_inscritos,
        'confirmados' => $confirmados,
        'hombres' => $hombres,
        'mujeres' => $mujeres,
        'resumen_clubes' => array_values($resumen_clubes),
        'torneo_iniciado' => $torneo_iniciado,
        'puede_confirmar_retirar' => $puede_confirmar_retirar
    ];
}

/**
 * Obtiene datos para la pantalla de notificaciones del torneo (plantillas + inscritos)
 */
function obtenerDatosNotificacionesTorneo($torneo_id) {
    $pdo = DB::pdo();
    $stmt = $pdo->prepare("SELECT t.*, o.nombre as organizacion_nombre FROM tournaments t LEFT JOIN organizaciones o ON t.club_responsable = o.id WHERE t.id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$torneo) {
        return ['torneo' => null, 'plantillas' => [], 'ultima_ronda' => 0, 'total_inscritos' => 0];
    }
    require_once __DIR__ . '/../lib/NotificationManager.php';
    $nm = new NotificationManager($pdo);
    $plantillas = $nm->listarPlantillas('torneo');
    $ultima_ronda = 0;
    try {
        $modalidad = (int)($torneo['modalidad'] ?? 0);
        if ($modalidad === 3) {
            require_once __DIR__ . '/../config/MesaAsignacionEquiposService.php';
            $mesaService = new MesaAsignacionEquiposService();
        } else {
            require_once __DIR__ . '/../config/MesaAsignacionService.php';
            $mesaService = new MesaAsignacionService();
        }
        $ultima_ronda = $mesaService->obtenerUltimaRonda($torneo_id);
    } catch (Exception $e) {}
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO);
    $stmt->execute([$torneo_id]);
    $total_inscritos = (int) $stmt->fetchColumn();

    $inscritos_prueba = [];
    if ($total_inscritos > 0) {
        $ronda_ref = $ultima_ronda > 0 ? $ultima_ronda : 1;
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.telegram_chat_id,
                   COALESCE(i.posicion, 0) AS posicion, COALESCE(i.ganados, 0) AS ganados, COALESCE(i.perdidos, 0) AS perdidos,
                   COALESCE(i.efectividad, 0) AS efectividad, COALESCE(i.puntos, 0) AS puntos
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            WHERE i.torneo_id = ? AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('i') . "
            ORDER BY i.id
            LIMIT 50
        ");
        $stmt->execute([$torneo_id]);
        $inscritos_prueba = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mesaPareja = [];
        $stmtMesa = $pdo->prepare("
            SELECT pr.id_usuario, pr.mesa, pr_p.id_usuario AS pareja_id, u_pareja.nombre AS pareja_nombre
            FROM partiresul pr
            LEFT JOIN partiresul pr_p ON pr_p.id_torneo = pr.id_torneo AND pr_p.partida = pr.partida AND pr_p.mesa = pr.mesa
                AND pr_p.secuencia = CASE pr.secuencia WHEN 1 THEN 2 WHEN 2 THEN 1 WHEN 3 THEN 4 WHEN 4 THEN 3 END
            LEFT JOIN usuarios u_pareja ON u_pareja.id = pr_p.id_usuario
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
        ");
        $stmtMesa->execute([$torneo_id, $ronda_ref]);
        while ($row = $stmtMesa->fetch(PDO::FETCH_ASSOC)) {
            $mesaPareja[(int)$row['id_usuario']] = [
                'mesa' => (string)$row['mesa'],
                'pareja_id' => (int)($row['pareja_id'] ?? 0),
                'pareja' => trim((string)($row['pareja_nombre'] ?? '')) ?: '—',
            ];
        }
        require_once __DIR__ . '/../lib/app_helpers.php';
        foreach ($inscritos_prueba as &$ins) {
            $uid = (int)$ins['id'];
            $ins['mesa'] = $mesaPareja[$uid]['mesa'] ?? '—';
            $ins['pareja_id'] = $mesaPareja[$uid]['pareja_id'] ?? 0;
            $ins['pareja'] = $mesaPareja[$uid]['pareja'] ?? '—';
            $ins['url_resumen'] = AppHelpers::url('index.php', ['page' => 'torneo_gestion', 'action' => 'resumen_individual', 'torneo_id' => $torneo_id, 'inscrito_id' => $uid, 'from' => 'notificaciones']);
        }
        unset($ins);
    }

    return [
        'torneo' => $torneo,
        'torneo_id' => (int) $torneo_id,
        'plantillas' => $plantillas,
        'ultima_ronda' => $ultima_ronda,
        'total_inscritos' => $total_inscritos,
        'inscritos_prueba' => $inscritos_prueba,
    ];
}

/**
 * Envía notificación masiva según plantilla: a inscritos del torneo o a todos los usuarios del administrador.
 * Si POST prueba=1 e inscrito_id=X, envía solo una notificación de prueba a ese inscrito (con prefijo [Prueba]).
 */
function enviarNotificacionTorneo($torneo_id, $user_id, $is_admin_general) {
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: ' . buildRedirectUrl('notificaciones', ['torneo_id' => $torneo_id]));
        exit;
    }
    $pdo = DB::pdo();
    $clave_plantilla = trim((string)($_POST['plantilla_clave'] ?? ''));
    $ronda = (int)($_POST['ronda'] ?? 0);
    $es_prueba = !empty($_POST['prueba']);
    $inscrito_id_prueba = $es_prueba ? (int)($_POST['inscrito_id'] ?? 0) : 0;

    if ($clave_plantilla === '') {
        $_SESSION['error'] = 'Debe seleccionar una plantilla.';
        header('Location: ' . buildRedirectUrl('notificaciones', ['torneo_id' => $torneo_id]));
        exit;
    }

    require_once __DIR__ . '/../lib/NotificationManager.php';
    $nm = new NotificationManager($pdo);
    $plantilla = $nm->obtenerPlantilla($clave_plantilla);
    if (!$plantilla) {
        $_SESSION['error'] = 'Plantilla no encontrada.';
        header('Location: ' . buildRedirectUrl('notificaciones', ['torneo_id' => $torneo_id]));
        exit;
    }

    if ($es_prueba && $inscrito_id_prueba > 0) {
        enviarNotificacionPrueba($pdo, $nm, $torneo_id, $inscrito_id_prueba, $plantilla, $ronda);
        $_SESSION['success'] = 'Notificación de prueba encolada para 1 inscrito. Revisa la campanita con ese usuario.';
        header('Location: ' . buildRedirectUrl('notificaciones', ['torneo_id' => $torneo_id]));
        exit;
    }

    $stmt = $pdo->prepare("SELECT nombre, club_responsable FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $torneo_nombre = $torneo_row['nombre'] ?? 'Torneo';
    $club_responsable = (int)($torneo_row['club_responsable'] ?? 0);

    $destinatarios = isset($plantilla['destinatarios']) ? trim((string)$plantilla['destinatarios']) : 'inscritos';
    if ($destinatarios !== 'todos_usuarios_admin') {
        $destinatarios = 'inscritos';
    }

    if ($destinatarios === 'todos_usuarios_admin') {
        require_once __DIR__ . '/../lib/ClubHelper.php';
        $club_ids = ClubHelper::getClubesSupervised($club_responsable);
        if (empty($club_ids)) {
            $club_ids = [$club_responsable];
        }
        $placeholders = implode(',', array_fill(0, count($club_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT id, nombre, telegram_chat_id
            FROM usuarios
            WHERE club_id IN ($placeholders) AND role = 'usuario' AND status = 0
        ");
        $stmt->execute(array_values($club_ids));
        $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($jugadores)) {
            $_SESSION['error'] = 'No hay usuarios en los clubes del administrador.';
            header('Location: ' . buildRedirectUrl('notificaciones', ['torneo_id' => $torneo_id]));
            exit;
        }
        $items = [];
        foreach ($jugadores as $j) {
            $mensaje = $nm->procesarMensaje($plantilla['cuerpo_mensaje'], [
                'nombre' => (string)($j['nombre'] ?? ''),
                'ronda' => (string)$ronda,
                'torneo' => $torneo_nombre,
                'ganados' => '—',
                'perdidos' => '—',
                'efectividad' => '—',
                'puntos' => '—',
                'mesa' => '—',
                'pareja' => '—',
            ]);
            $items[] = [
                'id' => (int)$j['id'],
                'telegram_chat_id' => trim((string)($j['telegram_chat_id'] ?? '')) ?: null,
                'mensaje' => $mensaje,
                'url_destino' => '',
            ];
        }
    } else {
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.telegram_chat_id,
                   COALESCE(i.ganados, 0) AS ganados, COALESCE(i.perdidos, 0) AS perdidos,
                   COALESCE(i.efectividad, 0) AS efectividad, COALESCE(i.puntos, 0) AS puntos
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            WHERE i.torneo_id = ? AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('i') . "
        ");
        $stmt->execute([$torneo_id]);
        $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($jugadores)) {
            $_SESSION['error'] = 'No hay inscritos activos en este torneo.';
            header('Location: ' . buildRedirectUrl('notificaciones', ['torneo_id' => $torneo_id]));
            exit;
        }

        $mesaPareja = [];
        if ($ronda > 0) {
            $stmtMesa = $pdo->prepare("
                SELECT pr.id_usuario, pr.mesa, u_pareja.nombre AS pareja_nombre
                FROM partiresul pr
                LEFT JOIN partiresul pr_p ON pr_p.id_torneo = pr.id_torneo AND pr_p.partida = pr.partida AND pr_p.mesa = pr.mesa
                    AND pr_p.secuencia = CASE pr.secuencia WHEN 1 THEN 2 WHEN 2 THEN 1 WHEN 3 THEN 4 WHEN 4 THEN 3 END
                LEFT JOIN usuarios u_pareja ON u_pareja.id = pr_p.id_usuario
                WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
            ");
            $stmtMesa->execute([$torneo_id, $ronda]);
            while ($row = $stmtMesa->fetch(PDO::FETCH_ASSOC)) {
                $mesaPareja[(int)$row['id_usuario']] = [
                    'mesa' => (string)$row['mesa'],
                    'pareja' => trim((string)($row['pareja_nombre'] ?? '')) ?: '—',
                ];
            }
        }

        require_once __DIR__ . '/../lib/app_helpers.php';
        $items = [];
        foreach ($jugadores as $j) {
            $uid = (int)$j['id'];
            $mp = $mesaPareja[$uid] ?? null;
            $url_resumen = AppHelpers::url('index.php', ['page' => 'torneo_gestion', 'action' => 'resumen_individual', 'torneo_id' => $torneo_id, 'inscrito_id' => $uid, 'from' => 'notificaciones']);
            $mensaje = $nm->procesarMensaje($plantilla['cuerpo_mensaje'], [
                'nombre' => (string)($j['nombre'] ?? ''),
                'ronda' => (string)$ronda,
                'torneo' => $torneo_nombre,
                'ganados' => (string)($j['ganados'] ?? '0'),
                'perdidos' => (string)($j['perdidos'] ?? '0'),
                'efectividad' => (string)($j['efectividad'] ?? '0'),
                'puntos' => (string)($j['puntos'] ?? '0'),
                'mesa' => $mp ? (string)$mp['mesa'] : '—',
                'pareja' => $mp ? (string)$mp['pareja'] : '—',
                'url_resumen' => $url_resumen,
            ]);
            $items[] = [
                'id' => $uid,
                'telegram_chat_id' => trim((string)($j['telegram_chat_id'] ?? '')) ?: null,
                'mensaje' => $mensaje,
                'url_destino' => $url_resumen,
            ];
        }
    }

    $nm->programarMasivoPersonalizado($items);
    $_SESSION['success'] = 'Notificaciones encoladas: ' . count($items) . ' mensaje(s). Se enviarán por Telegram y aparecerán en la campanita web.';
    header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
    exit;
}

/**
 * Envía una sola notificación de prueba a un inscrito (datos reales de inscritos).
 */
function enviarNotificacionPrueba(PDO $pdo, NotificationManager $nm, int $torneo_id, int $inscrito_id, array $plantilla, int $ronda): void {
    $stmt = $pdo->prepare("SELECT nombre FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo_nombre = $stmt->fetchColumn() ?: 'Torneo';
    $stmt = $pdo->prepare("
        SELECT u.id, u.nombre, u.telegram_chat_id,
               COALESCE(i.posicion, 0) AS posicion, COALESCE(i.ganados, 0) AS ganados, COALESCE(i.perdidos, 0) AS perdidos,
               COALESCE(i.efectividad, 0) AS efectividad, COALESCE(i.puntos, 0) AS puntos
        FROM inscritos i
        INNER JOIN usuarios u ON i.id_usuario = u.id
        WHERE i.torneo_id = ? AND i.id_usuario = ? AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('i') . "
    ");
    $stmt->execute([$torneo_id, $inscrito_id]);
    $j = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$j) {
        $_SESSION['error'] = 'Inscrito no encontrado.';
        return;
    }
    $mesaPareja = [];
    if ($ronda > 0) {
        $stmtMesa = $pdo->prepare("
            SELECT pr.id_usuario, pr.mesa, pr_p.id_usuario AS pareja_id, u_pareja.nombre AS pareja_nombre
            FROM partiresul pr
            LEFT JOIN partiresul pr_p ON pr_p.id_torneo = pr.id_torneo AND pr_p.partida = pr.partida AND pr_p.mesa = pr.mesa
                AND pr_p.secuencia = CASE pr.secuencia WHEN 1 THEN 2 WHEN 2 THEN 1 WHEN 3 THEN 4 WHEN 4 THEN 3 END
            LEFT JOIN usuarios u_pareja ON u_pareja.id = pr_p.id_usuario
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0 AND pr.id_usuario = ?
        ");
        $stmtMesa->execute([$torneo_id, $ronda, $inscrito_id]);
        $row = $stmtMesa->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $mesaPareja = [
                'mesa' => (string)$row['mesa'],
                'pareja_id' => (int)($row['pareja_id'] ?? 0),
                'pareja' => trim((string)($row['pareja_nombre'] ?? '')) ?: '—',
            ];
        }
    }
    require_once __DIR__ . '/../lib/app_helpers.php';
    $url_resumen = AppHelpers::url('index.php', ['page' => 'torneo_gestion', 'action' => 'resumen_individual', 'torneo_id' => $torneo_id, 'inscrito_id' => $inscrito_id, 'from' => 'notificaciones']);
    $url_clasificacion = AppHelpers::url('index.php', ['page' => 'torneo_gestion', 'action' => 'posiciones', 'torneo_id' => $torneo_id, 'from' => 'notificaciones']);
    $mensaje = $nm->procesarMensaje($plantilla['cuerpo_mensaje'], [
        'nombre' => (string)($j['nombre'] ?? ''),
        'ronda' => (string)$ronda,
        'torneo' => $torneo_nombre,
        'ganados' => (string)($j['ganados'] ?? '0'),
        'perdidos' => (string)($j['perdidos'] ?? '0'),
        'efectividad' => (string)($j['efectividad'] ?? '0'),
        'puntos' => (string)($j['puntos'] ?? '0'),
        'mesa' => $mesaPareja['mesa'] ?? '—',
        'pareja' => $mesaPareja['pareja'] ?? '—',
        'url_resumen' => $url_resumen,
    ]);
    $nm->programarMasivoPersonalizado([[
        'id' => (int)$j['id'],
        'telegram_chat_id' => trim((string)($j['telegram_chat_id'] ?? '')) ?: null,
        'mensaje' => '[Prueba] ' . $mensaje,
        'url_destino' => $url_resumen,
        'datos_json' => [
            'tipo' => 'nueva_ronda',
            'ronda' => (string) $ronda,
            'mesa' => $mesaPareja['mesa'] ?? '—',
            'usuario_id' => (int)$j['id'],
            'nombre' => (string)($j['nombre'] ?? ''),
            'pareja_id' => (int)($mesaPareja['pareja_id'] ?? 0),
            'pareja_nombre' => $mesaPareja['pareja'] ?? '—',
            'posicion' => (string)($j['posicion'] ?? '0'),
            'ganados' => (string)($j['ganados'] ?? '0'),
            'perdidos' => (string)($j['perdidos'] ?? '0'),
            'efectividad' => (string)($j['efectividad'] ?? '0'),
            'puntos' => (string)($j['puntos'] ?? '0'),
            'url_resumen' => $url_resumen,
            'url_clasificacion' => $url_clasificacion,
        ],
    ]]);
}

/**
 * Envía notificaciones (web + Telegram) a los 4 jugadores de una mesa tras registrar resultados.
 * Mensaje: atleta id/nombre, ganó/perdió ronda X mesa Y, resultados R1 a R2; si aplica sanción y/o tarjeta; "Si no está conforme notifique a mesa técnica."
 *
 * @param PDO $pdo
 * @param int $torneo_id
 * @param int $ronda
 * @param int $mesa
 */
function enviarNotificacionesResultadosMesa(PDO $pdo, int $torneo_id, int $ronda, int $mesa): void {
    $hasTg = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'telegram_chat_id'")->rowCount() > 0;
    $sql = "SELECT pr.id_usuario, pr.resultado1, pr.resultado2, pr.sancion, pr.tarjeta,
            u.nombre" . ($hasTg ? ", u.telegram_chat_id" : "") . "
            FROM partiresul pr
            INNER JOIN usuarios u ON u.id = pr.id_usuario
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ? AND pr.registrado = 1
            ORDER BY pr.secuencia";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda, $mesa]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($filas) === 0) return;

    require_once __DIR__ . '/../lib/app_helpers.php';
    require_once __DIR__ . '/../lib/NotificationManager.php';
    $nm = new NotificationManager($pdo);

    $tarjetaTexto = [1 => 'Amarilla', 3 => 'Roja', 4 => 'Negra'];
    $items = [];
    foreach ($filas as $row) {
        $id_usuario = (int)$row['id_usuario'];
        $nombre = trim((string)($row['nombre'] ?? ''));
        $r1 = (int)($row['resultado1'] ?? 0);
        $r2 = (int)($row['resultado2'] ?? 0);
        $sancion = (int)($row['sancion'] ?? 0);
        $tarjeta = (int)($row['tarjeta'] ?? 0);
        $ganado = $r1 > $r2;
        $textoResultado = $ganado ? 'ganado' : 'perdido';
        $mensaje = "Atleta {$id_usuario}, {$nombre}, usted ha {$textoResultado} la ronda número {$ronda} en la mesa {$mesa}, con los siguientes resultados: {$r1} a {$r2}.";
        if ($sancion > 0 || $tarjeta > 0) {
            $partes = [];
            if ($sancion > 0) $partes[] = "sancionado con {$sancion} pts";
            if ($tarjeta > 0) $partes[] = "tarjeta " . ($tarjetaTexto[$tarjeta] ?? $tarjeta);
            $mensaje .= " " . ucfirst(implode(" y ", $partes)) . ".";
        }
        $mensaje .= " Si no está conforme notifique a mesa técnica.";

        $url_resumen = AppHelpers::url('index.php', [
            'page' => 'torneo_gestion',
            'action' => 'resumen_individual',
            'torneo_id' => $torneo_id,
            'inscrito_id' => $id_usuario,
            'from' => 'notificaciones',
        ]);
        $url_clasificacion = AppHelpers::url('index.php', [
            'page' => 'torneo_gestion',
            'action' => 'posiciones',
            'torneo_id' => $torneo_id,
            'from' => 'notificaciones',
        ]);
        $tarjetaStr = $tarjeta > 0 ? ($tarjetaTexto[$tarjeta] ?? (string)$tarjeta) : '';
        $items[] = [
            'id' => $id_usuario,
            'telegram_chat_id' => $hasTg && !empty(trim((string)($row['telegram_chat_id'] ?? ''))) ? trim((string)$row['telegram_chat_id']) : null,
            'mensaje' => $mensaje,
            'url_destino' => $url_resumen,
            'datos_json' => [
                'tipo' => 'resultados_mesa',
                'ronda' => (string)$ronda,
                'mesa' => (string)$mesa,
                'usuario_id' => $id_usuario,
                'nombre' => $nombre,
                'resultado_texto' => $textoResultado,
                'resultado1' => (string)$r1,
                'resultado2' => (string)$r2,
                'sancion' => (string)$sancion,
                'tarjeta_texto' => $tarjetaStr,
                'url_resumen' => $url_resumen,
                'url_clasificacion' => $url_clasificacion,
            ],
        ];
    }
    if (!empty($items)) {
        $nm->programarMasivoPersonalizado($items);
    }
}

/**
 * Envía notificaciones a los 4 jugadores tras APROBAR un acta QR.
 * Mensaje con cláusula de veracidad: resultado definitivo, revisión ante juez en 2 rondas.
 *
 * @param PDO $pdo
 * @param int $torneo_id
 * @param int $ronda
 * @param int $mesa
 */
function enviarNotificacionesResultadosAprobados(PDO $pdo, int $torneo_id, int $ronda, int $mesa): void {
    $hasTg = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'telegram_chat_id'")->rowCount() > 0;
    $sql = "SELECT pr.id_usuario, pr.resultado1, pr.resultado2, pr.sancion, pr.tarjeta,
            u.nombre" . ($hasTg ? ", u.telegram_chat_id" : "") . "
            FROM partiresul pr
            INNER JOIN usuarios u ON u.id = pr.id_usuario
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ? AND pr.registrado = 1
            ORDER BY pr.secuencia";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda, $mesa]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($filas) === 0) return;

    require_once __DIR__ . '/../lib/app_helpers.php';
    require_once __DIR__ . '/../lib/NotificationManager.php';
    $nm = new NotificationManager($pdo);

    $tarjetaTexto = [1 => 'Amarilla', 3 => 'Roja', 4 => 'Negra'];
    $items = [];
    foreach ($filas as $row) {
        $id_usuario = (int)$row['id_usuario'];
        $nombre = trim((string)($row['nombre'] ?? ''));
        $r1 = (int)($row['resultado1'] ?? 0);
        $r2 = (int)($row['resultado2'] ?? 0);
        $sancion = (int)($row['sancion'] ?? 0);
        $tarjeta = (int)($row['tarjeta'] ?? 0);
        $puntos = "{$r1} a {$r2}";
        if ($sancion > 0 || $tarjeta > 0) {
            $partes = [];
            if ($sancion > 0) $partes[] = "sancion {$sancion} pts";
            if ($tarjeta > 0) $partes[] = "tarjeta " . ($tarjetaTexto[$tarjeta] ?? $tarjeta);
            $puntos .= " (" . implode(", ", $partes) . ")";
        }
        $mensaje = "Resultados registrados: {$puntos}. Nota: Pasadas dos rondas, se tomará como verídico este resultado. Cualquier discrepancia debe ser reportada físicamente ante la mesa de control antes de ese plazo.";

        $url_resumen = AppHelpers::url('index.php', [
            'page' => 'torneo_gestion',
            'action' => 'resumen_individual',
            'torneo_id' => $torneo_id,
            'inscrito_id' => $id_usuario,
            'from' => 'notificaciones',
        ]);
        $url_clasificacion = AppHelpers::url('index.php', [
            'page' => 'torneo_gestion',
            'action' => 'posiciones',
            'torneo_id' => $torneo_id,
            'from' => 'notificaciones',
        ]);
        $tarjetaStr = $tarjeta > 0 ? ($tarjetaTexto[$tarjeta] ?? (string)$tarjeta) : '';
        $items[] = [
            'id' => $id_usuario,
            'telegram_chat_id' => $hasTg && !empty(trim((string)($row['telegram_chat_id'] ?? ''))) ? trim((string)$row['telegram_chat_id']) : null,
            'mensaje' => $mensaje,
            'url_destino' => $url_resumen,
            'datos_json' => [
                'tipo' => 'resultados_aprobados',
                'ronda' => (string)$ronda,
                'mesa' => (string)$mesa,
                'usuario_id' => $id_usuario,
                'nombre' => $nombre,
                'resultado1' => (string)$r1,
                'resultado2' => (string)$r2,
                'sancion' => (string)$sancion,
                'tarjeta_texto' => $tarjetaStr,
                'url_resumen' => $url_resumen,
                'url_clasificacion' => $url_clasificacion,
            ],
        ];
    }
    if (!empty($items)) {
        $nm->programarMasivoPersonalizado($items);
    }
}

/**
 * Obtiene datos de equipos para el administrador
 */
function obtenerDatosEquiposAdmin($torneo_id) {
    require_once __DIR__ . '/../lib/EquiposHelper.php';
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener todos los equipos del torneo (de todos los clubes)
    $stmt = $pdo->prepare("
        SELECT e.*, c.nombre as nombre_club,
               (SELECT COUNT(*) FROM inscritos i WHERE i.torneo_id = e.id_torneo AND i.codigo_equipo = e.codigo_equipo AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('i') . ") AS total_jugadores
        FROM equipos e
        LEFT JOIN clubes c ON e.id_club = c.id
        WHERE e.id_torneo = ?
        ORDER BY c.nombre ASC, e.nombre_equipo ASC
    ");
    $stmt->execute([$torneo_id]);
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por club
    $equipos_por_club = [];
    foreach ($equipos as $equipo) {
        $club_id = $equipo['id_club'];
        if (!isset($equipos_por_club[$club_id])) {
            $equipos_por_club[$club_id] = [
                'nombre' => $equipo['nombre_club'] ?? 'Sin Club',
                'equipos' => []
            ];
        }
        $equipos_por_club[$club_id]['equipos'][] = $equipo;
    }
    
    return [
        'torneo' => $torneo,
        'equipos' => $equipos,
        'equipos_por_club' => $equipos_por_club,
        'total_equipos' => count($equipos)
    ];
}


/**
 * Obtiene datos para el panel de control de torneos por equipos
 */
function obtenerDatosPanelEquipos($torneo_id) {
    require_once __DIR__ . '/../lib/EquiposHelper.php';
    
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$torneo) {
        throw new Exception('Torneo no encontrado');
    }
    
    // Obtener total de equipos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipos WHERE id_torneo = ?");
    $stmt->execute([$torneo_id]);
    $total_equipos = (int)$stmt->fetchColumn();
    
    // Obtener total de jugadores inscritos (con codigo_equipo)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND codigo_equipo IS NOT NULL AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO);
    $stmt->execute([$torneo_id]);
    $total_jugadores_inscritos = (int)$stmt->fetchColumn();
    
    // Obtener total de clubes con equipos
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id_club) FROM equipos WHERE id_torneo = ?");
    $stmt->execute([$torneo_id]);
    $total_clubes_con_equipos = (int)$stmt->fetchColumn();
    
    // Obtener jugadores disponibles (NO inscritos - sin codigo_equipo y no retirados)
    $current_user = Auth::user();
    $user_club_id_raw = Auth::getUserClubId();
    $user_club_id = ($user_club_id_raw !== null && (int)$user_club_id_raw > 0) ? (int)$user_club_id_raw : null;
    $is_admin_general = Auth::isAdminGeneral();
    $is_admin_club = Auth::isAdminClub();
    
    $jugadores_disponibles = [];
    
    if ($is_admin_general) {
        // Admin general: todos los usuarios que no están inscritos
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM usuarios u
            LEFT JOIN inscritos ins ON ins.id_usuario = u.id AND ins.torneo_id = ? AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('ins') . "
            WHERE u.role = 'usuario' 
              AND u.status = 0
              AND (ins.id IS NULL OR ins.codigo_equipo IS NULL)
        ");
        $stmt->execute([$torneo_id]);
        $total_jugadores_disponibles = (int)$stmt->fetchColumn();
    } else if ($user_club_id) {
        // Admin club o usuario: jugadores del territorio que no están inscritos
        if ($is_admin_club) {
            require_once __DIR__ . '/../lib/ClubHelper.php';
            $clubes_supervisados = ClubHelper::getClubesSupervised($user_club_id);
            $clubes_ids = array_merge([$user_club_id], $clubes_supervisados);
        } else {
            $clubes_ids = [$user_club_id];
        }
        
        if (!empty($clubes_ids)) {
            $placeholders = str_repeat('?,', count($clubes_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM usuarios u
                LEFT JOIN inscritos ins ON ins.id_usuario = u.id AND ins.torneo_id = ? AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('ins') . "
                WHERE u.role = 'usuario' 
                  AND u.status = 0
                  AND u.club_id IN ({$placeholders})
                  AND (ins.id IS NULL OR ins.codigo_equipo IS NULL)
            ");
            $stmt->execute(array_merge([$torneo_id], $clubes_ids));
            $total_jugadores_disponibles = (int)$stmt->fetchColumn();
        } else {
            $total_jugadores_disponibles = 0;
        }
    } else {
        $total_jugadores_disponibles = 0;
    }
    
    // Obtener información de rondas (igual que panel individual)
    $rondas_generadas = obtenerRondasGeneradas($torneo_id);
    $ultima_ronda = !empty($rondas_generadas) ? max(array_column($rondas_generadas, 'num_ronda')) : 0;
    $proxima_ronda = $ultima_ronda + 1;
    
    // Calcular si se puede generar la próxima ronda
    $puede_generar = true;
    $mesas_incompletas = 0;
    $total_mesas_ronda = 0;
    if ($ultima_ronda > 0) {
        $mesas_incompletas = contarMesasIncompletas($torneo_id, $ultima_ronda);
        $puede_generar = $mesas_incompletas === 0;
        
        // Contar total de mesas de la última ronda
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT mesa) FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa > 0");
        $stmt->execute([$torneo_id, $ultima_ronda]);
        $total_mesas_ronda = (int)$stmt->fetchColumn();
    }
    
    // Estadísticas adicionales
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM partiresul WHERE id_torneo = ? AND registrado = 1");
    $stmt->execute([$torneo_id]);
    $total_partidas = (int)$stmt->fetchColumn();
    
    // Obtener información del club responsable
    $club_nombre = 'N/A';
    if (!empty($torneo['club_responsable'])) {
        $stmt = $pdo->prepare("SELECT nombre FROM clubes WHERE id = ?");
        $stmt->execute([$torneo['club_responsable']]);
        $club = $stmt->fetch(PDO::FETCH_ASSOC);
        $club_nombre = $club['nombre'] ?? 'N/A';
    }
    $torneo['club_nombre'] = $club_nombre;
    
    return [
        'torneo' => $torneo,
        'total_equipos' => $total_equipos,
        'total_jugadores_inscritos' => $total_jugadores_inscritos,
        'total_clubes_con_equipos' => $total_clubes_con_equipos,
        'total_jugadores_disponibles' => $total_jugadores_disponibles,
        'jugadores_por_equipo' => max(2, (int)($torneo['pareclub'] ?? 4)),
        // Información de rondas
        'rondas_generadas' => $rondas_generadas,
        'ultima_ronda' => $ultima_ronda,
        'proxima_ronda' => $proxima_ronda,
        'puede_generar_ronda' => $puede_generar,
        'mesas_incompletas' => $mesas_incompletas,
        'estadisticas' => [
            'total_equipos' => $total_equipos,
            'total_jugadores' => $total_jugadores_inscritos,
            'total_partidas' => $total_partidas,
            'mesas_ronda' => $total_mesas_ronda
        ]
    ];
}

/**
 * Obtiene datos para gestionar inscripciones de equipos (listado completo y por club)
 */
function obtenerDatosGestionarInscripcionesEquipos($torneo_id) {
    require_once __DIR__ . '/../lib/EquiposHelper.php';
    
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$torneo) {
        throw new Exception('Torneo no encontrado');
    }
    
    // Obtener todos los equipos del torneo ordenados por club y código de equipo (secuencial)
    $stmt = $pdo->prepare("
        SELECT 
            e.*, 
            c.nombre as nombre_club,
            c.id as club_id
        FROM equipos e
        LEFT JOIN clubes c ON e.id_club = c.id
        WHERE e.id_torneo = ?
        ORDER BY 
            COALESCE(c.nombre, 'ZZZ') ASC,
            e.codigo_equipo ASC,
            e.nombre_equipo ASC
    ");
    $stmt->execute([$torneo_id]);
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar jugadores a cada equipo usando codigo_equipo desde inscritos
    foreach ($equipos as &$equipo) {
        $jugadores = [];
        if (!empty($equipo['codigo_equipo'])) {
            $stmt_jugadores = $pdo->prepare("
                SELECT 
                    i.id as id_inscrito,
                    i.id_usuario,
                    i.codigo_equipo,
                    u.cedula,
                    u.nombre,
                    u.id as usuario_id
                FROM inscritos i
                INNER JOIN usuarios u ON i.id_usuario = u.id
                WHERE i.torneo_id = ? 
                    AND i.codigo_equipo = ?
                    AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('i') . "
                ORDER BY i.id ASC
            ");
            $stmt_jugadores->execute([$torneo_id, $equipo['codigo_equipo']]);
            $jugadores = $stmt_jugadores->fetchAll(PDO::FETCH_ASSOC);
        }
        $equipo['jugadores'] = $jugadores;
        $equipo['total_jugadores'] = count($jugadores);
    }
    unset($equipo);
    
    // Agrupar equipos por club manteniendo el orden secuencial
    $equipos_por_club = [];
    $club_ids_orden = [];
    foreach ($equipos as $equipo) {
        $club_id = $equipo['club_id'] ?? 0;
        $club_nombre = $equipo['nombre_club'] ?? 'Sin Club';
        
        if (!isset($equipos_por_club[$club_id])) {
            $equipos_por_club[$club_id] = [
                'id' => $club_id,
                'nombre' => $club_nombre,
                'equipos' => []
            ];
            $club_ids_orden[] = $club_id;
        }
        $equipos_por_club[$club_id]['equipos'][] = $equipo;
    }
    
    // Reordenar equipos_por_club según el orden de club_ids_orden para mantener el orden secuencial
    $equipos_por_club_ordenado = [];
    foreach ($club_ids_orden as $club_id) {
        if (isset($equipos_por_club[$club_id])) {
            $equipos_por_club_ordenado[] = $equipos_por_club[$club_id];
        }
    }
    $equipos_por_club = $equipos_por_club_ordenado;
    
    return [
        'torneo' => $torneo,
        'equipos' => $equipos,
        'equipos_por_club' => $equipos_por_club,
        'jugadores_por_equipo' => max(2, (int)($torneo['pareclub'] ?? 4))
    ];
}

/**
 * Obtiene datos para inscribir equipos en sitio (solo jugadores NO inscritos + equipos registrados)
 */
function obtenerDatosInscribirEquipoSitio($torneo_id) {
    require_once __DIR__ . '/../lib/ClubHelper.php';
    require_once __DIR__ . '/../lib/EquiposHelper.php';
    require_once __DIR__ . '/../config/auth.php';
    
    $pdo = DB::pdo();
    
    $current_user = Auth::user();
    $user_club_id_raw = Auth::getUserClubId();
    $user_club_id = ($user_club_id_raw !== null && (int)$user_club_id_raw > 0) ? (int)$user_club_id_raw : null;
    $is_admin_general = Auth::isAdminGeneral();
    $is_admin_club = Auth::isAdminClub();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$torneo) {
        throw new Exception('Torneo no encontrado');
    }
    
    // Obtener jugadores NO inscritos (sin codigo_equipo) del territorio del administrador
    $jugadores_disponibles = [];
    
    if ($is_admin_general) {
        // Admin general: todos los usuarios que no están inscritos o no tienen codigo_equipo
        $stmt = $pdo->prepare("
            SELECT u.id as id_usuario, u.nombre, u.cedula, u.sexo,
                   u.club_id as club_id, c.nombre as club_nombre,
                   ins.id as id_inscrito
            FROM usuarios u
            LEFT JOIN clubes c ON u.club_id = c.id
            LEFT JOIN inscritos ins ON ins.id_usuario = u.id AND ins.torneo_id = ? AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('ins') . "
            WHERE u.role = 'usuario' 
              AND u.status = 0
              AND (ins.id IS NULL OR ins.codigo_equipo IS NULL)
            ORDER BY COALESCE(u.nombre, u.username) ASC
        ");
        $stmt->execute([$torneo_id]);
        $jugadores_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if ($user_club_id) {
        // Admin club o usuario: jugadores del territorio que no están inscritos
        if ($is_admin_club) {
            $clubes_supervisados = ClubHelper::getClubesSupervised($user_club_id);
            $clubes_ids = array_merge([$user_club_id], $clubes_supervisados);
        } else {
            $clubes_ids = [$user_club_id];
        }
        
        if (!empty($clubes_ids)) {
            $placeholders = str_repeat('?,', count($clubes_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT u.id as id_usuario, u.nombre, u.cedula, u.sexo,
                       u.club_id as club_id, c.nombre as club_nombre,
                       ins.id as id_inscrito
                FROM usuarios u
                LEFT JOIN clubes c ON u.club_id = c.id
                LEFT JOIN inscritos ins ON ins.id_usuario = u.id AND ins.torneo_id = ? AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('ins') . "
                WHERE u.role = 'usuario' 
                  AND u.status = 0
                  AND u.club_id IN ({$placeholders})
                  AND (ins.id IS NULL OR ins.codigo_equipo IS NULL)
                ORDER BY COALESCE(u.nombre, u.username) ASC
            ");
            $stmt->execute(array_merge([$torneo_id], $clubes_ids));
            $jugadores_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Agregar campo id para compatibilidad
    foreach ($jugadores_disponibles as &$jugador) {
        $jugador['id'] = $jugador['id_inscrito'] ?? null;
        $jugador['club_nombre'] = $jugador['club_nombre'] ?? 'Sin Club';
    }
    unset($jugador);
    
    // Obtener clubes disponibles para el formulario
    $clubes_disponibles = [];
    if ($is_admin_general) {
        $stmt = $pdo->query("SELECT id, nombre FROM clubes WHERE (estatus = 1 OR estatus = '1' OR estatus = 'activo') ORDER BY nombre ASC");
        $clubes_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if ($user_club_id) {
        if ($is_admin_club) {
            $clubes_disponibles = ClubHelper::getClubesSupervisedWithData($user_club_id);
            $club_ids = array_column($clubes_disponibles, 'id');
            if (!in_array($user_club_id, $club_ids)) {
                $stmt = $pdo->prepare("SELECT id, nombre FROM clubes WHERE id = ? AND (estatus = 1 OR estatus = '1' OR estatus = 'activo')");
                $stmt->execute([$user_club_id]);
                $club = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($club) {
                    array_unshift($clubes_disponibles, $club);
                }
            }
        } else {
            $stmt = $pdo->prepare("SELECT id, nombre FROM clubes WHERE id = ? AND (estatus = 1 OR estatus = '1' OR estatus = 'activo')");
            $stmt->execute([$user_club_id]);
            $club = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($club) {
                $clubes_disponibles = [$club];
            }
        }
    }
    
    // Obtener equipos registrados del torneo (solo código, club y nombre)
    $stmt = $pdo->prepare("
        SELECT e.id, e.codigo_equipo, e.nombre_equipo, e.id_club, c.nombre as nombre_club
        FROM equipos e
        LEFT JOIN clubes c ON e.id_club = c.id
        WHERE e.id_torneo = ?
        ORDER BY e.codigo_equipo ASC, e.nombre_equipo ASC
    ");
    $stmt->execute([$torneo_id]);
    $equipos_registrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'torneo' => $torneo,
        'jugadores_disponibles' => $jugadores_disponibles,
        'clubes_disponibles' => $clubes_disponibles,
        'equipos_registrados' => $equipos_registrados,
        'total_jugadores_disponibles' => count($jugadores_disponibles),
        'total_equipos' => count($equipos_registrados),
        'jugadores_por_equipo' => max(2, (int)($torneo['pareclub'] ?? 4))
    ];
}

/**
 * Obtiene datos para inscribir jugador en sitio.
 * Disponibles = todos los usuarios registrados bajo la entidad del torneo (no inscritos aún).
 * Inscritos = ya inscritos con estatus confirmado.
 */
function obtenerDatosInscribirSitio($torneo_id, $user_id, $is_admin_general) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT t.*, COALESCE(t.entidad, o.entidad) AS entidad_torneo
                           FROM tournaments t
                           LEFT JOIN organizaciones o ON o.id = t.club_responsable AND o.estatus = 1
                           WHERE t.id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $entidad_torneo = isset($torneo['entidad_torneo']) ? (int)$torneo['entidad_torneo'] : (int)($torneo['entidad'] ?? 0);
    unset($torneo['entidad_torneo']);
    
    // Usuarios disponibles = todos los de la entidad del torneo (role usuario, activos)
    // Pertenen a la entidad si: u.entidad = entidad_torneo O su club está en una org de esa entidad
    $usuarios_territorio = [];
    if ($entidad_torneo > 0) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.username, u.nombre, u.cedula, c.nombre as club_nombre, c.id as club_id
            FROM usuarios u
            LEFT JOIN clubes c ON u.club_id = c.id
            LEFT JOIN organizaciones o ON c.organizacion_id = o.id AND o.estatus = 1
            WHERE u.role = 'usuario'
              AND u.status = 0
              AND (u.entidad = ? OR o.entidad = ?)
            ORDER BY COALESCE(u.nombre, u.username) ASC
        ");
        $stmt->execute([$entidad_torneo, $entidad_torneo]);
        $usuarios_territorio = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Sin entidad en torneo: usuarios de la organización que organiza el torneo (club_responsable = org id)
        $org_id = (int)($torneo['club_responsable'] ?? 0);
        if ($org_id > 0) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.id, u.username, u.nombre, u.cedula, c.nombre as club_nombre, c.id as club_id
                FROM usuarios u
                LEFT JOIN clubes c ON u.club_id = c.id
                WHERE u.role = 'usuario'
                  AND u.status = 0
                  AND c.organizacion_id = ?
                ORDER BY COALESCE(u.nombre, u.username) ASC
            ");
            $stmt->execute([$org_id]);
            $usuarios_territorio = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Inscritos: solo confirmados (estatus = 'confirmado' o valor numérico 1)
    $stmt = $pdo->prepare("
        SELECT i.id_usuario, i.estatus, i.id_club,
               u.id, u.username, u.nombre, u.cedula, c.nombre as club_nombre
        FROM inscritos i
        LEFT JOIN usuarios u ON i.id_usuario = u.id
        LEFT JOIN clubes c ON i.id_club = c.id
        WHERE i.torneo_id = ?
          AND (i.estatus IN ('confirmado', 'solvente', 'no_solvente') OR i.estatus IN (1, 2, 3))
        ORDER BY COALESCE(u.nombre, u.username) ASC
    ");
    $stmt->execute([$torneo_id]);
    $usuarios_inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $usuarios_inscritos_ids = array_column($usuarios_inscritos, 'id_usuario');
    
    // Disponibles = usuarios de la entidad que aún no están inscritos (confirmados)
    $usuarios_disponibles = array_filter($usuarios_territorio, function($u) use ($usuarios_inscritos_ids) {
        return !in_array($u['id'], $usuarios_inscritos_ids);
    });
    
    // Clubes disponibles: de la misma entidad (o de la org del torneo)
    $clubes_disponibles = [];
    if ($entidad_torneo > 0) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.nombre
            FROM clubes c
            INNER JOIN organizaciones o ON c.organizacion_id = o.id AND o.estatus = 1
            WHERE o.entidad = ? AND c.estatus = 1
            ORDER BY c.nombre ASC
        ");
        $stmt->execute([$entidad_torneo]);
        $clubes_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $org_id = (int)($torneo['club_responsable'] ?? 0);
        if ($org_id > 0) {
            $stmt = $pdo->prepare("SELECT id, nombre FROM clubes WHERE organizacion_id = ? AND estatus = 1 ORDER BY nombre ASC");
            $stmt->execute([$org_id]);
            $clubes_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    return [
        'torneo' => $torneo,
        'usuarios_disponibles' => array_values($usuarios_disponibles),
        'usuarios_inscritos' => $usuarios_inscritos,
        'clubes_disponibles' => $clubes_disponibles
    ];
}

/**
 * Guarda inscripción de jugador en sitio
 */
function guardarInscripcionSitio($torneo_id, $user_id, $is_admin_general) {
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        // Incluir helper de estatus
        require_once __DIR__ . '/../lib/InscritosHelper.php';
        
        $pdo = DB::pdo();
        $id_usuario = (int)($_POST['id_usuario'] ?? 0);
        $cedula = trim($_POST['cedula'] ?? '');
        $id_club = !empty($_POST['id_club']) ? (int)$_POST['id_club'] : null;
        
        // Inscripción en sitio o confirmada por otra vía: siempre confirmado
        $estatus = 1; // confirmado
        
        $inscrito_por = $user_id;
        
        $current_user = Auth::user();
        $user_club_id = $current_user['club_id'] ?? null;
        
        // Si se proporciona cédula pero no id_usuario, buscar el usuario
        if (empty($id_usuario) && !empty($cedula)) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ? AND status = 0");
            $stmt->execute([$cedula]);
            $usuario_encontrado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario_encontrado) {
                $id_usuario = (int)$usuario_encontrado['id'];
            } else {
                $_SESSION['error'] = 'No se encontró un usuario registrado con la cédula ' . htmlspecialchars($cedula) . '. Debe registrar al usuario primero.';
                header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
                exit;
            }
        }
        
        if ($id_usuario <= 0) {
            $_SESSION['error'] = 'Debe seleccionar un usuario o proporcionar una cédula válida';
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        }
        
        // Validar que el usuario tenga todos los campos obligatorios completos
        $stmt = $pdo->prepare("SELECT nombre, cedula, sexo, email, username, entidad FROM usuarios WHERE id = ?");
        $stmt->execute([$id_usuario]);
        $usuario_datos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario_datos) {
            $_SESSION['error'] = 'No se encontró el usuario seleccionado';
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        }
        
        // Validar campos obligatorios
        $campos_faltantes = [];
        if (empty(trim($usuario_datos['nombre'] ?? ''))) {
            $campos_faltantes[] = 'Nombre';
        }
        if (empty(trim($usuario_datos['cedula'] ?? ''))) {
            $campos_faltantes[] = 'Cédula';
        }
        if (empty($usuario_datos['sexo'] ?? '')) {
            $campos_faltantes[] = 'Sexo';
        }
        if (empty(trim($usuario_datos['email'] ?? ''))) {
            $campos_faltantes[] = 'Email';
        }
        if (empty(trim($usuario_datos['username'] ?? ''))) {
            $campos_faltantes[] = 'Username';
        }
        
        if (!empty($campos_faltantes)) {
            $campos_lista = implode(', ', $campos_faltantes);
            $_SESSION['error'] = 'El usuario no puede ser inscrito porque faltan los siguientes campos obligatorios: ' . $campos_lista . '. Por favor complete la información del usuario antes de inscribirlo.';
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        }
        
        // Verificar que no esté ya inscrito (excluir retirados)
        $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE id_usuario = ? AND torneo_id = ? AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO);
        $stmt->execute([$id_usuario, $torneo_id]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Este usuario ya está inscrito en el torneo';
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        }
        
        // Si no se especificó club, usar el club del usuario o el club del administrador
        if (!$id_club) {
            $stmt = $pdo->prepare("SELECT club_id FROM usuarios WHERE id = ?");
            $stmt->execute([$id_usuario]);
            $usuario_club = $stmt->fetchColumn();
            $id_club = $usuario_club ?: $user_club_id;
        }
        
        // Validar que todos los campos obligatorios estén presentes antes de insertar
        if ($id_usuario <= 0) {
            throw new Exception('ID de usuario inválido');
        }
        if ($torneo_id <= 0) {
            throw new Exception('ID de torneo inválido');
        }
        
        // Insertar inscripción usando función centralizada
        try {
            // Usar función centralizada que valida y maneja todos los campos
            $id_inscrito = InscritosHelper::insertarInscrito($pdo, [
                'id_usuario' => $id_usuario,
                'torneo_id' => $torneo_id,
                'id_club' => $id_club,
                'estatus' => $estatus,
                'inscrito_por' => $inscrito_por,
                'numero' => 0 // Se asignará después si es necesario para equipos
            ]);
            
            $_SESSION['success'] = 'Jugador inscrito exitosamente';
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        } catch (PDOException $e) {
            error_log("Error PDO al inscribir jugador: " . $e->getMessage());
            $_SESSION['error'] = 'Error al guardar la inscripción: ' . $e->getMessage();
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        } catch (Exception $e) {
            error_log("Error al inscribir jugador: " . $e->getMessage());
            $_SESSION['error'] = 'Error al inscribir: ' . $e->getMessage();
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Error al inscribir jugador: " . $e->getMessage());
        $_SESSION['error'] = 'Error al inscribir: ' . $e->getMessage();
        header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
        exit;
    }
}

/**
 * Cuenta mesas incompletas de una ronda
 */
function contarMesasIncompletas($torneo_id, $ronda) {
    $pdo = DB::pdo();
    
    $sql = "SELECT COUNT(DISTINCT pr.mesa) as mesas_incompletas
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
            AND (pr.registrado = 0 OR pr.registrado IS NULL)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (int)($result['mesas_incompletas'] ?? 0);
}

/**
 * Devuelve los números de mesa asignados a un operador para un torneo y ronda (ámbito del operador).
 * Si el usuario no es operador o no tiene asignación, devuelve null (sin restricción).
 */
function obtenerMesasAsignadasOperador($torneo_id, $ronda, $user_id, $user_role) {
    if ($user_role !== 'operador' || $user_id <= 0) {
        return null;
    }
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->query("SHOW TABLES LIKE 'operador_mesa_asignacion'");
        if ($stmt->rowCount() === 0) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT mesa_numero FROM operador_mesa_asignacion WHERE torneo_id = ? AND ronda = ? AND user_id_operador = ? ORDER BY mesa_numero ASC");
        $stmt->execute([$torneo_id, $ronda, $user_id]);
        $nums = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'mesa_numero');
        return array_map('intval', $nums);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Obtiene datos para registrar resultados (versión v2).
 * Si el usuario es operador, solo ve y puede operar las mesas asignadas (ámbito limitado).
 */
function obtenerDatosRegistroResultados($torneo_id, $ronda, $mesa, $user_id = 0, $user_role = '') {
    $pdo = DB::pdo();
    ensureTournamentsCorreccionesCierreColumn();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener todas las rondas del torneo
    $sql = "SELECT DISTINCT partida FROM partiresul WHERE id_torneo = ? ORDER BY partida ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id]);
    $todasLasRondas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener todas las mesas de la ronda
    $sql = "SELECT DISTINCT 
                pr.mesa as numero,
                MAX(pr.registrado) as registrado
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
            GROUP BY pr.mesa
            ORDER BY CAST(pr.mesa AS UNSIGNED) ASC, pr.mesa ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda]);
    $todasLasMesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Asegurar que el número de mesa sea un entero y filtrar valores inválidos
    $mesas_filtradas = [];
    foreach ($todasLasMesas as $m) {
        $numeroMesa = (int)$m['numero'];
        if ($numeroMesa > 0) {
            $mesas_filtradas[] = [
                'numero' => $numeroMesa,
                'registrado' => (int)($m['registrado'] ?? 0),
                'tiene_resultados' => ($m['registrado'] ?? 0) > 0
            ];
        }
    }
    usort($mesas_filtradas, function($a, $b) {
        return $a['numero'] - $b['numero'];
    });
    
    // Operador: limitar ámbito a sus mesas asignadas (ej. mesas 1 a 10)
    $mesas_operador = obtenerMesasAsignadasOperador($torneo_id, $ronda, $user_id, $user_role);
    if ($mesas_operador !== null) {
        if (empty($mesas_operador)) {
            $mesas_filtradas = [];
        } else {
            $set_operador = array_flip($mesas_operador);
            $mesas_filtradas = array_filter($mesas_filtradas, function($m) use ($set_operador) {
                return isset($set_operador[$m['numero']]);
            });
            $mesas_filtradas = array_values($mesas_filtradas);
        }
    }
    
    $todasLasMesas = $mesas_filtradas;
    
    // Validar que la mesa solicitada existe en las mesas permitidas
    $mesa = (int)$mesa;
    $mesasExistentes = array_column($todasLasMesas, 'numero');
    $maxMesa = !empty($mesasExistentes) ? max($mesasExistentes) : 0;
    
    if ($mesa > 0 && !in_array($mesa, $mesasExistentes)) {
        if (!empty($mesasExistentes)) {
            $mesa = min($mesasExistentes);
            $_SESSION['warning'] = "La mesa solicitada no está en su ámbito. Se ha redirigido a la mesa #{$mesa}.";
        } else {
            $_SESSION['error'] = $mesas_operador !== null
                ? "No tiene mesas asignadas para esta ronda. Contacte al administrador."
                : "No hay mesas asignadas para la ronda {$ronda}.";
            $mesa = 0;
        }
    }
    
    if ($mesa === 0 && !empty($mesasExistentes)) {
        $mesa = min($mesasExistentes);
    }
    
    if ($mesa > $maxMesa && $maxMesa > 0) {
        $mesa = $maxMesa;
        $_SESSION['warning'] = "Se ha redirigido a la última mesa de su ámbito (mesa #{$maxMesa}).";
    }
    
    // Debug: Log de mesas encontradas
    error_log("Mesas encontradas para torneo $torneo_id, ronda $ronda: " . implode(', ', array_column($todasLasMesas, 'numero')));
    
    // Obtener jugadores de la mesa actual (incluyendo id de partiresul y estado de tarjeta en inscritos en una sola consulta)
    $sql = "SELECT 
                pr.id,
                pr.*,
                u.nombre as nombre_completo,
                i.posicion,
                i.ganados,
                i.perdidos,
                i.efectividad,
                i.puntos as puntos_acumulados,
                i.sancion as sancion_acumulada,
                COALESCE(i.tarjeta, 0) AS tarjeta_inscritos
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            LEFT JOIN inscritos i ON i.id_usuario = u.id AND i.torneo_id = pr.id_torneo
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
            ORDER BY pr.secuencia ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda, $mesa]);
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tarjeta previa desde partidas anteriores (para indicador y resaltado; evita doble escalación al re-editar)
    require_once __DIR__ . '/../lib/SancionesHelper.php';
    $idsJugadores = array_map(function ($j) { return (int)$j['id_usuario']; }, $jugadores);
    $tarjetaPreviaPorUsuario = SancionesHelper::getTarjetaPreviaDesdePartidasAnteriores($pdo, $torneo_id, $ronda, $idsJugadores);
    foreach ($jugadores as &$jugador) {
        $tarjetaPrevia = (int)($tarjetaPreviaPorUsuario[$jugador['id_usuario']] ?? 0);
        $jugador['inscrito'] = [
            'posicion' => (int)($jugador['posicion'] ?? 0),
            'ganados' => (int)($jugador['ganados'] ?? 0),
            'perdidos' => (int)($jugador['perdidos'] ?? 0),
            'efectividad' => (int)($jugador['efectividad'] ?? 0),
            'puntos' => (int)($jugador['puntos'] ?? 0),
            'tarjeta' => (int)($jugador['tarjeta_inscritos'] ?? 0),
            'tarjeta_previa' => $tarjetaPrevia
        ];
    }
    
    // Obtener observaciones
    $observacionesMesa = '';
    if (!empty($jugadores) && isset($jugadores[0]['observaciones'])) {
        $observacionesMesa = $jugadores[0]['observaciones'] ?? '';
    }
    
    // Estadísticas de mesas
    $mesasCompletadas = 0;
    foreach ($todasLasMesas as $m) {
        if ($m['tiene_resultados']) {
            $mesasCompletadas++;
        }
    }
    $totalMesas = count($todasLasMesas);
    $mesasPendientes = $totalMesas - $mesasCompletadas;
    
    // Mesa anterior y siguiente
    $mesaAnterior = null;
    $mesaSiguiente = null;
    foreach ($todasLasMesas as $index => $m) {
        if ($m['numero'] == $mesa) {
            if ($index > 0) {
                $mesaAnterior = $todasLasMesas[$index - 1]['numero'];
            }
            if ($index < count($todasLasMesas) - 1) {
                $mesaSiguiente = $todasLasMesas[$index + 1]['numero'];
            }
            break;
        }
    }
    
    $vieneDeResumen = isset($_GET['from']) && $_GET['from'] === 'resumen';
    $inscritoId = isset($_GET['inscrito_id']) ? (int)$_GET['inscrito_id'] : null;
    
    // Countdown "Correcciones se cierran en": usa correcciones_cierre_at (fijado al guardar última mesa; no se resetea)
    $rondas_generadas = obtenerRondasGeneradas($torneo_id);
    $ultima_ronda_global = !empty($rondas_generadas) ? max(array_column($rondas_generadas, 'num_ronda')) : 0;
    $totalRondas = (int)($torneo['rondas'] ?? 0);
    $mesas_incompletas_global = $ultima_ronda_global > 0 ? contarMesasIncompletas($torneo_id, $ultima_ronda_global) : 0;
    $torneo_completado = $totalRondas > 0 && $ultima_ronda_global >= $totalRondas && $mesas_incompletas_global == 0;
    $countdown_fin_timestamp = null;
    $mostrar_countdown_correcciones = false;
    $correcciones_cierre_at = isset($torneo['correcciones_cierre_at']) ? $torneo['correcciones_cierre_at'] : null;
    if (!empty($correcciones_cierre_at) && $correcciones_cierre_at !== '0000-00-00 00:00:00') {
        $countdown_fin_timestamp = strtotime($correcciones_cierre_at);
        $mostrar_countdown_correcciones = $torneo_completado;
    }
    $puede_cerrar_torneo = $torneo_completado && !((int)($torneo['locked'] ?? 0) === 1);
    
    return [
        'torneo' => $torneo,
        'ronda' => $ronda,
        'mesaActual' => $mesa,
        'jugadores' => $jugadores,
        'todasLasMesas' => $todasLasMesas,
        'todasLasRondas' => $todasLasRondas,
        'mesasCompletadas' => $mesasCompletadas,
        'mesasPendientes' => $mesasPendientes,
        'totalMesas' => $totalMesas,
        'mesaAnterior' => $mesaAnterior,
        'mesaSiguiente' => $mesaSiguiente,
        'observacionesMesa' => $observacionesMesa,
        'vieneDeResumen' => $vieneDeResumen,
        'inscritoId' => $inscritoId,
        'es_operador_ambito' => $mesas_operador !== null,
        'mesas_ambito' => $mesas_operador !== null ? $mesas_operador : [],
        'mostrar_countdown_correcciones' => $mostrar_countdown_correcciones,
        'countdown_fin_timestamp' => $countdown_fin_timestamp,
        'puede_cerrar_torneo' => $puede_cerrar_torneo,
    ];
}

/**
 * Obtiene datos para la cuadrícula
 */
function obtenerDatosCuadricula($torneo_id, $ronda) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener asignaciones (incluye mesa > 0 y mesa 0 = BYE), ordenadas por id_usuario ASC
    // La letra (A,C,B,D) se asigna según secuencia: 1=A, 2=C, 3=B, 4=D
    $sql = "SELECT 
                pr.id_usuario,
                pr.mesa,
                pr.secuencia,
                u.nombre as nombre_completo,
                u.username
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            WHERE pr.id_torneo = ? AND pr.partida = ?
            ORDER BY pr.id_usuario ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda]);
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'titulo' => 'Cuadrícula de Asignaciones - Ronda ' . $ronda,
        'torneo' => $torneo,
        'numRonda' => $ronda,
        'asignaciones' => $asignaciones,
        'totalAsignaciones' => count($asignaciones)
    ];
}

/**
 * Obtiene datos para hojas de anotación
 */
function obtenerDatosHojasAnotacion($torneo_id, $ronda) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $es_torneo_equipos = (int)($torneo['modalidad'] ?? 0) === 3;
    
    // Obtener inscritos con estadísticas (incluyendo tarjeta y codigo_equipo)
    $sql = "SELECT 
                id_usuario,
                codigo_equipo,
                posicion,
                ganados,
                perdidos,
                efectividad,
                puntos,
                sancion,
                tarjeta
            FROM inscritos
            WHERE torneo_id = ?
            ORDER BY posicion ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id]);
    $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $inscritosMap = [];
    foreach ($inscritos as $inscrito) {
        $inscritosMap[$inscrito['id_usuario']] = $inscrito;
    }
    
    // Obtener información de equipos si es torneo de equipos
    $equiposMap = [];
    $estadisticasEquipos = [];
    if ($es_torneo_equipos) {
        $sql = "SELECT 
                    e.codigo_equipo,
                    e.nombre_equipo,
                    e.id_club,
                    c.nombre as nombre_club
                FROM equipos e
                LEFT JOIN clubes c ON e.id_club = c.id
                WHERE e.id_torneo = ? AND e.estatus = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$torneo_id]);
        $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($equipos as $equipo) {
            $equiposMap[$equipo['codigo_equipo']] = $equipo;
        }
        
        // Estadísticas y posición de equipos desde tabla equipos (posicion/clasiequi ya calculada)
        $sql = "SELECT codigo_equipo, posicion, puntos, ganados, perdidos, efectividad
                FROM equipos
                WHERE id_torneo = ? AND estatus = 0 AND codigo_equipo IS NOT NULL AND codigo_equipo != ''
                ORDER BY posicion ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$torneo_id]);
        $statsEquipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($statsEquipos as $stat) {
            $posicion = (int)($stat['posicion'] ?? 0);
            $estadisticasEquipos[$stat['codigo_equipo']] = [
                'posicion' => $posicion,
                'clasiequi' => $posicion,
                'puntos' => (int)($stat['puntos'] ?? 0),
                'ganados' => (int)($stat['ganados'] ?? 0),
                'perdidos' => (int)($stat['perdidos'] ?? 0),
                'efectividad' => (int)($stat['efectividad'] ?? 0),
                'total_jugadores' => 4
            ];
        }
    }
    
    // Obtener mesas
    $sql = "SELECT 
                pr.*,
                u.nombre as nombre_completo,
                i.codigo_equipo,
                c.nombre as nombre_club
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            LEFT JOIN inscritos i ON i.id_usuario = u.id AND i.torneo_id = pr.id_torneo
            LEFT JOIN clubes c ON i.id_club = c.id
            WHERE pr.id_torneo = ? AND pr.partida = ?
            ORDER BY pr.mesa ASC, pr.secuencia ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por mesa
    $mesas = [];
    foreach ($resultados as $resultado) {
        $numMesa = (int)$resultado['mesa'];
        if ($numMesa > 0) {
            if (!isset($mesas[$numMesa])) {
                $mesas[$numMesa] = [
                    'numero' => $numMesa,
                    'jugadores' => []
                ];
            }
            
            $idUsuario = $resultado['id_usuario'];
            $inscritoData = $inscritosMap[$idUsuario] ?? [
                'posicion' => 0, 'ganados' => 0, 'perdidos' => 0,
                'efectividad' => 0, 'puntos' => 0, 'sancion' => 0, 'tarjeta' => 0,
                'codigo_equipo' => null
            ];
            
            // Agregar información del equipo si es torneo de equipos
            $codigoEquipo = $resultado['codigo_equipo'] ?? $inscritoData['codigo_equipo'] ?? null;
            if ($es_torneo_equipos && $codigoEquipo) {
                $equipoData = $equiposMap[$codigoEquipo] ?? null;
                if ($equipoData) {
                    $resultado['nombre_equipo'] = $equipoData['nombre_equipo'];
                    $resultado['codigo_equipo_display'] = $equipoData['codigo_equipo'];
                }
                
                // Agregar estadísticas del equipo
                if (isset($estadisticasEquipos[$codigoEquipo])) {
                    $resultado['estadisticas_equipo'] = $estadisticasEquipos[$codigoEquipo];
                }
            }
            
            // Usar la tarjeta de inscritos (última tarjeta del jugador en el torneo)
            $resultado['tarjeta'] = (int)($inscritoData['tarjeta'] ?? 0);
            $resultado['inscrito'] = [
                'posicion' => (int)($inscritoData['posicion'] ?? 0),
                'ganados' => (int)($inscritoData['ganados'] ?? 0),
                'perdidos' => (int)($inscritoData['perdidos'] ?? 0),
                'efectividad' => (int)($inscritoData['efectividad'] ?? 0),
                'puntos' => (int)($inscritoData['puntos'] ?? 0),
                'sancion' => (int)($inscritoData['sancion'] ?? 0),
                'tarjeta' => (int)($inscritoData['tarjeta'] ?? 0)
            ];
            
            $mesas[$numMesa]['jugadores'][] = $resultado;
        }
    }
    
    return [
        'torneo' => $torneo,
        'ronda' => $ronda,
        'mesas' => array_values($mesas),
        'es_torneo_equipos' => $es_torneo_equipos
    ];
}

/**
 * Obtiene datos para asignar mesas a operadores (operadores del club del torneo, mesas de la ronda, asignaciones actuales).
 */
function obtenerDatosAsignarMesasOperador($torneo_id, $ronda) {
    $pdo = DB::pdo();
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$torneo) {
        throw new Exception('Torneo no encontrado');
    }
    $club_responsable = (int)($torneo['club_responsable'] ?? 0);
    require_once __DIR__ . '/../lib/ClubHelper.php';
    $club_ids = ClubHelper::getClubesSupervised($club_responsable);
    if (empty($club_ids)) {
        $club_ids = [$club_responsable];
    }
    $placeholders = implode(',', array_fill(0, count($club_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT u.id, u.nombre, u.username
        FROM usuarios u
        WHERE u.role = 'operador' AND u.club_id IN ($placeholders) AND u.status = 0
        ORDER BY u.nombre ASC
    ");
    $stmt->execute(array_values($club_ids));
    $operadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("
        SELECT DISTINCT CAST(pr.mesa AS UNSIGNED) as numero
        FROM partiresul pr
        WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
        ORDER BY numero ASC
    ");
    $stmt->execute([$torneo_id, $ronda]);
    $mesas_numeros = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'numero');
    $asignaciones = [];
    $table_exists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'operador_mesa_asignacion'");
        $table_exists = $stmt->rowCount() > 0;
    } catch (Exception $e) {}
    if ($table_exists) {
        $stmt = $pdo->prepare("SELECT mesa_numero, user_id_operador FROM operador_mesa_asignacion WHERE torneo_id = ? AND ronda = ?");
        $stmt->execute([$torneo_id, $ronda]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $asignaciones[(int)$row['mesa_numero']] = (int)$row['user_id_operador'];
        }
    }
    return [
        'torneo' => $torneo,
        'torneo_id' => $torneo_id,
        'ronda' => $ronda,
        'operadores' => $operadores,
        'mesas_numeros' => $mesas_numeros,
        'asignaciones' => $asignaciones,
        'tabla_existe' => $table_exists,
    ];
}

/**
 * Guarda la asignación de mesas a operadores (crea tabla si no existe).
 */
function guardarAsignacionMesasOperador($torneo_id, $ronda, $user_id, $is_admin_general) {
    verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
    $pdo = DB::pdo();
    $sql_file = __DIR__ . '/../sql/operador_mesa_asignacion.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        if ($sql) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {
                // Tabla ya existe o error; continuar
            }
        }
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS operador_mesa_asignacion (
          torneo_id INT NOT NULL,
          ronda INT NOT NULL,
          mesa_numero INT NOT NULL,
          user_id_operador INT NOT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (torneo_id, ronda, mesa_numero),
          KEY idx_oma_operador (user_id_operador)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    $asignaciones = $_POST['asignacion'] ?? [];
    if (!is_array($asignaciones)) {
        $asignaciones = [];
    }
    $pdo->beginTransaction();
    try {
        $stmtDel = $pdo->prepare("DELETE FROM operador_mesa_asignacion WHERE torneo_id = ? AND ronda = ?");
        $stmtDel->execute([$torneo_id, $ronda]);
        $stmtIns = $pdo->prepare("INSERT INTO operador_mesa_asignacion (torneo_id, ronda, mesa_numero, user_id_operador) VALUES (?, ?, ?, ?)");
        foreach ($asignaciones as $mesa_numero => $user_id_op) {
            $mesa_numero = (int)$mesa_numero;
            $user_id_op = (int)$user_id_op;
            if ($mesa_numero > 0 && $user_id_op > 0) {
                $stmtIns->execute([$torneo_id, $ronda, $mesa_numero, $user_id_op]);
            }
        }
        $pdo->commit();
        $_SESSION['success'] = 'Asignación de mesas a operadores guardada correctamente.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error al guardar: ' . $e->getMessage();
    }
    $url = buildRedirectUrl('asignar_mesas_operador', ['torneo_id' => $torneo_id, 'ronda' => $ronda]);
    header('Location: ' . $url);
    exit;
}

/**
 * Obtiene datos para resumen individual
 */
function obtenerDatosResumenIndividual($torneo_id, $inscrito_id) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener inscrito
    $sql = "SELECT i.*, u.nombre as nombre_completo, c.nombre as nombre_club
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            LEFT JOIN clubes c ON i.id_club = c.id
            WHERE i.torneo_id = ? AND i.id_usuario = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $inscrito_id]);
    $inscrito = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inscrito) {
        throw new Exception('Jugador no encontrado en este torneo');
    }
    
    // Obtener la última ronda asignada
    $stmtUltimaRonda = $pdo->prepare("SELECT MAX(partida) as ultima_ronda FROM partiresul WHERE id_torneo = ? AND mesa > 0");
    $stmtUltimaRonda->execute([$torneo_id]);
    $ultimaRonda = (int)$stmtUltimaRonda->fetchColumn() ?? 0;
    
    // Obtener resumen de participación con información de pareja y contrarios
    // Incluir solo registrados, pero también incluir la última ronda aunque no esté registrada
    $sql = "SELECT 
                pr.partida,
                pr.mesa,
                pr.secuencia,
                pr.resultado1,
                pr.resultado2,
                pr.efectividad,
                pr.sancion,
                pr.ff,
                pr.tarjeta,
                pr.registrado,
                CASE WHEN pr.resultado1 > pr.resultado2 THEN 1 ELSE 0 END as gano
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.id_usuario = ? AND pr.mesa > 0 
            AND (pr.registrado = 1 OR (pr.registrado = 0 AND pr.partida = ?))
            ORDER BY pr.partida ASC, pr.mesa ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $inscrito_id, $ultimaRonda]);
    $partidasJugador = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada partida, obtener información de pareja y contrarios
    $resumenParticipacion = [];
    
    foreach ($partidasJugador as $partidaJugador) {
        $partida = (int)$partidaJugador['partida'];
        $mesa = (int)$partidaJugador['mesa'];
        $secuencia = (int)$partidaJugador['secuencia'];
        
        // Obtener todos los jugadores de esta mesa
        $sqlMesa = "SELECT 
                        pr.secuencia,
                        pr.id_usuario,
                        u.nombre as nombre_completo,
                        COALESCE(c.nombre, 'Sin Club') as club_nombre
                    FROM partiresul pr
                    INNER JOIN usuarios u ON pr.id_usuario = u.id
                    LEFT JOIN inscritos i ON i.id_usuario = pr.id_usuario AND i.torneo_id = pr.id_torneo
                    LEFT JOIN clubes c ON i.id_club = c.id
                    WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
                    ORDER BY pr.secuencia ASC";
        
        $stmtMesa = $pdo->prepare($sqlMesa);
        $stmtMesa->execute([$torneo_id, $partida, $mesa]);
        $jugadoresMesa = $stmtMesa->fetchAll(PDO::FETCH_ASSOC);
        
        // Identificar compañero y contrarios
        // Pareja A: secuencias 1-2, Pareja B: secuencias 3-4
        $compañero = null;
        $contrarios = [];
        
        $esParejaA = ($secuencia == 1 || $secuencia == 2);
        
        foreach ($jugadoresMesa as $jugador) {
            $seq = (int)$jugador['secuencia'];
            
            if ($seq == $secuencia) {
                // Es el jugador actual, saltar
                continue;
            }
            
            if ($esParejaA) {
                // Jugador está en Pareja A (secuencias 1-2)
                if ($seq == 1 || $seq == 2) {
                    // Es compañero
                    $compañero = [
                        'nombre' => $jugador['nombre_completo'],
                        'club' => $jugador['club_nombre'] ?? 'Sin Club',
                        'id_usuario' => (int)$jugador['id_usuario']
                    ];
                } elseif ($seq == 3 || $seq == 4) {
                    // Es contrario (Pareja B)
                    $contrarios[] = [
                        'nombre' => $jugador['nombre_completo'],
                        'club' => $jugador['club_nombre'] ?? 'Sin Club',
                        'id_usuario' => (int)$jugador['id_usuario']
                    ];
                }
            } else {
                // Jugador está en Pareja B (secuencias 3-4)
                if ($seq == 3 || $seq == 4) {
                    // Es compañero
                    $compañero = [
                        'nombre' => $jugador['nombre_completo'],
                        'club' => $jugador['club_nombre'] ?? 'Sin Club',
                        'id_usuario' => (int)$jugador['id_usuario']
                    ];
                } elseif ($seq == 1 || $seq == 2) {
                    // Es contrario (Pareja A)
                    $contrarios[] = [
                        'nombre' => $jugador['nombre_completo'],
                        'club' => $jugador['club_nombre'] ?? 'Sin Club',
                        'id_usuario' => (int)$jugador['id_usuario']
                    ];
                }
            }
        }
        
        $estaRegistrado = ((int)($partidaJugador['registrado'] ?? 0) == 1);
        
        // Solo calcular resultados si está registrado
        $hayForfait = false;
        $hayTarjetaGrave = false;
        $sancion = 0;
        $resultado1 = 0;
        $resultado2 = 0;
        $gano = false;
        
        if ($estaRegistrado) {
            // Determinar si ganó - evaluar individualmente considerando sanciones
            $hayForfait = ((int)$partidaJugador['ff'] == 1);
            $hayTarjetaGrave = ((int)$partidaJugador['tarjeta'] >= 3);
            $sancion = (int)($partidaJugador['sancion'] ?? 0);
            $resultado1 = (int)($partidaJugador['resultado1'] ?? 0);
            $resultado2 = (int)($partidaJugador['resultado2'] ?? 0);
            
            if ($hayForfait) {
                $gano = false; // El jugador con forfait pierde
            } elseif ($hayTarjetaGrave) {
                $gano = false; // El jugador con tarjeta grave pierde
            } elseif ($sancion > 0) {
                // Si hay sanción, evaluar individualmente
                // Obtener puntos del torneo
                $puntosTorneo = (int)($torneo['puntos'] ?? 100);
                
                // Obtener resultado del oponente (pareja contraria) - buscar en los jugadores de la mesa
                $resultadoOponente = 0;
                $sqlOponente = "SELECT resultado1 FROM partiresul 
                               WHERE id_torneo = ? AND partida = ? AND mesa = ?
                               AND secuencia IN (" . ($esParejaA ? "3,4" : "1,2") . ")
                               LIMIT 1";
                $stmtOponente = $pdo->prepare($sqlOponente);
                $stmtOponente->execute([$torneo_id, $partida, $mesa]);
                $oponenteData = $stmtOponente->fetch(PDO::FETCH_ASSOC);
                if ($oponenteData) {
                    $resultadoOponente = (int)($oponenteData['resultado1'] ?? 0);
                }
                
                // Evaluar sanción individualmente
                $evaluacionSancion = evaluarSancionIndividual($resultado1, $resultadoOponente, $sancion, $puntosTorneo);
                $gano = $evaluacionSancion['gano'];
            } else {
                $gano = ($resultado1 > $resultado2);
            }
        }
        
        $resumenParticipacion[] = [
            'partida' => $partida,
            'mesa' => $mesa,
            'compañero' => $compañero,
            'contrarios' => $contrarios,
            'resultado1' => $estaRegistrado ? (int)($partidaJugador['resultado1'] ?? 0) : null,
            'resultado2' => $estaRegistrado ? (int)($partidaJugador['resultado2'] ?? 0) : null,
            'efectividad' => $estaRegistrado ? (int)($partidaJugador['efectividad'] ?? 0) : null,
            'sancion' => $estaRegistrado ? (int)($partidaJugador['sancion'] ?? 0) : null,
            'gano' => $estaRegistrado ? $gano : null,
            'ff' => $estaRegistrado ? $hayForfait : false,
            'tarjeta' => $estaRegistrado ? (int)($partidaJugador['tarjeta'] ?? 0) : null,
            'registrado' => $estaRegistrado
        ];
    }
    
    // Calcular totales
    $totales = [
        'resultado1' => 0,
        'resultado2' => 0,
        'efectividad' => 0,
        'sancion' => 0,
        'ganados' => 0,
        'perdidos' => 0
    ];
    
    foreach ($resumenParticipacion as $partida) {
        // Solo sumar a totales si está registrado
        if (!empty($partida['registrado']) && $partida['registrado']) {
            $totales['resultado1'] += (int)($partida['resultado1'] ?? 0);
            $totales['resultado2'] += (int)($partida['resultado2'] ?? 0);
            $totales['efectividad'] += (int)($partida['efectividad'] ?? 0);
            $totales['sancion'] += (int)($partida['sancion'] ?? 0);
            
            if ($partida['gano']) {
                $totales['ganados']++;
            } else {
                $totales['perdidos']++;
            }
        }
    }
    
    // Detectar desde dónde viene para construir el enlace de retorno
    $from = $_GET['from'] ?? 'panel';
    $vieneDePosiciones = ($from === 'posiciones');
    
    // Construir URL de retorno según el origen
    $use_standalone = (basename($_SERVER['PHP_SELF'] ?? '') === 'admin_torneo.php');
    $base_url_retorno = $use_standalone ? 'admin_torneo.php' : 'index.php?page=torneo_gestion';
    $action_param = $use_standalone ? '?' : '&';
    
    $urlRetorno = $base_url_retorno . $action_param . 'action=panel&torneo_id=' . $torneo_id;
    
    if ($from === 'posiciones') {
        $urlRetorno = $base_url_retorno . $action_param . 'action=posiciones&torneo_id=' . $torneo_id;
    } elseif ($from === 'resultados_equipos_detallado') {
        $urlRetorno = $base_url_retorno . $action_param . 'action=resultados_equipos_detallado&torneo_id=' . $torneo_id;
    } elseif ($from === 'resultados_equipos_resumido') {
        $urlRetorno = $base_url_retorno . $action_param . 'action=resultados_equipos_resumido&torneo_id=' . $torneo_id;
    } elseif ($from === 'resultados_general') {
        $urlRetorno = $base_url_retorno . $action_param . 'action=resultados_general&torneo_id=' . $torneo_id;
    } elseif ($from === 'resultados_por_club') {
        $urlRetorno = $base_url_retorno . $action_param . 'action=resultados_por_club&torneo_id=' . $torneo_id;
    }
    
    return [
        'torneo' => $torneo,
        'inscrito' => $inscrito,
        'resumenParticipacion' => $resumenParticipacion,
        'totales' => $totales,
        'vieneDePosiciones' => $vieneDePosiciones,
        'from' => $from,
        'urlRetorno' => $urlRetorno
    ];
}

/**
 * Obtiene datos para agregar mesa adicional.
 * Solo muestra jugadores NO asignados en esta ronda (excluye los que ya están en partiresul).
 */
function obtenerDatosAgregarMesa($torneo_id, $ronda) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener solo jugadores NO asignados en esta ronda (no están en partiresul para esta ronda)
    $sql = "SELECT i.id_usuario, u.nombre, u.nombre as nombre_completo
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            LEFT JOIN partiresul pr ON pr.id_torneo = i.torneo_id AND pr.id_usuario = i.id_usuario AND pr.partida = ?
            WHERE i.torneo_id = ? AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('i') . "
              AND pr.id_usuario IS NULL
            ORDER BY u.nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ronda, $torneo_id]);
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'torneo' => $torneo,
        'ronda' => $ronda,
        'jugadores' => $jugadores
    ];
}

/**
 * Verifica si una mesa existe
 */
function verificarMesaExiste($torneo_id, $ronda, $mesa) {
    $pdo = DB::pdo();
    
    $sql = "SELECT COUNT(*) as total
            FROM partiresul
            WHERE id_torneo = ? AND partida = ? AND mesa = ? AND mesa > 0";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda, $mesa]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return isset($resultado['total']) && (int)$resultado['total'] > 0;
}

/**
 * Obtiene lista de actas pendientes de verificación (origen QR, estatus pendiente_verificacion)
 */
function obtenerDatosVerificarActasLista($torneo_id) {
    $pdo = DB::pdo();
    $cols = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('estatus', $cols)) {
        return ['actas_pendientes' => []];
    }
    $has_origen = in_array('origen_dato', $cols);
    $sql = "
        SELECT DISTINCT partida, mesa
        FROM partiresul
        WHERE id_torneo = ? AND mesa > 0 AND estatus = 'pendiente_verificacion'"
        . ($has_origen ? " AND origen_dato = 'qr'" : "") . "
        ORDER BY partida ASC, mesa ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id]);
    return ['actas_pendientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/**
 * Obtiene torneos con actas pendientes de verificación (QR) según el rol del usuario.
 * Usado por verificar_actas_index para listar torneos con mesas pendientes.
 *
 * @param int $user_id
 * @param bool $is_admin_general
 * @return array ['torneos' => array, 'total_actas_pendientes' => int]
 */
function obtenerTorneosConActasPendientes($user_id, $is_admin_general) {
    $pdo = DB::pdo();
    $cols = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('estatus', $cols)) {
        return ['torneos' => [], 'total_actas_pendientes' => 0];
    }
    $has_origen = in_array('origen_dato', $cols);
    $tournament_filter = Auth::getTournamentFilterForRole('t');
    $where_t = !empty($tournament_filter['where']) ? "AND " . $tournament_filter['where'] : "";
    $params = $tournament_filter['params'];

    $extra_where = $has_origen ? " AND pr.origen_dato = 'qr'" : "";
    $sql = "
        SELECT t.id, t.nombre, t.fechator, t.club_responsable,
               o.nombre as organizacion_nombre,
               COUNT(DISTINCT CONCAT(pr.partida, '-', pr.mesa)) as actas_pendientes
        FROM partiresul pr
        INNER JOIN tournaments t ON pr.id_torneo = t.id
        LEFT JOIN organizaciones o ON t.club_responsable = o.id
        WHERE pr.mesa > 0 AND pr.estatus = 'pendiente_verificacion' $extra_where
        AND t.estatus = 1
        $where_t
        GROUP BY t.id, t.nombre, t.fechator, t.club_responsable, o.nombre
        HAVING actas_pendientes > 0
        ORDER BY t.fechator DESC, t.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $torneos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = array_sum(array_column($torneos, 'actas_pendientes'));
    return ['torneos' => $torneos, 'total_actas_pendientes' => (int)$total];
}

/**
 * Obtiene datos de una acta específica para verificación
 */
function obtenerDatosVerificarActa($torneo_id, $ronda, $mesa) {
    $pdo = DB::pdo();
    $cols = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_COLUMN);
    $has_estatus = in_array('estatus', $cols);
    $sql = "
        SELECT pr.id, pr.id_usuario, pr.secuencia, pr.resultado1, pr.resultado2, pr.efectividad,
               pr.ff, pr.tarjeta, pr.sancion, pr.foto_acta, pr.estatus, pr.origen_dato,
               u.nombre as nombre_completo
        FROM partiresul pr
        INNER JOIN usuarios u ON pr.id_usuario = u.id
        WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
        ORDER BY pr.secuencia ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda, $mesa]);
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($jugadores) !== 4) {
        return null;
    }
    if ($has_estatus) {
        $estatus_primero = $jugadores[0]['estatus'] ?? '';
        if ($estatus_primero !== 'pendiente_verificacion') {
            return null; // Ya verificada
        }
    }
    return ['jugadores' => $jugadores];
}

/**
 * Aprueba una acta QR: marca estatus=confirmado, actualiza puntos si se corrigieron, recalcula rankings
 */
function verificarActaAprobar($user_id, $is_admin_general) {
    $torneo_id = (int)($_POST['torneo_id'] ?? 0);
    $ronda = (int)($_POST['ronda'] ?? 0);
    $mesa = (int)($_POST['mesa'] ?? 0);
    $jugadores_raw = $_POST['jugadores'] ?? [];
    if ($torneo_id <= 0 || $ronda <= 0 || $mesa <= 0) {
        $_SESSION['error'] = 'Parámetros inválidos.';
        header('Location: ' . buildRedirectUrl('verificar_actas', ['torneo_id' => $torneo_id]));
        exit;
    }
    verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
    $pdo = DB::pdo();
    $torneo_finalizado = isTorneoLocked($torneo_id);
    if ($torneo_finalizado && !$is_admin_general) {
        $_SESSION['error'] = 'No puede aprobar actas en un torneo finalizado. Solo el administrador general puede realizar correcciones.';
        header('Location: ' . buildRedirectUrl('verificar_resultados', ['torneo_id' => $torneo_id]));
        exit;
    }
    require_once __DIR__ . '/../lib/SancionesHelper.php';
    $cols = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_COLUMN);
    $has_estatus = in_array('estatus', $cols);
    if (!$has_estatus) {
        $_SESSION['error'] = 'La tabla partiresul no tiene la columna estatus.';
        header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
        exit;
    }
    $stmt = $pdo->prepare("SELECT puntos FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $puntosTorneo = (int)($stmt->fetchColumn() ?: 200);
    $stmt = $pdo->prepare("SELECT pr.id, pr.id_usuario, pr.secuencia, pr.resultado1, pr.resultado2, pr.sancion FROM partiresul pr WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ? ORDER BY pr.secuencia");
    $stmt->execute([$torneo_id, $ronda, $mesa]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ids_usuarios = array_column($rows, 'id_usuario');
    $tarjeta_previa = SancionesHelper::getTarjetaPreviaDesdePartidasAnteriores($pdo, $torneo_id, $ronda, $ids_usuarios);
    $validarPuntos = fn($p, $pt) => min($p, (int)round($pt * 1.6));
    $efAlcanzo = fn($r1, $r2, $pt) => $r1 == $r2 ? 0 : ($r1 > $r2 ? $pt - $r2 : -($pt - $r1));
    $efNoAlcanzo = fn($r1, $r2) => $r1 == $r2 ? 0 : ($r1 > $r2 ? $r1 - $r2 : -($r2 - $r1));
    $calcularEf = function ($r1, $r2, $pt, $ff, $tarjeta) use ($validarPuntos, $efAlcanzo, $efNoAlcanzo) {
        $r1 = $validarPuntos($r1, $pt);
        $r2 = $validarPuntos($r2, $pt);
        if ($ff == 1) return -$pt;
        if (in_array($tarjeta, [3, 4])) return -$pt;
        $mayor = max($r1, $r2);
        return $mayor >= $pt ? $efAlcanzo($r1, $r2, $pt) : $efNoAlcanzo($r1, $r2);
    };
    try {
        $pdo->beginTransaction();
        foreach ($rows as $row) {
            $partiresul_id = (int)$row['id'];
            $j = $jugadores_raw[$partiresul_id] ?? [];
            $resultado1 = (int)($j['resultado1'] ?? $row['resultado1'] ?? 0);
            $resultado2 = (int)($j['resultado2'] ?? $row['resultado2'] ?? 0);
            $sancion_input = (int)($j['sancion'] ?? 0);
            $tarjeta_inscritos = (int)($tarjeta_previa[(int)$row['id_usuario']] ?? 0);
            $procesado = SancionesHelper::procesar($sancion_input, 0, $tarjeta_inscritos);
            $tarjeta = $procesado['tarjeta'];
            $sancion_guardar = $procesado['sancion_guardar'];
            $sancion_calc = $procesado['sancion_para_calculo'];
            $resultado1_ajust = max(0, $resultado1 - $sancion_calc);
            $efectividad = $calcularEf($resultado1_ajust, $resultado2, $puntosTorneo, 0, $tarjeta);
            $pdo->prepare("
                UPDATE partiresul SET resultado1 = ?, resultado2 = ?, efectividad = ?, tarjeta = ?, sancion = ?, estatus = 'confirmado'
                WHERE id = ?
            ")->execute([$resultado1, $resultado2, $efectividad, $tarjeta, $sancion_guardar, $partiresul_id]);
        }
        $pdo->commit();
        actualizarEstadisticasInscritos($torneo_id);
        try {
            enviarNotificacionesResultadosAprobados($pdo, $torneo_id, $ronda, $mesa);
        } catch (Exception $e) {
            error_log("Error al enviar notificaciones de acta aprobada: " . $e->getMessage());
        }
        $_SESSION['success'] = 'Acta aprobada y rankings actualizados. Notificaciones enviadas a los jugadores.';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = 'Error al aprobar: ' . $e->getMessage();
    }
    $redirect_action = (!empty($_POST['redirect_action']) && $_POST['redirect_action'] === 'verificar_resultados') ? 'verificar_resultados' : 'verificar_actas';
    header('Location: ' . buildRedirectUrl($redirect_action, ['torneo_id' => $torneo_id]));
    exit;
}

/**
 * Rechaza una acta QR: limpia resultados y foto, pone estatus para re-escaneo
 */
function verificarActaRechazar($user_id, $is_admin_general) {
    $torneo_id = (int)($_POST['torneo_id'] ?? 0);
    $ronda = (int)($_POST['ronda'] ?? 0);
    $mesa = (int)($_POST['mesa'] ?? 0);
    if ($torneo_id <= 0 || $ronda <= 0 || $mesa <= 0) {
        $_SESSION['error'] = 'Parámetros inválidos.';
        header('Location: ' . buildRedirectUrl('verificar_actas', ['torneo_id' => $torneo_id]));
        exit;
    }
    verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
    $pdo = DB::pdo();
    $torneo_finalizado = isTorneoLocked($torneo_id);
    if ($torneo_finalizado && !$is_admin_general) {
        $_SESSION['error'] = 'No puede rechazar actas en un torneo finalizado. Solo el administrador general puede realizar correcciones.';
        $redirect_action = (!empty($_POST['redirect_action']) && $_POST['redirect_action'] === 'verificar_resultados') ? 'verificar_resultados' : 'verificar_actas';
        header('Location: ' . buildRedirectUrl($redirect_action, ['torneo_id' => $torneo_id]));
        exit;
    }
    $cols = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_COLUMN);
    $has_estatus = in_array('estatus', $cols);
    $has_foto = in_array('foto_acta', $cols);
    try {
        $updates = ["registrado = 0", "resultado1 = 0", "resultado2 = 0", "efectividad = 0", "ff = 0", "tarjeta = 0", "sancion = 0"];
        if ($has_estatus) $updates[] = "estatus = 'pendiente_verificacion'";
        if ($has_foto) $updates[] = "foto_acta = NULL";
        $pdo->prepare("UPDATE partiresul SET " . implode(', ', $updates) . " WHERE id_torneo = ? AND partida = ? AND mesa = ?")
            ->execute([$torneo_id, $ronda, $mesa]);
        actualizarEstadisticasInscritos($torneo_id);
        $_SESSION['success'] = 'Acta rechazada. El jugador puede volver a escanear y enviar el acta.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error al rechazar: ' . $e->getMessage();
    }
    $redirect_action = (!empty($_POST['redirect_action']) && $_POST['redirect_action'] === 'verificar_resultados') ? 'verificar_resultados' : 'verificar_actas';
    header('Location: ' . buildRedirectUrl($redirect_action, ['torneo_id' => $torneo_id]));
    exit;
}

/**
 * Genera una nueva ronda
 */
function generarRonda($torneo_id, $user_id, $is_admin_general) {
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        $pdo = DB::pdo();
        
        // Solo estatus 1 (confirmado) cuentan para participar en el torneo
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO);
        $stmt->execute([$torneo_id]);
        $num_inscritos = (int)$stmt->fetchColumn();
        if ($num_inscritos < 4) {
            $_SESSION['error'] = 'No se puede generar ronda: se necesitan al menos 4 participantes inscritos y activos en el torneo. Actualmente hay ' . $num_inscritos . '.';
            header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
            exit;
        }
        
        // Obtener torneo para verificar modalidad y nombre
        $stmt = $pdo->prepare("SELECT nombre, rondas, modalidad FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_rondas = (int)($torneo['rondas'] ?? 0);
        $modalidad = (int)($torneo['modalidad'] ?? 0);
        
        // Determinar qué servicio usar según modalidad (3 = Equipos)
        $es_torneo_equipos = ($modalidad === 3);
        
        if ($es_torneo_equipos) {
            require_once __DIR__ . '/../config/MesaAsignacionEquiposService.php';
            $mesaService = new MesaAsignacionEquiposService();
        } else {
            require_once __DIR__ . '/../config/MesaAsignacionService.php';
            $mesaService = new MesaAsignacionService();
        }
        
        // Verificar que la última ronda esté completa
        $ultima_ronda = $mesaService->obtenerUltimaRonda($torneo_id);
        
        if ($ultima_ronda > 0) {
            $todas_completas = $mesaService->todasLasMesasCompletas($torneo_id, $ultima_ronda);
            if (!$todas_completas) {
                $mesas_incompletas = $mesaService->contarMesasIncompletas($torneo_id, $ultima_ronda);
                $_SESSION['error'] = "No se puede generar una nueva ronda. Faltan resultados en {$mesas_incompletas} mesa(s) de la ronda {$ultima_ronda}";
                header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
                exit;
            }
        }
        
        // Actualizar estadísticas antes de generar nueva ronda
        try {
            actualizarEstadisticasInscritos($torneo_id);
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al actualizar estadísticas: ' . $e->getMessage();
            header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
            exit;
        }
        
        $proxima_ronda = $ultima_ronda + 1;
        $msg_no_presentes = '';
        
        // Antes de generar la 3.ª ronda: marcar como retirados a los no presentes (pendientes sin ninguna partida)
        if ($proxima_ronda === 3) {
            $marcados_retirados = marcarNoPresentesRetiradosAntesRonda3($torneo_id);
            if ($marcados_retirados > 0) {
                $msg_no_presentes = $marcados_retirados . ' inscrito(s) no presente(s) marcado(s) como retirado(s).';
            }
            // Revalidar que sigan habiendo al menos 4 confirmados tras retirar no presentes
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO);
            $stmt->execute([$torneo_id]);
            if ((int)$stmt->fetchColumn() < 4) {
                $_SESSION['error'] = 'No se puede generar la ronda 3: tras marcar no presentes quedan menos de 4 participantes confirmados.';
                header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
                exit;
            }
        }
        
        // Obtener estrategia de asignación (para equipos puede ser: secuencial, intercalada_13_24, intercalada_14_23, por_rendimiento)
        if ($es_torneo_equipos) {
            $estrategia = $_POST['estrategia_asignacion'] ?? 'secuencial';
        } else {
            $estrategia = $_POST['estrategia_ronda2'] ?? 'separar';
        }
        
        // Generar ronda usando el servicio apropiado
        if ($es_torneo_equipos) {
            $resultado = $mesaService->generarAsignacionRonda(
                $torneo_id,
                $proxima_ronda,
                $total_rondas,
                $estrategia
            );
        } else {
            $resultado = $mesaService->generarAsignacionRonda(
                $torneo_id,
                $proxima_ronda,
                $total_rondas,
                $estrategia
            );
        }
        
        if ($resultado['success']) {
            $mensaje = $resultado['message'];
            if (isset($resultado['total_mesas'])) {
                $mensaje .= ': ' . $resultado['total_mesas'] . ' mesas';
            }
            if (isset($resultado['total_equipos'])) {
                $mensaje .= ', ' . $resultado['total_equipos'] . ' equipos';
            }
            if (isset($resultado['jugadores_bye']) && $resultado['jugadores_bye'] > 0) {
                $mensaje .= ', ' . $resultado['jugadores_bye'] . ' jugadores BYE';
            }
            if ($msg_no_presentes !== '') {
                $mensaje .= '. ' . $msg_no_presentes;
            }
            $_SESSION['success'] = $mensaje;

            // Encolar notificaciones masivas (Telegram + campanita web) usando plantilla 'nueva_ronda'
            try {
                $stmtJug = $pdo->prepare("
                    SELECT u.id, u.nombre, u.telegram_chat_id,
                           COALESCE(i.posicion, 0) AS posicion, COALESCE(i.ganados, 0) AS ganados, COALESCE(i.perdidos, 0) AS perdidos,
                           COALESCE(i.efectividad, 0) AS efectividad, COALESCE(i.puntos, 0) AS puntos
                    FROM inscritos i
                    INNER JOIN usuarios u ON i.id_usuario = u.id
                    WHERE i.torneo_id = ? AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('i') . "
                ");
                $stmtJug->execute([$torneo_id]);
                $jugadores = $stmtJug->fetchAll(PDO::FETCH_ASSOC);

                // Mesa y pareja para esta ronda (partiresul ya tiene la asignación recién generada)
                $mesaPareja = [];
                $stmtMesa = $pdo->prepare("
                    SELECT pr.id_usuario, pr.mesa, pr_p.id_usuario AS pareja_id, u_pareja.nombre AS pareja_nombre
                    FROM partiresul pr
                    LEFT JOIN partiresul pr_p ON pr_p.id_torneo = pr.id_torneo AND pr_p.partida = pr.partida AND pr_p.mesa = pr.mesa
                        AND pr_p.secuencia = CASE pr.secuencia WHEN 1 THEN 2 WHEN 2 THEN 1 WHEN 3 THEN 4 WHEN 4 THEN 3 END
                    LEFT JOIN usuarios u_pareja ON u_pareja.id = pr_p.id_usuario
                    WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
                ");
                $stmtMesa->execute([$torneo_id, $proxima_ronda]);
                while ($row = $stmtMesa->fetch(PDO::FETCH_ASSOC)) {
                    $mesaPareja[(int)$row['id_usuario']] = [
                        'mesa' => (string)$row['mesa'],
                        'pareja_id' => (int)($row['pareja_id'] ?? 0),
                        'pareja' => trim((string)($row['pareja_nombre'] ?? '')) ?: '—',
                    ];
                }

                require_once __DIR__ . '/../lib/app_helpers.php';
                foreach ($jugadores as &$j) {
                    $uid = (int)$j['id'];
                    $j['mesa'] = $mesaPareja[$uid]['mesa'] ?? '—';
                    $j['pareja_id'] = $mesaPareja[$uid]['pareja_id'] ?? 0;
                    $j['pareja'] = $mesaPareja[$uid]['pareja'] ?? '—';
                    $j['url_resumen'] = AppHelpers::url('index.php', ['page' => 'torneo_gestion', 'action' => 'resumen_individual', 'torneo_id' => $torneo_id, 'inscrito_id' => $uid, 'from' => 'notificaciones']);
                    $j['url_clasificacion'] = AppHelpers::url('index.php', ['page' => 'torneo_gestion', 'action' => 'posiciones', 'torneo_id' => $torneo_id, 'from' => 'notificaciones']);
                }
                unset($j);

                $titulo = $torneo['nombre'] ?? 'Torneo';
                if (!empty($jugadores)) {
                    require_once __DIR__ . '/../lib/NotificationManager.php';
                    $nm = new NotificationManager($pdo);
                    $nm->programarRondaMasiva($jugadores, $titulo, $proxima_ronda, null, 'nueva_ronda', $torneo_id);
                }
            } catch (Exception $e) {
                error_log("Notificaciones ronda: " . $e->getMessage());
            }
        } else {
            $_SESSION['error'] = $resultado['message'];
        }
        
    } catch (Exception $e) {
        error_log("Error al generar ronda: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        $_SESSION['error'] = 'Error al generar ronda: ' . $e->getMessage();
    }
    
    // Permanecer siempre en el panel: éxito o error. El usuario irá al formulario de resultados cuando lo requiera.
    if (isset($torneo_id) && $torneo_id > 0) {
        header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
        exit;
    }
    
    header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
    exit;
}

/**
 * Elimina la última ronda generada.
 * - Sin resultados en mesas: elimina con la confirmación normal del panel.
 * - Con resultados en mesas: exige confirmación estricta (escribir ELIMINAR) por seguridad.
 */
function eliminarUltimaRonda($torneo_id, $user_id, $is_admin_general) {
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        $mesaService = new MesaAsignacionService();
        $ultima_ronda = $mesaService->obtenerUltimaRonda($torneo_id);
        
        if ($ultima_ronda === 0) {
            $_SESSION['error'] = 'No hay rondas generadas para eliminar';
            header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
            exit;
        }
        
        $tiene_resultados_mesas = $mesaService->rondaTieneResultadosEnMesas($torneo_id, $ultima_ronda);
        if ($tiene_resultados_mesas) {
            $confirmacion = trim((string)($_POST['confirmar_eliminar_con_resultados'] ?? ''));
            if ($confirmacion !== 'ELIMINAR') {
                $_SESSION['error'] = 'La ronda ' . $ultima_ronda . ' tiene resultados de mesas registrados. Para eliminarla debe confirmar de forma segura (escribir ELIMINAR en el cuadro de confirmación).';
                header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
                exit;
            }
        }
        
        $eliminada = $mesaService->eliminarRonda($torneo_id, $ultima_ronda);
        
        if ($eliminada) {
            $_SESSION['success'] = "Ronda {$ultima_ronda} eliminada exitosamente";
        } else {
            $_SESSION['error'] = "Error al eliminar la ronda {$ultima_ronda}";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error al eliminar ronda: ' . $e->getMessage();
    }
    
    header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
    exit;
}

/**
 * Guarda resultados de una mesa
 */
function guardarResultados($user_id, $is_admin_general) {
    $torneo_id = (int)($_POST['torneo_id'] ?? 0);
    $ronda = (int)($_POST['ronda'] ?? 0);
    $mesa = (int)($_POST['mesa'] ?? 0);
    
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        if ($mesa <= 0) {
            $_SESSION['error'] = 'No hay una mesa válida asignada. Seleccione una mesa de la lista antes de guardar.';
            header('Location: ' . buildRedirectUrl('registrar_resultados', ['torneo_id' => $torneo_id, 'ronda' => $ronda]));
            exit;
        }
        
        // Operador: solo puede guardar resultados en mesas de su ámbito
        $current = Auth::user();
        $user_role = $current['role'] ?? '';
        if ($user_role === 'operador') {
            $mesas_operador = obtenerMesasAsignadasOperador($torneo_id, $ronda, $user_id, $user_role);
            if ($mesas_operador !== null && !in_array($mesa, $mesas_operador, true)) {
                throw new Exception("No tiene permiso para registrar resultados en la mesa #{$mesa}. Solo puede operar las mesas asignadas a su ámbito.");
            }
        }
        
        $jugadores = $_POST['jugadores'] ?? [];
        $observaciones = trim($_POST['observaciones'] ?? '');
        
        if (empty($jugadores) || !is_array($jugadores) || count($jugadores) != 4) {
            throw new Exception('Debe haber exactamente 4 jugadores por mesa');
        }
        
        $pdo = DB::pdo();
        
        // Validar que la mesa existe en las mesas asignadas para esta ronda
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT pr.mesa) as total_mesas, MAX(CAST(pr.mesa AS UNSIGNED)) as max_mesa
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
        ");
        $stmt->execute([$torneo_id, $ronda]);
        $mesasInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxMesa = (int)($mesasInfo['max_mesa'] ?? 0);
        
        // Verificar que la mesa existe
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as existe
            FROM partiresul
            WHERE id_torneo = ? AND partida = ? AND mesa = ?
        ");
        $stmt->execute([$torneo_id, $ronda, $mesa]);
        $mesaExiste = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mesaExiste['existe'] == 0) {
            throw new Exception("La mesa #{$mesa} no existe en la ronda {$ronda}. " . 
                              ($maxMesa > 0 ? "El número máximo de mesa asignada es {$maxMesa}." : "No hay mesas asignadas para esta ronda."));
        }
        
        // Validar que la mesa no exceda el máximo
        if ($maxMesa > 0 && $mesa > $maxMesa) {
            throw new Exception("La mesa #{$mesa} no existe. El número máximo de mesa asignada es {$maxMesa}.");
        }
        
        $pdo->beginTransaction();
        
        // Obtener puntos del torneo
        $stmt = $pdo->prepare("SELECT puntos FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        $puntosTorneo = (int)($torneo['puntos'] ?? 100);
        
        // Procesar cada jugador
        // Asegurar que el array está indexado numéricamente
        $jugadores = array_values($jugadores);
        
        // Tarjeta previa = desde partiresul de partidas ANTERIORES (excluir esta partida para evitar doble escalación al re-editar)
        require_once __DIR__ . '/../lib/SancionesHelper.php';
        $idsParaChequearTarjeta = [];
        foreach ($jugadores as $jugador) {
            $uid = (int)($jugador['id_usuario'] ?? 0);
            if ($uid <= 0) continue;
            $s = (int)($jugador['sancion'] ?? 0);
            $t = (int)($jugador['tarjeta'] ?? 0);
            if ($s >= SancionesHelper::SANCION_AMARILLA || $t === SancionesHelper::TARJETA_AMARILLA) {
                $idsParaChequearTarjeta[$uid] = true;
            }
        }
        $tarjetaPreviaPartidasAnteriores = SancionesHelper::getTarjetaPreviaDesdePartidasAnteriores($pdo, $torneo_id, $ronda, array_keys($idsParaChequearTarjeta));
        
        // Primero, detectar si hay algún forfait en la mesa
        $hayForfaitEnMesa = false;
        foreach ($jugadores as $jugador) {
            $ff_temp = isset($jugador['ff']) && ($jugador['ff'] == '1' || $jugador['ff'] === true || $jugador['ff'] === 'on') ? 1 : 0;
            if ($ff_temp == 1) {
                $hayForfaitEnMesa = true;
                break;
            }
        }
        
        // Primera pasada: recopilar todos los datos y calcular resultados ajustados (usa SancionesHelper)
        $datosJugadores = [];
        foreach ($jugadores as $index => $jugador) {
            $id = (int)($jugador['id'] ?? 0);
            $id_usuario = (int)($jugador['id_usuario'] ?? 0);
            $secuencia = (int)($jugador['secuencia'] ?? 0);
            $resultado1 = (int)($jugador['resultado1'] ?? 0);
            $resultado2 = (int)($jugador['resultado2'] ?? 0);
            $ff = isset($jugador['ff']) && ($jugador['ff'] == '1' || $jugador['ff'] === true || $jugador['ff'] === 'on') ? 1 : 0;
            $tarjetaForm = (int)($jugador['tarjeta'] ?? 0);
            $sancion = (int)($jugador['sancion'] ?? 0);
            $tarjetaPrevia = (int)($tarjetaPreviaPartidasAnteriores[$id_usuario] ?? 0);
            $procesado = SancionesHelper::procesar($sancion, $tarjetaForm, $tarjetaPrevia);
            $tarjeta = $procesado['tarjeta'];
            $sancionParaCalculo = $procesado['sancion_para_calculo'];
            $sancionGuardar = $procesado['sancion_guardar'];
            $chancleta = (int)($jugador['chancleta'] ?? 0);
            $zapato = (int)($jugador['zapato'] ?? 0);
            
            // Validar que tenemos los datos necesarios
            if ($id_usuario == 0 || $secuencia == 0) {
                throw new Exception("Datos incompletos para el jugador " . ($index + 1) . ": id_usuario=$id_usuario, secuencia=$secuencia");
            }
            
            // Validar que resultado1 y resultado2 no excedan el máximo permitido (puntos del torneo + 60%)
            $maximoPermitido = (int)round($puntosTorneo * 1.6);
            if ($resultado1 > $maximoPermitido) {
                throw new Exception("El resultado1 del jugador " . ($index + 1) . " ($resultado1) excede el máximo permitido ($maximoPermitido = puntos del torneo + 60%)");
            }
            if ($resultado2 > $maximoPermitido) {
                throw new Exception("El resultado2 del jugador " . ($index + 1) . " ($resultado2) excede el máximo permitido ($maximoPermitido = puntos del torneo + 60%)");
            }
            
            // Determinar pareja (A: secuencias 1-2, B: secuencias 3-4)
            $esParejaA = ($secuencia == 1 || $secuencia == 2);
            
            // Aplicar sanción al cálculo (40 pts NO resta: advertencia administrativa)
            $resultado1Ajustado = max(0, $resultado1 - $sancionParaCalculo);
            
            $datosJugadores[] = [
                'id' => $id,
                'id_usuario' => $id_usuario,
                'secuencia' => $secuencia,
                'resultado1' => $resultado1,
                'resultado2' => $resultado2,
                'resultado1Ajustado' => $resultado1Ajustado,
                'ff' => $ff,
                'tarjeta' => $tarjeta,
                'sancion' => $sancionGuardar,
                'sancion_para_calculo' => $sancionParaCalculo,
                'chancleta' => $chancleta,
                'zapato' => $zapato,
                'esParejaA' => $esParejaA,
                'index' => $index
            ];
        }
        
        // Detectar si hay tarjeta grave en la mesa (usar tarjeta ya asignada: 3=roja, 4=negra)
        $hayTarjetaGraveEnMesa = false;
        foreach ($datosJugadores as $jugador) {
            $t = (int)($jugador['tarjeta'] ?? 0);
            if ($t == 3 || $t == 4) {
                $hayTarjetaGraveEnMesa = true;
                break;
            }
        }
        
        // Segunda pasada: calcular efectividad considerando sanciones
        foreach ($datosJugadores as $jugador) {
            $id = $jugador['id'];
            $id_usuario = $jugador['id_usuario'];
            $secuencia = $jugador['secuencia'];
            $resultado1 = $jugador['resultado1'];
            $resultado2 = $jugador['resultado2'];
            $resultado1Ajustado = $jugador['resultado1Ajustado'];
            $ff = $jugador['ff'];
            $tarjeta = $jugador['tarjeta'];
            $sancion = $jugador['sancion'];
            $chancleta = $jugador['chancleta'];
            $zapato = $jugador['zapato'];
            $esParejaA = $jugador['esParejaA'];
            
            // Calcular efectividad según el caso
            // PRIORIDAD 1: Si hay forfait en la mesa, usar lógica especial de forfait
            if ($hayForfaitEnMesa) {
                $calculoForfait = calcularEfectividadForfait($ff == 1, $puntosTorneo);
                $efectividad = $calculoForfait['efectividad'];
                // También actualizar resultado1 y resultado2 según el cálculo de forfait
                $resultado1 = $calculoForfait['resultado1'];
                $resultado2 = $calculoForfait['resultado2'];
            }
            // PRIORIDAD 2: Si hay tarjeta grave en la mesa, usar lógica especial de tarjeta grave
            elseif ($hayTarjetaGraveEnMesa) {
                $calculoTarjeta = calcularEfectividadTarjetaGrave($tarjeta == 3 || $tarjeta == 4, $puntosTorneo);
                $efectividad = $calculoTarjeta['efectividad'];
                // También actualizar resultado1 y resultado2 según el cálculo de tarjeta grave
                $resultado1 = $calculoTarjeta['resultado1'];
                $resultado2 = $calculoTarjeta['resultado2'];
            }
            // PRIORIDAD 3: Calcular efectividad normal (sin forfait ni tarjeta grave)
            // Evaluar sanciones individualmente para cada jugador
            else {
                // Obtener el resultado del oponente (pareja contraria) SIN ajustar
                $resultadoOponente = 0;
                foreach ($datosJugadores as $oponente) {
                    if ($oponente['esParejaA'] != $esParejaA) {
                        // Es de la pareja contraria, obtener su resultado1 (puntos de su pareja)
                        $resultadoOponente = $oponente['resultado1'];
                        break;
                    }
                }
                
                // Si hay sanción que resta puntos, evaluar individualmente (40 pts no resta)
                $sancionCalc = (int)($jugador['sancion_para_calculo'] ?? $jugador['sancion'] ?? 0);
                if ($sancionCalc > 0) {
                    $evaluacionSancion = evaluarSancionIndividual($resultado1, $resultadoOponente, $sancionCalc, $puntosTorneo);
                    $efectividad = $evaluacionSancion['efectividad'];
                } else {
                    $efectividad = calcularEfectividad($resultado1Ajustado, $resultado2, $puntosTorneo, $ff, $tarjeta, 0);
                }
            }
            
            // Si tenemos el ID del registro, actualizar directamente
            if ($id > 0) {
                $sql = "UPDATE partiresul SET 
                        resultado1 = ?,
                        resultado2 = ?,
                        efectividad = ?,
                        ff = ?,
                        tarjeta = ?,
                        sancion = ?,
                        chancleta = ?,
                        zapato = ?,
                        fecha_partida = NOW(),
                        registrado_por = ?,
                        registrado = 1
                        WHERE id = ?";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $resultado1, $resultado2, $efectividad, $ff, $tarjeta,
                    $sancion, $chancleta, $zapato, $user_id, $id
                ]);
                
                if (!$result || $stmt->rowCount() == 0) {
                    throw new Exception("No se pudo actualizar el registro del jugador " . ($index + 1) . " (ID: $id)");
                }
            } else {
                // Si no tenemos ID, buscar por torneo, ronda, mesa, usuario y secuencia
                $sql = "UPDATE partiresul SET 
                        resultado1 = ?,
                        resultado2 = ?,
                        efectividad = ?,
                        ff = ?,
                        tarjeta = ?,
                        sancion = ?,
                        chancleta = ?,
                        zapato = ?,
                        fecha_partida = NOW(),
                        registrado_por = ?,
                        registrado = 1
                        WHERE id_torneo = ? AND partida = ? AND mesa = ? 
                        AND id_usuario = ? AND secuencia = ?";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $resultado1, $resultado2, $efectividad, $ff, $tarjeta,
                    $sancion, $chancleta, $zapato, $user_id,
                    $torneo_id, $ronda, $mesa, $id_usuario, $secuencia
                ]);
                
                if (!$result || $stmt->rowCount() == 0) {
                    throw new Exception("No se pudo actualizar el registro del jugador " . ($index + 1) . " (usuario: $id_usuario, secuencia: $secuencia)");
                }
            }
        }
        
        // Actualizar observaciones (el countdown de correcciones no se resetea con correcciones)
        if (!empty($observaciones)) {
            $stmt = $pdo->prepare("UPDATE partiresul SET observaciones = ? WHERE id_torneo = ? AND partida = ? AND mesa = ?");
            $stmt->execute([$observaciones, $torneo_id, $ronda, $mesa]);
        }
        
        $pdo->commit();
        
        // Actualizar estadísticas de los jugadores involucrados y recalcular posiciones
        try {
            actualizarEstadisticasInscritos($torneo_id);
        } catch (Exception $e) {
            error_log("Error al actualizar estadísticas después de guardar resultados: " . $e->getMessage());
        }

        // Fijar correcciones_cierre_at al guardar la última mesa (solo una vez; no se resetea con correcciones)
        try {
            ensureTournamentsCorreccionesCierreColumn();
            $rondas_gen = obtenerRondasGeneradas($torneo_id);
            $ultima_r = !empty($rondas_gen) ? max(array_column($rondas_gen, 'num_ronda')) : 0;
            $stmt_tr = $pdo->prepare("SELECT rondas FROM tournaments WHERE id = ?");
            $stmt_tr->execute([$torneo_id]);
            $total_r = (int)($stmt_tr->fetchColumn());
            $mesas_inc = $ultima_r > 0 ? contarMesasIncompletas($torneo_id, $ultima_r) : 1;
            if ($total_r > 0 && $ultima_r >= $total_r && $mesas_inc === 0) {
                $stmt_up = $pdo->prepare("UPDATE tournaments SET correcciones_cierre_at = NOW() + INTERVAL 20 MINUTE WHERE id = ? AND (correcciones_cierre_at IS NULL OR correcciones_cierre_at = '0000-00-00 00:00:00')");
                $stmt_up->execute([$torneo_id]);
            }
        } catch (Exception $e) {
            // Ignorar
        }

        // Notificaciones a los 4 jugadores de la mesa (solo mesas reales, no BYE)
        if ($mesa > 0) {
            try {
                enviarNotificacionesResultadosMesa($pdo, $torneo_id, $ronda, $mesa);
            } catch (Exception $e) {
                error_log("Error al enviar notificaciones de resultados de mesa: " . $e->getMessage());
            }
        }
        
        // No regenerar token CSRF aquí: causa "Token inválido" en doble clic o pestaña antigua.
        // La redirección carga una página nueva con el mismo token vigente.
        
        // Sin mensaje de éxito: si no hay error, no se muestra nada
        $_SESSION['limpiar_formulario'] = true;
        $_SESSION['resultados_guardados'] = true;
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Error al guardar resultados: ' . $e->getMessage();
    }
    
        $redirectUrl = buildRedirectUrl('registrar_resultados', ['torneo_id' => $torneo_id, 'ronda' => $ronda, 'mesa' => $mesa]) . '#formResultados';
        header('Location: ' . $redirectUrl);
        exit;
}

/**
 * Calcula efectividad según las reglas del torneo
 * Versión mejorada con lógica individual para forfait y tarjetas graves
 */
function calcularEfectividad($resultado1, $resultado2, $puntosTorneo, $ff, $tarjeta, $sancion = 0) {
    // Validar puntos máximos
    $resultado1 = validarPuntos($resultado1, $puntosTorneo);
    $resultado2 = validarPuntos($resultado2, $puntosTorneo);
    
    // Aplicar sanción antes de calcular efectividad
    $resultado1Ajustado = max(0, $resultado1 - $sancion);
    
    // Forfait individual: -puntos_torneo efectividad
    if ($ff == 1) {
        return -$puntosTorneo;
    }
    
    // Tarjeta roja (3) o negra (4): -puntos_torneo efectividad
    if ($tarjeta == 3 || $tarjeta == 4) {
        return -$puntosTorneo;
    }
    
    // Calcular efectividad según si se alcanzaron los puntos del torneo
    $mayor = max($resultado1Ajustado, $resultado2);
    
    if ($mayor >= $puntosTorneo) {
        // Se alcanzaron los puntos: efectividad = puntos_torneo - resultado_contrario
        return calcularEfectividadAlcanzo($resultado1Ajustado, $resultado2, $puntosTorneo);
    } else {
        // No se alcanzaron: efectividad = diferencia de puntos
        return calcularEfectividadNoAlcanzo($resultado1Ajustado, $resultado2);
    }
}

/**
 * Calcular efectividad cuando SÍ se alcanzaron los puntos del torneo
 */
function calcularEfectividadAlcanzo($resultado1, $resultado2, $puntosTorneo) {
    if ($resultado1 == $resultado2) {
        return 0; // Empate
    } elseif ($resultado1 > $resultado2) {
        return $puntosTorneo - $resultado2; // Ganó
    } else {
        return -($puntosTorneo - $resultado1); // Perdió
    }
}

/**
 * Calcular efectividad cuando NO se alcanzaron los puntos del torneo
 */
function calcularEfectividadNoAlcanzo($resultado1, $resultado2) {
    if ($resultado1 == $resultado2) {
        return 0; // Empate
    } elseif ($resultado1 > $resultado2) {
        return $resultado1 - $resultado2; // Ganó
    } else {
        return -($resultado2 - $resultado1); // Perdió
    }
}

/**
 * Evaluar sanción de puntos para un jugador individualmente
 * Calcula el resultado ajustado aplicando la sanción y determina si ganó o perdió
 * 
 * @param int $resultado1 Resultado1 original del jugador
 * @param int $resultadoOponente Resultado1 de la pareja oponente (sin ajustar)
 * @param int $sancion Puntos de sanción del jugador
 * @param int $puntosTorneo Puntos del torneo
 * @return array ['resultado_ajustado' => int, 'gano' => bool, 'efectividad' => int]
 */
function evaluarSancionIndividual($resultado1, $resultadoOponente, $sancion, $puntosTorneo) {
    // Aplicar sanción: resultado ajustado = resultado1 - sanción (mínimo 0)
    $resultadoAjustado = max(0, $resultado1 - $sancion);
    
    // Determinar si ganó o perdió comparando resultado ajustado con oponente
    $gano = ($resultadoAjustado > $resultadoOponente);
    
    // Calcular efectividad según si ganó o perdió
    $mayor = max($resultadoAjustado, $resultadoOponente);
    
    if ($gano) {
        // Ganó: efectividad positiva
        if ($mayor >= $puntosTorneo) {
            $efectividad = calcularEfectividadAlcanzo($resultadoAjustado, $resultadoOponente, $puntosTorneo);
        } else {
            $efectividad = calcularEfectividadNoAlcanzo($resultadoAjustado, $resultadoOponente);
        }
    } else {
        // Perdió: efectividad negativa
        if ($mayor >= $puntosTorneo) {
            $efectividad = -($puntosTorneo - $resultadoAjustado);
        } else {
            $efectividad = -($resultadoOponente - $resultadoAjustado);
        }
    }
    
    return [
        'resultado_ajustado' => $resultadoAjustado,
        'gano' => $gano,
        'efectividad' => $efectividad
    ];
}

/**
 * Calcular efectividad cuando hay sanción
 * Si el resultado ajustado (resultado1 - sanción) es igual o inferior al resultado del oponente,
 * se computa como perdida la partida
 * 
 * @param int $resultado1Ajustado Resultado1 del jugador sancionado menos la sanción
 * @param int $resultadoOponente Resultado1 de la pareja oponente
 * @param int $puntosTorneo Puntos del torneo
 * @param int $sancion Puntos de sanción
 * @return int Efectividad calculada
 */
function calcularEfectividadConSancion($resultado1Ajustado, $resultadoOponente, $puntosTorneo, $sancion) {
    // Si el resultado ajustado es igual o inferior al oponente, se computa como perdida
    if ($resultado1Ajustado <= $resultadoOponente) {
        // Perdió: efectividad negativa
        $mayor = max($resultado1Ajustado, $resultadoOponente);
        if ($mayor >= $puntosTorneo) {
            return -($puntosTorneo - $resultado1Ajustado);
        } else {
            return -($resultadoOponente - $resultado1Ajustado);
        }
    } else {
        // Ganó: calcular efectividad normal con resultado ajustado
        $mayor = max($resultado1Ajustado, $resultadoOponente);
        if ($mayor >= $puntosTorneo) {
            return calcularEfectividadAlcanzo($resultado1Ajustado, $resultadoOponente, $puntosTorneo);
        } else {
            return calcularEfectividadNoAlcanzo($resultado1Ajustado, $resultadoOponente);
        }
    }
}

/**
 * Validar que los puntos no excedan el máximo permitido
 */
function validarPuntos($puntos, $puntosTorneo) {
    // Máximo permitido: puntos del torneo + 60% = puntosTorneo * 1.6
    $maximo = (int)round($puntosTorneo * 1.6);
    if ($puntos > $maximo) {
        return $maximo;
    }
    return $puntos;
}

/**
 * Calcular efectividad cuando hay forfait
 * @param bool $tieneForfait Si true, el jugador tiene forfait (pierde). Si false, el jugador NO tiene forfait (gana).
 * @param int $puntosTorneo Puntos del torneo
 * @return array ['efectividad' => int, 'resultado1' => int, 'resultado2' => int]
 */
function calcularEfectividadForfait($tieneForfait, $puntosTorneo) {
    if ($tieneForfait) {
        // Jugador CON forfait: PIERDE
        return [
            'efectividad' => -$puntosTorneo,
            'resultado1' => 0,
            'resultado2' => $puntosTorneo
        ];
    } else {
        // Jugador SIN forfait: GANA
        // Los ganadores por forfait reciben solo el 50% de efectividad (no el 100%)
        return [
            'efectividad' => (int)($puntosTorneo / 2),
            'resultado1' => $puntosTorneo,
            'resultado2' => 0
        ];
    }
}

/**
 * Calcular efectividad cuando hay tarjeta grave (roja o negra)
 * 
 * REGLAS PARA TARJETA GRAVE:
 * - Los jugadores NO sancionados reciben:
 *   * Puntos del torneo en su totalidad (resultado1 = puntos_torneo, resultado2 = 0)
 *   * Efectividad = puntos del torneo (100% de efectividad)
 * - Los jugadores sancionados (infractores) reciben:
 *   * 0 puntos (resultado1 = 0, resultado2 = puntos_torneo)
 *   * Efectividad = -puntos del torneo
 * 
 * @param bool $tieneTarjetaGrave Si true, el jugador tiene tarjeta grave (pierde). Si false, el jugador NO tiene tarjeta grave (gana).
 * @param int $puntosTorneo Puntos del torneo
 * @return array ['efectividad' => int, 'resultado1' => int, 'resultado2' => int]
 */
function calcularEfectividadTarjetaGrave($tieneTarjetaGrave, $puntosTorneo) {
    if ($tieneTarjetaGrave) {
        // Jugador CON tarjeta grave (infractor): PIERDE
        // Recibe 0 puntos y efectividad negativa igual a -puntos del torneo
        return [
            'efectividad' => -$puntosTorneo,  // -puntos del torneo
            'resultado1' => 0,                 // 0 puntos para el infractor
            'resultado2' => $puntosTorneo      // puntos del torneo para el oponente
        ];
    } else {
        // Jugador SIN tarjeta grave (no sancionado): GANA
        // Recibe los puntos del torneo en su totalidad y efectividad igual a puntos del torneo
        return [
            'efectividad' => $puntosTorneo,    // puntos del torneo (100% de efectividad, no 50% como en forfait)
            'resultado1' => $puntosTorneo,     // puntos del torneo en su totalidad
            'resultado2' => 0                  // 0 puntos para el oponente (infractor)
        ];
    }
}

/**
 * Guarda una mesa adicional
 */
function guardarMesaAdicional($torneo_id, $ronda, $user_id, $is_admin_general) {
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        // Verificar que haya al menos 4 jugadores disponibles (no asignados en esta ronda)
        $pdo = DB::pdo();
        $sql = "SELECT COUNT(*) as total FROM inscritos i
                LEFT JOIN partiresul pr ON pr.id_torneo = i.torneo_id AND pr.id_usuario = i.id_usuario AND pr.partida = ?
                WHERE i.torneo_id = ? AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('i') . " AND pr.id_usuario IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ronda, $torneo_id]);
        $disponibles = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        if ($disponibles < 4) {
            $_SESSION['error'] = 'No hay jugadores disponibles.';
            header('Location: ' . buildRedirectUrl('agregar_mesa', ['torneo_id' => $torneo_id, 'ronda' => $ronda]));
            exit;
        }
        
        $jugadores_ids = $_POST['jugadores'] ?? [];
        
        if (empty($jugadores_ids) || !is_array($jugadores_ids) || count($jugadores_ids) != 4) {
            throw new Exception('Debe seleccionar exactamente 4 jugadores');
        }
        
        // Verificar que los jugadores sean diferentes
        if (count($jugadores_ids) !== count(array_unique($jugadores_ids))) {
            throw new Exception('Los jugadores deben ser diferentes');
        }
        
        $pdo->beginTransaction();
        
        // Obtener el siguiente número de mesa
        $stmt = $pdo->prepare("SELECT MAX(mesa) as max_mesa 
                               FROM partiresul 
                               WHERE id_torneo = ? AND partida = ? AND mesa > 0");
        $stmt->execute([$torneo_id, $ronda]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nuevaMesa = ((int)($result['max_mesa'] ?? 0)) + 1;
        
        // Verificar que los jugadores estén inscritos y no estén ya asignados en esta ronda
        $stmt = $pdo->prepare("SELECT id_usuario FROM inscritos 
                               WHERE torneo_id = ? AND id_usuario = ? AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO);
        $stmt2 = $pdo->prepare("SELECT COUNT(*) as existe 
                                FROM partiresul 
                                WHERE id_torneo = ? AND partida = ? AND id_usuario = ? AND mesa > 0");
        
        foreach ($jugadores_ids as $jugador_id) {
            $stmt->execute([$torneo_id, $jugador_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Uno de los jugadores seleccionados no está inscrito o no está disponible');
            }
            
            $stmt2->execute([$torneo_id, $ronda, $jugador_id]);
            $existe = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($existe && $existe['existe'] > 0) {
                throw new Exception('Uno de los jugadores seleccionados ya está asignado en esta ronda');
            }
        }
        
        // Insertar los jugadores en la nueva mesa
        $stmt = $pdo->prepare("INSERT INTO partiresul 
                               (id_torneo, id_usuario, partida, mesa, secuencia, fecha_partida, registrado)
                               VALUES (?, ?, ?, ?, ?, NOW(), 0)");
        
        foreach ($jugadores_ids as $index => $jugador_id) {
            $stmt->execute([
                $torneo_id,
                $jugador_id,
                $ronda,
                $nuevaMesa,
                $index + 1
            ]);
        }
        
        // Registrar parejas en historial_parejas (id_menor-id_mayor) para evitar que vuelvan a jugar juntos
        try {
            $stmtH = $pdo->prepare(
                "INSERT IGNORE INTO historial_parejas (torneo_id, ronda_id, jugador_1_id, jugador_2_id, llave) VALUES (?, ?, ?, ?, ?)"
            );
            $a = (int)$jugadores_ids[0];
            $c = (int)$jugadores_ids[1];
            $b = (int)$jugadores_ids[2];
            $d = (int)$jugadores_ids[3];
            if ($a > 0 && $c > 0) {
                $idMenor = min($a, $c);
                $idMayor = max($a, $c);
                $stmtH->execute([$torneo_id, $ronda, $idMenor, $idMayor, $idMenor . '-' . $idMayor]);
            }
            if ($b > 0 && $d > 0) {
                $idMenor = min($b, $d);
                $idMayor = max($b, $d);
                $stmtH->execute([$torneo_id, $ronda, $idMenor, $idMayor, $idMenor . '-' . $idMayor]);
            }
        } catch (Exception $e) {
            try {
                $stmtH = $pdo->prepare(
                    "INSERT IGNORE INTO historial_parejas (torneo_id, ronda_id, jugador_1_id, jugador_2_id) VALUES (?, ?, ?, ?)"
                );
                $a = (int)$jugadores_ids[0];
                $c = (int)$jugadores_ids[1];
                $b = (int)$jugadores_ids[2];
                $d = (int)$jugadores_ids[3];
                if ($a > 0 && $c > 0) $stmtH->execute([$torneo_id, $ronda, min($a, $c), max($a, $c)]);
                if ($b > 0 && $d > 0) $stmtH->execute([$torneo_id, $ronda, min($b, $d), max($b, $d)]);
            } catch (Exception $e2) {
                // Tabla puede no existir
            }
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "Mesa adicional {$nuevaMesa} creada exitosamente. Los jugadores han sido asignados y aparecen en la cuadrícula.";
        
        // Redirigir a cuadrícula para que el usuario vea la reconstrucción con los nuevos jugadores
        header('Location: ' . buildRedirectUrl('cuadricula', ['torneo_id' => $torneo_id, 'ronda' => $ronda]));
        exit;
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Error al crear mesa adicional: ' . $e->getMessage();
    }
    
    header('Location: ' . buildRedirectUrl('agregar_mesa', ['torneo_id' => $torneo_id, 'ronda' => $ronda]));
    exit;
}

/**
 * Actualiza estadísticas manualmente
 */
function actualizarEstadisticasManual($torneo_id, $user_id, $is_admin_general) {
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        actualizarEstadisticasInscritos($torneo_id);
        $_SESSION['success'] = 'Estadísticas y puntos de ranking actualizados exitosamente';
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error al actualizar estadísticas: ' . $e->getMessage();
    }
    
        header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
        exit;
}

/**
 * Recalcular puntos y efectividad de todas las partidas BYE (mesa = 0) del torneo.
 * Asigna 100% de los puntos del torneo a resultado1 y 50% a efectividad. El torneo no puede tener 0 puntos.
 */
function recalcularBye($torneo_id, $user_id, $is_admin_general) {
    if ($torneo_id <= 0) {
        $_SESSION['error'] = 'Torneo no especificado.';
        header('Location: ' . buildRedirectUrl('index'));
        exit;
    }
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        $pdo = DB::pdo();

        $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(puntos, 0), 200) AS puntos FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $puntosTorneo = ($row && isset($row['puntos'])) ? (int)$row['puntos'] : 200;
        if ($puntosTorneo <= 0) {
            $puntosTorneo = 200;
        }
        $efectividadBye = (int)round($puntosTorneo * 0.5); // 50% exacto de los puntos del torneo

        $stmt = $pdo->prepare("
            UPDATE partiresul
            SET resultado1 = ?, resultado2 = 0, efectividad = ?, registrado = 1
            WHERE id_torneo = ? AND mesa = 0
        ");
        $stmt->execute([$puntosTorneo, $efectividadBye, $torneo_id]);
        $actualizados = $stmt->rowCount();

        $stmt = $pdo->prepare("SELECT DISTINCT id_usuario FROM partiresul WHERE id_torneo = ? AND mesa = 0");
        $stmt->execute([$torneo_id]);
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int)($fila['id_usuario'] ?? 0);
            if ($uid > 0) {
                InscritosPartiresulHelper::actualizarEstadisticas($uid, $torneo_id);
            }
        }

        $_SESSION['success'] = $actualizados > 0
            ? "BYE recalculados: $actualizados partida(s) actualizadas con $puntosTorneo puntos (100%) y efectividad $efectividadBye (50%)."
            : 'No hay partidas BYE en este torneo.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error al recalcular BYE: ' . $e->getMessage();
    }
    header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
    exit;
}

/**
 * Marca como retirados a los inscritos no presentes antes de generar la 3.ª ronda.
 * No presentes = estatus pendiente y que no tienen ninguna partida en partiresul (nunca participaron).
 * Se ejecuta solo cuando se va a generar la ronda 3; los listados y la siguiente generación ya no los incluyen.
 *
 * @param int $torneo_id
 * @return int Número de inscritos marcados como retirados
 */
function marcarNoPresentesRetiradosAntesRonda3($torneo_id) {
    $pdo = DB::pdo();
    // Compatible con columna estatus ENUM o INT: usar 'retirado' (enum) o 4 (int)
    $estatus_retirado = InscritosHelper::ESTATUS_RETIRADO; // 'retirado'
    $stmt = $pdo->prepare("
        UPDATE inscritos i
        SET i.estatus = ?
        WHERE i.torneo_id = ?
          AND (i.estatus = 0 OR i.estatus = 'pendiente')
          AND NOT EXISTS (
              SELECT 1 FROM partiresul pr
              WHERE pr.id_torneo = i.torneo_id AND pr.id_usuario = i.id_usuario
          )
    ");
    $stmt->execute([$estatus_retirado, $torneo_id]);
    return (int) $stmt->rowCount();
}

/**
 * Actualizar estadísticas de inscritos desde partiresul.
 *
 * BASE: tabla partiresul (única fuente de verdad para resultados de partidas).
 * LLAVE DE ACTUALIZACIÓN: (id_usuario, id_torneo). Se agregan los campos computables
 * por esa llave y se actualiza la tabla inscritos con esos totales.
 *
 * Tarjetas en inscritos: se guarda el valor de la ÚLTIMA tarjeta (partida más reciente).
 * Se consulta inscritos cuando hay sanción 80 pts o tarjeta directa: si inscritos.tarjeta = 0
 * → amarilla en formulario y partiresul; si inscritos.tarjeta > 0 → roja en formulario y partiresul.
 *
 * Norma: una fila por jugador por partida en partiresul; se eliminan duplicados
 * (mismo id_usuario, id_torneo, partida) y se agrega por (id_usuario, id_torneo).
 * Solo se consideran filas con registrado = 1 (mesas y BYE).
 */
function actualizarEstadisticasInscritos($torneo_id) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT id FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Torneo no encontrado");
    }
    
    // Eliminar duplicados: una sola fila por (id_torneo, id_usuario, partida), conservar la de menor id
    $stmtDup = $pdo->prepare("
        SELECT pr.id FROM partiresul pr
        INNER JOIN (
            SELECT id_torneo, id_usuario, partida, MIN(id) AS keep_id
            FROM partiresul WHERE id_torneo = ?
            GROUP BY id_torneo, id_usuario, partida
            HAVING COUNT(*) > 1
        ) dup ON pr.id_torneo = dup.id_torneo AND pr.id_usuario = dup.id_usuario AND pr.partida = dup.partida AND pr.id != dup.keep_id
    ");
    $stmtDup->execute([$torneo_id]);
    $idsEliminar = $stmtDup->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($idsEliminar)) {
        $placeholders = implode(',', array_fill(0, count($idsEliminar), '?'));
        $pdo->prepare("DELETE FROM partiresul WHERE id IN ($placeholders)")->execute($idsEliminar);
    }
    
    // No se inicializa a 0 toda la tabla: solo se actualiza con totales desde partiresul y
    // se pone a 0 únicamente a los inscritos que no tienen partidas registradas.
    
    // 1) Actualizar inscritos que tienen partidas: sumas desde partiresul; tarjeta = valor de la ÚLTIMA tarjeta (por partida más reciente)
    $sqlUpdate = "
        UPDATE inscritos i
        INNER JOIN (
            SELECT
                id_usuario,
                id_torneo,
                SUM(ganado) AS ganados,
                SUM(perdido) AS perdidos,
                SUM(efectividad) AS efectividad,
                SUM(puntos) AS puntos,
                SUM(sancion) AS sancion,
                SUM(chancletas) AS chancletas,
                SUM(zapatos) AS zapatos,
                CAST(SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(por_ronda.tarjeta, 0) ORDER BY por_ronda.partida DESC SEPARATOR ','), ',', 1) AS UNSIGNED) AS tarjeta
            FROM (
                SELECT
                    id_usuario,
                    id_torneo,
                    partida,
                    MAX(CASE WHEN resultado1 > resultado2 THEN 1 ELSE 0 END) AS ganado,
                    MAX(CASE WHEN resultado1 < resultado2 THEN 1 ELSE 0 END) AS perdido,
                    MAX(COALESCE(efectividad, 0)) AS efectividad,
                    MAX(COALESCE(resultado1, 0)) AS puntos,
                    MAX(COALESCE(sancion, 0)) AS sancion,
                    MAX(COALESCE(chancleta, 0)) AS chancletas,
                    MAX(COALESCE(zapato, 0)) AS zapatos,
                    MAX(COALESCE(tarjeta, 0)) AS tarjeta
                FROM partiresul
                WHERE id_torneo = ? AND registrado = 1
                GROUP BY id_usuario, id_torneo, partida
            ) por_ronda
            GROUP BY id_usuario, id_torneo
        ) agg ON i.id_usuario = agg.id_usuario AND i.torneo_id = agg.id_torneo
        SET
            i.ganados = agg.ganados,
            i.perdidos = agg.perdidos,
            i.efectividad = agg.efectividad,
            i.puntos = agg.puntos,
            i.sancion = agg.sancion,
            i.chancletas = agg.chancletas,
            i.zapatos = agg.zapatos,
            i.tarjeta = agg.tarjeta
        WHERE i.torneo_id = ?
    ";
    $pdo->prepare($sqlUpdate)->execute([$torneo_id, $torneo_id]);
    
    // 2) Poner a 0 solo los inscritos del torneo que no tienen ninguna partida registrada en partiresul
    $pdo->prepare("
        UPDATE inscritos i
        LEFT JOIN (
            SELECT DISTINCT id_usuario, id_torneo
            FROM partiresul
            WHERE id_torneo = ? AND registrado = 1
        ) has_data ON i.id_usuario = has_data.id_usuario AND i.torneo_id = has_data.id_torneo
        SET i.ganados = 0, i.perdidos = 0, i.efectividad = 0, i.puntos = 0,
            i.sancion = 0, i.chancletas = 0, i.zapatos = 0, i.tarjeta = 0
        WHERE i.torneo_id = ? AND has_data.id_usuario IS NULL
    ")->execute([$torneo_id, $torneo_id]);
    
    recalcularClasificacionEquiposYJugadores($torneo_id);
}

/**
 * Recalcula toda la clasificación para torneos por equipos:
 * 1) Recalcula posiciones de inscritos (usa estadísticas vigentes en inscritos/partiresul)
 * 2) Actualiza estadísticas de equipos y su posición
 * 3) Sincroniza clasiequi en inscritos y numera 1..4 dentro de cada código de equipo
 */
function recalcularClasificacionEquiposYJugadores($torneo_id) {
    // Paso 1: recalcular posiciones individuales
    recalcularPosiciones($torneo_id);
    // Paso 2: actualizar stats y posición de equipos (sincroniza clasiequi en inscritos)
    actualizarEstadisticasEquipos($torneo_id);
    // Paso 3: numerar 1..4 dentro de cada equipo según clasificación individual
    asignarNumeroSecuencialPorEquipo($torneo_id);
}

/**
 * Actualiza las estadísticas de equipos desde la tabla inscritos
 * Suma los valores de puntos, ganados, perdidos y calcula efectividad promedio
 * por codigo_equipo
 */
function actualizarEstadisticasEquipos($torneo_id) {
    $pdo = DB::pdo();
    
    // Verificar si el torneo es modalidad equipos (modalidad 3)
    $stmt = $pdo->prepare("SELECT modalidad FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$torneo || (int)($torneo['modalidad'] ?? 0) !== 3) {
        // No es torneo de equipos, no hay nada que actualizar
        return;
    }
    
    // Obtener estadísticas agregadas por codigo_equipo desde inscritos
    // Suma de puntos, ganados, perdidos, efectividad (suma de todas las efectividades), sanciones
    $sql = "SELECT 
                codigo_equipo,
                SUM(puntos) as puntos_equipo,
                SUM(ganados) as ganados_equipo,
                SUM(perdidos) as perdidos_equipo,
                SUM(efectividad) as efectividad_equipo,
                SUM(sancion) as sancion_equipo,
                COUNT(*) as total_jugadores
            FROM inscritos
            WHERE torneo_id = ? 
                AND codigo_equipo IS NOT NULL 
                AND codigo_equipo != ''
                AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO . "
            GROUP BY codigo_equipo";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id]);
    $estadisticasEquipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($estadisticasEquipos)) {
        // No hay equipos con inscritos, no hay nada que actualizar
        return;
    }
    
    // Actualizar cada equipo con sus estadísticas agregadas
    $stmtUpdate = $pdo->prepare("
        UPDATE equipos 
        SET puntos = ?,
            ganados = ?,
            perdidos = ?,
            efectividad = ?,
            sancion = ?,
            fecha_actualizacion = CURRENT_TIMESTAMP
        WHERE id_torneo = ? AND codigo_equipo = ?
    ");
    
    foreach ($estadisticasEquipos as $stats) {
        $codigoEquipo = $stats['codigo_equipo'];
        $puntosEquipo = (int)($stats['puntos_equipo'] ?? 0);
        $ganadosEquipo = (int)($stats['ganados_equipo'] ?? 0);
        $perdidosEquipo = (int)($stats['perdidos_equipo'] ?? 0);
        $efectividadEquipo = (int)($stats['efectividad_equipo'] ?? 0); // Suma de efectividades de todos los jugadores
        $sancionEquipo = (int)($stats['sancion_equipo'] ?? 0);
        
        $stmtUpdate->execute([
            $puntosEquipo,
            $ganadosEquipo,
            $perdidosEquipo,
            $efectividadEquipo,
            $sancionEquipo,
            $torneo_id,
            $codigoEquipo
        ]);
    }
    
    // Recalcular posiciones de equipos después de actualizar estadísticas
    recalcularPosicionesEquipos($torneo_id);
}

/**
 * Recalcular posiciones de equipos según sus estadísticas
 * Orden: 1. Puntos DESC, 2. Ganados DESC, 3. Efectividad DESC
 */
function recalcularPosicionesEquipos($torneo_id) {
    $pdo = DB::pdo();
    
    // Obtener equipos ordenados por clasificación (ganados DESC, efectividad DESC, puntos DESC)
    $sql = "SELECT codigo_equipo, puntos, ganados, efectividad
            FROM equipos
            WHERE id_torneo = ? AND estatus = 0
            ORDER BY ganados DESC, efectividad DESC, puntos DESC, codigo_equipo ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id]);
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($equipos)) {
        return;
    }
    
    // Actualizar posiciones secuencialmente
    $stmtUpdate = $pdo->prepare("
        UPDATE equipos 
        SET posicion = ?
        WHERE id_torneo = ? AND codigo_equipo = ?
    ");
    // Preparar update para sincronizar clasiequi en inscritos con la posición del equipo
    $stmtUpdateInscritos = $pdo->prepare("
        UPDATE inscritos
        SET clasiequi = ?
        WHERE torneo_id = ? AND codigo_equipo = ? AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO . "
    ");
    
    $posicion = 1;
    foreach ($equipos as $equipo) {
        $stmtUpdate->execute([
            $posicion,
            $torneo_id,
            $equipo['codigo_equipo']
        ]);
        
        // Sincronizar campo clasiequi en inscritos con la clasificación del equipo
        $stmtUpdateInscritos->execute([
            $posicion,
            $torneo_id,
            $equipo['codigo_equipo']
        ]);
        $posicion++;
    }
}

/**
 * Asigna numero 1..4 dentro de cada equipo según clasificación individual:
 * Orden: ganados DESC, efectividad DESC, puntos DESC, id_usuario ASC.
 */
function asignarNumeroSecuencialPorEquipo($torneo_id) {
    $pdo = DB::pdo();
    $stmtEquipos = $pdo->prepare("
        SELECT DISTINCT codigo_equipo
        FROM inscritos
        WHERE torneo_id = ? AND codigo_equipo IS NOT NULL AND codigo_equipo != '' AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO . "
    ");
    $stmtEquipos->execute([$torneo_id]);
    $codigos = $stmtEquipos->fetchAll(PDO::FETCH_COLUMN);

    $stmtJugadores = $pdo->prepare("
        SELECT id
        FROM inscritos
        WHERE torneo_id = ? AND codigo_equipo = ? AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO . "
        ORDER BY 
            CAST(ganados AS SIGNED) DESC,
            CAST(efectividad AS SIGNED) DESC,
            CAST(puntos AS SIGNED) DESC,
            id_usuario ASC
    ");
    $stmtUpdateNumero = $pdo->prepare("UPDATE inscritos SET numero = ? WHERE id = ?");

    foreach ($codigos as $codigo) {
        $stmtJugadores->execute([$torneo_id, $codigo]);
        $jugadoresEquipo = $stmtJugadores->fetchAll(PDO::FETCH_ASSOC);

        $numeroSecuencial = 1;
        foreach ($jugadoresEquipo as $jug) {
            $stmtUpdateNumero->execute([$numeroSecuencial, $jug['id']]);
            $numeroSecuencial++;
        }
    }
}

/**
 * Recalcular posiciones de todos los inscritos
 */
/**
 * Recalcular posiciones de todos los inscritos
 * Orden de clasificación: 1. Ganados DESC, 2. Efectividad DESC, 3. Puntos DESC
 * Las posiciones deben ser consecutivas (1, 2, 3, 4...) sin repeticiones
 */
function recalcularPosiciones($torneo_id) {
    try {
        $pdo = DB::pdo();
        
        error_log("recalcularPosiciones: Iniciando para torneo_id = $torneo_id");
        
        // Obtener información del torneo para saber el tipo
        $stmt = $pdo->prepare("SELECT modalidad, nombre FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$torneo) {
            error_log("recalcularPosiciones: Torneo no encontrado");
            return;
        }
        
        // Mapear modalidad a tipo de torneo
        // modalidad puede ser INT (1=Individual, 2=Parejas, 3=Equipos) o texto
        $modalidad = $torneo['modalidad'] ?? 1;
        $tipoTorneo = 1; // Por defecto Individual
        
        if (is_numeric($modalidad)) {
            // Si es numérico, usar directamente
            $tipoTorneo = (int)$modalidad;
        } else {
            // Si es texto, convertir
            $modalidad_str = strtolower(trim((string)$modalidad));
            if (stripos($modalidad_str, 'pareja') !== false) {
                $tipoTorneo = 2;
            } elseif (stripos($modalidad_str, 'equipo') !== false) {
                $tipoTorneo = 3;
            }
        }
        
        // Asegurar que el tipo esté en el rango válido (1-3)
        if ($tipoTorneo < 1 || $tipoTorneo > 3) {
            $tipoTorneo = 1;
        }
        
        // Definir límite de posiciones según tipo de torneo
        // Individual: hasta posición 30, Parejas: hasta posición 20, Equipos: hasta posición 10
        $limitePosiciones = 30; // Por defecto Individual
        if ($tipoTorneo == 2) {
            $limitePosiciones = 20; // Parejas
        } elseif ($tipoTorneo == 3) {
            $limitePosiciones = 10; // Equipos
        }
        
        error_log("recalcularPosiciones: Tipo torneo = $tipoTorneo, Límite posiciones = $limitePosiciones");
        
        // Primero, resetear todas las posiciones a 0 para evitar conflictos
        $stmt = $pdo->prepare("UPDATE inscritos SET posicion = 0 WHERE torneo_id = ?");
        $stmt->execute([$torneo_id]);
        $reseteados = $stmt->rowCount();
        error_log("recalcularPosiciones: Reseteados $reseteados registros");
        
        // Obtener inscritos ordenados por: 1. ganados DESC, 2. efectividad DESC, 3. puntos DESC
        // Filtro: excluir retirados
        // Asegurar que los valores sean numéricos en el ORDER BY usando CAST
        $stmt = $pdo->prepare("SELECT id, id_usuario, 
                               CAST(ganados AS SIGNED) as ganados, 
                               CAST(efectividad AS SIGNED) as efectividad, 
                               CAST(puntos AS SIGNED) as puntos
                               FROM inscritos 
                               WHERE torneo_id = ? AND " . InscritosHelper::SQL_WHERE_SOLO_CONFIRMADO . "
                               ORDER BY CAST(ganados AS SIGNED) DESC, 
                                        CAST(efectividad AS SIGNED) DESC, 
                                        CAST(puntos AS SIGNED) DESC");
        $stmt->execute([$torneo_id]);
        $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("recalcularPosiciones: Encontrados " . count($inscritos) . " inscritos");
        
        if (empty($inscritos)) {
            error_log("recalcularPosiciones: No hay inscritos para actualizar");
            return;
        }
        
        // Actualizar posiciones consecutivamente (1, 2, 3, 4...) y calcular puntos de ranking
        // Cada jugador recibe una posición única, incluso si hay empates en los valores
        $posicion = 1;
        $actualizados = 0;
        $puntosRankingActualizados = 0;
        
        foreach ($inscritos as $inscrito) {
            $id = (int)$inscrito['id'];
            $ganados = (int)($inscrito['ganados'] ?? 0);
            
            // Calcular puntos de ranking según la posición actual
            $ptosrnk = 1; // Por defecto, punto por participación
            
            if ($posicion <= $limitePosiciones) {
                // Obtener configuración de ranking para esta posición y tipo de torneo
                $stmt = $pdo->prepare("SELECT puntos_posicion, puntos_por_partida_ganada 
                                       FROM clasiranking 
                                       WHERE tipo_torneo = ? AND clasificacion = ? 
                                       LIMIT 1");
                $stmt->execute([$tipoTorneo, $posicion]);
                $ranking = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ranking) {
                    $puntosPorPosicion = (int)$ranking['puntos_posicion'];
                    $puntosPorPartidaGanada = (int)$ranking['puntos_por_partida_ganada'];
                    // ptosrnk = puntos por posición + (partidas ganadas × puntos por partida ganada) + 1 punto por participación
                    $ptosrnk = $puntosPorPosicion + ($ganados * $puntosPorPartidaGanada) + 1;
                }
            }
            
            // Actualizar posición y puntos de ranking
            $stmt = $pdo->prepare("UPDATE inscritos SET posicion = ?, ptosrnk = ? WHERE id = ?");
            $result = $stmt->execute([$posicion, $ptosrnk, $id]);
            if ($result) {
                $actualizados++;
                $puntosRankingActualizados++;
            } else {
                error_log("recalcularPosiciones: Error al actualizar posición para inscrito id=$id");
            }
            $posicion++;
        }
        
        error_log("recalcularPosiciones: Actualizadas $actualizados posiciones y $puntosRankingActualizados puntos de ranking");
        
        // Verificar que no hay duplicados
        $stmt = $pdo->prepare("SELECT posicion, COUNT(*) as cantidad 
                               FROM inscritos 
                               WHERE torneo_id = ? AND posicion > 0
                               GROUP BY posicion 
                               HAVING cantidad > 1");
        $stmt->execute([$torneo_id]);
        $duplicados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($duplicados)) {
            error_log("ADVERTENCIA: Se encontraron posiciones duplicadas en el torneo $torneo_id: " . json_encode($duplicados));
        } else {
            error_log("recalcularPosiciones: No se encontraron posiciones duplicadas");
        }
        
    } catch (Exception $e) {
        error_log("ERROR en recalcularPosiciones: " . $e->getMessage());
        error_log("ERROR stack trace: " . $e->getTraceAsString());
        throw $e;
    }
}

/**
 * Obtiene datos para mostrar formulario de reasignación de mesa
 */
function obtenerDatosReasignarMesa($torneo_id, $ronda, $mesa) {
    $pdo = DB::pdo();
    
    // Obtener jugadores de la mesa actual
    $stmt = $pdo->prepare("SELECT 
                pr.*,
                u.nombre as nombre_completo,
                i.posicion,
                i.ganados,
                i.perdidos,
                i.efectividad
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            LEFT JOIN inscritos i ON i.id_usuario = u.id AND i.torneo_id = pr.id_torneo
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
            ORDER BY pr.secuencia ASC");
    $stmt->execute([$torneo_id, $ronda, $mesa]);
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener todas las mesas de la ronda
    $stmt = $pdo->prepare("SELECT DISTINCT 
                CAST(pr.mesa AS UNSIGNED) as numero,
                MAX(pr.registrado) as registrado,
                COUNT(DISTINCT pr.id_usuario) as total_jugadores
            FROM partiresul pr
            WHERE pr.id_torneo = ? 
              AND pr.partida = ? 
              AND pr.mesa IS NOT NULL 
              AND pr.mesa > 0 
              AND CAST(pr.mesa AS UNSIGNED) > 0
            GROUP BY CAST(pr.mesa AS UNSIGNED)
            ORDER BY CAST(pr.mesa AS UNSIGNED) ASC");
    $stmt->execute([$torneo_id, $ronda]);
    $todasLasMesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir numero a entero y ordenar
    foreach ($todasLasMesas as &$m) {
        $m['numero'] = (int)$m['numero'];
        $m['tiene_resultados'] = $m['registrado'];
    }
    usort($todasLasMesas, function($a, $b) {
        return $a['numero'] <=> $b['numero'];
    });
    
    // Obtener todas las rondas del torneo
    $stmt = $pdo->prepare("SELECT DISTINCT partida as ronda 
                          FROM partiresul 
                          WHERE id_torneo = ? AND partida > 0 
                          ORDER BY partida ASC");
    $stmt->execute([$torneo_id]);
    $todasLasRondas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Determinar mesa anterior y siguiente
    $mesaAnterior = null;
    $mesaSiguiente = null;
    foreach ($todasLasMesas as $index => $m) {
        if ($m['numero'] == $mesa) {
            if ($index > 0) {
                $mesaAnterior = $todasLasMesas[$index - 1]['numero'];
            }
            if ($index < count($todasLasMesas) - 1) {
                $mesaSiguiente = $todasLasMesas[$index + 1]['numero'];
            }
            break;
        }
    }
    
    return [
        'jugadores' => $jugadores,
        'todasLasMesas' => $todasLasMesas,
        'todasLasRondas' => $todasLasRondas,
        'mesaAnterior' => $mesaAnterior,
        'mesaSiguiente' => $mesaSiguiente
    ];
}

/**
 * Ejecuta la reasignación de una mesa
 */
function ejecutarReasignacion($torneo_id, $ronda, $mesa, $user_id, $is_admin_general) {
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        // Verificar CSRF
        $csrf_token = $_POST['csrf_token'] ?? '';
        $session_token = $_SESSION['csrf_token'] ?? '';
        if (!$csrf_token || !$session_token || !hash_equals($session_token, $csrf_token)) {
            throw new Exception('Token de seguridad inválido. Por favor, recarga la página e intenta nuevamente.');
        }
        
        $opcion = (int)($_POST['opcion_reasignacion'] ?? 0);
        
        if (!in_array($opcion, [1, 2, 3, 4, 5, 6])) {
            throw new Exception('Opción de reasignación no válida');
        }
        
        $pdo = DB::pdo();
        
        // Obtener jugadores actuales de la mesa
        $stmt = $pdo->prepare("SELECT * FROM partiresul 
                              WHERE id_torneo = ? AND partida = ? AND mesa = ?
                              ORDER BY secuencia ASC");
        $stmt->execute([$torneo_id, $ronda, $mesa]);
        $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($jugadores) != 4) {
            throw new Exception('La mesa debe tener exactamente 4 jugadores');
        }
        
        // Crear mapa de secuencias actuales
        $mapaActual = [];
        foreach ($jugadores as $jugador) {
            $mapaActual[$jugador['secuencia']] = $jugador;
        }
        
        // Definir cambios según la opción
        $cambios = [];
        switch ($opcion) {
            case 1: // 1 con 3
                $cambios = [[1, 3], [3, 1]];
                break;
            case 2: // 1 con 4
                $cambios = [[1, 4], [4, 1]];
                break;
            case 3: // 2 con 3
                $cambios = [[2, 3], [3, 2]];
                break;
            case 4: // 2 con 4
                $cambios = [[2, 4], [4, 2]];
                break;
            case 5: // 1 con 3 y 2 con 4 (intercambio completo de parejas)
                $cambios = [[1, 3], [3, 1], [2, 4], [4, 2]];
                break;
            case 6: // 1 con 4 y 2 con 3 (intercambio cruzado)
                $cambios = [[1, 4], [4, 1], [2, 3], [3, 2]];
                break;
        }
        
        $pdo->beginTransaction();
        
        try {
            // Crear mapa de cambios finales
            $mapaFinal = [];
            foreach ($mapaActual as $seq => $jugador) {
                $mapaFinal[$seq] = $jugador['id_usuario'];
            }
            
            // Aplicar cambios
            foreach ($cambios as $cambio) {
                $secuenciaOrigen = $cambio[0];
                $secuenciaDestino = $cambio[1];
                $temp = $mapaFinal[$secuenciaOrigen];
                $mapaFinal[$secuenciaOrigen] = $mapaFinal[$secuenciaDestino];
                $mapaFinal[$secuenciaDestino] = $temp;
            }
            
            // Actualizar cada jugador a su nueva secuencia
            foreach ($mapaFinal as $nuevaSecuencia => $idUsuario) {
                $stmt = $pdo->prepare("UPDATE partiresul 
                                      SET secuencia = ? 
                                      WHERE id_torneo = ? 
                                        AND partida = ? 
                                        AND mesa = ? 
                                        AND id_usuario = ?");
                $stmt->execute([$nuevaSecuencia, $torneo_id, $ronda, $mesa, $idUsuario]);
            }
            
            $pdo->commit();
            $_SESSION['success'] = 'Mesa reasignada exitosamente. Los cambios se han aplicado correctamente.';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
        header('Location: ' . buildRedirectUrl('registrar_resultados', [
            'torneo_id' => $torneo_id, 
            'ronda' => $ronda, 
            'mesa' => $mesa
        ]));
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error al reasignar la mesa: ' . $e->getMessage();
        error_log("Error en reasignación de mesa: " . $e->getMessage());
        header('Location: ' . buildRedirectUrl('reasignar_mesa', [
            'torneo_id' => $torneo_id, 
            'ronda' => $ronda, 
            'mesa' => $mesa
        ]));
        exit;
    }
}

