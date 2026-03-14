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
<div class="container-fluid py-4" style="max-width:980px">
    <h1 class="h3 mb-2"><i class="fas fa-file-import text-primary"></i> Carga datos torneo externo</h1>
    <p class="text-muted small mb-2">Solo <strong>administrador general</strong>. Recapitulación del procedimiento:</p>

    <div class="alert alert-warning border mb-4">
        <h2 class="h6 text-dark mb-2"><i class="fas fa-exclamation-triangle me-1"></i> Procedimiento completo (obligatorio)</h2>
        <p class="small mb-2">Siempre deben cumplirse los <strong>tres pasos</strong>. El <strong>id numérico que trae el archivo de resultados de la otra plataforma no es</strong> el <code>id</code> de <code>usuarios</code> en Mistorneos; <strong>no se usa para cargar</strong>. Solo sirven la <strong>cédula</strong> (o <strong>pareja + jugador</strong> alineado al archivo del paso 2) para obtener el <code>id_usuario</code> correcto.</p>
        <ol class="mb-0 ps-3 small">
            <li class="mb-2"><strong>Paso 1 — Torneo.</strong> Destino en <code>partiresul</code>; misma fecha de partida que el torneo.</li>
            <li class="mb-2"><strong>Paso 2 — Tabla parejas / cédula.</strong> Homologación contra <code>usuarios</code> → <code>id_usuario</code>.</li>
            <li class="mb-2"><strong>Paso 3 — Resultados por mesa.</strong> Misma lógica que el panel: <strong>por mesa</strong>, una fila por jugador con <strong>secuencia 1–4</strong> (asiento en la mesa), más partida/ronda y puntos. Tras sustituir cada jugador por su <code>id_usuario</code>, el INSERT en <code>partiresul</code> es el mismo que al registrar resultados en el panel (efectividad, zapato/chancleta, FF, etc.).</li>
        </ol>
        <p class="small mb-0 mt-2"><strong>Pasos 2 y 3 en una sola acción:</strong> suba los dos archivos juntos en el formulario de abajo.</p>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string)$_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string)$_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['warning'])): ?>
        <div class="alert alert-warning"><?= htmlspecialchars((string)$_SESSION['warning']) ?></div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>

    <div class="card mb-4 shadow border-primary">
        <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-trophy me-2"></i>Paso 1 — Seleccionar torneo</div>
        <div class="card-body">
            <p class="mb-2 small">Torneo ya creado; es el destino de <code>partiresul</code> y la fecha de partida tomada del torneo.</p>
            <form method="get" action="index.php" class="row g-2 align-items-end">
                <input type="hidden" name="page" value="importacion_torneo_externo">
                <div class="col-md-10">
                    <label class="form-label">Torneo</label>
                    <select name="torneo_id" class="form-select" required>
                        <option value="">— Elija un torneo —</option>
                        <?php foreach ($torneos as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= $torneo_id_sel === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre'] . ' · ' . $t['fechator'] . ' · mod.' . (int)$t['modalidad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check"></i> Aplicar</button>
                </div>
            </form>
            <?php if ($torneo_actual): ?>
                <div class="mt-3 p-3 bg-light rounded border">
                    <strong>Torneo activo:</strong> <?= htmlspecialchars((string)$torneo_actual['nombre']) ?>
                    <span class="badge bg-secondary ms-2"><?= htmlspecialchars($etiqueta_modalidad) ?></span>
                    <div class="mt-2 small">
                        <a href="<?= htmlspecialchars($url_panel) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Panel del torneo (registrar resultados manual)</a>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-warning small mt-2 mb-0">Complete el paso 1 para habilitar pasos 2–3 e inscripciones masivas.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($torneo_actual): ?>
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-secondary text-white fw-bold"><i class="fas fa-users me-2"></i>Inscripciones (antes de resultados, si aplica)</div>
        <div class="card-body small">
            <p class="text-muted">Misma carga masiva que en el panel del torneo (no sustituye pasos 2–3 de resultados).</p>
            <?php if ($es_equipos): ?>
                <a href="<?= htmlspecialchars($url_carga_equipos) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-upload"></i> Carga masiva equipos</a>
                <a href="<?= htmlspecialchars($url_plantilla_equipos) ?>" class="btn btn-outline-success btn-sm ms-1"><i class="fas fa-download"></i> Plantilla</a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($url_import_individual) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> Importación masiva (panel)</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4 shadow-lg border-success">
        <div class="card-header bg-success text-white fw-bold"><i class="fas fa-link me-2"></i>Pasos 2 + 3 — Homologar e ingresar a <code>partiresul</code> (única carga de resultados aquí)</div>
        <div class="card-body">
            <p class="small mb-2"><strong>Archivo paso 2:</strong> pareja + cédula → mapa a <code>id_usuario</code> (usuarios).</p>
            <p class="small mb-2"><strong>Archivo paso 3:</strong> una fila por jugador en cada mesa: <code>partida</code> (ronda), <code>mesa</code>, <code>secuencia</code> (1–4 = orden en la mesa, igual que el panel), <code>resultado1</code>, <code>resultado2</code>. Cada fila debe poder resolverse con columna <strong>cédula</strong> o con <strong>pareja</strong> + <strong>jugador</strong> (1, 2… según el orden de ese jugador en el archivo paso 2). <em>Los ids del otro sistema en este archivo se ignoran.</em></p>
            <div class="alert alert-danger small mb-3"><strong>Vaciar partiresul del torneo</strong> antes de insertar solo si rehace la carga completa.</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::token()) ?>">
                <input type="hidden" name="accion" value="fase2_dual">
                <input type="hidden" name="torneo_id" value="<?= (int)$torneo_id_sel ?>">
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Paso 2 — Tabla parejas / cédula</label>
                        <input type="file" name="archivo_homologacion" class="form-control" accept=".xlsx,.csv,.txt" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Paso 3 — Tabla resultados (otra plataforma)</label>
                        <input type="file" name="archivo_resultados" class="form-control" accept=".xlsx,.csv,.txt" required>
                    </div>
                </div>
                <div class="mb-2 form-check">
                    <input type="checkbox" class="form-check-input" name="reemplazar_partiresul_dual" value="1" id="rep2">
                    <label class="form-check-label" for="rep2">Vaciar <code>partiresul</code> de este torneo antes de importar</label>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Ejecutar pasos 2 y 3 → partiresul</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-secondary"><i class="fas fa-arrow-up"></i> Complete el <strong>paso 1</strong> para continuar.</div>
    <?php endif; ?>

    <div class="card mb-2 border-info">
        <div class="card-header bg-info text-white py-2 small fw-bold">Auxiliar — Descargar CSV homologación (paso 2 solo para revisar)</div>
        <div class="card-body py-2 small">
            <p class="mb-2">Genera CSV con columna <code>id_usuario</code> sin subir resultados. Útil para auditoría; el flujo normal es <strong>Pasos 2+3 juntos</strong> arriba.</p>
            <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::token()) ?>">
                <input type="hidden" name="accion" value="fase1">
                <div class="flex-grow-1" style="min-width:200px">
                    <input type="file" name="archivo" class="form-control form-control-sm" accept=".xlsx,.csv,.txt" required>
                </div>
                <button type="submit" class="btn btn-sm btn-info text-white">Descargar CSV</button>
            </form>
        </div>
    </div>
</div>
