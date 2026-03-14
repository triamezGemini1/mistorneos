<?php
/**
 * Módulo solo admin_general: importar desde otra plataforma + enlaces a cargas masivas del panel.
 * Fase 1: Excel/CSV pareja + cédula → CSV con id_usuario.
 * Fase 2: Excel resultados → partiresul (torneo elegido). Estadísticas = mismas que al registrar en el torneo.
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
            $_SESSION['error'] = 'Debe elegir el torneo destino.';
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
        $msg = 'Insertados en partiresul: ' . $res['insertados'] . '.';
        if ($res['homologacion_sin_usuario'] > 0) {
            $msg .= ' Filas en homologación sin id_usuario: ' . $res['homologacion_sin_usuario'] . '.';
        }
        if ($res['resultados_sin_resolver'] > 0) {
            $msg .= ' Filas de resultados sin poder asignar jugador: ' . $res['resultados_sin_resolver'] . ' (revise cédula/pareja/jugador).';
        }
        if ($res['cedulas_no_encontradas'] !== []) {
            $msg .= ' Cédulas sin usuario en BD (muestra): ' . implode(', ', array_slice($res['cedulas_no_encontradas'], 0, 8)) . '.';
        }
        $_SESSION['success'] = $msg;
        if ($res['errores'] !== []) {
            $_SESSION['warning'] = implode(' ', array_slice($res['errores'], 0, 5));
        }
        header('Location: ' . $baseList . '&torneo_id=' . $torneo_id);
        exit;
    }

    if ($accion === 'fase2' && isset($_FILES['archivo_resultados']) && is_uploaded_file($_FILES['archivo_resultados']['tmp_name'])) {
        $torneo_id = (int)($_POST['torneo_id'] ?? 0);
        $reemplazar = !empty($_POST['reemplazar_partiresul']);
        if ($torneo_id <= 0) {
            $_SESSION['error'] = 'Debe elegir el torneo destino antes de importar resultados.';
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
        $rows = ImportacionTorneoExternoService::leerExcelOCsv(
            (string)$_FILES['archivo_resultados']['tmp_name'],
            (string)($_FILES['archivo_resultados']['name'] ?? 'x.xlsx')
        );
        if ($reemplazar) {
            $pdo->prepare('DELETE FROM partiresul WHERE id_torneo = ?')->execute([$torneo_id]);
        }
        $res = ImportacionTorneoExternoService::fase2InsertarPartiresul($pdo, $torneo_id, $userId, $fecha, $rows);
        $_SESSION['success'] = 'Filas insertadas en partiresul: ' . $res['insertados'] . '. Las estadísticas del torneo siguen el flujo habitual del panel (misma modalidad).';
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
    <p class="text-muted small mb-3">Solo <strong>administrador general</strong>. Cree el torneo en Mistorneos, elija aquí el torneo y use las cargas masivas de inscripción (igual que en el panel) y, si aplica, la importación de resultados a <code>partiresul</code>.</p>

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

    <!-- Paso 0: Torneo obligatorio para resultados y para enlaces de carga masiva -->
    <div class="card mb-4 shadow border-primary">
        <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-trophy me-2"></i>1. Torneo de trabajo</div>
        <div class="card-body">
            <p class="mb-2">Seleccione el torneo <strong>ya creado</strong> sobre el que va a cargar inscripciones o resultados.</p>
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
                    <strong><?= htmlspecialchars((string)$torneo_actual['nombre']) ?></strong>
                    <span class="badge bg-secondary ms-2"><?= htmlspecialchars($etiqueta_modalidad) ?></span>
                    <div class="mt-2 small">
                        <a href="<?= htmlspecialchars($url_panel) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Abrir panel del torneo</a>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-warning small mt-2 mb-0"><i class="fas fa-info-circle"></i> Sin torneo no puede importar resultados ni usar los accesos directos a carga masiva abajo.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($torneo_actual): ?>
    <!-- Cargas masivas (misma lógica que el panel) -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-success text-white fw-bold"><i class="fas fa-users me-2"></i>2. Carga masiva de inscripciones (panel)</div>
        <div class="card-body">
            <p class="small text-muted">Misma funcionalidad que en el panel del torneo. Tras cargar, las estadísticas se calculan con el procedimiento habitual de cada modalidad.</p>
            <?php if ($es_equipos): ?>
                <p>Este torneo es <strong>modalidad equipos</strong>. Use la carga masiva de equipos (Excel/CSV/ADEAZ).</p>
                <a href="<?= htmlspecialchars($url_carga_equipos) ?>" class="btn btn-success"><i class="fas fa-file-upload"></i> Carga masiva equipos</a>
                <a href="<?= htmlspecialchars($url_plantilla_equipos) ?>" class="btn btn-outline-success ms-1"><i class="fas fa-download"></i> Plantilla CSV</a>
            <?php else: ?>
                <p>Torneo <strong>individual</strong> (o parejas según modalidad). En el panel use <em>Importación masiva</em> (Excel/CSV).</p>
                <a href="<?= htmlspecialchars($url_import_individual) ?>" class="btn btn-success"><i class="fas fa-file-csv"></i> Ir al panel e importación masiva</a>
                <span class="small text-muted ms-2">Se abre el modal de importación al cargar la página.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-info text-white fw-bold">Fase A — Enriquecer listado (pareja + cédula → id_usuario)</div>
        <div class="card-body">
            <p class="small">No requiere torneo. Útil para revisar o mezclar a mano. Si usa arriba <strong>Dos archivos en un solo envío</strong>, no hace falta descargar este CSV.</p>
            <form method="post" enctype="multipart/form-data" class="mt-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::token()) ?>">
                <input type="hidden" name="accion" value="fase1">
                <div class="mb-2">
                    <input type="file" name="archivo" class="form-control" accept=".xlsx,.csv,.txt" required>
                </div>
                <button type="submit" class="btn btn-info text-white"><i class="fas fa-file-export"></i> Procesar y descargar CSV</button>
            </form>
        </div>
    </div>

    <?php if ($torneo_actual): ?>
    <div class="card mb-4 shadow-lg border-success">
        <div class="card-header bg-success text-white fw-bold"><i class="fas fa-layer-group me-2"></i>Recomendado — Dos archivos → <code>partiresul</code> (un solo envío)</div>
        <div class="card-body">
            <p class="mb-2"><strong>Archivo 1 — Homologación:</strong> mismas columnas que la Fase A (<code>pareja</code> o <code>id_pareja</code> + <code>cédula</code>). El sistema resuelve <code>id_usuario</code> en Mistorneos y arma el mapa pareja↔jugadores.</p>
            <p class="mb-2"><strong>Archivo 2 — Resultados:</strong> <code>partida</code>, <code>mesa</code>, <code>secuencia</code>, <code>resultado1</code>, <code>resultado2</code>. Identificación del jugador, en este orden:</p>
            <ol class="small">
                <li>Si trae <code>id_usuario</code> o <code>cédula</code>, se usa (la homologación refuerza cédulas iguales al archivo 1).</li>
                <li>Si solo trae <code>pareja</code> + <code>jugador</code> (1 = primer integrante en el archivo 1, 2 = segundo…), se asigna según el orden de filas del archivo 1 para esa pareja.</li>
            </ol>
            <div class="alert alert-danger small mb-3"><strong>Vaciar partiresul:</strong> borra resultados previos de este torneo antes de insertar.</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::token()) ?>">
                <input type="hidden" name="accion" value="fase2_dual">
                <input type="hidden" name="torneo_id" value="<?= (int)$torneo_id_sel ?>">
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Archivo 1 — Homologación (pareja + cédula)</label>
                        <input type="file" name="archivo_homologacion" class="form-control" accept=".xlsx,.csv,.txt" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Archivo 2 — Resultados (mesa / ronda / puntos)</label>
                        <input type="file" name="archivo_resultados" class="form-control" accept=".xlsx,.csv,.txt" required>
                    </div>
                </div>
                <div class="mb-2 form-check">
                    <input type="checkbox" class="form-check-input" name="reemplazar_partiresul_dual" value="1" id="rep2">
                    <label class="form-check-label" for="rep2">Vaciar <code>partiresul</code> de este torneo antes de importar</label>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-bolt"></i> Homologar e importar</button>
            </form>
        </div>
    </div>

    <div class="card mb-4 shadow-sm border-warning">
        <div class="card-header bg-warning text-dark fw-bold">Opcional — Solo archivo de resultados (ya con id_usuario o cédula por fila)</div>
        <div class="card-body">
            <p class="small">Si el archivo 2 ya incluye <code>id_usuario</code> o <code>cédula</code> por fila, puede usar solo este formulario sin el archivo de homologación.</p>
            <div class="alert alert-danger small"><strong>Vaciar partiresul:</strong> borra resultados previos de este torneo antes de insertar.</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::token()) ?>">
                <input type="hidden" name="accion" value="fase2">
                <input type="hidden" name="torneo_id" value="<?= (int)$torneo_id_sel ?>">
                <div class="mb-2 form-check">
                    <input type="checkbox" class="form-check-input" name="reemplazar_partiresul" value="1" id="rep">
                    <label class="form-check-label" for="rep">Vaciar <code>partiresul</code> de este torneo antes de importar</label>
                </div>
                <div class="mb-2">
                    <input type="file" name="archivo_resultados" class="form-control" accept=".xlsx,.csv,.txt" required>
                </div>
                <button type="submit" class="btn btn-warning"><i class="fas fa-database"></i> Importar solo resultados</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-secondary"><i class="fas fa-arrow-up"></i> Elija un torneo arriba para habilitar la <strong>carga masiva</strong> y la <strong>importación a partiresul</strong>.</div>
    <?php endif; ?>
</div>
