<?php
/**
 * Ingreso de resultados de mesa (Desktop).
 * Misma lógica y campos que la versión web; escribe en SQLite vía save_resultados.php (core/db_bridge).
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/../../desktop/core/db_bridge.php';

$pdo = DB_Local::pdo();
$entidad_id = DB::getEntidadId();
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$partida_filtro = isset($_GET['partida']) ? (int)$_GET['partida'] : 0;
$mesa_filtro = isset($_GET['mesa']) ? (int)$_GET['mesa'] : 0;
$error_message = isset($_GET['error']) ? (string)$_GET['error'] : '';
$success_message = isset($_GET['success']) ? (string)$_GET['success'] : '';

$torneos = [];
$rondas_disponibles = [];
$mesas_disponibles = [];
$partidas_mesa = [];
$tabla_partiresul_existe = false;

try {
    if ($entidad_id > 0) {
        $st = $pdo->prepare("SELECT id, nombre, fechator, estatus, rondas FROM tournaments WHERE entidad = ? ORDER BY id DESC");
        $st->execute([$entidad_id]);
        $torneos = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $torneos = $pdo->query("SELECT id, nombre, fechator, estatus, rondas FROM tournaments ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
    $tabla_partiresul_existe = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='partiresul' LIMIT 1")->fetch();
} catch (Throwable $e) {
}

$ent_sql = $entidad_id > 0 ? ' AND p.entidad_id = ?' : '';
$ent_bind = $entidad_id > 0 ? [$entidad_id] : [];
if ($torneo_id > 0 && $tabla_partiresul_existe) {
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT partida FROM partiresul WHERE id_torneo = ?" . ($entidad_id > 0 ? ' AND entidad_id = ?' : '') . " ORDER BY partida DESC");
        $stmt->execute($entidad_id > 0 ? [$torneo_id, $entidad_id] : [$torneo_id]);
        $rondas_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
    }
    if ($partida_filtro > 0) {
        try {
            $stmt = $pdo->prepare("SELECT DISTINCT mesa FROM partiresul WHERE id_torneo = ? AND partida = ?" . ($entidad_id > 0 ? ' AND entidad_id = ?' : '') . " ORDER BY mesa ASC");
            $stmt->execute($entidad_id > 0 ? [$torneo_id, $partida_filtro, $entidad_id] : [$torneo_id, $partida_filtro]);
            $mesas_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
        }
    }
    if ($partida_filtro > 0 && $mesa_filtro > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, u.username, i.posicion, i.efectividad as efectividad_total, i.puntos
                FROM partiresul p
                INNER JOIN inscritos i ON p.id_usuario = i.id_usuario AND p.id_torneo = i.torneo_id
                LEFT JOIN usuarios u ON p.id_usuario = u.id
                WHERE p.id_torneo = ? AND p.partida = ? AND p.mesa = ?" . $ent_sql . "
                ORDER BY p.secuencia ASC
            ");
            $stmt->execute(array_merge([$torneo_id, $partida_filtro, $mesa_filtro], $ent_bind));
            $partidas_mesa = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
        }
}

$pageTitle = 'Ingresar Resultados';
$desktopActive = 'panel';
require_once __DIR__ . '/desktop_layout.php';
?>
<div class="container-fluid py-3">
    <h2 class="h4 mb-3"><i class="fas fa-edit text-primary me-2"></i>Ingreso de Resultados</h2>
    <p class="text-muted">Seleccione torneo, ronda y mesa. Al guardar se usa la base local (SQLite).</p>

    <?php if ($error_message): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if (!$tabla_partiresul_existe): ?>
    <div class="alert alert-warning">No existe la tabla <code>partiresul</code> en la base local. Genere rondas desde el panel para crear mesas.</div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label fw-bold">Torneo</label>
            <select class="form-select" id="selectTorneo" onchange="cambiarTorneo(this.value)">
                <option value="0">-- Seleccione un torneo --</option>
                <?php foreach ($torneos as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $torneo_id === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if ($torneo_id > 0): ?>
    <div class="card">
        <div class="card-header bg-warning text-dark"><h5 class="mb-0"><i class="fas fa-edit me-2"></i>Ronda y Mesa</h5></div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Ronda</label>
                    <select class="form-select" id="selectRonda" onchange="cambiarRonda(this.value)">
                        <option value="0">-- Seleccione una ronda --</option>
                        <?php foreach ($rondas_disponibles as $ronda): ?>
                        <option value="<?= (int)$ronda ?>" <?= $partida_filtro == (int)$ronda ? 'selected' : '' ?>>Ronda #<?= (int)$ronda ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Mesa</label>
                    <select class="form-select" id="selectMesa" onchange="cambiarMesa(this.value)" <?= empty($mesas_disponibles) ? 'disabled' : '' ?>>
                        <option value="0">-- Seleccione una mesa --</option>
                        <?php foreach ($mesas_disponibles as $mesa): ?>
                        <option value="<?= (int)$mesa ?>" <?= $mesa_filtro == (int)$mesa ? 'selected' : '' ?>>Mesa #<?= (int)$mesa ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (empty($partidas_mesa)): ?>
            <div class="alert alert-info">Seleccione una ronda y mesa para ingresar resultados.</div>
            <?php else: ?>
            <form method="POST" action="save_resultados.php" id="formResultados">
                <input type="hidden" name="guardar_resultados" value="1">
                <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
                <input type="hidden" name="partida" value="<?= $partida_filtro ?>">
                <input type="hidden" name="mesa" value="<?= $mesa_filtro ?>">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">Ronda #<?= $partida_filtro ?> - Mesa #<?= $mesa_filtro ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sec.</th>
                                        <th>Jugador</th>
                                        <th>Pos.</th>
                                        <th>Resultado 1</th>
                                        <th>Resultado 2</th>
                                        <th>Efect.</th>
                                        <th>FF</th>
                                        <th>Tarjeta</th>
                                        <th>Sanción</th>
                                        <th>Chancleta</th>
                                        <th>Zapato</th>
                                        <th>Registrado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($partidas_mesa as $partida): ?>
                                    <tr>
                                        <td><?= (int)($partida['secuencia'] ?? 0) ?></td>
                                        <td><strong><?= htmlspecialchars($partida['username'] ?? 'N/A') ?></strong></td>
                                        <td><?= (int)($partida['posicion'] ?? 0) > 0 ? '#' . (int)$partida['posicion'] : '-' ?></td>
                                        <td><input type="number" name="resultados[<?= (int)$partida['id'] ?>][resultado1]" class="form-control form-control-sm" value="<?= (int)($partida['resultado1'] ?? 0) ?>" min="0"></td>
                                        <td><input type="number" name="resultados[<?= (int)$partida['id'] ?>][resultado2]" class="form-control form-control-sm" value="<?= (int)($partida['resultado2'] ?? 0) ?>" min="0"></td>
                                        <td><input type="number" name="resultados[<?= (int)$partida['id'] ?>][efectividad]" class="form-control form-control-sm" value="<?= (int)($partida['efectividad'] ?? 0) ?>"></td>
                                        <td class="text-center"><input type="checkbox" name="resultados[<?= (int)$partida['id'] ?>][ff]" value="1" <?= !empty($partida['ff']) ? 'checked' : '' ?>></td>
                                        <td><input type="number" name="resultados[<?= (int)$partida['id'] ?>][tarjeta]" class="form-control form-control-sm" value="<?= (int)($partida['tarjeta'] ?? 0) ?>" min="0"></td>
                                        <td><input type="number" name="resultados[<?= (int)$partida['id'] ?>][sancion]" class="form-control form-control-sm" value="<?= (int)($partida['sancion'] ?? 0) ?>" min="0"></td>
                                        <td><input type="number" name="resultados[<?= (int)$partida['id'] ?>][chancleta]" class="form-control form-control-sm" value="<?= (int)($partida['chancleta'] ?? 0) ?>" min="0"></td>
                                        <td><input type="number" name="resultados[<?= (int)$partida['id'] ?>][zapato]" class="form-control form-control-sm" value="<?= (int)($partida['zapato'] ?? 0) ?>" min="0"></td>
                                        <td class="text-center"><input type="checkbox" name="resultados[<?= (int)$partida['id'] ?>][registrado]" value="1" <?= !empty($partida['registrado']) ? 'checked' : '' ?>></td>
                                    </tr>
                                    <tr>
                                        <td colspan="12">
                                            <label class="form-label small">Observaciones:</label>
                                            <textarea name="resultados[<?= (int)$partida['id'] ?>][observaciones]" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($partida['observaciones'] ?? '') ?></textarea>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning small">Marque "Registrado" cuando los resultados estén verificados. Se actualizarán estadísticas en inscritos.</div>
                <div class="d-flex justify-content-end gap-2">
                    <?php if ($torneo_id > 0): ?><a href="panel_torneo.php?torneo_id=<?= $torneo_id ?>" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Cancelar</a><?php else: ?><a href="torneos.php" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Torneos</a><?php endif; ?>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>GUARDAR</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
function cambiarTorneo(id) {
    window.location = 'resultados.php?torneo_id=' + (id || '');
}
function cambiarRonda(v) {
    var url = new URL(window.location.href);
    if (v > 0) { url.searchParams.set('partida', v); url.searchParams.delete('mesa'); } else { url.searchParams.delete('partida'); url.searchParams.delete('mesa'); }
    window.location = url.toString();
}
function cambiarMesa(v) {
    var url = new URL(window.location.href);
    if (v > 0) url.searchParams.set('mesa', v); else url.searchParams.delete('mesa');
    window.location = url.toString();
}
</script>
</main></body></html>

