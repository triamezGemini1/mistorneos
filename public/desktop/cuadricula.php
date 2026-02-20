<?php
/**
 * Paso 4: Cuadrícula de asignaciones por ID (Desktop).
 * Tabla ordenada por id_usuario ASC para localizar jugadores rápido. 22 filas x 9 segmentos (ID + MESA+letra).
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/../../desktop/core/db_bridge.php';

$pdo = DB_Local::pdo();
$entidad_id = DB::getEntidadId();
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$numRonda = isset($_GET['ronda']) ? (int)$_GET['ronda'] : 0;

$torneo = null;
$asignaciones = [];
$letras = [1 => 'A', 2 => 'C', 3 => 'B', 4 => 'D'];
$ent_sql = $entidad_id > 0 ? ' AND pr.entidad_id = ?' : '';
$ent_bind = $entidad_id > 0 ? [$entidad_id] : [];

if ($torneo_id > 0 && $numRonda > 0) {
    try {
        $st = $pdo->prepare("SELECT id, nombre, rondas FROM tournaments WHERE id = ?");
        $st->execute([$torneo_id]);
        $torneo = $st->fetch(PDO::FETCH_ASSOC);
        if ($torneo) {
            $st = $pdo->prepare("
                SELECT pr.id_usuario, pr.mesa, pr.secuencia, u.nombre AS nombre_completo, u.username
                FROM partiresul pr
                INNER JOIN usuarios u ON pr.id_usuario = u.id
                WHERE pr.id_torneo = ? AND pr.partida = ?" . $ent_sql . "
                ORDER BY pr.id_usuario ASC
            ");
            $st->execute(array_merge([$torneo_id, $numRonda], $ent_bind));
            $asignaciones = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
    }
}

$pageTitle = 'Cuadrícula - Ronda ' . $numRonda;
$desktopActive = 'panel';
require_once __DIR__ . '/desktop_layout.php';
?>
<div class="container-fluid py-3 no-print">
    <p class="text-muted mb-2">Paso 4: Cuadrícula ordenada por <strong>ID Usuario</strong> para localizar jugadores rápido.</p>
    <form method="get" action="cuadricula.php" class="row g-3 mb-3">
        <?php
        if ($entidad_id > 0) {
            $stmtT = $pdo->prepare("SELECT id, nombre FROM tournaments WHERE entidad = ? ORDER BY id DESC");
            $stmtT->execute([$entidad_id]);
            $torneos = $stmtT->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $torneos = $pdo->query("SELECT id, nombre FROM tournaments ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        }
        $rondas = [];
        if ($torneo_id > 0) {
            $st = $pdo->prepare("SELECT DISTINCT partida FROM partiresul WHERE id_torneo = ?" . ($entidad_id > 0 ? ' AND entidad_id = ?' : '') . " ORDER BY partida ASC");
            $st->execute(array_merge([$torneo_id], $ent_bind));
            $rondas = $st->fetchAll(PDO::FETCH_COLUMN);
        }
        ?>
        <div class="col-md-4">
            <label class="form-label fw-bold">Torneo</label>
            <select name="torneo_id" class="form-select" id="selTorneo">
                <option value="0">-- Seleccione --</option>
                <?php foreach ($torneos as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $torneo_id === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-bold">Ronda</label>
            <select name="ronda" class="form-select">
                <option value="0">-- Seleccione --</option>
                <?php foreach ($rondas as $r): ?>
                <option value="<?= (int)$r ?>" <?= $numRonda === (int)$r ? 'selected' : '' ?>><?= (int)$r ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Ver cuadrícula</button>
            <?php if ($torneo_id > 0): ?><a href="panel_torneo.php?torneo_id=<?= $torneo_id ?>" class="btn btn-outline-secondary">Panel del torneo</a><?php else: ?><a href="torneos.php" class="btn btn-outline-secondary">Torneos</a><?php endif; ?>
        </div>
    </form>
</div>

<?php if ($torneo && $numRonda > 0): ?>
<div class="cuadricula-container mb-4">
    <div class="cuadricula-header">
        <div class="cuadricula-header-left">
            <span class="cuadricula-header-torneo"><?= htmlspecialchars(strtoupper($torneo['nombre'] ?? 'Torneo')) ?> - RONDA <?= $numRonda ?></span>
        </div>
        <div class="cuadricula-header-right no-print">
            <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print me-2"></i>Imprimir</button>
            <?php if ($torneo_id > 0): ?><a href="panel_torneo.php?torneo_id=<?= $torneo_id ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-2"></i>Volver al Panel</a><?php else: ?><a href="torneos.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-2"></i>Torneos</a><?php endif; ?>
        </div>
    </div>
    <div class="cuadricula-table-wrapper">
        <table class="cuadricula-table table table-bordered">
            <thead>
                <tr>
                    <?php for ($segmento = 0; $segmento < 9; $segmento++): ?>
                        <th class="col-id-usuario">ID</th>
                        <th class="col-mesa-letra">MESA</th>
                        <?php if ($segmento < 8): ?><td class="col-separador"></td><?php endif; ?>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalFilas = 22;
                $totalSegmentos = 9;
                $jugadoresPorSegmento = $totalFilas;
                $segmentos = array_fill(0, $totalSegmentos, []);
                $indice = 0;
                foreach ($asignaciones as $asignacion) {
                    $segmento = (int)floor($indice / $jugadoresPorSegmento);
                    if ($segmento >= $totalSegmentos) break;
                    $segmentos[$segmento][] = $asignacion;
                    $indice++;
                }
                for ($fila = 0; $fila < $totalFilas; $fila++):
                ?>
                <tr>
                    <?php for ($segmento = 0; $segmento < $totalSegmentos; $segmento++):
                        $asignacion = $segmentos[$segmento][$fila] ?? null;
                        if ($asignacion):
                            $idUsuario = $asignacion['id_usuario'] ?? '';
                            $mesaRaw = $asignacion['mesa'] ?? 0;
                            $mesa = (int)$mesaRaw;
                            $secuencia = (int)($asignacion['secuencia'] ?? 0);
                            $letra = $letras[$secuencia] ?? '';
                            $esBye = ($mesa === 0 || $mesaRaw === '0');
                    ?>
                            <td class="col-id-usuario<?= $esBye ? ' celda-bye' : '' ?>"><?= htmlspecialchars((string)$idUsuario) ?></td>
                            <td class="col-mesa-letra<?= $esBye ? ' celda-bye' : '' ?>"><?= $esBye ? 'BYE' : $mesa . $letra ?></td>
                        <?php else: ?>
                            <td class="celda-vacia col-id-usuario"></td>
                            <td class="celda-vacia col-mesa-letra"></td>
                        <?php endif; ?>
                        <?php if ($segmento < $totalSegmentos - 1): ?><td class="col-separador"></td><?php endif; ?>
                    <?php endfor; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>
<style>
.col-id-usuario { background-color: #4ade80 !important; font-weight: bold; color: #000; }
.col-mesa-letra { background-color: #60a5fa !important; font-weight: bold; color: #000; }
.col-separador { background-color: #fb923c !important; width: 1%; padding: 0; border: none; }
.celda-vacia { background-color: #f0f0f0; color: #999; }
.celda-bye { background-color: #fef08a !important; font-style: italic; }
@media print { .no-print { display: none !important; } }
</style>
<?php elseif ($torneo_id > 0 || $numRonda > 0): ?>
<div class="alert alert-warning">Seleccione torneo y ronda con datos generados.</div>
<?php endif; ?>
</main></body></html>
