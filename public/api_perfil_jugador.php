<?php
declare(strict_types=1);
/**
 * API JSON para la SPA de perfil jugador (móvil, QR + Cédula).
 * Valida cédula solo contra tabla inscritos del torneo actual.
 * Devuelve: jugador, mesa asignada (ronda activa), resumen individual, url clasificación.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/app_helpers.php';
require_once __DIR__ . '/../lib/InscritosPartiresulHelper.php';

$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : (int)($_POST['torneo_id'] ?? 0);
$cedula_raw = trim((string)($_GET['cedula'] ?? $_POST['cedula'] ?? ''));

// Normalizar cédula (V/E/J/P + dígitos)
$cedula = preg_replace('/[^0-9VEJPvejp]/', '', $cedula_raw);
if (strlen($cedula) > 1 && preg_match('/^[VEJP]/i', $cedula_raw)) {
    $cedula = strtoupper(substr($cedula_raw, 0, 1)) . preg_replace('/\D/', '', $cedula_raw);
}

$response = ['ok' => false, 'error' => null, 'jugador' => null, 'torneo' => null, 'mesa_actual' => null, 'resumen' => null, 'url_clasificacion' => null];

if ($torneo_id <= 0) {
    $response['error'] = 'Falta el torneo. Use el enlace del QR del evento.';
    echo json_encode($response);
    exit;
}

if ($cedula === '') {
    $response['error'] = 'Ingrese su cédula.';
    echo json_encode($response);
    exit;
}

try {
    $pdo = DB::pdo();

    $stmt = $pdo->prepare("
        SELECT t.id, t.nombre, t.estatus
        FROM tournaments t
        WHERE t.id = ? AND t.estatus = 1
    ");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$torneo) {
        $response['error'] = 'Torneo no encontrado o no disponible.';
        echo json_encode($response);
        exit;
    }

    $cedula_variantes = array_unique(array_filter([
        preg_replace('/^[VEJP]/i', '', $cedula),
        $cedula,
        strlen($cedula) >= 6 && !preg_match('/^[VEJP]/i', $cedula) ? 'V' . $cedula : null,
        strlen($cedula) >= 6 && !preg_match('/^[VEJP]/i', $cedula) ? 'E' . $cedula : null,
    ]));

    $jugador = null;
    foreach ($cedula_variantes as $c) {
        $stmt = $pdo->prepare("SELECT id, nombre, cedula FROM usuarios WHERE cedula = ? LIMIT 1");
        $stmt->execute([$c]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) continue;

        $stmt = $pdo->prepare("
            SELECT i.id_usuario, i.torneo_id, u.nombre, u.cedula
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            WHERE i.torneo_id = ? AND i.id_usuario = ?
            AND (i.estatus IS NULL OR i.estatus = 1 OR i.estatus = 2 OR i.estatus = '1' OR i.estatus = 'confirmado')
            LIMIT 1
        ");
        $stmt->execute([$torneo_id, (int)$u['id']]);
        $jugador = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($jugador) {
            $jugador['id_usuario'] = (int)$jugador['id_usuario'];
            break;
        }
    }

    if (!$jugador) {
        $response['error'] = 'No está inscrito en este torneo con esta cédula.';
        echo json_encode($response);
        exit;
    }

    $id_usuario = (int)$jugador['id_usuario'];
    $base_public = rtrim(AppHelpers::getPublicUrl(), '/');

    $response['ok'] = true;
    $response['jugador'] = [
        'id_usuario' => $id_usuario,
        'nombre' => $jugador['nombre'] ?? '',
        'cedula' => $jugador['cedula'] ?? $cedula_raw,
    ];
    $response['torneo'] = ['id' => (int)$torneo['id'], 'nombre' => $torneo['nombre'] ?? ''];

    // Ronda activa: MAX(partida) del torneo
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(partida), 0) as ronda FROM partiresul WHERE id_torneo = ?");
    $stmt->execute([$torneo_id]);
    $ronda_activa = (int)$stmt->fetchColumn();

    $mesa_actual = null;
    $ubicaciones = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D'];
    if ($ronda_activa > 0) {
        $stmt = $pdo->prepare("
            SELECT partida, mesa, secuencia
            FROM partiresul
            WHERE id_torneo = ? AND id_usuario = ? AND partida = ?
            LIMIT 1
        ");
        $stmt->execute([$torneo_id, $id_usuario, $ronda_activa]);
        $mi_fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($mi_fila) {
            $mesa_num = (int)$mi_fila['mesa'];
            $stmt = $pdo->prepare("
                SELECT pr.id_usuario, pr.secuencia, u.nombre
                FROM partiresul pr
                INNER JOIN usuarios u ON pr.id_usuario = u.id
                WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
                ORDER BY pr.secuencia ASC
            ");
            $stmt->execute([$torneo_id, $ronda_activa, $mesa_num]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $jugadores_mesa = [];
            foreach ($filas as $row) {
                $sec = (int)($row['secuencia'] ?? 0);
                $jugadores_mesa[] = [
                    'id_usuario' => (int)$row['id_usuario'],
                    'nombre' => $row['nombre'] ?? '—',
                    'ubicacion' => $ubicaciones[$sec] ?? (string)$sec,
                ];
            }
            $mesa_actual = [
                'ronda' => $ronda_activa,
                'mesa_numero' => $mesa_num,
                'jugadores_mesa' => $jugadores_mesa,
            ];
        }
    }
    $response['mesa_actual'] = $mesa_actual;

    $stats = InscritosPartiresulHelper::obtenerEstadisticas($id_usuario, $torneo_id);
    $puntos = (int)($stats['puntos'] ?? 0);
    $efectividad = (int)($stats['efectividad'] ?? 0);

    // Posición: contar cuántos tienen más puntos (o mismos puntos y más efectividad)
    $stmt = $pdo->prepare("
        SELECT pr.id_usuario,
               COALESCE(SUM(pr.resultado1), 0) as pts,
               COALESCE(SUM(pr.efectividad), 0) as ef
        FROM partiresul pr
        INNER JOIN inscritos i ON i.id_usuario = pr.id_usuario AND i.torneo_id = pr.id_torneo
        WHERE pr.id_torneo = ? AND pr.registrado = 1
        AND (i.estatus IS NULL OR i.estatus = 1 OR i.estatus = 2 OR i.estatus = '1' OR i.estatus = 'confirmado')
        GROUP BY pr.id_usuario
    ");
    $stmt->execute([$torneo_id]);
    $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $posicion = 1;
    foreach ($todos as $row) {
        $pt = (int)($row['pts'] ?? 0);
        $ef = (int)($row['ef'] ?? 0);
        if ($pt > $puntos || ($pt === $puntos && $ef > $efectividad)) {
            $posicion++;
        }
    }

    $response['resumen'] = [
        'puntos' => $puntos,
        'efectividad' => $efectividad,
        'ganados' => (int)($stats['ganados'] ?? 0),
        'perdidos' => (int)($stats['perdidos'] ?? 0),
        'posicion' => $posicion,
    ];

    $response['url_clasificacion'] = $base_public . '/torneo_info.php?torneo_id=' . $torneo_id . '&seccion=general';

} catch (Throwable $e) {
    error_log('api_perfil_jugador: ' . $e->getMessage());
    $response['ok'] = false;
    $response['error'] = 'Error al cargar los datos.';
}

echo json_encode($response);
