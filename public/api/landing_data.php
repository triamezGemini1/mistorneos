<?php
/**
 * API Landing - Datos para la SPA de la landing page
 * GET: Retorna todos los datos necesarios para renderizar la landing
 * Usa exclusivamente LandingDataService como fuente de datos.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../lib/app_helpers.php';
require_once __DIR__ . '/../../lib/UrlHelper.php';
require_once __DIR__ . '/../../lib/LandingDataService.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$baseUrl = rtrim(app_base_url(), '/') . '/public/';
$entidadParam = isset($_GET['entidad']) ? (int)$_GET['entidad'] : 0;

function getAficheUrlApi($torneo, $baseUrl) {
    if (!empty($torneo['afiche'])) {
        $file = basename($torneo['afiche']);
        return $baseUrl . 'view_tournament_file.php?file=' . urlencode($file);
    }
    return null;
}

function getLogoOrganizacionUrlApi($evento, $baseUrl) {
    if (!empty($evento['organizacion_logo'])) {
        return $baseUrl . 'view_image.php?path=' . rawurlencode($evento['organizacion_logo']);
    }
    return null;
}

function limpiarNombreTorneo($nombre) {
    if (empty($nombre)) return $nombre;
    $nombre = preg_replace('/\bmasivos?\b/i', '', $nombre);
    $nombre = preg_replace('/\s+Masivos\s*/i', ' ', $nombre);
    $nombre = preg_replace('/^Masivos\s+/i', '', $nombre);
    $nombre = preg_replace('/\s+Masivos$/i', '', $nombre);
    $nombre = preg_replace('/\s+/', ' ', $nombre);
    return trim($nombre);
}

function enriquecerEvento(&$ev, $baseUrl) {
    $ev['logo_url'] = getLogoOrganizacionUrlApi($ev, $baseUrl);
    $ev['afiche_url'] = getAficheUrlApi($ev, $baseUrl);
    $ev['nombre_limpio'] = limpiarNombreTorneo($ev['nombre'] ?? '');
}

try {
    $pdo = DB::pdo();
    $user = Auth::user();
    $service = new LandingDataService($pdo);

    // Eventos realizados (LandingDataService)
    $eventos_realizados = $service->getEventosRealizados(50);
    foreach ($eventos_realizados as &$ev) {
        enriquecerEvento($ev, $baseUrl);
    }
    unset($ev);

    // Eventos futuros (LandingDataService)
    $eventos_todos_futuros = $service->getProximosEventos(500);
    $eventos_futuros = array_values(array_filter($eventos_todos_futuros, fn($e) => ($e['es_evento_masivo'] ?? null) === null || $e['es_evento_masivo'] === '' || in_array((int)($e['es_evento_masivo'] ?? 0), [0, 4])));
    $eventos_inscripcion_linea = array_values(array_filter($eventos_todos_futuros, fn($e) => in_array((int)($e['es_evento_masivo'] ?? 0), [1, 2, 3])));
    $eventos_masivos = $eventos_inscripcion_linea;
    $eventos_privados = array_values(array_filter($eventos_todos_futuros, fn($e) => (int)($e['es_evento_masivo'] ?? 0) === 4));

    foreach (array_merge($eventos_futuros, $eventos_masivos, $eventos_privados) as &$ev) {
        enriquecerEvento($ev, $baseUrl);
    }
    unset($ev);

    // Entidades con eventos (LandingDataService)
    $entidades_con_eventos = $service->getEntidadesConEventos();

    // Eventos por entidad/club (filtro de usuario o parámetro)
    $eventos_mi_entidad = [];
    $filtro_aplicado_entidad = '';
    $entidad_nombre_usuario = '';
    $entidad_filtro = $entidadParam;

    if ($user && $entidadParam === 0) {
        $user_entidad = (int)($user['entidad'] ?? 0);
        $user_club_id = (int)($user['club_id'] ?? 0);
        $user_role = $user['role'] ?? 'usuario';
        if ($user_role === 'admin_club' || $user_role === 'admin_torneo') {
            $entidad_filtro = $user_entidad;
        } elseif ($user_club_id > 0) {
            $org_id = $service->getOrgIdPorClub($user_club_id);
            if ($org_id) {
                $eventos_mi_entidad = $service->getProximosEventosPorOrganizaciones([$org_id], 12);
                try {
                    $stmt = $pdo->prepare("SELECT nombre FROM clubes WHERE id = ? LIMIT 1");
                    $stmt->execute([$user_club_id]);
                    $club_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($club_data) {
                        $entidad_nombre_usuario = $club_data['nombre'];
                        $filtro_aplicado_entidad = "de su club: " . $club_data['nombre'];
                    }
                } catch (Exception $e) {}
            }
            $entidad_filtro = 0;
        } elseif ($user_entidad > 0) {
            $entidad_filtro = $user_entidad;
        }
    } elseif ($entidadParam > 0) {
        $entidad_filtro = $entidadParam;
    }

    if ($entidad_filtro > 0 && empty($eventos_mi_entidad)) {
        try {
            $stmt = $pdo->prepare("SELECT nombre FROM entidad WHERE id = ? LIMIT 1");
            $stmt->execute([$entidad_filtro]);
            $ent_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ent_data) {
                $entidad_nombre_usuario = $ent_data['nombre'];
                $filtro_aplicado_entidad = "de la entidad: " . $entidad_nombre_usuario;
            }
        } catch (Exception $e) {}
        $eventos_mi_entidad = $service->getProximosEventosPorEntidad($entidad_filtro, 12);
    }

    foreach ($eventos_mi_entidad as &$ev) {
        enriquecerEvento($ev, $baseUrl);
    }
    unset($ev);

    // Calendario (LandingDataService)
    $eventos_calendario = $service->getEventosCalendario();
    $eventos_por_fecha = [];
    foreach ($eventos_calendario as $ev) {
        $fecha_key = date('Y-m-d', strtotime($ev['fechator'] ?? ''));
        if (!isset($eventos_por_fecha[$fecha_key])) {
            $eventos_por_fecha[$fecha_key] = [];
        }
        enriquecerEvento($ev, $baseUrl);
        $eventos_por_fecha[$fecha_key][] = $ev;
    }

    // Comentarios aprobados
    $comentarios = [];
    try {
        $comentarios = $pdo->query("
            SELECT c.*, u.username as usuario_username, u.nombre as usuario_nombre
            FROM comentariossugerencias c
            LEFT JOIN usuarios u ON c.usuario_id = u.id
            WHERE c.estatus = 'aprobado'
            ORDER BY c.fecha_creacion DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    // Logos de clientes: desde carpeta de logos de clubes (tabla clubes, columna logo = upload/logos/...)
    $logos_clientes = [];
    try {
        $stmt = $pdo->prepare("SELECT id, nombre, logo FROM clubes WHERE logo IS NOT NULL AND TRIM(logo) != '' AND (estatus = 1 OR estatus = '1') ORDER BY nombre ASC");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $logos_clientes[] = ['nombre' => $row['nombre'] ?? 'Club', 'path' => $row['logo']];
        }
    } catch (Exception $e) {}

    $csrf_token = CSRF::token();

    echo json_encode([
        'success' => true,
        'base_url' => $baseUrl,
        'user' => $user ? [
            'id' => Auth::id() ?: null,
            'nombre' => $user['nombre'] ?? $user['username'] ?? '',
            'username' => $user['username'] ?? '',
        ] : null,
        'csrf_token' => $csrf_token,
        'eventos_realizados' => $eventos_realizados,
        'eventos_futuros' => $eventos_futuros,
        'eventos_masivos' => $eventos_masivos,
        'eventos_inscripcion_linea' => $eventos_inscripcion_linea,
        'eventos_privados' => $eventos_privados,
        'entidades_con_eventos' => $entidades_con_eventos,
        'eventos_mi_entidad' => $eventos_mi_entidad,
        'eventos_por_fecha' => $eventos_por_fecha,
        'comentarios' => $comentarios,
        'entidad_seleccionada' => $entidadParam,
        'filtro_aplicado_entidad' => $filtro_aplicado_entidad,
        'entidad_nombre_usuario' => $entidad_nombre_usuario,
        'logos_clientes' => $logos_clientes,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API landing_data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar los datos',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
