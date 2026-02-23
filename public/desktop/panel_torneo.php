<?php
/**
 * Panel de Torneo (Desktop) – Modelo simplificado de la web.
 * Misma estructura en 3 bloques: Gestión de Mesas | Operaciones | Resultados.
 * Sin formato complejo (Tailwind, cronómetro, actas QR). Solo Bootstrap 5 y enlaces.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';
require_once __DIR__ . '/../../desktop/core/db_bridge.php';

$pdo = DB_Local::pdo();
$entidad_id = DB::getEntidadId();
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;

$torneos = [];
$torneo = null;
$ultima_ronda = 0;
$mesas_incompletas = 0;
$puede_generar = true;
$inscripciones_bloqueado = false;
$es_equipos = false;
$total_inscritos = 0;
$mesas_ultima_ronda = 0;
$tabla_partiresul = false;

try {
    $sqlT = "SELECT id, nombre, fechator, estatus, rondas, COALESCE(modalidad, 0) AS modalidad FROM tournaments ORDER BY id DESC";
    if ($entidad_id > 0) {
        $stmtT = $pdo->prepare("SELECT id, nombre, fechator, estatus, rondas, COALESCE(modalidad, 0) AS modalidad FROM tournaments WHERE entidad = ? ORDER BY id DESC");
        $stmtT->execute([$entidad_id]);
        $torneos = $stmtT->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $torneos = $pdo->query($sqlT)->fetchAll(PDO::FETCH_ASSOC);
    }
    $tabla_partiresul = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='partiresul' LIMIT 1")->fetch();
} catch (Throwable $e) {
}

if ($torneo_id > 0) {
    foreach ($torneos as $t) {
        if ((int)$t['id'] === $torneo_id) {
            $torneo = $t;
            break;
        }
    }
    if (!$torneo) {
        $stmt = $pdo->prepare("SELECT id, nombre, fechator, estatus, rondas, COALESCE(modalidad, 0) AS modalidad FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($torneo) {
        $es_equipos = (int)($torneo['modalidad'] ?? 0) === 3;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE torneo_id = ?");
        $stmt->execute([$torneo_id]);
        $total_inscritos = (int) $stmt->fetchColumn();
        $mesas_ultima_ronda = 0;
        if ($tabla_partiresul) {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(partida), 0) FROM partiresul WHERE id_torneo = ?");
            $stmt->execute([$torneo_id]);
            $ultima_ronda = (int) $stmt->fetchColumn();
            if ($ultima_ronda > 0) {
                // Solo mesas de juego (mesa > 0); la mesa 0 es bye y no bloquea generar siguiente ronda
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT partida, mesa FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa > 0 AND (registrado = 0 OR registrado IS NULL) GROUP BY partida, mesa)");
                $stmt->execute([$torneo_id, $ultima_ronda]);
                $mesas_incompletas = (int) $stmt->fetchColumn();
                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT mesa) FROM partiresul WHERE id_torneo = ? AND partida = ?");
                $stmt->execute([$torneo_id, $ultima_ronda]);
                $mesas_ultima_ronda = (int) $stmt->fetchColumn();
            }
        }
        $inscripciones_bloqueado = $es_equipos ? ($ultima_ronda >= 1) : ($ultima_ronda >= 2);
        $puede_generar = ($ultima_ronda === 0) || ($mesas_incompletas === 0);
    }
}

// Sin torneo: redirigir al listado de torneos (acceso al panel solo desde el torneo)
if ($torneo_id <= 0 || !$torneo) {
    header('Location: torneos.php');
    exit;
}

$proxima_ronda = $ultima_ronda + 1;
$total_rondas = (int)($torneo['rondas'] ?? 0);
$todas_rondas_generadas = ($total_rondas > 0 && $ultima_ronda >= $total_rondas);
$puede_finalizar = $todas_rondas_generadas && $mesas_incompletas === 0;
$fecha_formato = !empty($torneo['fechator']) ? date('d/m/Y', strtotime($torneo['fechator'])) : ($torneo['fechator'] ?? '');

$pageTitle = 'Panel de Torneo - ' . htmlspecialchars($torneo['nombre'] ?? 'Torneo');
$desktopActive = 'torneos';
require_once __DIR__ . '/desktop_layout.php';
?>
<style>
.panel-torneo-cronometro { background: linear-gradient(135deg, #0f766e 0%, #0d9488 50%, #14b8a6 100%); color: #fff; border: none; padding: 0.75rem 1.5rem; font-weight: 700; border-radius: 0.5rem; width: 100%; transition: transform .2s, box-shadow .2s; }
.panel-torneo-cronometro:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); color: #fff; }
.panel-torneo-finalizar { background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); color: #fff; border: none; padding: 0.6rem 1.25rem; font-weight: 600; border-radius: 0.5rem; }
.panel-torneo-finalizar:hover { color: #fff; opacity: 0.95; }
.panel-torneo-btn { width: 100%; text-align: left; margin-bottom: 0.5rem; }
</style>
<div class="container-fluid py-3">
    <?php
    $msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
    $err = isset($_GET['error']) ? (string)$_GET['error'] : '';
    if ($msg === 'creado'): ?>
    <div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i>Torneo creado correctamente.</div>
    <?php endif; ?>
    <?php if ($msg === 'finalizado'): ?>
    <div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i>Torneo finalizado correctamente.</div>
    <?php endif; ?>
    <?php if ($msg === 'ronda_eliminada'): ?>
    <div class="alert alert-info py-2"><i class="fas fa-info-circle me-2"></i>Ronda eliminada.</div>
    <?php endif; ?>
    <?php if ($msg === 'ronda_generada'): ?>
    <div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i>Ronda generada correctamente.</div>
    <?php endif; ?>
    <?php if ($msg === 'estadisticas_actualizadas'): ?>
    <div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i>Estadísticas actualizadas.</div>
    <?php endif; ?>
    <?php if ($msg === 'resultados_guardados'): ?>
    <div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i>Resultados guardados correctamente.</div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-2"></i>Error: <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="torneos.php">Torneos</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($torneo['nombre'] ?? '') ?></li>
        </ol>
    </nav>

    <!-- Línea resumen torneo (como en la imagen) -->
    <p class="text-muted mb-1"><?= $fecha_formato ?> · <?= $es_equipos ? 'Equipos' : 'Individual' ?> · <?= $total_rondas ?> rondas</p>

    <!-- Barra Ronda Actual -->
    <?php if ($ultima_ronda > 0): ?>
    <div class="alert alert-info py-2 mb-3 d-flex flex-wrap align-items-center gap-3">
        <strong>Ronda Actual: <?= $ultima_ronda ?></strong>
        <span><?= $mesas_ultima_ronda ?> mesas</span>
        <span><?= $total_inscritos ?> inscritos</span>
    </div>
    <?php endif; ?>

    <!-- Puede finalizar el torneo + botón Finalizar torneo -->
    <?php if ($puede_finalizar): ?>
    <div class="mb-3 p-3 rounded border border-success bg-light">
        <p class="mb-2 fw-semibold text-success"><i class="fas fa-check-circle me-2"></i>Puede finalizar el torneo</p>
        <form method="post" action="cerrar_torneo.php" class="d-inline" onsubmit="return confirm('¿Finalizar el torneo? No se podrán modificar datos después.');">
            <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
            <button type="submit" class="btn panel-torneo-finalizar"><i class="fas fa-lock me-2"></i>Finalizar torneo</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Botón grande: ACTIVAR CRONÓMETRO DE RONDA -->
    <div class="mb-4">
        <a href="cronometro.php?torneo_id=<?= $torneo_id ?>&ronda=<?= $ultima_ronda ?: 1 ?>" class="btn panel-torneo-cronometro d-inline-flex align-items-center justify-content-center">
            <i class="fas fa-clock me-2"></i><span id="lblCronometro">ACTIVAR CRONÓMETRO DE RONDA</span>
        </a>
    </div>

    <!-- Tres paneles (mismo orden y etiquetas que la imagen) -->
    <div class="row g-3">
        <!-- Panel 1: Gestión de Mesas (header verde) -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header py-2 text-white" style="background: linear-gradient(to right, #10b981, #14b8a6);">
                    <h3 class="h6 mb-0"><i class="fas fa-table me-2"></i>Gestión de Mesas</h3>
                </div>
                <div class="card-body">
                    <a href="../index.php?page=invitacion_clubes&torneo_id=<?= $torneo_id ?>" class="btn btn-info panel-torneo-btn"><i class="fas fa-paper-plane me-1"></i>Invitar Clubes</a>
                    <?php if ($inscripciones_bloqueado): ?>
                    <button type="button" disabled class="btn btn-secondary panel-torneo-btn"><i class="fas fa-lock me-1"></i>Inscripciones (Cerrado)</button>
                    <?php else: ?>
                    <a href="inscripciones.php?torneo_id=<?= $torneo_id ?>" class="btn btn-primary panel-torneo-btn"><i class="fas fa-clipboard-list me-1"></i>Gestionar Inscripciones</a>
                    <a href="inscribir.php?torneo_id=<?= $torneo_id ?>" class="btn btn-warning panel-torneo-btn"><i class="fas fa-user-plus me-1"></i>Inscribir en sitio</a>
                    <?php endif; ?>
                    <?php if ($ultima_ronda > 0): ?>
                    <a href="asignaciones.php?torneo_id=<?= $torneo_id ?>&ronda=<?= $ultima_ronda ?>" class="btn btn-success panel-torneo-btn"><i class="fas fa-eye me-1"></i>Mostrar Asignaciones</a>
                    <a href="asignar_mesas_operador.php?torneo_id=<?= $torneo_id ?>&ronda=<?= $ultima_ronda ?>" class="btn btn-success panel-torneo-btn"><i class="fas fa-user-cog me-1"></i>Asignar mesas al operador</a>
                    <a href="agregar_mesa.php?torneo_id=<?= $torneo_id ?>&ronda=<?= $ultima_ronda ?>" class="btn btn-success panel-torneo-btn"><i class="fas fa-plus-circle me-1"></i>Agregar Mesa</a>
                    <form method="post" action="eliminar_ronda.php" class="mb-0" onsubmit="return confirm('¿Eliminar la última ronda? Se perderán las asignaciones de esta ronda.');">
                        <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
                        <input type="hidden" name="ronda" value="<?= $ultima_ronda ?>">
                        <button type="submit" class="btn btn-danger panel-torneo-btn w-100"><i class="fas fa-trash-alt me-1"></i>Eliminar Ronda</button>
                    </form>
                    <?php else: ?>
                    <p class="text-muted small mb-0">Genera la primera ronda para ver estas opciones.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Panel 2: Operaciones (header azul) -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header py-2 text-white" style="background: linear-gradient(to right, #3b82f6, #6366f1);">
                    <h3 class="h6 mb-0"><i class="fas fa-cogs me-2"></i>Operaciones</h3>
                </div>
                <div class="card-body">
                    <a href="actualizar_estadisticas.php?torneo_id=<?= $torneo_id ?>" class="btn btn-info panel-torneo-btn" onclick="return confirm('¿Actualizar estadísticas de inscritos?');"><i class="fas fa-sync-alt me-1"></i>Actualizar Estadísticas</a>
                    <button type="button" disabled class="btn btn-secondary panel-torneo-btn" title="Desktop: sin envíos QR"><i class="fas fa-check-double me-1"></i>Verificar Mesas (envíos QR pendientes)</button>
                    <?php if ($todas_rondas_generadas): ?>
                    <div class="alert alert-success small py-2 mb-2"><i class="fas fa-check-circle me-1"></i>Todas las rondas generadas</div>
                    <?php else: ?>
                    <form method="post" action="generar_ronda.php" class="mb-2">
                        <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
                        <input type="hidden" name="estrategia_ronda2" value="separar">
                        <input type="hidden" name="estrategia_asignacion" value="secuencial">
                        <button type="submit" class="btn btn-primary panel-torneo-btn" <?= !$puede_generar ? 'disabled' : '' ?>><i class="fas fa-random me-1"></i>Generar Ronda <?= $proxima_ronda ?></button>
                    </form>
                    <?php endif; ?>
                    <?php if ($ultima_ronda > 0): ?>
                    <a href="captura.php?torneo_id=<?= $torneo_id ?>&partida=<?= $ultima_ronda ?>" class="btn btn-warning panel-torneo-btn"><i class="fas fa-edit me-1"></i>Ingresar Resultados</a>
                    <a href="cuadricula.php?torneo_id=<?= $torneo_id ?>&ronda=<?= $ultima_ronda ?>" class="btn btn-secondary panel-torneo-btn" style="background:#6f42c1;color:white;"><i class="fas fa-th me-1"></i>Cuadrícula</a>
                    <a href="imprimir_hojas.php?torneo_id=<?= $torneo_id ?>&ronda=<?= $ultima_ronda ?>" class="btn panel-torneo-btn" style="background:#4f46e5;color:white;"><i class="fas fa-print me-1"></i>Imprimir Hojas</a>
                    <?php else: ?>
                    <span class="btn btn-secondary panel-torneo-btn disabled">Ingresar Resultados (genera una ronda)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Panel 3: Resultados (header naranja) -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header py-2 text-dark" style="background: linear-gradient(to right, #f59e0b, #f97316);">
                    <h3 class="h6 mb-0"><i class="fas fa-trophy me-2"></i>Resultados</h3>
                </div>
                <div class="card-body">
                    <a href="posiciones.php?torneo_id=<?= $torneo_id ?>" class="btn panel-torneo-btn" style="background:#6f42c1;color:white;"><i class="fas fa-list-ol me-1"></i>Resultados</a>
                    <a href="resultados_por_club.php?torneo_id=<?= $torneo_id ?>" class="btn btn-success panel-torneo-btn"><i class="fas fa-building me-1"></i>Resultados Clubes</a>
                    <a href="podios.php?torneo_id=<?= $torneo_id ?>" class="btn btn-warning panel-torneo-btn"><i class="fas fa-medal me-1"></i>Podios</a>
                    <hr class="my-2">
                    <p class="text-muted small mb-0">Finalizar torneo</p>
                    <?php if ($puede_finalizar): ?>
                    <form method="post" action="cerrar_torneo.php" class="mb-0" onsubmit="return confirm('¿Finalizar el torneo?');">
                        <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
                        <button type="submit" class="btn btn-dark panel-torneo-btn btn-sm"><i class="fas fa-lock me-1"></i>Finalizar torneo</button>
                    </form>
                    <?php else: ?>
                    <button type="button" disabled class="btn btn-secondary panel-torneo-btn btn-sm">Finalizar torneo</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <span class="text-muted small align-self-center">ID del Torneo: <strong>#<?= (int)$torneo['id'] ?></strong></span>
        <a href="torneos.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Ver torneos</a>
    </div>
</div>
</main></body></html>
