<?php
/**
 * Mostrar asignaciones — Mesas de la ronda con Pareja A, Pareja B y Resultados.
 * Vista tipo tarjetas: título "Asignaciones - Mesas Ronda X - [Torneo]", selector Ir a mesa, grid de cards.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/../../desktop/core/db_bridge.php';

$entidad_id = DB::getEntidadId();
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$numRonda = isset($_GET['ronda']) ? (int)$_GET['ronda'] : 0;
$torneo = null;
$mesas = [];
$rondasDisponibles = [];
$ent_sql = $entidad_id > 0 ? ' AND pr.entidad_id = ?' : '';
$ent_bind = $entidad_id > 0 ? [$entidad_id] : [];

if ($torneo_id > 0) {
    $pdo = DB_Local::pdo();
    $st = $pdo->prepare("SELECT id, nombre, rondas FROM tournaments WHERE id = ?");
    $st->execute([$torneo_id]);
    $torneo = $st->fetch(PDO::FETCH_ASSOC);

    if ($torneo && $numRonda > 0) {
        $sql = "SELECT 
                pr.id, pr.mesa, pr.secuencia, pr.id_usuario, pr.resultado1, pr.resultado2, pr.registrado,
                u.nombre AS nombre_completo,
                c.nombre AS club_nombre
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            LEFT JOIN inscritos i ON i.id_usuario = u.id AND i.torneo_id = pr.id_torneo
            LEFT JOIN clubes c ON c.id = COALESCE(NULLIF(i.id_club, 0), NULLIF(u.club_id, 0))
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0" . $ent_sql . "
            ORDER BY CAST(pr.mesa AS INTEGER) ASC, pr.secuencia ASC";
        $st = $pdo->prepare($sql);
        $st->execute(array_merge([$torneo_id, $numRonda], $ent_bind));
        $filas = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as $r) {
            $numMesa = (int)$r['mesa'];
            if (!isset($mesas[$numMesa])) {
                $mesas[$numMesa] = ['numero' => $numMesa, 'jugadores' => []];
            }
            $mesas[$numMesa]['jugadores'][] = [
                'nombre_completo' => $r['nombre_completo'] ?? '',
                'club_nombre'     => $r['club_nombre'] ?? null,
                'secuencia'       => (int)$r['secuencia'],
                'resultado1'      => (int)($r['resultado1'] ?? 0),
                'resultado2'      => (int)($r['resultado2'] ?? 0),
                'registrado'      => (int)($r['registrado'] ?? 0),
            ];
        }
        $mesas = array_values($mesas);
        usort($mesas, function ($a, $b) { return $a['numero'] - $b['numero']; });
    }

    $st = $pdo->prepare("SELECT DISTINCT partida FROM partiresul WHERE id_torneo = ?" . ($entidad_id > 0 ? ' AND entidad_id = ?' : '') . " ORDER BY partida ASC");
    $st->execute(array_merge([$torneo_id], $ent_bind));
    $rondasDisponibles = $st->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = $torneo && $numRonda > 0
    ? 'Asignaciones - Mesas Ronda ' . $numRonda . ' - ' . ($torneo['nombre'] ?? '')
    : 'Asignaciones';
$desktopActive = 'panel';
require_once __DIR__ . '/desktop_layout.php';
?>
<div class="container-fluid py-3">
    <div class="row mb-3">
        <div class="col-12">
            <h1 class="h4 mb-2">
                <i class="fas fa-chess me-2"></i>
                <?php if ($torneo && $numRonda > 0): ?>
                    Asignaciones - Mesas Ronda <?= $numRonda ?> - <?= htmlspecialchars($torneo['nombre'] ?? '') ?>
                <?php else: ?>
                    Asignaciones
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-2">Todas las mesas asignadas para esta ronda. Use el selector para ir a una mesa en particular.</p>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="panel_torneo.php?torneo_id=<?= $torneo_id ?>">Volver</a></li>
                    <li class="breadcrumb-item"><a href="torneos.php">Torneos</a></li>
                    <?php if ($torneo): ?>
                    <li class="breadcrumb-item"><a href="panel_torneo.php?torneo_id=<?= $torneo_id ?>"><?= htmlspecialchars($torneo['nombre']) ?></a></li>
                    <li class="breadcrumb-item active">Ronda <?= $numRonda ?></li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
    </div>

    <?php if ($torneo_id <= 0 || $numRonda <= 0): ?>
    <form method="get" action="asignaciones.php" class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label fw-bold">Torneo</label>
            <select name="torneo_id" class="form-select">
                <option value="0">— Seleccione —</option>
                <?php
                $pdo = DB_Local::pdo();
                if ($entidad_id > 0) {
                    $st = $pdo->prepare("SELECT id, nombre FROM tournaments WHERE entidad = ? ORDER BY id DESC");
                    $st->execute([$entidad_id]);
                    $torneos = $st->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $torneos = $pdo->query("SELECT id, nombre FROM tournaments ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
                }
                foreach ($torneos as $t):
                ?>
                <option value="<?= (int)$t['id'] ?>" <?= $torneo_id === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-bold">Ronda</label>
            <select name="ronda" class="form-select">
                <option value="0">— Seleccione —</option>
                <?php foreach ($rondasDisponibles as $r): ?>
                <option value="<?= (int)$r ?>" <?= $numRonda === (int)$r ? 'selected' : '' ?>><?= (int)$r ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Ver asignaciones</button>
            <a href="torneos.php" class="btn btn-outline-secondary">Torneos</a>
        </div>
    </form>
    <?php else: ?>
    <div class="row mb-3">
        <div class="col-12 d-flex flex-wrap align-items-center gap-2">
            <a href="panel_torneo.php?torneo_id=<?= $torneo_id ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>Volver al Panel de Control
            </a>
            <a href="asignaciones.php?torneo_id=<?= $torneo_id ?>" class="btn btn-info">
                <i class="fas fa-layer-group me-1"></i>Ver Todas las Rondas
            </a>
            <?php if (!empty($mesas)): ?>
            <label for="ir-a-mesa-select" class="form-label mb-0 fw-bold">Ir a mesa:</label>
            <select id="ir-a-mesa-select" class="form-select form-select-sm d-inline-block w-auto" onchange="irAMesa(this.value)">
                <option value="">Todas las mesas (<?= count($mesas) ?>)</option>
                <?php foreach ($mesas as $md): $n = (int)($md['numero'] ?? 0); if ($n <= 0) continue; ?>
                <option value="mesa-<?= $n ?>">Mesa <?= $n ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($mesas)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>No hay mesas asignadas para esta ronda. Genere la ronda desde el panel del torneo.
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($mesas as $mesa_data): 
            $num_mesa = (int)($mesa_data['numero'] ?? 0);
            $jugadores = $mesa_data['jugadores'] ?? [];
            $pareja_a = array_filter($jugadores, function ($j) { return (int)($j['secuencia'] ?? 0) <= 2; });
            $pareja_b = array_filter($jugadores, function ($j) { return (int)($j['secuencia'] ?? 0) > 2; });
            $puntos_a = null;
            $puntos_b = null;
            foreach ($jugadores as $j) {
                if (in_array((int)($j['secuencia'] ?? 0), [1, 2])) $puntos_a = (int)($j['resultado1'] ?? 0);
                if (in_array((int)($j['secuencia'] ?? 0), [3, 4])) $puntos_b = (int)($j['resultado1'] ?? 0);
                if ($puntos_a !== null && $puntos_b !== null) break;
            }
            $tiene_resultados = ($puntos_a !== null || $puntos_b !== null) && ($puntos_a > 0 || $puntos_b > 0);
        ?>
        <div class="col-md-6 col-lg-4 mb-4" id="mesa-<?= $num_mesa ?>">
            <div class="card shadow-sm">
                <div class="card-header py-2" style="background-color: #e3f2fd; color: #1565c0;">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Mesa <?= $num_mesa ?></h5>
                </div>
                <div class="card-body">
                    <?php if (count($jugadores) === 4): ?>
                    <div class="mb-3">
                        <strong class="text-primary">Pareja A:</strong>
                        <ul class="list-unstyled ms-3 mb-0">
                            <?php foreach ($pareja_a as $jugador): ?>
                            <li><i class="fas fa-user me-1 small"></i><?= htmlspecialchars($jugador['nombre_completo'] ?? '') ?>
                                <?php if (!empty($jugador['club_nombre'])): ?><span class="text-muted">(<?= htmlspecialchars($jugador['club_nombre']) ?>)</span><?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="mb-3">
                        <strong class="text-success">Pareja B:</strong>
                        <ul class="list-unstyled ms-3 mb-0">
                            <?php foreach ($pareja_b as $jugador): ?>
                            <li><i class="fas fa-user me-1 small"></i><?= htmlspecialchars($jugador['nombre_completo'] ?? '') ?>
                                <?php if (!empty($jugador['club_nombre'])): ?><span class="text-muted">(<?= htmlspecialchars($jugador['club_nombre']) ?>)</span><?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php if ($tiene_resultados): ?>
                    <hr class="my-2">
                    <div class="small">
                        <strong>Resultados:</strong><br>
                        Pareja A: <?= (int)$puntos_a ?> | Pareja B: <?= (int)$puntos_b ?>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <p class="text-muted small mb-0">Mesa incompleta (<?= count($jugadores) ?> jugadores)</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<script>
function irAMesa(val) {
    if (!val) return;
    var el = document.getElementById(val);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
</main></body></html>
