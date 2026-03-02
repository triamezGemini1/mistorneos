<?php
/**
 * Resumen individual del jugador (público).
 * Muestra toda la trayectoria de partidas con toda la información una por una.
 * Acceso: resumen_jugador.php?torneo_id=X&id_usuario=Y
 */
declare(strict_types=1);

// Evitar caché en dispositivos para que siempre se vea la versión actual
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/app_helpers.php';
require_once __DIR__ . '/../lib/InscritosPartiresulHelper.php';

$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$id_usuario = isset($_GET['id_usuario']) ? (int)$_GET['id_usuario'] : 0;

if ($torneo_id <= 0 || $id_usuario <= 0) {
    $base = rtrim(AppHelpers::getPublicUrl(), '/');
    header('Location: ' . $base . '/landing-spa.php');
    exit;
}

$pdo = DB::pdo();
$torneo = null;
$inscrito = null;
$resumen = [];
$partidas = [];

try {
    $stmt = $pdo->prepare("SELECT id, nombre, fechator FROM tournaments WHERE id = ? AND estatus = 1");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$torneo) {
        header('Location: ' . rtrim(AppHelpers::getPublicUrl(), '/') . '/landing-spa.php');
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT i.*, COALESCE(u.nombre, u.username) AS nombre_completo, u.cedula, c.nombre AS club_nombre
        FROM inscritos i
        LEFT JOIN usuarios u ON i.id_usuario = u.id
        LEFT JOIN clubes c ON i.id_club = c.id
        WHERE i.torneo_id = ? AND i.id_usuario = ?
        AND (i.estatus IN (1, 2, '1', '2', 'confirmado', 'solvente'))
        LIMIT 1
    ");
    $stmt->execute([$torneo_id, $id_usuario]);
    $inscrito = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$inscrito) {
        header('Location: ' . rtrim(AppHelpers::getPublicUrl(), '/') . '/clasificacion.php?torneo_id=' . $torneo_id);
        exit;
    }

    $resumen = InscritosPartiresulHelper::obtenerEstadisticas($id_usuario, $torneo_id);
    $resumen['nombre'] = $inscrito['nombre_completo'] ?? '';
    $resumen['cedula'] = $inscrito['cedula'] ?? '';
    $resumen['club'] = $inscrito['club_nombre'] ?? '—';
    $resumen['puntos'] = (int)($inscrito['puntos'] ?? 0);
    $resumen['efectividad'] = (int)($inscrito['efectividad'] ?? 0);
    $resumen['ptosrnk'] = (int)($inscrito['ptosrnk'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT partida, mesa, secuencia, resultado1, resultado2, efectividad, ff, tarjeta, sancion, chancleta, zapato, observaciones, registrado
        FROM partiresul
        WHERE id_torneo = ? AND id_usuario = ?
        ORDER BY partida ASC, CAST(mesa AS UNSIGNED) ASC
    ");
    $stmt->execute([$torneo_id, $id_usuario]);
    $partidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('resumen_jugador.php: ' . $e->getMessage());
}

$base_public = rtrim(AppHelpers::getPublicUrl(), '/');
$url_retorno = $base_public . '/clasificacion.php?torneo_id=' . $torneo_id;
$logo_url = AppHelpers::getAppLogo();
$torneo_nombre = $torneo['nombre'] ?? 'Torneo';
$nombre_jugador = $resumen['nombre'] ?? $inscrito['nombre_completo'] ?? '—';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#0f172a">
    <title>Resumen — <?= htmlspecialchars($nombre_jugador) ?> · <?= htmlspecialchars($torneo_nombre) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            font-size: 15px;
            padding: 12px;
            padding-bottom: 2rem;
        }
        .wrap { max-width: 480px; margin: 0 auto; }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .header img { height: 36px; width: auto; }
        .btn-retorno {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 14px;
            background: #1e293b;
            color: #f1f5f9;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .btn-retorno:hover { background: #334155; color: #38bdf8; }
        h1 { font-size: 1.1rem; margin: 0 0 4px 0; color: #94a3b8; font-weight: 600; }
        .sub { font-size: 0.85rem; color: #64748b; margin-bottom: 16px; }
        .card {
            background: #1e293b;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 14px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .card h2 { font-size: 0.95rem; color: #94a3b8; margin: 0 0 12px 0; font-weight: 600; }
        .info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .info-row:last-child { border-bottom: 0; }
        .info-label { color: #94a3b8; }
        .info-value { font-weight: 500; }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 12px;
        }
        .stat-box {
            text-align: center;
            padding: 14px 10px;
            border-radius: 10px;
            background: rgba(255,255,255,0.05);
        }
        .stat-box .num { font-size: 1.4rem; font-weight: 700; display: block; }
        .stat-box .lbl { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
        .stat-box.primary .num { color: #38bdf8; }
        .stat-box.success .num { color: #4ade80; }
        .stat-box.danger .num { color: #f87171; }
        .stat-box.warning .num { color: #fbbf24; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 0 -12px; padding: 0 12px; }
        table { width: 100%; min-width: 320px; border-collapse: collapse; font-size: 0.88rem; }
        th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.08); }
        th { color: #94a3b8; font-weight: 600; font-size: 0.8rem; }
        td { color: #f1f5f9; }
        .num { text-align: center; }
        .empty { text-align: center; padding: 2rem; color: #64748b; }
        .partida-card { margin-bottom: 16px; }
        .partida-card .partida-titulo { font-size: 1rem; font-weight: 700; color: #38bdf8; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .partida-card .info-row { padding: 8px 0; }
        @media (min-width: 481px) {
            body { padding: 20px; }
            .wrap { box-shadow: 0 0 0 1px rgba(255,255,255,0.06); border-radius: 16px; padding: 20px; background: #0f172a; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="header">
            <a href="<?= htmlspecialchars($url_retorno) ?>" class="btn-retorno"><i class="fas fa-arrow-left"></i> Retorno</a>
            <img src="<?= htmlspecialchars($logo_url) ?>" alt="La Estación del Dominó">
        </header>

        <h1><i class="fas fa-user-circle" style="color: #38bdf8;"></i> Resumen del jugador</h1>
        <p class="sub"><?= htmlspecialchars($torneo_nombre) ?></p>

        <div class="card">
            <h2>Datos</h2>
            <div class="info-row"><span class="info-label">Nombre</span><span class="info-value"><?= htmlspecialchars($nombre_jugador) ?></span></div>
            <?php if (!empty($resumen['cedula'])): ?>
            <div class="info-row"><span class="info-label">Cédula</span><span class="info-value"><?= htmlspecialchars($resumen['cedula']) ?></span></div>
            <?php endif; ?>
            <div class="info-row"><span class="info-label">Club</span><span class="info-value"><?= htmlspecialchars($resumen['club'] ?? '—') ?></span></div>
        </div>

        <div class="card">
            <h2>Estadísticas</h2>
            <div class="stats-grid">
                <div class="stat-box primary"><span class="num"><?= (int)($resumen['total_partidas'] ?? 0) ?></span><span class="lbl">Partidas</span></div>
                <div class="stat-box success"><span class="num"><?= (int)($resumen['ganados'] ?? 0) ?></span><span class="lbl">Ganadas</span></div>
                <div class="stat-box danger"><span class="num"><?= (int)($resumen['perdidos'] ?? 0) ?></span><span class="lbl">Perdidas</span></div>
                <div class="stat-box warning"><span class="num"><?= (int)($resumen['efectividad'] ?? 0) ?></span><span class="lbl">Efectividad</span></div>
            </div>
            <div class="info-row" style="margin-top: 12px;"><span class="info-label">Puntos</span><span class="info-value"><?= (int)($resumen['puntos'] ?? 0) ?></span></div>
            <div class="info-row"><span class="info-label">Ranking</span><span class="info-value"><?= (int)($resumen['ptosrnk'] ?? 0) ?></span></div>
        </div>

        <div class="card">
            <h2>Trayectoria de partidas</h2>
            <p style="font-size: 0.85rem; color: #94a3b8; margin: 0 0 14px 0;">Cada partida con toda la información.</p>
            <?php if (empty($partidas)): ?>
                <p class="empty">Aún no hay partidas registradas.</p>
            <?php else: ?>
                <?php
                $n = 0;
                foreach ($partidas as $p):
                    $n++;
                    $mesa_raw = $p['mesa'] ?? 0;
                    $mesa = (int)$mesa_raw;
                    $es_bye = ($mesa === 0 || $mesa_raw === '0' || (string)$mesa_raw === '0');
                    $obs = trim($p['observaciones'] ?? '');
                ?>
                <div class="partida-card card">
                    <div class="partida-titulo">Partida <?= $n ?> — Ronda <?= (int)($p['partida'] ?? 0) ?> · <?= $es_bye ? 'BYE' : 'Mesa ' . $mesa ?></div>
                    <div class="info-row"><span class="info-label">Ronda</span><span class="info-value"><?= (int)($p['partida'] ?? 0) ?></span></div>
                    <div class="info-row"><span class="info-label">Mesa</span><span class="info-value"><?= $es_bye ? 'BYE' : (string)$mesa ?></span></div>
                    <div class="info-row"><span class="info-label">Posición (secuencia)</span><span class="info-value"><?= (int)($p['secuencia'] ?? 0) ?></span></div>
                    <div class="info-row"><span class="info-label">Resultado equipo 1</span><span class="info-value"><?= (int)($p['resultado1'] ?? 0) ?></span></div>
                    <div class="info-row"><span class="info-label">Resultado equipo 2</span><span class="info-value"><?= (int)($p['resultado2'] ?? 0) ?></span></div>
                    <div class="info-row"><span class="info-label">Efectividad</span><span class="info-value"><?= (int)($p['efectividad'] ?? 0) ?></span></div>
                    <div class="info-row"><span class="info-label">Forfait (no presentado)</span><span class="info-value"><?= !empty($p['ff']) ? 'Sí' : 'No' ?></span></div>
                    <div class="info-row"><span class="info-label">Bye</span><span class="info-value"><?= $es_bye ? 'Sí' : 'No' ?></span></div>
                    <div class="info-row"><span class="info-label">Tarjeta</span><span class="info-value"><?= (int)($p['tarjeta'] ?? 0) ?></span></div>
                    <div class="info-row"><span class="info-label">Sanción (pts)</span><span class="info-value"><?= (int)($p['sancion'] ?? 0) ?></span></div>
                    <div class="info-row"><span class="info-label">Chancleta</span><span class="info-value"><?= !empty($p['chancleta']) ? 'Sí' : 'No' ?></span></div>
                    <div class="info-row"><span class="info-label">Zapato</span><span class="info-value"><?= !empty($p['zapato']) ? 'Sí' : 'No' ?></span></div>
                    <?php if ($obs !== ''): ?>
                    <div class="info-row"><span class="info-label">Observaciones</span><span class="info-value"><?= htmlspecialchars($obs) ?></span></div>
                    <?php endif; ?>
                    <div class="info-row"><span class="info-label">Registrado</span><span class="info-value"><?= !empty($p['registrado']) ? 'Sí' : 'No' ?></span></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
