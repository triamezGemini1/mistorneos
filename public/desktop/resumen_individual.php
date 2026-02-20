<?php
/**
 * Resumen individual del jugador (Desktop).
 * Muestra jugador, club, y detalle de partidas: ronda, mesa, resultado1/2, ff (falta), bye, tarjeta, sanción, chancleta, zapato, observaciones.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/../../desktop/core/db_bridge.php';

$pdo = DB_Local::pdo();
$entidad_id = DB::getEntidadId();
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$inscrito_id = isset($_GET['inscrito_id']) ? (int)$_GET['inscrito_id'] : 0;
$from = isset($_GET['from']) ? trim((string)$_GET['from']) : 'posiciones';

$torneo = null;
$inscrito = null;
$partidas = [];
$ent_sql = $entidad_id > 0 ? ' AND pr.entidad_id = ?' : '';
$ent_bind = $entidad_id > 0 ? [$entidad_id] : [];

if ($torneo_id > 0 && $inscrito_id > 0) {
    $st = $pdo->prepare("SELECT id, nombre FROM tournaments WHERE id = ?");
    $st->execute([$torneo_id]);
    $torneo = $st->fetch(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("
        SELECT i.*, u.nombre AS nombre_completo, c.nombre AS club_nombre
        FROM inscritos i
        INNER JOIN usuarios u ON i.id_usuario = u.id
        LEFT JOIN clubes c ON c.id = COALESCE(NULLIF(i.id_club, 0), NULLIF(u.club_id, 0))
        WHERE i.torneo_id = ? AND i.id_usuario = ?" . ($entidad_id > 0 ? ' AND i.entidad_id = ?' : '')
    );
    $st->execute($entidad_id > 0 ? [$torneo_id, $inscrito_id, $entidad_id] : [$torneo_id, $inscrito_id]);
    $inscrito = $st->fetch(PDO::FETCH_ASSOC);

    if ($torneo && $inscrito) {
        $st = $pdo->prepare("
            SELECT pr.partida, pr.mesa, pr.secuencia, pr.resultado1, pr.resultado2, pr.efectividad,
                   pr.ff, pr.tarjeta, pr.sancion, pr.chancleta, pr.zapato, pr.observaciones, pr.registrado
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.id_usuario = ?" . $ent_sql . "
            ORDER BY pr.partida ASC, CAST(pr.mesa AS INTEGER) ASC
        ");
        $st->execute(array_merge([$torneo_id, $inscrito_id], $ent_bind));
        $partidas = $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

$url_volver = 'posiciones.php?torneo_id=' . $torneo_id;
if ($from === 'reporte') $url_volver = 'reporte_resultados_general.php?torneo_id=' . $torneo_id;

$pageTitle = 'Resumen individual';
$desktopActive = 'panel';
require_once __DIR__ . '/desktop_layout.php';
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h4 mb-0"><i class="fas fa-user-circle text-primary me-2"></i>Resumen individual</h2>
        <a href="<?= htmlspecialchars($url_volver) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Volver</a>
    </div>

    <?php if (!$torneo || !$inscrito): ?>
    <div class="alert alert-warning">No se encontró el jugador en este torneo. <a href="posiciones.php?torneo_id=<?= $torneo_id ?>">Volver a posiciones</a></div>
    <?php else: ?>
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><?= htmlspecialchars($torneo['nombre'] ?? '') ?></h5>
        </div>
        <div class="card-body">
            <p class="mb-2"><strong>Jugador:</strong> <?= htmlspecialchars($inscrito['nombre_completo'] ?? $inscrito['nombre'] ?? '') ?></p>
            <p class="mb-0"><strong>Club:</strong> <?= htmlspecialchars($inscrito['club_nombre'] ?? '—') ?></p>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">Detalle de partidas (GFF, Bye, tarjeta, sanción, etc.)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($partidas)): ?>
            <div class="alert alert-info m-3">Aún no hay partidas registradas para este jugador.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ronda</th>
                            <th>Mesa</th>
                            <th>Res.1</th>
                            <th>Res.2</th>
                            <th>Efect.</th>
                            <th>FF</th>
                            <th>Bye</th>
                            <th>Tarj.</th>
                            <th>Sanc.</th>
                            <th>Chanc.</th>
                            <th>Zap.</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partidas as $p):
                            $mesa_raw = $p['mesa'] ?? 0;
                            $mesa = (int)$mesa_raw;
                            $es_bye = ($mesa === 0 || $mesa_raw === '0' || (string)$mesa_raw === '0');
                        ?>
                        <tr>
                            <td><?= (int)($p['partida'] ?? 0) ?></td>
                            <td><?= $es_bye ? 'BYE' : $mesa ?></td>
                            <td><?= (int)($p['resultado1'] ?? 0) ?></td>
                            <td><?= (int)($p['resultado2'] ?? 0) ?></td>
                            <td><?= (int)($p['efectividad'] ?? 0) ?></td>
                            <td><?= !empty($p['ff']) ? 'Sí' : '—' ?></td>
                            <td><?= $es_bye ? 'Sí' : '—' ?></td>
                            <td><?= (int)($p['tarjeta'] ?? 0) ?></td>
                            <td><?= (int)($p['sancion'] ?? 0) ?></td>
                            <td><?= !empty($p['chancleta']) ? 'Sí' : '—' ?></td>
                            <td><?= !empty($p['zapato']) ? 'Sí' : '—' ?></td>
                            <td class="small"><?= htmlspecialchars(trim($p['observaciones'] ?? '') ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</main></body></html>
