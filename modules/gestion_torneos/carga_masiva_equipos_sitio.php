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
$frase = CargaMasivaEquiposSitioService::CONFIRMACION_REEMPLAZO;
?>
<div class="container-fluid py-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($base_url . $sep . 'action=panel_equipos&torneo_id=' . $torneo_id) ?>">Panel equipos</a></li>
            <li class="breadcrumb-item active">Carga masiva</li>
        </ol>
    </nav>
    <h1 class="h4"><i class="fas fa-file-upload text-primary"></i> Carga masiva — <?= htmlspecialchars((string)($torneo['nombre'] ?? '')) ?></h1>

    <?php if ($locked): ?>
        <div class="alert alert-secondary">Torneo cerrado.</div>
    <?php else: ?>

        <div class="alert alert-danger">
            <strong><i class="fas fa-exclamation-triangle"></i> Antes de ejecutar la carga</strong>
            <ul class="mb-0 mt-2">
                <li>Se <strong>eliminarán todos los registros en <code>inscritos</code></strong> de este torneo.</li>
                <li>Se <strong>eliminarán todos los <code>equipos</code></strong> registrados en este torneo.</li>
                <li>Luego se crearán de nuevo solo lo que venga en el archivo (mismo criterio que inscripción en sitio).</li>
            </ul>
            <p class="mb-0 mt-2 small">No se ejecuta nada hasta que el archivo pase la validación y usted escriba la frase de confirmación.</p>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Plantilla</span>
                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($href_plantilla) ?>"><i class="fas fa-download"></i> Descargar CSV</a>
            </div>
            <div class="card-body small">
                <p class="mb-1">Cada equipo: fila <code>NAC=R</code> con <code>equipo</code>, <code>club</code>, <code>organizacion</code> (opcional).</p>
                <p class="mb-1">Justo debajo, <strong>exactamente 4 filas</strong> por equipo con <code>Cedula</code> + <code>N1</code> (nombre). Sin cédulas repetidas en todo el archivo.</p>
                <p class="mb-0">Si el club no existe en la organización del torneo, se asignará al club por defecto cuyo nombre es el <strong>código de la organización</strong> (o <code>ORG-{id}</code> si no hay columna código).</p>
            </div>
        </div>

        <form id="formCargaMasiva" class="card mb-3" enctype="multipart/form-data">
            <input type="hidden" name="action" value="carga_masiva_equipos_validar">
            <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::token()) ?>">
            <div class="card-body">
                <div class="form-group">
                    <label for="archivo">Archivo CSV o Excel</label>
                    <input type="file" class="form-control-file" name="archivo" id="archivo" accept=".csv,.xlsx,.xls" required>
                </div>
                <button type="button" id="btnValidar" class="btn btn-primary"><i class="fas fa-check-circle"></i> 1. Validar archivo</button>
                <a href="<?= htmlspecialchars($base_url . $sep . 'action=inscribir_equipo_sitio&torneo_id=' . $torneo_id) ?>" class="btn btn-outline-secondary">Volver</a>
            </div>
        </form>

        <div id="panelValidacion" class="d-none card border-warning mb-3">
            <div class="card-header bg-warning text-dark">Resultado de la validación</div>
            <div class="card-body" id="contenidoValidacion"></div>
        </div>

        <div id="panelEjecutar" class="d-none card border-danger mb-3">
            <div class="card-header bg-danger text-white">Ejecutar reemplazo total</div>
            <div class="card-body">
                <p>Solo si la validación fue correcta. Copie y pegue exactamente:</p>
                <code class="d-block p-2 bg-light mb-2 user-select-all"><?= htmlspecialchars($frase) ?></code>
                <div class="form-group">
                    <label for="confirmar_reemplazo">Frase de confirmación</label>
                    <input type="text" class="form-control" id="confirmar_reemplazo" name="confirmar_reemplazo" autocomplete="off" placeholder="<?= htmlspecialchars($frase) ?>">
                </div>
                <button type="button" id="btnEjecutar" class="btn btn-danger"><i class="fas fa-bolt"></i> 2. Borrar inscritos/equipos y cargar</button>
            </div>
        </div>

        <div id="resultadoCarga" class="mt-3"></div>
    <?php endif; ?>
</div>
<script>
(function () {
    var form = document.getElementById('formCargaMasiva');
    var archivoInput = document.getElementById('archivo');
    var postValidar = <?= json_encode($post_validar) ?>;
    var postEjecutar = <?= json_encode($post_ejecutar) ?>;
    var torneoId = <?= (int)$torneo_id ?>;
    var csrf = form ? form.querySelector('input[name="csrf_token"]').value : '';

    document.getElementById('btnValidar').addEventListener('click', function () {
        if (!archivoInput.files.length) { alert('Seleccione un archivo.'); return; }
        var fd = new FormData();
        fd.append('action', 'carga_masiva_equipos_validar');
        fd.append('torneo_id', torneoId);
        fd.append('csrf_token', csrf);
        fd.append('archivo', archivoInput.files[0]);
        var panel = document.getElementById('panelValidacion');
        var cont = document.getElementById('contenidoValidacion');
        var panelEj = document.getElementById('panelEjecutar');
        panel.classList.remove('d-none');
        panelEj.classList.add('d-none');
        cont.innerHTML = '<p class="text-muted">Validando…</p>';
        fetch(postValidar, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var html = '';
                var v = data.validacion || {};
                var rsum = v.resumen || {};
                html += '<p><strong>Inscritos actuales en el torneo:</strong> ' + (rsum.total_inscritos_torneo || 0) +
                    ' &nbsp;|&nbsp; <strong>Equipos actuales:</strong> ' + (rsum.total_equipos_torneo || 0) +
                    ' &nbsp;|&nbsp; <strong>Equipos en archivo:</strong> ' + (rsum.equipos_en_archivo || 0) + '</p>';
                if (v.cedulas_duplicadas && v.cedulas_duplicadas.length) {
                    html += '<div class="alert alert-danger"><strong>Cédulas repetidas en el archivo</strong> (corrija antes de cargar):<ul>';
                    v.cedulas_duplicadas.forEach(function (d) {
                        html += '<li><code>' + (d.cedula || '') + '</code> aparece en: ';
                        (d.apariciones || []).forEach(function (a) {
                            html += 'equipo «' + (a.equipo || '') + '» (aprox. línea ' + (a.linea || '') + '); ';
                        });
                        html += '</li>';
                    });
                    html += '</ul></div>';
                }
                if (v.equipos_incompletos && v.equipos_incompletos.length) {
                    html += '<div class="alert alert-danger"><strong>Equipos no completos</strong> (se exigen 4 integrantes con Cedula + N1 cada uno):<ul>';
                    v.equipos_incompletos.forEach(function (e) {
                        html += '<li>«' + (e.equipo || '') + '» (fila equipo ~línea ' + (e.linea_inicio || '') + '): ' +
                            (e.integrantes || 0) + '/' + (e.requeridos || 4) + ' — ' + (e.detalle || '') + '</li>';
                    });
                    html += '</ul></div>';
                }
                if (v.bloques_sin_r && v.bloques_sin_r.length) {
                    html += '<div class="alert alert-warning"><ul>';
                    v.bloques_sin_r.forEach(function (b) { html += '<li>' + b + '</li>'; });
                    html += '</ul></div>';
                }
                if (data.success) {
                    html += '<div class="alert alert-success">' + (data.message || 'Válido.') + '</div>';
                    panelEj.classList.remove('d-none');
                } else {
                    html += '<div class="alert alert-warning">' + (data.message || 'Con errores.') + '</div>';
                }
                cont.innerHTML = html;
            })
            .catch(function () { cont.innerHTML = '<div class="alert alert-danger">Error de red.</div>'; });
    });

    document.getElementById('btnEjecutar').addEventListener('click', function () {
        if (!archivoInput.files.length) { alert('Vuelva a elegir el mismo archivo validado.'); return; }
        var confirmTxt = (document.getElementById('confirmar_reemplazo').value || '').trim();
        var fd = new FormData();
        fd.append('action', 'carga_masiva_equipos_sitio');
        fd.append('torneo_id', torneoId);
        fd.append('csrf_token', csrf);
        fd.append('confirmar_reemplazo', confirmTxt);
        fd.append('archivo', archivoInput.files[0]);
        var out = document.getElementById('resultadoCarga');
        out.innerHTML = '<div class="alert alert-info">Ejecutando (borrado + carga)…</div>';
        fetch(postEjecutar, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var cls = data.success ? 'success' : 'danger';
                var html = '<div class="alert alert-' + cls + '">' + (data.message || '') + '</div>';
                if (data.validacion && !data.success) {
                    html += '<p class="small">Validación en servidor: corrija el archivo.</p>';
                }
                if (data.detalles && data.detalles.length) {
                    html += '<table class="table table-sm table-bordered"><thead><tr><th>Equipo</th><th></th><th>Mensaje</th></tr></thead><tbody>';
                    data.detalles.forEach(function (d) {
                        html += '<tr><td>' + (d.equipo || '') + '</td><td>' + (d.ok ? 'OK' : 'Error') + '</td><td>' + (d.message || '') + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }
                out.innerHTML = html;
            })
            .catch(function () { out.innerHTML = '<div class="alert alert-danger">Error de red.</div>'; });
    });
})();
</script>
