<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/CargaMasivaParejasSitioService.php';
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php'], true);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$sep = $use_standalone ? '?' : '&';
$torneo = $torneo ?? [];
$torneo_id = (int)($torneo_id ?? $torneo['id'] ?? 0);
$locked = (int)($torneo['locked'] ?? 0) === 1;
$clubes_disponibles = $clubes_disponibles ?? [];
$post_validar = $base_url . ($use_standalone ? '?' : '&') . 'action=carga_masiva_parejas_validar&torneo_id=' . $torneo_id;
$post_ejecutar = $base_url . ($use_standalone ? '?' : '&') . 'action=carga_masiva_parejas_sitio&torneo_id=' . $torneo_id;
$href_plantilla = $base_url . ($use_standalone ? '?' : '&') . 'action=carga_masiva_parejas_plantilla&torneo_id=' . $torneo_id;
$href_inicio = $use_standalone ? ($base_url . '?action=index') : 'index.php?page=torneo_gestion&action=index';
$frase = CargaMasivaParejasSitioService::CONFIRMACION_REEMPLAZO;
$cache_cleanup = $cache_cleanup ?? ['ok' => true, 'message' => '', 'archivos_eliminados' => 0];
?>
<style>
    .cm-ayuda-card {
        border: 1px solid #dee2e6;
        border-radius: 12px;
        box-shadow: 0 4px 18px rgba(0,0,0,.08);
        overflow: hidden;
        max-width: 920px;
        font-size: 1rem;
        line-height: 1.55;
    }
    .cm-ayuda-card .cm-head {
        background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
        color: #fff;
        padding: 1.1rem 1.35rem;
        font-weight: 600;
        font-size: 1.15rem;
        letter-spacing: .02em;
    }
    .cm-ayuda-card .cm-body { padding: 0; background: #f8fafc; }
    .cm-seccion {
        padding: 1.15rem 1.35rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .cm-seccion:last-child { border-bottom: 0; }
    .cm-seccion h3 {
        font-size: 1.05rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 .65rem 0;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .cm-seccion h3 i { color: #3b82f6; width: 1.35rem; text-align: center; }
    .cm-pasos { margin: 0; padding-left: 1.25rem; }
    .cm-pasos li { margin-bottom: .5rem; }
    .cm-advertencia {
        background: #fff5f5;
        border: 2px solid #f56565;
        border-radius: 8px;
        padding: 1rem 1.15rem;
        margin-top: .5rem;
    }
    .cm-advertencia .cm-adv-title {
        color: #c53030;
        font-weight: 800;
        font-size: 1.05rem;
        margin-bottom: .6rem;
        display: flex;
        align-items: center;
        gap: .45rem;
    }
    .cm-advertencia ul { margin: 0; padding-left: 1.2rem; color: #742a2a; }
    .cm-advertencia li { margin-bottom: .35rem; }
    .cm-info-box {
        background: #eff6ff;
        border-left: 4px solid #3b82f6;
        padding: .85rem 1rem;
        border-radius: 0 8px 8px 0;
        color: #1e3a8a;
    }
    .cm-formato dt { font-weight: 700; color: #334155; margin-top: .5rem; }
    .cm-formato dd { margin-left: 0; color: #475569; font-size: .95rem; }
    #panelValidacion.cm-result-card,
    #resultadoCarga .cm-result-card {
        max-width: 920px;
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        box-shadow: 0 2px 12px rgba(0,0,0,.06);
    }
    #panelValidacion .cm-result-head { font-weight: 700; padding: .75rem 1.15rem; }
    #contenidoValidacion.cm-result-body,
    .cm-result-body { padding: 1rem 1.25rem; font-size: .98rem; }
    .cm-valid-ok { background: #f0fdf4; border-color: #86efac !important; }
    .cm-valid-err { background: #fffbeb; border-color: #fcd34d !important; }
    .cm-btn-home {
        position: fixed;
        right: 22px;
        bottom: 22px;
        z-index: 1200;
        border-radius: 999px;
        box-shadow: 0 6px 20px rgba(0,0,0,.24);
        padding: .7rem 1rem;
        font-weight: 700;
    }
</style>

<div class="container-fluid py-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($base_url . $sep . 'action=panel_equipos&torneo_id=' . $torneo_id) ?>">Panel equipos</a></li>
            <li class="breadcrumb-item active">Carga masiva parejas</li>
        </ol>
    </nav>
    <h1 class="h4 mb-2"><i class="fas fa-user-friends text-primary"></i> Carga masiva parejas — <?= htmlspecialchars((string)($torneo['nombre'] ?? '')) ?></h1>
    <?php
    require_once __DIR__ . '/../../lib/InscritosHelper.php';
    $contadores_inscripcion = InscritosHelper::contadoresResumenInscripcionTorneo(DB::pdo(), $torneo_id, (int) ($torneo['modalidad'] ?? 2));
    require __DIR__ . '/../../resources/views/partials/torneo_inscripcion_badges_bs5.php';
    ?>
    <div class="alert alert-info" style="max-width:920px">
        <strong>Contexto:</strong> Torneo <strong>#<?= (int)$torneo_id ?></strong> — <?= htmlspecialchars((string)($torneo['nombre'] ?? '')) ?>.
        Todas las parejas del archivo se inscribirán en el <strong>club</strong> que elija abajo (como en inscripción en sitio).
    </div>
    <div class="alert <?= !empty($cache_cleanup['ok']) ? 'alert-success' : 'alert-warning' ?>" style="max-width:920px">
        <strong>Limpieza previa de caché:</strong> <?= htmlspecialchars((string)($cache_cleanup['message'] ?? '')) ?>
        <?php if (isset($cache_cleanup['archivos_eliminados'])): ?>
            <span class="ml-1">(archivos eliminados: <strong><?= (int)$cache_cleanup['archivos_eliminados'] ?></strong>)</span>
        <?php endif; ?>
    </div>

    <?php if ($locked): ?>
        <div class="card border-secondary cm-ayuda-card" style="max-width:520px">
            <div class="cm-head bg-secondary">Torneo cerrado</div>
            <div class="p-3">No se puede usar la carga masiva mientras el torneo esté cerrado.</div>
        </div>
    <?php else: ?>

        <div class="card cm-ayuda-card mb-4">
            <div class="cm-head">
                <i class="fas fa-info-circle mr-2"></i> Formato Excel y reglas
            </div>
            <div class="cm-body">
                <div class="cm-seccion">
                    <h3><i class="fas fa-list-ol"></i> Estructura del archivo</h3>
                    <ol class="cm-pasos">
                        <li><strong>Fila 1 (opcional):</strong> título o texto libre.</li>
                        <li><strong>Fila de encabezados:</strong> columnas reconocibles, por ejemplo:
                            <code>Número</code>, <code>Nombre del equipo</code>, <code>Nombre y Apellido</code>, <code>nacionalidad</code>, <code>Ficha</code> (cédula), <code>Número de telefono</code>.
                        </li>
                        <li><strong>Datos:</strong> cada pareja ocupa <strong>2 filas seguidas</strong>. La primera: número de pareja (columna «Número» o «Equipo/pareja»), <strong>nombre del equipo</strong> y datos del jugador 1. La segunda: celdas de número y nombre de equipo vacías (o repetidas por fusión en Excel) y solo los datos del jugador 2.</li>
                        <li><strong>Filas vacías</strong> entre parejas se ignoran automáticamente. En .xlsx se usa la <strong>primera hoja</strong> que tenga cabeceras reconocibles (Ficha/Cédula, Nacionalidad, Nombre del equipo, Nombre del jugador).</li>
                    </ol>
                    <div class="cm-info-box mt-2 mb-0">
                        <strong>Nacionalidad <code>B</code>:</strong> solo se consulta la base local de usuarios; si no existe, se crea el usuario con los datos de la hoja (sin base externa).
                        <strong>V, E, J, P:</strong> usuarios locales, luego base externa de personas (si está configurada), en línea con la inscripción en sitio; si no hay registro externo, se usa la fila del Excel.
                    </div>
                </div>
                <div class="cm-seccion" style="background:#fff;">
                    <h3><i class="fas fa-exclamation-triangle"></i> Al ejecutar</h3>
                    <div class="cm-advertencia">
                        <div class="cm-adv-title"><i class="fas fa-bomb"></i> Se eliminarán inscripciones y equipos/parejas actuales del torneo</div>
                        <ul>
                            <li>Se borran todos los <strong>inscritos</strong> de este torneo.</li>
                            <li>Se borran todos los <strong>equipos</strong> (parejas) del torneo.</li>
                            <li>Luego se crean solo las parejas del archivo, todas en el club seleccionado.</li>
                        </ul>
                    </div>
                </div>
                <div class="cm-seccion">
                    <dl class="cm-formato mb-0">
                        <dt>Archivos</dt>
                        <dd><strong>.xlsx</strong>, <strong>.csv</strong> o <strong>.txt</strong> (tabuladores). Texto normalizado a UTF-8 (NFC) como en la carga de equipos.</dd>
                        <dt>Reglas</dt>
                        <dd>Sin cédulas duplicadas en el archivo. Cada pareja: 2 jugadores válidos (nombre, nacionalidad B/V/E/J/P, ficha mín. 4 dígitos).</dd>
                    </dl>
                    <a class="btn btn-primary btn-sm mt-3" href="<?= htmlspecialchars($href_plantilla) ?>"><i class="fas fa-download"></i> Descargar plantilla CSV</a>
                </div>
            </div>
        </div>

        <div class="card mb-3" style="max-width:920px">
            <div class="card-header font-weight-bold"><i class="fas fa-upload mr-1"></i> Club, archivo y validación</div>
            <div class="card-body">
                <form id="formCargaMasivaParejas" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="carga_masiva_parejas_validar">
                    <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::token()) ?>">
                    <div class="form-group">
                        <label for="club_id" class="font-weight-bold">Club *</label>
                        <select class="form-control" name="club_id" id="club_id" required>
                            <option value="">Seleccione club…</option>
                            <?php foreach ($clubes_disponibles as $c): ?>
                                <option value="<?= (int)($c['id'] ?? 0) ?>"><?= htmlspecialchars((string)($c['nombre'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Todas las parejas del archivo quedarán bajo este club (mismo criterio que inscribir en sitio).</small>
                    </div>
                    <div class="form-group">
                        <label for="archivo" class="font-weight-bold">Archivo</label>
                        <input type="file" class="form-control-file" name="archivo" id="archivo" accept=".csv,.txt,.xlsx,.xls" required>
                    </div>
                    <button type="button" id="btnValidar" class="btn btn-primary btn-lg"><i class="fas fa-check-circle"></i> Paso 1 — Validar archivo</button>
                    <a href="<?= htmlspecialchars($base_url . $sep . 'action=inscribir_equipo_sitio&torneo_id=' . $torneo_id . '&abrir_form=1') ?>" class="btn btn-outline-secondary">Volver a inscripción en sitio</a>
                </form>
            </div>
        </div>

        <div id="panelValidacion" class="d-none card mb-3 cm-result-card">
            <div class="cm-result-head bg-light border-bottom" id="panelValidacionTitulo">Resultado de la validación</div>
            <div class="cm-result-body" id="contenidoValidacion"></div>
        </div>

        <div id="panelEjecutar" class="d-none card mb-3 cm-result-card border-danger">
            <div class="cm-result-head bg-danger text-white">Paso 2 — Confirmar y ejecutar</div>
            <div class="cm-result-body">
                <p class="mb-2">Solo si la validación fue correcta. Escriba <strong>exactamente</strong> esta frase:</p>
                <code class="d-block p-3 bg-dark text-warning mb-3 rounded user-select-all" style="font-size:.95rem;word-break:break-all"><?= htmlspecialchars($frase) ?></code>
                <div class="form-group">
                    <label for="confirmar_reemplazo" class="font-weight-bold">Frase de confirmación</label>
                    <input type="text" class="form-control form-control-lg" id="confirmar_reemplazo" name="confirmar_reemplazo" autocomplete="off" placeholder="Pegue aquí la frase">
                </div>
                <button type="button" id="btnEjecutar" class="btn btn-danger btn-lg"><i class="fas fa-bolt"></i> Paso 2 — Borrar e importar parejas</button>
            </div>
        </div>

        <div id="resultadoCarga" class="mb-4" style="max-width:920px"></div>
    <?php endif; ?>
</div>
<a href="<?= htmlspecialchars($href_inicio) ?>" class="btn btn-dark cm-btn-home" title="Ir al inicio">
    <i class="fas fa-home"></i> Inicio
</a>
<script>
(function () {
    var form = document.getElementById('formCargaMasivaParejas');
    var archivoInput = document.getElementById('archivo');
    var clubSelect = document.getElementById('club_id');
    var postValidar = <?= json_encode($post_validar) ?>;
    var postEjecutar = <?= json_encode($post_ejecutar) ?>;
    var torneoId = <?= (int)$torneo_id ?>;
    var csrf = form ? form.querySelector('input[name="csrf_token"]').value : '';
    var hiddenTid = form ? parseInt((form.querySelector('input[name="torneo_id"]') || {}).value || '0', 10) : 0;

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function clubIdVal() {
        return clubSelect ? parseInt(clubSelect.value || '0', 10) : 0;
    }

    document.getElementById('btnValidar').addEventListener('click', function () {
        if (hiddenTid !== torneoId) { alert('Contexto inválido de torneo. Recargue la pantalla.'); return; }
        if (clubIdVal() <= 0) { alert('Seleccione el club.'); return; }
        if (!archivoInput.files.length) { alert('Seleccione un archivo.'); return; }
        var fd = new FormData();
        fd.append('action', 'carga_masiva_parejas_validar');
        fd.append('torneo_id', torneoId);
        fd.append('club_id', String(clubIdVal()));
        fd.append('csrf_token', csrf);
        fd.append('archivo', archivoInput.files[0]);
        var panel = document.getElementById('panelValidacion');
        var titulo = document.getElementById('panelValidacionTitulo');
        var cont = document.getElementById('contenidoValidacion');
        var panelEj = document.getElementById('panelEjecutar');
        panel.classList.remove('d-none', 'cm-valid-ok', 'cm-valid-err');
        panelEj.classList.add('d-none');
        titulo.textContent = 'Validando archivo…';
        cont.innerHTML = '<p class="text-muted mb-0"><i class="fas fa-spinner fa-spin"></i> Analizando…</p>';
        fetch(postValidar, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var v = data.validacion || {};
                var rsum = v.resumen || {};
                var html = '';
                html += '<div class="cm-info-box mb-3"><strong>Resumen</strong><br>';
                html += '• Inscritos actuales: <strong>' + (rsum.total_inscritos_torneo || 0) + '</strong><br>';
                html += '• Equipos/parejas actuales: <strong>' + (rsum.total_equipos_torneo || 0) + '</strong><br>';
                html += '• Parejas en archivo: <strong>' + (rsum.equipos_en_archivo || 0) + '</strong></div>';
                if (v.cedulas_duplicadas && v.cedulas_duplicadas.length) {
                    html += '<div class="alert alert-danger border-0"><strong>Cédulas repetidas</strong><ul class="mb-0 mt-2">';
                    v.cedulas_duplicadas.forEach(function (d) {
                        html += '<li><code>' + esc(d.cedula) + '</code> — ';
                        (d.apariciones || []).forEach(function (a) {
                            html += '«' + esc(a.equipo) + '» (línea ~' + esc(String(a.linea)) + '); ';
                        });
                        html += '</li>';
                    });
                    html += '</ul></div>';
                }
                if (v.equipos_incompletos && v.equipos_incompletos.length) {
                    html += '<div class="alert alert-warning border-0 text-dark"><strong>Parejas incompletas</strong> — Cada pareja requiere 2 jugadores válidos.<ul class="mb-0 mt-2">';
                    v.equipos_incompletos.forEach(function (e) {
                        html += '<li><strong>' + esc(e.equipo) + '</strong> (línea ~' + esc(String(e.linea_inicio)) + '): ' + esc(e.detalle || '') + '</li>';
                    });
                    html += '</ul></div>';
                }
                if (v.bloques_sin_r && v.bloques_sin_r.length) {
                    html += '<div class="alert alert-secondary border-0"><ul class="mb-0">';
                    v.bloques_sin_r.forEach(function (b) { html += '<li>' + esc(b) + '</li>'; });
                    html += '</ul></div>';
                }
                if (v.clubs_excel_invalidos && v.clubs_excel_invalidos.length) {
                    html += '<div class="alert alert-danger border-0"><strong>Club</strong><ul class="mb-0 mt-2">';
                    v.clubs_excel_invalidos.forEach(function (c) {
                        html += '<li>' + esc(c.detalle || '') + '</li>';
                    });
                    html += '</ul></div>';
                }
                if (v.reporte_detallado && v.reporte_detallado.equipos && v.reporte_detallado.equipos.length) {
                    html += '<div class="alert alert-light border"><strong>Por pareja</strong><div class="table-responsive mt-2"><table class="table table-sm table-bordered bg-white"><thead><tr><th>Pareja</th><th>Jugadores</th><th>Estado</th></tr></thead><tbody>';
                    v.reporte_detallado.equipos.forEach(function (eq) {
                        var integrantes = (eq.integrantes || []).map(function (j) {
                            return esc((j.cedula || 'S/C') + ' — ' + (j.nombre || '') + ' [' + esc(j.nacionalidad || '') + ']');
                        }).join('<br>');
                        html += '<tr><td><strong>' + esc(eq.equipo || '') + '</strong><br><small>Línea ' + esc(String(eq.linea_inicio || 0)) + '</small></td><td>' + integrantes + '</td><td>' + (eq.ok ? '<span class="text-success">OK</span>' : '<span class="text-danger">Errores</span>') + '</td></tr>';
                    });
                    html += '</tbody></table></div></div>';
                }
                if (data.success) {
                    titulo.textContent = 'Validación correcta';
                    panel.classList.add('cm-valid-ok');
                    html += '<div class="alert alert-success border-0 mb-0"><strong>Archivo válido.</strong> ' + esc(data.message || '') + '</div>';
                    panelEj.classList.remove('d-none');
                } else {
                    titulo.textContent = 'Validación con errores';
                    panel.classList.add('cm-valid-err');
                    html += '<div class="alert alert-warning border-0 mb-0 text-dark"><strong>Corrija el archivo o el club.</strong> ' + esc(data.message || '') + '</div>';
                }
                cont.innerHTML = html;
            })
            .catch(function () {
                titulo.textContent = 'Error al validar';
                cont.innerHTML = '<div class="alert alert-danger mb-0">No se pudo validar. Recargue e intente de nuevo.</div>';
            });
    });

    document.getElementById('btnEjecutar').addEventListener('click', function () {
        if (hiddenTid !== torneoId) { alert('Contexto inválido. Recargue.'); return; }
        if (clubIdVal() <= 0) { alert('Seleccione el club.'); return; }
        if (!archivoInput.files.length) { alert('Seleccione de nuevo el archivo.'); return; }
        var confirmTxt = (document.getElementById('confirmar_reemplazo').value || '').trim();
        var fd = new FormData();
        fd.append('action', 'carga_masiva_parejas_sitio');
        fd.append('torneo_id', torneoId);
        fd.append('club_id', String(clubIdVal()));
        fd.append('csrf_token', csrf);
        fd.append('confirmar_reemplazo', confirmTxt);
        fd.append('archivo', archivoInput.files[0]);
        var out = document.getElementById('resultadoCarga');
        out.innerHTML = '<div class="card cm-result-card"><div class="cm-result-body"><i class="fas fa-spinner fa-spin"></i> Ejecutando…</div></div>';
        fetch(postEjecutar, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var ok = data.success;
                var headBg = ok ? 'bg-success text-white' : 'bg-danger text-white';
                var html = '<div class="card cm-result-card border-' + (ok ? 'success' : 'danger') + '">';
                html += '<div class="cm-result-head ' + headBg + '">' + (ok ? 'Carga finalizada' : 'Carga no completada') + '</div>';
                html += '<div class="cm-result-body"><p class="lead">' + esc(data.message || '') + '</p>';
                if (data.detalles && data.detalles.length) {
                    html += '<table class="table table-sm table-bordered"><thead><tr><th>Pareja</th><th>Estado</th><th>Mensaje</th></tr></thead><tbody>';
                    data.detalles.forEach(function (d) {
                        html += '<tr><td>' + esc(d.equipo) + '</td><td>' + (d.ok ? 'OK' : 'Error') + '</td><td>' + esc(d.message) + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }
                if (data.reporte_pdf_url) {
                    html += '<a class="btn btn-outline-primary mt-2" target="_blank" rel="noopener" href="' + esc(data.reporte_pdf_url) + '"><i class="fas fa-file-pdf"></i> PDF del reporte</a>';
                }
                html += '</div></div>';
                out.innerHTML = html;
            })
            .catch(function () {
                out.innerHTML = '<div class="alert alert-danger">Error de red.</div>';
            });
    });
})();
</script>
