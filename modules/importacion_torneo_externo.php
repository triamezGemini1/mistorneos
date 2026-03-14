<?php
/**
 * Admin general — Flujo acordado:
 * Paso 1: torneo. Paso 2: tabla parejas+cédula → id_usuario (usuarios).
 * Paso 3: tabla resultados → reemplazar por id_usuario → INSERT partiresul (mismo criterio que registrar resultados en panel).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
Auth::requireRole(['admin_general']);

require_once __DIR__ . '/../lib/ImportacionTorneoExternoService.php';

$userId = (int)(Auth::id() ?: 0);
$baseList = 'index.php?page=importacion_torneo_externo';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!$csrf || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        $_SESSION['error'] = 'Token CSRF inválido.';
        $tid = (int)($_POST['torneo_id'] ?? $_GET['torneo_id'] ?? 0);
        header('Location: ' . $baseList . ($tid > 0 ? '&torneo_id=' . $tid : ''));
        exit;
    }
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'fase1' && isset($_FILES['archivo']) && is_uploaded_file($_FILES['archivo']['tmp_name'])) {
        $rows = ImportacionTorneoExternoService::leerExcelOCsv(
            (string)$_FILES['archivo']['tmp_name'],
            (string)($_FILES['archivo']['name'] ?? 'x.xlsx')
        );
        $pdo = DB::pdo();
        $out = ImportacionTorneoExternoService::fase1Enriquecer($pdo, $rows);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="jugadores_con_id_usuario_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $fh = fopen('php://output', 'w');
        foreach ($out['filas'] as $line) {
            fputcsv($fh, $line);
        }
        fclose($fh);
        exit;
    }

    if ($accion === 'fase2_dual'
        && isset($_FILES['archivo_homologacion'], $_FILES['archivo_resultados'])
        && is_uploaded_file($_FILES['archivo_homologacion']['tmp_name'])
        && is_uploaded_file($_FILES['archivo_resultados']['tmp_name'])
    ) {
        $torneo_id = (int)($_POST['torneo_id'] ?? 0);
        $reemplazar = !empty($_POST['reemplazar_partiresul_dual']);
        if ($torneo_id <= 0) {
            $_SESSION['error'] = 'Debe elegir el torneo (paso 1).';
            header('Location: ' . $baseList);
            exit;
        }
        $pdo = DB::pdo();
        $st = $pdo->prepare('SELECT id, nombre, fechator FROM tournaments WHERE id = ?');
        $st->execute([$torneo_id]);
        $torneo = $st->fetch(PDO::FETCH_ASSOC);
        if (!$torneo) {
            $_SESSION['error'] = 'Torneo no encontrado.';
            header('Location: ' . $baseList);
            exit;
        }
        $fecha = substr((string)($torneo['fechator'] ?? ''), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }
        $rowsH = ImportacionTorneoExternoService::leerExcelOCsv(
            (string)$_FILES['archivo_homologacion']['tmp_name'],
            (string)($_FILES['archivo_homologacion']['name'] ?? 'x.xlsx')
        );
        $rowsR = ImportacionTorneoExternoService::leerExcelOCsv(
            (string)$_FILES['archivo_resultados']['tmp_name'],
            (string)($_FILES['archivo_resultados']['name'] ?? 'x.xlsx')
        );
        if ($reemplazar) {
            $pdo->prepare('DELETE FROM partiresul WHERE id_torneo = ?')->execute([$torneo_id]);
        }
        $res = ImportacionTorneoExternoService::importarDosArchivosPartiresul($pdo, $torneo_id, $userId, $fecha, $rowsH, $rowsR);
        $msg = 'Carga por mesa/secuencia (como el panel): ' . $res['insertados'] . ' filas en partiresul.';
        if ($res['homologacion_sin_usuario'] > 0) {
            $msg .= ' En homologación, filas sin usuario en BD: ' . $res['homologacion_sin_usuario'] . '.';
        }
        if ($res['resultados_sin_resolver'] > 0) {
            $msg .= ' Resultados sin poder asignar jugador: ' . $res['resultados_sin_resolver'] . '.';
        }
        if ($res['cedulas_no_encontradas'] !== []) {
            $msg .= ' Cédulas sin usuario (muestra): ' . implode(', ', array_slice($res['cedulas_no_encontradas'], 0, 8)) . '.';
        }
        $_SESSION['success'] = $msg;
        if ($res['errores'] !== []) {
            $_SESSION['warning'] = implode(' ', array_slice($res['errores'], 0, 5));
        }
        header('Location: ' . $baseList . '&torneo_id=' . $torneo_id);
        exit;
    }
}

$pdo = DB::pdo();
$torneos = $pdo->query('SELECT id, nombre, fechator, modalidad FROM tournaments ORDER BY fechator DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
$torneo_id_sel = (int)($_GET['torneo_id'] ?? 0);
$torneo_actual = null;
foreach ($torneos as $t) {
    if ((int)$t['id'] === $torneo_id_sel) {
        $torneo_actual = $t;
        break;
    }
}
$modalidad = (int)($torneo_actual['modalidad'] ?? 0);
$es_equipos = $modalidad === 3;
$etiqueta_modalidad = $es_equipos ? 'Equipos (4 integrantes)' : ($modalidad === 4 ? 'Parejas fijas' : 'Individual / mesas');
$url_panel = 'index.php?page=torneo_gestion&action=panel&torneo_id=' . $torneo_id_sel;
$url_carga_equipos = 'index.php?page=torneo_gestion&action=carga_masiva_equipos_sitio&torneo_id=' . $torneo_id_sel;
$url_plantilla_equipos = 'index.php?page=torneo_gestion&action=carga_masiva_equipos_plantilla&torneo_id=' . $torneo_id_sel;
$url_import_individual = $url_panel . '#importacion-masiva';
?>
<style>
    .imp-paso-num { width:2.25rem; height:2.25rem; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:1.1rem; }
    .imp-card-paso { border-left: 4px solid var(--bs-card-border-color); }
    .imp-card-paso.paso-1 { border-left-color: #0d6efd; }
    .imp-card-paso.paso-15 { border-left-color: #6c757d; }
    .imp-card-paso.paso-2 { border-left-color: #198754; }
    .imp-card-paso.paso-3 { border-left-color: #0dcaf0; }
    .imp-card-paso.paso-4 { border-left-color: #198754; }
    .imp-card-paso.paso-aux { border-left-color: #0dcaf0; }
    .imp-seccion { background: #f8f9fa; border-radius: 8px; padding: .75rem 1rem; margin-bottom: .75rem; }
    .imp-seccion h6 { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; margin-bottom: .35rem; }
</style>
<div class="container-fluid py-4" style="max-width:1040px">
    <div class="card mb-4 shadow-sm border-0 bg-light">
        <div class="card-body py-3">
            <h1 class="h4 mb-1"><i class="fas fa-file-import text-primary me-2"></i>Carga de datos desde otra plataforma</h1>
            <p class="text-muted small mb-0">Solo <strong>administrador general</strong>. Siga la secuencia en orden. Los pasos 2 y 3 se envían juntos en un solo formulario al final.</p>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success shadow-sm"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger shadow-sm"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['warning'])): ?>
        <div class="alert alert-warning shadow-sm"><?= htmlspecialchars((string)$_SESSION['warning']) ?></div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>

    <!-- Tarjeta índice -->
    <div class="card mb-4 shadow imp-card-paso paso-1">
        <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
            <span class="imp-paso-num bg-primary text-white">0</span>
            <div>
                <span class="fw-bold">Secuencia del proceso</span>
                <div class="small text-muted">Vista rápida antes de ejecutar cada paso</div>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="row g-2 small">
                <div class="col-md-6 col-lg-4">
                    <div class="border rounded p-2 h-100 bg-primary bg-opacity-10">
                        <strong class="text-primary">Paso 1</strong> — Elegir torneo destino y fecha de partida.
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="border rounded p-2 h-100 bg-secondary bg-opacity-10">
                        <strong class="text-secondary">(Opcional)</strong> — Inscribir jugadores/equipos si aún no están.
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="border rounded p-2 h-100 bg-success bg-opacity-10">
                        <strong class="text-success">Pasos 2 + 3</strong> — Homologar (pareja/cédula) + resultados por mesa → <code>partiresul</code>.
                    </div>
                </div>
            </div>
            <div class="alert alert-warning small mb-0 mt-3">
                <strong>Importante:</strong> el id numérico del export de la otra plataforma <strong>no</strong> es el <code>id_usuario</code> de Mistorneos; el sistema <strong>no lo usa</strong>. Hace falta <strong>cédula</strong> o <strong>pareja + jugador</strong> en el archivo de resultados para enlazar con el paso 2.
            </div>
        </div>
    </div>

    <!-- PASO 1 -->
    <div class="card mb-4 shadow imp-card-paso paso-1">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2 flex-wrap py-3">
            <span class="imp-paso-num bg-white text-primary">1</span>
            <div>
                <span class="fw-bold">Paso 1 — Seleccionar torneo</span>
                <div class="small opacity-90">Primero fije dónde se guardarán los resultados</div>
            </div>
        </div>
        <div class="card-body">
            <div class="imp-seccion">
                <h6>Qué hace este paso</h6>
                <p class="small mb-0">Asocia toda la carga al torneo que elija. Los registros van a <code>partiresul</code> con ese <code>id_torneo</code>. La <strong>fecha de partida</strong> de cada fila será la <strong>fecha del torneo</strong> en Mistorneos.</p>
            </div>
            <div class="imp-seccion">
                <h6>Qué debe hacer usted</h6>
                <ol class="small mb-0 ps-3">
                    <li>El torneo debe existir ya (creado en gestión de torneos).</li>
                    <li>Elija el torneo en la lista y pulse <strong>Aplicar</strong>.</li>
                    <li>Solo después podrá subir los archivos de los pasos 2 y 3.</li>
                </ol>
            </div>
            <form method="get" action="index.php" class="row g-2 align-items-end mt-2">
                <input type="hidden" name="page" value="importacion_torneo_externo">
                <div class="col-md-9">
                    <label class="form-label fw-semibold">Torneo destino</label>
                    <select name="torneo_id" class="form-select" required>
                        <option value="">— Seleccione un torneo —</option>
                        <?php foreach ($torneos as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= $torneo_id_sel === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre'] . ' · ' . $t['fechator'] . ' · mod.' . (int)$t['modalidad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check me-1"></i> Aplicar paso 1</button>
                </div>
            </form>
            <?php if ($torneo_actual): ?>
                <div class="mt-3 p-3 border border-success rounded bg-success bg-opacity-10">
                    <div class="small text-success fw-bold mb-1"><i class="fas fa-check-circle me-1"></i> Paso 1 completado</div>
                    <div><strong><?= htmlspecialchars((string)$torneo_actual['nombre']) ?></strong> <span class="badge bg-secondary"><?= htmlspecialchars($etiqueta_modalidad) ?></span></div>
                    <a href="<?= htmlspecialchars($url_panel) ?>" class="btn btn-sm btn-outline-dark mt-2" target="_blank" rel="noopener"><i class="fas fa-external-link-alt me-1"></i> Abrir panel del torneo</a>
                </div>
            <?php else: ?>
                <p class="text-warning small mt-3 mb-0"><i class="fas fa-hand-point-up me-1"></i> Debe completar el paso 1 para desbloquear el envío de archivos (pasos 2 y 3).</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($torneo_actual): ?>
    <!-- OPCIONAL inscripciones -->
    <div class="card mb-4 shadow imp-card-paso paso-15">
        <div class="card-header bg-secondary text-white d-flex align-items-center gap-2 py-3">
            <span class="imp-paso-num bg-white text-secondary">1b</span>
            <div>
                <span class="fw-bold">Opcional — Inscripciones antes de resultados</span>
                <div class="small opacity-90">Solo si el torneo aún no tiene inscritos</div>
            </div>
        </div>
        <div class="card-body">
            <div class="imp-seccion">
                <h6>Qué hace este paso</h6>
                <p class="small mb-0">Es el mismo flujo que en el <strong>panel del torneo</strong>: carga masiva de equipos o importación masiva individual. No sustituye la homologación ni el archivo de resultados.</p>
            </div>
            <div class="imp-seccion">
                <h6>Cuándo usarlo</h6>
                <p class="small mb-0">Si los jugadores aún no están inscritos en este torneo, hágalo antes o después del paso 1, pero <strong>antes</strong> de depender de listados del panel (posiciones, etc.).</p>
            </div>
            <?php if ($es_equipos): ?>
                <a href="<?= htmlspecialchars($url_carga_equipos) ?>" class="btn btn-success"><i class="fas fa-file-upload me-1"></i> Carga masiva equipos</a>
                <a href="<?= htmlspecialchars($url_plantilla_equipos) ?>" class="btn btn-outline-secondary ms-1"><i class="fas fa-download me-1"></i> Plantilla CSV</a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($url_import_individual) ?>" class="btn btn-success"><i class="fas fa-file-csv me-1"></i> Importación masiva (abre panel)</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Indicaciones paso 2 (solo texto) -->
    <div class="card mb-3 shadow imp-card-paso paso-2">
        <div class="card-header bg-success bg-opacity-10 text-success border-success d-flex align-items-center gap-2 py-3">
            <span class="imp-paso-num bg-success text-white">2</span>
            <div>
                <span class="fw-bold text-dark">Paso 2 — Archivo de homologación (parejas y cédulas)</span>
                <div class="small text-muted">Lo subirá en el formulario del paso final junto al archivo de resultados</div>
            </div>
        </div>
        <div class="card-body">
            <div class="imp-seccion">
                <h6>Qué hace el sistema con este archivo</h6>
                <p class="small mb-0">Por cada fila lee la <strong>cédula</strong>, busca al usuario en Mistorneos (<code>usuarios</code>) y obtiene <code>id_usuario</code>. Agrupa por <strong>pareja</strong> para saber quién es el jugador 1, 2, … de cada pareja (orden de las filas en este archivo).</p>
            </div>
            <div class="imp-seccion">
                <h6>Qué debe traer el archivo (primera fila = encabezados)</h6>
                <ul class="small mb-0 ps-3">
                    <li>Columna de <strong>cédula</strong> (nombre flexible: cédula, cedula1, ci, documento…).</li>
                    <li>Columna de <strong>pareja</strong> (pareja, id_pareja…), misma clave que usará en el archivo de resultados si no lleva cédula por fila.</li>
                    <li>Formato: Excel <code>.xlsx</code> o CSV.</li>
                </ul>
            </div>
            <div class="imp-seccion border border-warning">
                <h6 class="text-warning">Atención</h6>
                <p class="small mb-0">Toda cédula debe existir en Mistorneos; si no, esa fila no tendrá <code>id_usuario</code> y fallará el enlace en el paso 3 para ese jugador.</p>
            </div>
        </div>
    </div>

    <!-- Indicaciones paso 3 -->
    <div class="card mb-3 shadow imp-card-paso paso-3">
        <div class="card-header bg-info bg-opacity-10 text-info border-info d-flex align-items-center gap-2 py-3">
            <span class="imp-paso-num bg-info text-dark">3</span>
            <div>
                <span class="fw-bold text-dark">Paso 3 — Archivo de resultados (por mesa, como el panel)</span>
                <div class="small text-muted">Export de la otra plataforma — se sube junto al paso 2</div>
            </div>
        </div>
        <div class="card-body">
            <div class="imp-seccion">
                <h6>Qué hace el sistema</h6>
                <p class="small mb-0">Cada fila = un jugador en una mesa y ronda. Sustituye la identidad del jugador por el <code>id_usuario</code> de Mistorneos (usando cédula o pareja+jugador del paso 2). Luego inserta en <code>partiresul</code> igual que al registrar resultados en el panel (mesa, secuencia 1–4, puntos, efectividad, zapato/chancleta, FF, etc.).</p>
            </div>
            <div class="imp-seccion">
                <h6>Columnas obligatorias en el archivo de resultados</h6>
                <ul class="small mb-0 ps-3">
                    <li><code>partida</code> (o ronda)</li>
                    <li><code>mesa</code></li>
                    <li><code>secuencia</code> (1 a 4 = puesto en la mesa, igual que en el panel)</li>
                    <li><code>resultado1</code>, <code>resultado2</code></li>
                    <li>Y además, en <strong>cada fila</strong>: o bien <strong>cédula</strong>, o bien <strong>pareja</strong> + <strong>jugador</strong> (1 o 2… según el orden en el archivo del paso 2)</li>
                </ul>
            </div>
            <div class="imp-seccion border border-danger bg-danger bg-opacity-10">
                <h6 class="text-danger">No usar el id del otro sistema</h6>
                <p class="small mb-0">Cualquier id numérico que venga del otro software <strong>no</strong> se utiliza para cargar. Solo cédula o pareja+jugador.</p>
            </div>
        </div>
    </div>

    <!-- Formulario ejecución 2+3 -->
    <div class="card mb-4 shadow-lg imp-card-paso paso-4 border-success">
        <div class="card-header bg-success text-white d-flex align-items-center gap-2 py-3">
            <span class="imp-paso-num bg-white text-success"><i class="fas fa-play"></i></span>
            <div>
                <span class="fw-bold">Ejecutar pasos 2 y 3 — Subir archivos e ingresar a partiresul</span>
                <div class="small opacity-90">Un solo envío: homologación + resultados</div>
            </div>
        </div>
        <div class="card-body">
            <div class="imp-seccion mb-3">
                <h6>Secuencia al pulsar el botón</h6>
                <ol class="small mb-0 ps-3">
                    <li>Lee el archivo del <strong>paso 2</strong> y construye mapas cédula → usuario y pareja → lista de usuarios.</li>
                    <li>Lee el archivo del <strong>paso 3</strong> y para cada fila asigna el <code>id_usuario</code> correcto.</li>
                    <li>Si marcó vaciar, borra antes los resultados previos de este torneo en <code>partiresul</code>.</li>
                    <li>Inserta cada fila con la misma lógica que el panel (por mesa y secuencia).</li>
                </ol>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::token()) ?>">
                <input type="hidden" name="accion" value="fase2_dual">
                <input type="hidden" name="torneo_id" value="<?= (int)$torneo_id_sel ?>">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold"><span class="badge bg-success me-1">2</span> Archivo homologación (pareja + cédula)</label>
                        <input type="file" name="archivo_homologacion" class="form-control" accept=".xlsx,.csv,.txt" required>
                        <div class="form-text">Mismo contenido descrito en la tarjeta del paso 2.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold"><span class="badge bg-info text-dark me-1">3</span> Archivo resultados (mesa, secuencia, puntos)</label>
                        <input type="file" name="archivo_resultados" class="form-control" accept=".xlsx,.csv,.txt" required>
                        <div class="form-text">Mismo contenido descrito en la tarjeta del paso 3.</div>
                    </div>
                </div>
                <div class="card bg-light mb-3">
                    <div class="card-body py-2">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="reemplazar_partiresul_dual" value="1" id="rep2">
                            <label class="form-check-label small" for="rep2"><strong>Vaciar partiresul</strong> de este torneo antes de importar (solo si va a recargar todo el histórico de resultados de una vez).</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>Ejecutar pasos 2 y 3 → guardar en partiresul</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="card mb-4 border-secondary imp-card-paso paso-15">
        <div class="card-body text-center py-4 text-muted">
            <i class="fas fa-lock fa-2x mb-2 d-block"></i>
            <strong>Pasos 2 y 3 bloqueados</strong>
            <p class="small mb-0">Complete primero el <strong>paso 1</strong> (seleccionar torneo y Aplicar) para ver las indicaciones y el formulario de archivos.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Auxiliar -->
    <div class="card mb-2 shadow-sm imp-card-paso paso-aux">
        <div class="card-header bg-info text-white d-flex align-items-center gap-2 py-2">
            <span class="imp-paso-num bg-white text-info small" style="width:1.75rem;height:1.75rem;font-size:.85rem;">A</span>
            <span class="fw-bold">Auxiliar — Revisar homologación sin cargar resultados</span>
        </div>
        <div class="card-body">
            <div class="imp-seccion">
                <h6>Qué hace</h6>
                <p class="small mb-0">Sube el mismo archivo del paso 2 y descarga un CSV con la columna <code>id_usuario</code> para revisar cédulas mal cargadas o usuarios faltantes, <strong>sin</strong> tocar <code>partiresul</code>.</p>
            </div>
            <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::token()) ?>">
                <input type="hidden" name="accion" value="fase1">
                <div class="col-md-8">
                    <input type="file" name="archivo" class="form-control" accept=".xlsx,.csv,.txt" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-info text-white w-100"><i class="fas fa-download me-1"></i> Descargar CSV revisión</button>
                </div>
            </form>
        </div>
    </div>
</div>
