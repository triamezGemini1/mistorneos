<?php
declare(strict_types=1);
/**
 * Portal público (QR torneo + ID jugador): mesa por ronda, resumen, listado, posiciones equipos.
 * Sesión hasta que el torneo cierre (locked). Actualizar recarga datos sin volver a ingresar ID.
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../lib/app_helpers.php';
require_once __DIR__ . '/../lib/PublicInfoTorneoMesasService.php';
require_once __DIR__ . '/../lib/PublicTorneoPortalHelper.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$pdo = DB::pdo();
$torneo_id = (int) ($_GET['torneo_id'] ?? $_POST['torneo_id'] ?? 0);
$error = '';
$vista_portal = false;
$torneo = null;
$ronda = 0;
$id_usuario_form = (int) ($_POST['id_usuario'] ?? 0);
$asignacion = null;
$viewerId = 0;
$listado_general = [];
$resumen_pack = null;
$ranking_equipos = [];
$mi_codigo_equipo = '';

if ($torneo_id <= 0) {
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enlace no válido</title></head><body style="font-family:sans-serif;padding:1rem;"><p>Falta el identificador del torneo. Use el código QR del evento.</p></body></html>';
    exit;
}

if (isset($_GET['salir']) && (string) $_GET['salir'] === '1') {
    PublicTorneoPortalHelper::sessionClear();
    header('Location: info_torneo_mesas.php?torneo_id=' . $torneo_id);
    exit;
}

$torneo = PublicTorneoPortalHelper::getTorneoParaPortal($pdo, $torneo_id);
if (!$torneo) {
    $basico = PublicTorneoPortalHelper::getTorneoBasico($pdo, $torneo_id);
    PublicTorneoPortalHelper::sessionClear();
    http_response_code(200);
    $msg = 'El torneo no existe o no está disponible.';
    if ($basico && (int) ($basico['locked'] ?? 0) === 1) {
        $msg = 'El torneo ha finalizado. Gracias por participar.';
    }
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Torneo</title></head><body style="font-family:sans-serif;padding:1.2rem;max-width:32rem;margin:auto;">';
    echo '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    exit;
}

$rondas_disponibles = PublicInfoTorneoMesasService::rondasConDatos($pdo, $torneo_id);
if ($rondas_disponibles === []) {
    $rondas_disponibles = range(1, max(1, (int) ($torneo['rondas'] ?? 1)));
}
$default_ronda = PublicInfoTorneoMesasService::ultimaRondaConPartidas($pdo, $torneo_id);
if (!in_array($default_ronda, $rondas_disponibles, true)) {
    $rondas_disponibles[] = $default_ronda;
    sort($rondas_disponibles, SORT_NUMERIC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');
    if ($postedCsrf === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $postedCsrf)) {
        $error = 'Sesión expirada. Vuelva a cargar la página e intente de nuevo.';
    } else {
        $ronda_post = (int) ($_POST['ronda'] ?? 0);
        $uid = (int) ($_POST['id_usuario'] ?? 0);
        if ($ronda_post <= 0 || !in_array($ronda_post, $rondas_disponibles, true)) {
            $error = 'Seleccione una ronda válida.';
        } elseif ($uid <= 0) {
            $error = 'Ingrese su ID de jugador.';
        } elseif (!PublicInfoTorneoMesasService::estaInscrito($pdo, $torneo_id, $uid)) {
            $error = 'Ese ID no está inscrito en este torneo.';
        } else {
            PublicTorneoPortalHelper::sessionSet($torneo_id, $uid);
            header('Location: info_torneo_mesas.php?torneo_id=' . $torneo_id . '&ronda=' . $ronda_post);
            exit;
        }
    }
}

$ronda = (int) ($_GET['ronda'] ?? 0);
if ($ronda <= 0 || !in_array($ronda, $rondas_disponibles, true)) {
    $ronda = $default_ronda;
}

$sessionUid = PublicTorneoPortalHelper::sessionGetUserId($torneo_id);
if ($sessionUid !== null) {
    if (!PublicInfoTorneoMesasService::estaInscrito($pdo, $torneo_id, $sessionUid)) {
        PublicTorneoPortalHelper::sessionClear();
        $sessionUid = null;
    }
}

if ($sessionUid !== null) {
    $vista_portal = true;
    $viewerId = $sessionUid;
    $asignacion = PublicInfoTorneoMesasService::resumenAsignacion($pdo, $torneo_id, $ronda, $viewerId);
    $listado_general = PublicTorneoPortalHelper::fetchListadoGeneral($pdo, $torneo_id);
    try {
        $resumen_pack = PublicTorneoPortalHelper::fetchResumenParticipacion($pdo, $torneo_id, $viewerId);
    } catch (Throwable $e) {
        error_log('info_torneo_mesas resumen: ' . $e->getMessage());
        $resumen_pack = ['jugador' => [], 'resumen' => [], 'partidas' => [], 'posicion' => 0];
    }
    $mi_codigo_equipo = (string) ($resumen_pack['jugador']['codigo_equipo'] ?? '');
    if ((int) ($torneo['modalidad'] ?? 0) === 3) {
        require_once __DIR__ . '/../lib/Tournament/Handlers/TeamPerformanceHandler.php';
        try {
            $ranking_equipos = \Tournament\Handlers\TeamPerformanceHandler::getRankingPorEquipos($torneo_id, 'resumido');
        } catch (Throwable $e) {
            error_log('info_torneo_mesas equipos: ' . $e->getMessage());
            $ranking_equipos = [];
        }
    }
}

$csrf = CSRF::token();
$es_equipos = (int) ($torneo['modalidad'] ?? 0) === 3;
$clasificacion_url = rtrim(AppHelpers::getPublicUrl(), '/') . '/clasificacion.php?torneo_id=' . $torneo_id;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5">
    <meta name="theme-color" content="#0c4a6e">
    <title><?php echo $vista_portal ? 'Mi torneo' : 'Acceso'; ?> — <?php echo htmlspecialchars((string) $torneo['nombre'], ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --base: 16px;
            --bg: #f0f9ff;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #bae6fd;
            --mesa-head: #0369a1;
            --yo: #b91c1c;
            --max: 720px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: var(--base);
            background: var(--bg);
            color: var(--text);
            padding: 62px 12px 28px;
            line-height: 1.45;
        }
        @media (min-width: 400px) { body { padding-left: 14px; padding-right: 14px; } }
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: center;
            padding: 8px 10px;
            background: rgba(255,255,255,0.95);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 2px 10px rgba(15,23,42,0.06);
        }
        .toolbar form { margin: 0; display: inline-flex; align-items: center; gap: 6px; }
        .toolbar select {
            padding: 8px 10px;
            border-radius: 8px;
            border: 2px solid #cbd5e1;
            font-size: 0.9rem;
            max-width: 42vw;
        }
        .tb-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            font-size: 0.88rem;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            background: #0284c7;
        }
        .tb-btn.secondary { background: #64748b; }
        .tb-btn.danger { background: #dc2626; }
        .visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .wrap { max-width: var(--max); margin: 0 auto; }
        h1 { font-size: 1.12rem; margin: 0 0 6px; line-height: 1.25; }
        .sub { color: var(--muted); font-size: 0.88rem; margin-bottom: 14px; }
        .card {
            background: var(--card);
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 14px rgba(3, 105, 161, 0.08);
            overflow: hidden;
            margin-bottom: 14px;
        }
        .card-head {
            background: linear-gradient(135deg, #0284c7, var(--mesa-head));
            color: #fff;
            padding: 11px 14px;
            font-weight: 700;
            font-size: 1rem;
        }
        .card-body { padding: 12px 14px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; }
        input[type="number"], .form-select {
            width: 100%;
            padding: 12px 14px;
            font-size: 1.05rem;
            border: 2px solid #cbd5e1;
            border-radius: 10px;
            margin-bottom: 12px;
        }
        input:focus, .form-select:focus { outline: none; border-color: #0284c7; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 18px;
            font-size: 1.05rem;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            background: #0284c7;
            color: #fff;
            cursor: pointer;
        }
        .alert { padding: 11px 13px; border-radius: 10px; margin-bottom: 12px; font-size: 0.92rem; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        details.portal-sec { margin-bottom: 12px; border: 1px solid var(--border); border-radius: 12px; background: #fff; overflow: hidden; }
        details.portal-sec > summary {
            list-style: none;
            padding: 12px 14px;
            font-weight: 800;
            cursor: pointer;
            background: #e0f2fe;
            color: #0c4a6e;
        }
        details.portal-sec > summary::-webkit-details-marker { display: none; }
        details.portal-sec[open] > summary { border-bottom: 1px solid var(--border); }
        .sec-body { padding: 12px 14px; }
        .pareja-tit { font-weight: 700; margin: 8px 0 4px; font-size: 0.92rem; }
        .pareja-tit.a { color: #0369a1; }
        .pareja-tit.b { color: #047857; }
        ul.jugadores { list-style: none; margin: 0; padding: 0 0 0 4px; }
        ul.jugadores li {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 1rem;
            color: var(--text);
        }
        ul.jugadores li:last-child { border-bottom: none; }
        ul.jugadores li.info-mesa-yo {
            color: var(--yo);
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1.25;
            padding: 10px 0;
        }
        .club-hint { font-size: 0.88em; color: var(--muted); font-weight: 500; }
        .info-mesa-yo .club-hint { color: #7f1d1d; opacity: 0.95; }
        .bye-box { text-align: center; padding: 14px 10px; }
        .bye-box .info-mesa-yo { font-size: 1.65rem; color: var(--yo); font-weight: 800; }
        .resultados { margin-top: 10px; padding-top: 8px; border-top: 1px dashed #cbd5e1; font-size: 0.92rem; }
        .hint-id { font-size: 0.82rem; color: var(--muted); margin-top: 8px; }
        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
        .stat-box {
            background: #f8fafc;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .stat-box .k { font-size: 0.75rem; color: var(--muted); text-transform: uppercase; }
        .stat-box .v { font-size: 1.35rem; font-weight: 800; color: #0369a1; }
        .stat-box.yo .v { color: var(--yo); font-size: 1.5rem; }
        .tab-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 0 -4px; }
        table.data-tab { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        table.data-tab th, table.data-tab td {
            padding: 8px 6px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        table.data-tab th { background: #f1f5f9; font-weight: 700; white-space: nowrap; }
        tr.tab-yo { background: #fef2f2; color: var(--yo); font-weight: 800; font-size: 1.05em; }
        tr.eq-yo { background: #fef2f2; color: var(--yo); font-weight: 800; }
        .partidas-mini { font-size: 0.85rem; }
        .partidas-mini tr:nth-child(even) { background: #f8fafc; }
        a.link-out { color: #0284c7; font-weight: 700; word-break: break-all; }
    </style>
</head>
<body>

<?php if ($vista_portal): ?>
<div class="toolbar" role="toolbar" aria-label="Acciones">
    <button type="button" class="tb-btn" onclick="location.reload()" title="Actualizar datos del torneo"><i class="fas fa-sync-alt" aria-hidden="true"></i> Actualizar</button>
    <form method="get" action="">
        <input type="hidden" name="torneo_id" value="<?php echo (int) $torneo_id; ?>">
        <label class="visually-hidden" for="sel-ronda">Ronda</label>
        <select id="sel-ronda" name="ronda" onchange="this.form.submit()" aria-label="Cambiar ronda">
            <?php foreach ($rondas_disponibles as $rn): ?>
                <option value="<?php echo (int) $rn; ?>"<?php echo ((int) $rn === (int) $ronda) ? ' selected' : ''; ?>>Ronda <?php echo (int) $rn; ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <a class="tb-btn danger" href="?torneo_id=<?php echo (int) $torneo_id; ?>&salir=1"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Salir</a>
</div>
<?php endif; ?>

<div class="wrap">
    <h1><i class="fas fa-chess-board" aria-hidden="true"></i> <?php echo htmlspecialchars((string) $torneo['nombre'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="sub"><?php echo $vista_portal
        ? 'Portal del jugador: mesa, resumen y clasificaciones. Los datos se actualizan con «Actualizar».'
        : 'Escanee el QR del torneo e ingrese su ID de jugador para continuar.'; ?></p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$vista_portal): ?>
        <form method="post" action="" class="card">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="torneo_id" value="<?php echo (int) $torneo_id; ?>">
                <label for="ronda">Ronda a consultar</label>
                <select id="ronda" name="ronda" class="form-select" required>
                    <?php foreach ($rondas_disponibles as $rn): ?>
                        <option value="<?php echo (int) $rn; ?>"<?php echo ((int) $rn === (int) $ronda) ? ' selected' : ''; ?>>Ronda <?php echo (int) $rn; ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="id_usuario">ID de jugador</label>
                <input type="number" id="id_usuario" name="id_usuario" inputmode="numeric" min="1" step="1" required
                       placeholder="Ej: 1234"
                       value="<?php echo $id_usuario_form > 0 ? (int) $id_usuario_form : ''; ?>">
                <button type="submit" class="btn"><i class="fas fa-sign-in-alt" aria-hidden="true"></i> Entrar al portal</button>
                <p class="hint-id">El acceso permanece en este dispositivo hasta que el torneo finalice o pulse «Salir».</p>
            </div>
        </form>
    <?php else: ?>
        <?php
        $jug = $resumen_pack['jugador'] ?? [];
        $res = $resumen_pack['resumen'] ?? [];
        ?>

        <details class="portal-sec" open>
            <summary><i class="fas fa-map-pin" aria-hidden="true"></i> Mesa — Ronda <?php echo (int) $ronda; ?></summary>
            <div class="sec-body">
                <?php if ($asignacion === null): ?>
                    <div class="alert alert-warn">Sin asignación en partiresul para esta ronda (aún no generada o no participa).</div>
                <?php elseif (($asignacion['tipo'] ?? '') === 'bye'): ?>
                    <div class="bye-box">
                        <p>Descanso (BYE) esta ronda.</p>
                        <p class="info-mesa-yo"><?php echo htmlspecialchars((string) ($asignacion['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (!empty($asignacion['club_nombre'])): ?>
                            <p class="club-hint">(<?php echo htmlspecialchars((string) $asignacion['club_nombre'], ENT_QUOTES, 'UTF-8'); ?>)</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php
                    $num_mesa = (int) ($asignacion['mesa'] ?? 0);
                    $jugadores = $asignacion['jugadores'] ?? [];
                    $pareja_a = array_filter($jugadores, static function ($j) {
                        return is_array($j) && isset($j['secuencia']) && in_array((int) $j['secuencia'], [1, 2], true);
                    });
                    $pareja_b = array_filter($jugadores, static function ($j) {
                        return is_array($j) && isset($j['secuencia']) && in_array((int) $j['secuencia'], [3, 4], true);
                    });
                    $tiene_resultados = false;
                    foreach ($jugadores as $j) {
                        if (is_array($j) && (!empty($j['resultado1']) || !empty($j['resultado2']))) {
                            $tiene_resultados = true;
                            break;
                        }
                    }
                    ?>
                    <p style="margin:0 0 8px;font-weight:800;color:var(--mesa-head);">Mesa <?php echo $num_mesa; ?></p>
                    <?php if (count($jugadores) === 4): ?>
                        <div class="pareja-tit a">Pareja A</div>
                        <ul class="jugadores">
                            <?php foreach ($pareja_a as $jugador): ?>
                                <?php
                                if (!is_array($jugador)) {
                                    continue;
                                }
                                $uid = (int) ($jugador['jugador_uid'] ?? 0);
                                $yo = ($uid === $viewerId);
                                ?>
                                <li class="<?php echo $yo ? 'info-mesa-yo' : ''; ?>">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars((string) ($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($es_equipos && !empty($jugador['codigo_equipo_inscrito'])): ?>
                                        <strong>[<?php echo htmlspecialchars((string) $jugador['codigo_equipo_inscrito'], ENT_QUOTES, 'UTF-8'); ?>]</strong>
                                    <?php endif; ?>
                                    <?php if (!empty($jugador['club_nombre'])): ?>
                                        <span class="club-hint">(<?php echo htmlspecialchars((string) $jugador['club_nombre'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="pareja-tit b">Pareja B</div>
                        <ul class="jugadores">
                            <?php foreach ($pareja_b as $jugador): ?>
                                <?php
                                if (!is_array($jugador)) {
                                    continue;
                                }
                                $uid = (int) ($jugador['jugador_uid'] ?? 0);
                                $yo = ($uid === $viewerId);
                                ?>
                                <li class="<?php echo $yo ? 'info-mesa-yo' : ''; ?>">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars((string) ($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($es_equipos && !empty($jugador['codigo_equipo_inscrito'])): ?>
                                        <strong>[<?php echo htmlspecialchars((string) $jugador['codigo_equipo_inscrito'], ENT_QUOTES, 'UTF-8'); ?>]</strong>
                                    <?php endif; ?>
                                    <?php if (!empty($jugador['club_nombre'])): ?>
                                        <span class="club-hint">(<?php echo htmlspecialchars((string) $jugador['club_nombre'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($tiene_resultados && !empty($jugadores[0]) && is_array($jugadores[0])): ?>
                            <?php
                            $primer = reset($jugadores);
                            $r1 = (int) ($primer['resultado1'] ?? 0);
                            $r2 = (int) ($primer['resultado2'] ?? 0);
                            ?>
                            <div class="resultados"><strong>Resultados:</strong> Pareja A: <?php echo $r1; ?> | Pareja B: <?php echo $r2; ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <ul class="jugadores">
                            <?php foreach ($jugadores as $jugador): ?>
                                <?php
                                if (!is_array($jugador)) {
                                    continue;
                                }
                                $uid = (int) ($jugador['jugador_uid'] ?? 0);
                                $yo = ($uid === $viewerId);
                                ?>
                                <li class="<?php echo $yo ? 'info-mesa-yo' : ''; ?>">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars((string) ($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8'); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </details>

        <details class="portal-sec" open>
            <summary><i class="fas fa-chart-line" aria-hidden="true"></i> Resumen de mi participación</summary>
            <div class="sec-body">
                <p style="margin:0 0 10px;font-size:0.95rem;">
                    <strong><?php echo htmlspecialchars((string) ($jug['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span class="club-hint"> · ID jugador <?php echo (int) $viewerId; ?></span>
                </p>
                <div class="stat-grid">
                    <div class="stat-box yo"><span class="k">Posición</span><div class="v"><?php echo (int) ($res['posicion'] ?? $resumen_pack['posicion'] ?? 0); ?>º</div></div>
                    <div class="stat-box"><span class="k">Puntos</span><div class="v"><?php echo (int) ($res['puntos'] ?? 0); ?></div></div>
                    <div class="stat-box"><span class="k">Efectividad</span><div class="v"><?php echo (int) ($res['efectividad'] ?? 0); ?>%</div></div>
                    <div class="stat-box"><span class="k">G / P</span><div class="v"><?php echo (int) ($res['ganados'] ?? 0); ?> / <?php echo (int) ($res['perdidos'] ?? 0); ?></div></div>
                </div>
                <p style="font-weight:700;font-size:0.9rem;margin:10px 0 6px;">Partidas por ronda</p>
                <div class="tab-wrap">
                    <table class="data-tab partidas-mini">
                        <thead>
                            <tr>
                                <th>R</th><th>M</th><th>Res</th><th>Pareja</th><th>Rivales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resumen_pack['partidas'] ?? [] as $p): ?>
                                <tr>
                                    <td><?php echo (int) ($p['partida'] ?? 0); ?></td>
                                    <td><?php echo (int) ($p['mesa'] ?? 0) > 0 ? (int) $p['mesa'] : 'BYE'; ?></td>
                                    <td><?php echo (int) ($p['registrado'] ?? 0) === 1
                                        ? (((int) ($p['ganada'] ?? 0) === 1) ? '✓' : '✗')
                                        : '—'; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($p['compañero'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(trim(($p['contrario1'] ?? '') . ' / ' . ($p['contrario2'] ?? ''), ' /'), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="margin-top:12px;"><a class="link-out" href="<?php echo htmlspecialchars($clasificacion_url, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-trophy" aria-hidden="true"></i> Clasificación completa</a></p>
            </div>
        </details>

        <details class="portal-sec" open>
            <summary><i class="fas fa-users" aria-hidden="true"></i> Reporte general de jugadores</summary>
            <div class="sec-body">
                <div class="tab-wrap">
                    <table class="data-tab">
                        <thead>
                            <tr>
                                <th>#</th><th>Jugador</th><th>Club</th><th>Pts</th><th>Ef.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $idx = 0;
                            foreach ($listado_general as $row):
                                $idx++;
                                $uidr = (int) ($row['id_usuario'] ?? 0);
                                $yo = ($uidr === $viewerId);
                            ?>
                                <tr class="<?php echo $yo ? 'tab-yo' : ''; ?>">
                                    <td><?php echo $idx; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['nombre_jugador'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['club_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) ($row['puntos'] ?? 0); ?></td>
                                    <td><?php echo (int) ($row['efectividad'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <?php if ($es_equipos && $ranking_equipos !== []): ?>
        <details class="portal-sec" open>
            <summary><i class="fas fa-shield-alt" aria-hidden="true"></i> Posiciones por equipos</summary>
            <div class="sec-body">
                <div class="tab-wrap">
                    <table class="data-tab">
                        <thead>
                            <tr>
                                <th>Pos</th><th>Equipo</th><th>Club</th><th>G</th><th>P</th><th>Ef.</th><th>Pts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ranking_equipos as $eq):
                                $cod = (string) ($eq['codigo_equipo'] ?? '');
                                $yoEq = ($mi_codigo_equipo !== '' && $cod === $mi_codigo_equipo);
                            ?>
                                <tr class="<?php echo $yoEq ? 'eq-yo' : ''; ?>">
                                    <td><?php echo (int) ($eq['posicion'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($eq['nombre_equipo'] ?? $cod), ENT_QUOTES, 'UTF-8'); ?> <small>(<?php echo htmlspecialchars($cod, ENT_QUOTES, 'UTF-8'); ?>)</small></td>
                                    <td><?php echo htmlspecialchars((string) ($eq['club_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) ($eq['ganados'] ?? 0); ?></td>
                                    <td><?php echo (int) ($eq['perdidos'] ?? 0); ?></td>
                                    <td><?php echo (int) ($eq['efectividad'] ?? 0); ?></td>
                                    <td><?php echo (int) ($eq['puntos'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
        <?php endif; ?>

    <?php endif; ?>
</div>
</body>
</html>
