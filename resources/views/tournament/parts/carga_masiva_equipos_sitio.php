<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/CargaMasivaEquiposSitioService.php';
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php'], true);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$sep = $use_standalone ? '?' : '&';
$torneo = $torneo ?? [];
$torneo_id = (int)($torneo_id ?? $torneo['id'] ?? 0);
$locked = (int)($torneo['locked'] ?? 0) === 1;
$post_validar = $base_url . ($use_standalone ? '?' : '&') . 'action=carga_masiva_equipos_validar&torneo_id=' . $torneo_id;
$post_ejecutar = $base_url . ($use_standalone ? '?' : '&') . 'action=carga_masiva_equipos_sitio&torneo_id=' . $torneo_id;
$href_plantilla = $base_url . ($use_standalone ? '?' : '&') . 'action=carga_masiva_equipos_plantilla&torneo_id=' . $torneo_id;
$href_inicio = $use_standalone ? ($base_url . '?action=index') : 'index.php?page=torneo_gestion&action=index';
$frase = CargaMasivaEquiposSitioService::CONFIRMACION_REEMPLAZO;
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
            <li class="breadcrumb-item active">Carga automática de equipos</li>
        </ol>
    </nav>
    <h1 class="h4 mb-3"><i class="fas fa-users-cog text-primary"></i> Carga automática — <?= htmlspecialchars((string)($torneo['nombre'] ?? '')) ?></h1>
    <div class="alert alert-info" style="max-width:920px">
        <strong>Contexto bloqueado de operación:</strong> Torneo <strong>#<?= (int)$torneo_id ?></strong> — <?= htmlspecialchars((string)($torneo['nombre'] ?? '')) ?>.
        Para evitar cruces entre torneos, esta pantalla solo ejecuta acciones sobre este torneo.
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
            <div class="p-3">No se puede usar la carga automática mientras el torneo esté cerrado.</div>
        </div>
    <?php else: ?>

        <!-- Tarjeta única: información + advertencias + formato -->
        <div class="card cm-ayuda-card mb-4">
            <div class="cm-head">
                <i class="fas fa-info-circle mr-2"></i> Guía del proceso de carga automática
            </div>
            <div class="cm-body">

                <div class="cm-seccion">
                    <h3><i class="fas fa-list-ol"></i> Cómo funciona (2 pasos)</h3>
                    <ol class="cm-pasos">
                        <li><strong>Validar archivo:</strong> el sistema revisa el archivo <em>sin modificar</em> el torneo. Comprueba que no haya cédulas duplicadas, que cada equipo tenga exactamente 4 jugadores (cédula + nombre) y que el formato sea legible.</li>
                        <li><strong>Ejecutar carga:</strong> solo si la validación es correcta. Debe escribir la frase de confirmación. Entonces se borra lo anterior del torneo y se vuelve a crear todo según el archivo (igual que inscribir en sitio, pero masivo).</li>
                    </ol>
                    <div class="cm-info-box mt-2 mb-0">
                        <strong>Importante:</strong> hasta que no pulse «Ejecutar» y confirme con la frase, <strong>no se borra ni se guarda nada</strong> en inscripciones ni equipos.
                    </div>
                </div>

                <div class="cm-seccion" style="background:#fff;">
                    <h3><i class="fas fa-exclamation-triangle"></i> Advertencia antes de ejecutar</h3>
                    <div class="cm-advertencia">
                        <div class="cm-adv-title"><i class="fas fa-bomb"></i> Al ejecutar la carga se hará lo siguiente</div>
                        <ul>
                            <li>Se <strong>eliminarán todos los inscritos</strong> de este torneo (tabla de inscripciones).</li>
                            <li>Se <strong>eliminarán todos los equipos</strong> registrados en este torneo.</li>
                            <li>Después se crearán <strong>solo</strong> los equipos y jugadores que vengan en su archivo.</li>
                        </ul>
                        <p class="mb-0 mt-2 small font-weight-bold text-danger">Si ya había rondas o datos que dependen de esos inscritos, evalúe el impacto antes de continuar.</p>
                    </div>
                </div>

                <div class="cm-seccion">
                    <h3><i class="fas fa-file-alt"></i> Archivos y formatos aceptados</h3>
                    <dl class="cm-formato mb-0">
                        <dt>Tipos de archivo</dt>
                        <dd><strong>Excel</strong> (.xlsx, .xls), <strong>CSV</strong>, o <strong>texto separado por tabuladores</strong> (.txt). Puede descargar una plantilla CSV de ejemplo.</dd>
                        <dt>Formato tipo ADEAZ (tabuladores)</dt>
                        <dd>Primera columna <code>0</code> y en la segunda el <strong>nombre del equipo</strong>; debajo, 4 filas con <strong>cédula</strong> y <strong>nombre</strong> por jugador. La columna <code>club</code> debe ser el <strong>id del club</strong> en <code>clubes</code> (no código de entidad); ese valor numérico también sirve como prefijo del código de equipo cuando aplica.</dd>
                        <dt>Formato alternativo (plantilla CSV)</dt>
                        <dd>Fila con <code>NAC=R</code>, nombre del equipo, club y organización; luego 4 filas de jugadores con cédula y N1.</dd>
                        <dt>Reglas que debe cumplir el archivo</dt>
                        <dd>Sin cédulas repetidas en todo el archivo. Cada equipo: <strong>exactamente 4</strong> integrantes con cédula y nombre. El valor de <code>club</code> debe ser un <strong>id de club</strong> existente y activo en <code>clubes</code>.</dd>
                    </dl>
                    <a class="btn btn-primary btn-sm mt-3" href="<?= htmlspecialchars($href_plantilla) ?>"><i class="fas fa-download"></i> Descargar plantilla CSV</a>
                </div>
            </div>
        </div>

        <div class="card mb-3" style="max-width:920px">
            <div class="card-header font-weight-bold"><i class="fas fa-upload mr-1"></i> Subir archivo y validar</div>
            <div class="card-body">
                <form id="formCargaMasiva" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="carga_masiva_equipos_validar">
                    <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::token()) ?>">
                    <div class="form-group">
                        <label for="archivo" class="font-weight-bold">Archivo</label>
                        <input type="file" class="form-control-file" name="archivo" id="archivo" accept=".csv,.txt,.xlsx,.xls" required>
                        <small class="form-text text-muted">Seleccione el mismo archivo que usará luego al ejecutar (p. ej. exportación ADEAZ o Excel).</small>
                    </div>
                    <button type="button" id="btnValidar" class="btn btn-primary btn-lg"><i class="fas fa-check-circle"></i> Paso 1 — Validar archivo</button>
                    <a href="<?= htmlspecialchars($base_url . $sep . 'action=inscribir_equipo_sitio&torneo_id=' . $torneo_id) ?>" class="btn btn-outline-secondary">Volver a inscripción en sitio</a>
                </form>
            </div>
        </div>

        <div id="panelValidacion" class="d-none card mb-3 cm-result-card">
            <div class="cm-result-head bg-light border-bottom" id="panelValidacionTitulo">Resultado de la validación</div>
            <div class="cm-result-body" id="contenidoValidacion"></div>
        </div>

        <div id="panelEjecutar" class="d-none card mb-3 cm-result-card border-danger">
            <div class="cm-result-head bg-danger text-white">Paso 2 — Confirmar y ejecutar carga</div>
            <div class="cm-result-body">
                <p class="mb-2">Solo si arriba indicó que el archivo es <strong>válido</strong>. Escriba <strong>exactamente</strong> esta frase (mayúsculas y guiones bajos):</p>
                <code class="d-block p-3 bg-dark text-warning mb-3 rounded user-select-all" style="font-size:.95rem;word-break:break-all"><?= htmlspecialchars($frase) ?></code>
                <div class="form-group">
                    <label for="confirmar_reemplazo" class="font-weight-bold">Frase de confirmación</label>
                    <input type="text" class="form-control form-control-lg" id="confirmar_reemplazo" name="confirmar_reemplazo" autocomplete="off" placeholder="Pegue aquí la frase">
                </div>
                <button type="button" id="btnEjecutar" class="btn btn-danger btn-lg"><i class="fas fa-bolt"></i> Paso 2 — Borrar datos anteriores y cargar equipos</button>
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
    var form = document.getElementById('formCargaMasiva');
    var archivoInput = document.getElementById('archivo');
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

    document.getElementById('btnValidar').addEventListener('click', function () {
        if (hiddenTid !== torneoId) { alert('Contexto inválido de torneo. Recargue la pantalla.'); return; }
        if (!archivoInput.files.length) { alert('Seleccione un archivo.'); return; }
        var fd = new FormData();
        fd.append('action', 'carga_masiva_equipos_validar');
        fd.append('torneo_id', torneoId);
        fd.append('csrf_token', csrf);
        fd.append('archivo', archivoInput.files[0]);
        var panel = document.getElementById('panelValidacion');
        var titulo = document.getElementById('panelValidacionTitulo');
        var cont = document.getElementById('contenidoValidacion');
        var panelEj = document.getElementById('panelEjecutar');
        panel.classList.remove('d-none', 'cm-valid-ok', 'cm-valid-err');
        panelEj.classList.add('d-none');
        titulo.textContent = 'Validando archivo…';
        cont.innerHTML = '<p class="text-muted mb-0"><i class="fas fa-spinner fa-spin"></i> Analizando, espere un momento.</p>';
        fetch(postValidar, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var v = data.validacion || {};
                var rsum = v.resumen || {};
                var html = '';

                html += '<div class="cm-info-box mb-3"><strong>Resumen del torneo y del archivo</strong><br>';
                html += '• Inscritos actuales en este torneo: <strong>' + (rsum.total_inscritos_torneo || 0) + '</strong><br>';
                html += '• Equipos actuales en este torneo: <strong>' + (rsum.total_equipos_torneo || 0) + '</strong><br>';
                html += '• Equipos detectados en el archivo: <strong>' + (rsum.equipos_en_archivo || 0) + '</strong></div>';

                if (v.cedulas_duplicadas && v.cedulas_duplicadas.length) {
                    html += '<div class="alert alert-danger border-0" style="font-size:1rem"><strong><i class="fas fa-clone"></i> Cédulas repetidas</strong> — Corrija el archivo; no puede haber la misma cédula en dos equipos.<ul class="mb-0 mt-2">';
                    v.cedulas_duplicadas.forEach(function (d) {
                        html += '<li>Cédula <code>' + esc(d.cedula) + '</code> — aparece en: ';
                        (d.apariciones || []).forEach(function (a) {
                            html += '«' + esc(a.equipo) + '» (aprox. línea ' + esc(String(a.linea)) + '); ';
                        });
                        html += '</li>';
                    });
                    html += '</ul></div>';
                }
                if (v.equipos_incompletos && v.equipos_incompletos.length) {
                    html += '<div class="alert alert-warning border-0 text-dark" style="font-size:1rem"><strong><i class="fas fa-user-times"></i> Equipos incompletos</strong> — Cada equipo debe tener 4 jugadores con cédula y nombre.<ul class="mb-0 mt-2">';
                    v.equipos_incompletos.forEach(function (e) {
                        html += '<li><strong>' + esc(e.equipo) + '</strong> (cabecera ~línea ' + esc(String(e.linea_inicio)) + '): tiene ' +
                            (e.integrantes || 0) + ' de ' + (e.requeridos || 4) + ' jugadores. ' + esc(e.detalle || '') + '</li>';
                    });
                    html += '</ul></div>';
                }
                if (v.bloques_sin_r && v.bloques_sin_r.length) {
                    html += '<div class="alert alert-secondary border-0"><strong>Formato / estructura</strong><ul class="mb-0">';
                    v.bloques_sin_r.forEach(function (b) { html += '<li>' + esc(b) + '</li>'; });
                    html += '</ul></div>';
                }
                if (v.clubs_excel_invalidos && v.clubs_excel_invalidos.length) {
                    html += '<div class="alert alert-danger border-0" style="font-size:1rem"><strong><i class="fas fa-id-badge"></i> Columna club</strong> — Debe ser el <strong>id numérico del club</strong> en la tabla <code>clubes</code> (club activo).<ul class="mb-0 mt-2">';
                    v.clubs_excel_invalidos.forEach(function (c) {
                        html += '<li><strong>' + esc(c.equipo) + '</strong> (cabecera ~línea ' + esc(String(c.linea_inicio)) + '): ' + esc(c.detalle || '') + '</li>';
                    });
                    html += '</ul></div>';
                }
                if (v.reporte_detallado && v.reporte_detallado.equipos && v.reporte_detallado.equipos.length) {
                    html += '<div class="alert alert-light border"><strong>Reporte de validación por equipo</strong><div class="table-responsive mt-2"><table class="table table-sm table-bordered bg-white"><thead class="thead-light"><tr><th>Equipo</th><th>Integrantes</th><th>Estado</th><th>Errores y solución</th></tr></thead><tbody>';
                    v.reporte_detallado.equipos.forEach(function (eq) {
                        var integrantes = (eq.integrantes || []).map(function (j) {
                            var nom = (j.nombre || '').trim();
                            var ced = (j.cedula || '').trim();
                            var idu = (j.id_usuario || 0);
                            var nf = (j.numfvd || 0);
                            return esc((ced || 'S/C') + ' - ' + (nom || 'SIN NOMBRE')) + ' <small class="text-muted">[id_usuario: ' + esc(String(idu)) + ' | numfvd: ' + esc(String(nf)) + ']</small>' + (j.completo ? '' : ' <span class="text-danger">(incompleto)</span>');
                        }).join('<br>');
                        var errores = (eq.errores || []).map(function (er) {
                            return '<div><strong>' + esc(er.detalle || '') + '</strong><br><small class="text-muted">Cómo resolver: ' + esc(er.como_resolver || '') + '</small></div>';
                        }).join('<hr class="my-1">');
                        html += '<tr><td><strong>' + esc(eq.equipo || '') + '</strong><br><small>Línea ' + esc(String(eq.linea_inicio || 0)) + '</small></td><td>' + (integrantes || '<span class="text-muted">Sin integrantes</span>') + '</td><td>' + (eq.ok ? '<span class="text-success font-weight-bold">OK</span>' : '<span class="text-danger font-weight-bold">Con errores</span>') + '</td><td>' + (errores || '<span class="text-success">Sin errores</span>') + '</td></tr>';
                    });
                    html += '</tbody></table></div></div>';
                }

                if (data.success) {
                    titulo.textContent = 'Validación correcta — puede pasar al paso 2';
                    panel.classList.add('cm-valid-ok');
                    html += '<div class="alert alert-success border-0 mb-0" style="font-size:1.05rem"><i class="fas fa-check-circle"></i> <strong>El archivo es válido.</strong> ' + esc(data.message || '') + ' Revise el recuadro rojo de la guía: al ejecutar se borrarán inscritos y equipos actuales.</div>';
                    panelEj.classList.remove('d-none');
                } else {
                    titulo.textContent = 'Validación con errores — corrija el archivo';
                    panel.classList.add('cm-valid-err');
                    html += '<div class="alert alert-warning border-0 mb-0 text-dark" style="font-size:1.05rem"><i class="fas fa-exclamation-circle"></i> <strong>No puede ejecutar la carga</strong> hasta corregir lo indicado arriba. ' + esc(data.message || '') + '</div>';
                }
                cont.innerHTML = html;
            })
            .catch(function () {
                titulo.textContent = 'Error al validar';
                cont.innerHTML = '<div class="alert alert-danger mb-0">No se pudo contactar al servidor o la respuesta no es válida. Recargue e intente de nuevo.</div>';
            });
    });

    document.getElementById('btnEjecutar').addEventListener('click', function () {
        if (hiddenTid !== torneoId) { alert('Contexto inválido de torneo. Recargue la pantalla.'); return; }
        if (!archivoInput.files.length) { alert('Seleccione de nuevo el archivo validado.'); return; }
        var confirmTxt = (document.getElementById('confirmar_reemplazo').value || '').trim();
        var fd = new FormData();
        fd.append('action', 'carga_masiva_equipos_sitio');
        fd.append('torneo_id', torneoId);
        fd.append('csrf_token', csrf);
        fd.append('confirmar_reemplazo', confirmTxt);
        fd.append('archivo', archivoInput.files[0]);
        var out = document.getElementById('resultadoCarga');
        out.innerHTML = '<div class="card cm-result-card"><div class="cm-result-body"><i class="fas fa-spinner fa-spin"></i> Ejecutando: borrando inscripciones y equipos del torneo y cargando el archivo…</div></div>';
        fetch(postEjecutar, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var ok = data.success;
                var headBg = ok ? 'bg-success text-white' : 'bg-danger text-white';
                var headTxt = ok ? 'Carga finalizada correctamente' : 'La carga terminó con errores o no se ejecutó';
                var html = '<div class="card cm-result-card border-' + (ok ? 'success' : 'danger') + '">';
                html += '<div class="cm-result-head ' + headBg + '">' + headTxt + '</div>';
                html += '<div class="cm-result-body">';
                html += '<p class="lead mb-3">' + esc(data.message || '') + '</p>';
                if (data.validacion && !data.success) {
                    html += '<p class="text-muted">Revise la validación y el archivo.</p>';
                }
                if (data.detalles && data.detalles.length) {
                    html += '<h6 class="font-weight-bold mt-2">Detalle por equipo</h6>';
                    html += '<div class="table-responsive"><table class="table table-bordered table-sm bg-white"><thead class="thead-light"><tr><th>Equipo</th><th>Estado</th><th>Mensaje</th></tr></thead><tbody>';
                    data.detalles.forEach(function (d) {
                        html += '<tr><td>' + esc(d.equipo) + '</td><td>' + (d.ok ? '<span class="text-success font-weight-bold">OK</span>' : '<span class="text-danger font-weight-bold">Error</span>') + '</td><td>' + esc(d.message) + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }
                if (data.reporte_proceso && data.reporte_proceso.equipos && data.reporte_proceso.equipos.length) {
                    html += '<h6 class="font-weight-bold mt-3">Reporte completo del proceso</h6>';
                    html += '<div class="table-responsive"><table class="table table-bordered table-sm bg-white"><thead class="thead-light"><tr><th>Equipo</th><th>Integrantes</th><th>Resultado</th><th>Error / Cómo resolver</th></tr></thead><tbody>';
                    data.reporte_proceso.equipos.forEach(function (eq) {
                        var integrantes = (eq.integrantes || []).map(function (j) {
                            var nom = (j.nombre || '').trim();
                            var ced = (j.cedula || '').trim();
                            var idu = (j.id_usuario || 0);
                            var nf = (j.numfvd || 0);
                            return esc((ced || 'S/C') + ' - ' + (nom || 'SIN NOMBRE')) + ' <small class="text-muted">[id_usuario: ' + esc(String(idu)) + ' | numfvd: ' + esc(String(nf)) + ']</small>' + (j.completo ? '' : ' <span class="text-danger">(incompleto)</span>');
                        }).join('<br>');
                        var solucion = eq.ok ? '<span class="text-success">Sin acciones pendientes</span>' : '<strong>' + esc(eq.error || '') + '</strong><br><small class="text-muted">Cómo resolver: ' + esc(eq.como_resolver || '') + '</small>';
                        html += '<tr><td><strong>' + esc(eq.equipo || '') + '</strong><br><small>Línea ' + esc(String(eq.linea_inicio || 0)) + '</small></td><td>' + (integrantes || '<span class="text-muted">Sin integrantes</span>') + '</td><td>' + (eq.ok ? '<span class="text-success font-weight-bold">OK</span>' : '<span class="text-danger font-weight-bold">Error</span>') + '</td><td>' + solucion + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }
                if (data.reporte_pdf_url) {
                    html += '<div class="mt-3"><a class="btn btn-outline-primary" target="_blank" rel="noopener" href="' + esc(data.reporte_pdf_url) + '"><i class="fas fa-file-pdf"></i> Descargar PDF del reporte</a></div>';
                }
                html += '</div></div>';
                out.innerHTML = html;
            })
            .catch(function () {
                out.innerHTML = '<div class="card cm-result-card border-danger"><div class="cm-result-head bg-danger text-white">Error de red</div><div class="cm-result-body">No se pudo completar la petición.</div></div>';
            });
    });
})();
</script>
