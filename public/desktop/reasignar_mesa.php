<?php
/**
 * Reasignar Mesa (Desktop) — Intercambiar posiciones de jugadores en una mesa.
 * Misma lógica que la web: opciones 1-6 de intercambio de secuencias en partiresul.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/../../desktop/core/db_bridge.php';

$pdo = DB_Local::pdo();
$entidad_id = DB::getEntidadId();
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : (int)($_POST['torneo_id'] ?? 0);
$ronda = isset($_GET['partida']) ? (int)$_GET['partida'] : (int)($_POST['partida'] ?? 0);
$mesa = isset($_GET['mesa']) ? (int)$_GET['mesa'] : (int)($_POST['mesa'] ?? 0);

$ent_sql = $entidad_id > 0 ? ' AND pr.entidad_id = ?' : '';
$ent_bind = $entidad_id > 0 ? [$entidad_id] : [];

$torneo = null;
$jugadores = [];
$todasLasMesas = [];
$todasLasRondas = [];
$mesaAnterior = null;
$mesaSiguiente = null;
$error_message = '';
$success_message = '';

// POST: ejecutar reasignación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ejecutar_reasignacion') {
    $torneo_id = (int)($_POST['torneo_id'] ?? 0);
    $ronda = (int)($_POST['partida'] ?? 0);
    $mesa = (int)($_POST['mesa'] ?? 0);
    $opcion = (int)($_POST['opcion_reasignacion'] ?? 0);

    if ($torneo_id <= 0 || $ronda <= 0 || $mesa <= 0) {
        $error_message = 'Debe especificar torneo, ronda y mesa.';
    } elseif (!in_array($opcion, [1, 2, 3, 4, 5, 6], true)) {
        $error_message = 'Opción de reasignación no válida.';
    } else {
        try {
            $st = $pdo->prepare("SELECT * FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = ?" . ($entidad_id > 0 ? ' AND entidad_id = ?' : '') . " ORDER BY secuencia ASC");
            $st->execute(array_merge([$torneo_id, $ronda, $mesa], $ent_bind));
            $jugadores = $st->fetchAll(PDO::FETCH_ASSOC);

            if (count($jugadores) !== 4) {
                throw new Exception('La mesa debe tener exactamente 4 jugadores.');
            }

            $mapaActual = [];
            foreach ($jugadores as $j) {
                $mapaActual[(int)$j['secuencia']] = $j;
            }

            $cambios = [];
            switch ($opcion) {
                case 1: $cambios = [[1, 3], [3, 1]]; break;
                case 2: $cambios = [[1, 4], [4, 1]]; break;
                case 3: $cambios = [[2, 3], [3, 2]]; break;
                case 4: $cambios = [[2, 4], [4, 2]]; break;
                case 5: $cambios = [[1, 3], [3, 1], [2, 4], [4, 2]]; break;
                case 6: $cambios = [[1, 4], [4, 1], [2, 3], [3, 2]]; break;
            }

            $mapaFinal = [];
            foreach ($mapaActual as $seq => $jugador) {
                $mapaFinal[$seq] = (int)$jugador['id_usuario'];
            }
            foreach ($cambios as $c) {
                $o = $c[0];
                $d = $c[1];
                $t = $mapaFinal[$o];
                $mapaFinal[$o] = $mapaFinal[$d];
                $mapaFinal[$d] = $t;
            }

            $pdo->beginTransaction();
            try {
                foreach ($mapaFinal as $nuevaSecuencia => $idUsuario) {
                    $up = $pdo->prepare("UPDATE partiresul SET secuencia = ? WHERE id_torneo = ? AND partida = ? AND mesa = ? AND id_usuario = ?" . ($entidad_id > 0 ? ' AND entidad_id = ?' : ''));
                    $up->execute(array_merge([$nuevaSecuencia, $torneo_id, $ronda, $mesa, $idUsuario], $ent_bind));
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            header('Location: captura.php?torneo_id=' . $torneo_id . '&partida=' . $ronda . '&mesa=' . $mesa . '&msg=reasignado');
            exit;
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

if ($torneo_id > 0) {
    $st = $pdo->prepare("SELECT id, nombre FROM tournaments WHERE id = ?");
    $st->execute([$torneo_id]);
    $torneo = $st->fetch(PDO::FETCH_ASSOC);
}

if ($torneo_id > 0 && $ronda > 0 && $mesa > 0) {
    $es_get_o_error = $_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($error_message);
    if ($es_get_o_error) {
        $st = $pdo->prepare("
            SELECT pr.*, u.nombre as nombre_completo
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?" . $ent_sql . "
            ORDER BY pr.secuencia ASC
        ");
        $st->execute(array_merge([$torneo_id, $ronda, $mesa], $ent_bind));
        $jugadores = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $st = $pdo->prepare("
        SELECT pr.mesa as numero, MAX(pr.registrado) as registrado
        FROM partiresul pr
        WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0" . $ent_sql . "
        GROUP BY pr.mesa
        ORDER BY CAST(pr.mesa AS INTEGER) ASC
    ");
    $st->execute(array_merge([$torneo_id, $ronda], $ent_bind));
    $todasLasMesas = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($todasLasMesas as &$m) {
        $m['numero'] = (int)$m['numero'];
    }
    usort($todasLasMesas, function ($a, $b) { return $a['numero'] - $b['numero']; });

    $st = $pdo->prepare("SELECT DISTINCT partida FROM partiresul WHERE id_torneo = ?" . ($entidad_id > 0 ? ' AND entidad_id = ?' : '') . " ORDER BY partida ASC");
    $st->execute(array_merge([$torneo_id], $ent_bind));
    $todasLasRondas = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($todasLasMesas as $idx => $m) {
        if ((int)$m['numero'] === $mesa) {
            if ($idx > 0) $mesaAnterior = (int)$todasLasMesas[$idx - 1]['numero'];
            if ($idx < count($todasLasMesas) - 1) $mesaSiguiente = (int)$todasLasMesas[$idx + 1]['numero'];
            break;
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'reasignado') {
    $success_message = 'Mesa reasignada correctamente.';
}

$pageTitle = 'Reasignar Mesa';
$desktopActive = 'panel';
require_once __DIR__ . '/desktop_layout.php';

if ($error_message) {
    echo '<div class="container-fluid py-2"><div class="alert alert-danger">' . htmlspecialchars($error_message) . '</div></div>';
}
if ($success_message) {
    echo '<div class="container-fluid py-2"><div class="alert alert-success">' . htmlspecialchars($success_message) . '</div></div>';
}

if ($torneo_id <= 0 || $ronda <= 0 || $mesa <= 0) {
    echo '<div class="container-fluid py-3"><div class="alert alert-info">Indique torneo, ronda y mesa. Use el enlace desde Registro de Resultados.</div></div>';
} elseif (empty($jugadores) || count($jugadores) !== 4) {
    echo '<div class="container-fluid py-3"><div class="alert alert-warning">Esta mesa no tiene exactamente 4 jugadores. <a href="captura.php?torneo_id=' . $torneo_id . '&partida=' . $ronda . '&mesa=' . $mesa . '">Volver a Resultados</a></div></div>';
} else {
?>
<style>
.mesa-item { transition: all 0.2s; padding: 0.5rem 0.75rem !important; }
.mesa-activa { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; font-weight: bold; }
.opcion-reasignacion { border: 2px solid #e5e7eb; padding: 12px; border-radius: 8px; margin-bottom: 8px; cursor: pointer; }
.opcion-reasignacion:hover { border-color: #0d6efd; }
.opcion-reasignacion input:checked { accent-color: #0d6efd; }
</style>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Reasignar Mesa <?= $mesa ?></h5>
        <a href="captura.php?torneo_id=<?= $torneo_id ?>&partida=<?= $ronda ?>&mesa=<?= $mesa ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Volver a Resultados</a>
    </div>
    <?php if ($torneo): ?><p class="text-muted small"><?= htmlspecialchars($torneo['nombre']) ?></p><?php endif; ?>

    <div class="row">
        <div class="col-md-4 col-lg-3 mb-3">
            <div class="card border shadow-sm">
                <div class="card-header bg-primary text-white py-2"><h6 class="mb-0">Mesas (Ronda <?= $ronda ?>)</h6></div>
                <div class="list-group list-group-flush">
                    <?php foreach ($todasLasMesas as $m): $esActiva = (int)$m['numero'] === $mesa; ?>
                    <a href="reasignar_mesa.php?torneo_id=<?= $torneo_id ?>&partida=<?= $ronda ?>&mesa=<?= (int)$m['numero'] ?>" class="list-group-item list-group-item-action mesa-item <?= $esActiva ? 'mesa-activa' : '' ?>">Mesa <?= (int)$m['numero'] ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-md-8 col-lg-9">
            <form method="post" action="reasignar_mesa.php">
                <input type="hidden" name="action" value="ejecutar_reasignacion">
                <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
                <input type="hidden" name="partida" value="<?= $ronda ?>">
                <input type="hidden" name="mesa" value="<?= $mesa ?>">

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <h6 class="mb-3"><i class="fas fa-users me-2"></i>Configuración actual - Mesa <?= $mesa ?></h6>
                        <div class="card border-primary mb-3">
                            <div class="card-header bg-primary text-white py-2">Pareja A</div>
                            <div class="card-body">
                                <?php foreach ($jugadores as $j): if ((int)($j['secuencia'] ?? 0) <= 2): ?>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span><?= htmlspecialchars($j['nombre_completo'] ?? $j['nombre'] ?? 'N/A') ?></span><span class="badge bg-primary"><?= (int)$j['secuencia'] ?></span></div>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                        <div class="card border-success">
                            <div class="card-header bg-success text-white py-2">Pareja B</div>
                            <div class="card-body">
                                <?php foreach ($jugadores as $j): if ((int)($j['secuencia'] ?? 0) > 2): ?>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span><?= htmlspecialchars($j['nombre_completo'] ?? $j['nombre'] ?? 'N/A') ?></span><span class="badge bg-success"><?= (int)$j['secuencia'] ?></span></div>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="mb-3"><i class="fas fa-list-check me-2"></i>Opciones de reasignación</h6>
                        <div class="mb-2">
                            <label class="opcion-reasignacion d-block"><input type="radio" name="opcion_reasignacion" value="1" required class="me-2"> Opción 1: Intercambiar posición 1 con 3</label>
                            <label class="opcion-reasignacion d-block"><input type="radio" name="opcion_reasignacion" value="2" class="me-2"> Opción 2: Intercambiar posición 1 con 4</label>
                            <label class="opcion-reasignacion d-block"><input type="radio" name="opcion_reasignacion" value="3" class="me-2"> Opción 3: Intercambiar posición 2 con 3</label>
                            <label class="opcion-reasignacion d-block"><input type="radio" name="opcion_reasignacion" value="4" class="me-2"> Opción 4: Intercambiar posición 2 con 4</label>
                            <label class="opcion-reasignacion d-block"><input type="radio" name="opcion_reasignacion" value="5" class="me-2"> Opción 5: Intercambio completo de parejas (1↔3, 2↔4)</label>
                            <label class="opcion-reasignacion d-block"><input type="radio" name="opcion_reasignacion" value="6" class="me-2"> Opción 6: Intercambio cruzado (1↔4, 2↔3)</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>Ejecutar reasignación</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
}
?>
</main></body></html>
