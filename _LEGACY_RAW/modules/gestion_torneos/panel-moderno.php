<?php
/**
 * Vista Moderna: Panel de Control de Torneo
 * Panel común para todos los tipos de torneo (individual/parejas/equipos)
 * Diseño con Tailwind CSS - 3 columnas organizadas
 *
 * Datos: extract($view_data) en torneo_gestion.php → PanelTorneoViewData::build() + contexto (base_url, use_standalone, user_id, is_admin_general).
 * Refactorización 2026: sin SQL ni JS inline; assets en lvd-panel-moderno.css y panel-actions.js; cronómetro en partial.
 */
if (empty($torneo) || !is_array($torneo) || !isset($torneo['id'])) {
    throw new RuntimeException('panel-moderno: falta $torneo en view_data (¿PanelTorneoViewData::build?)');
}

$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
if (!isset($use_standalone)) {
    $use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php'], true);
}
if (!isset($base_url)) {
    $base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
}
if (!isset($user_id)) {
    $user_id = 0;
}
if (!isset($is_admin_general)) {
    $is_admin_general = false;
}
?>

<link rel="stylesheet" href="assets/css/design-system.css">
<link rel="stylesheet" href="assets/css/modern-panel.css">
<link rel="stylesheet" href="assets/css/lvd-panel-moderno.css">
<?php if ($use_standalone): ?>
<!-- Tailwind CSS solo en modo standalone para no romper el layout del dashboard -->
<link rel="stylesheet" href="assets/dist/output.css">
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                'panel-blue': '#3b82f6',
                'panel-purple': '#8b5cf6',
                'panel-green': '#10b981',
                'panel-amber': '#f59e0b',
                'panel-cyan': '#06b6d4',
                'panel-red': '#ef4444',
                'panel-indigo': '#6366f1',
                'panel-dark': '#111827',
            }
        }
    }
}
</script>
<?php endif; ?>

<div class="tw-panel ds-root lvd-panel-moderno-root">
    <?php include __DIR__ . '/partials/panel/_header_stats.php'; ?>

    <?php include __DIR__ . '/partials/panel/_cronometro.php'; ?>

    <?php if ($isLocked): ?>
        <div class="bg-gray-100 border-l-4 border-gray-500 rounded-lg p-2 mb-2">
            <div class="flex items-center gap-2 text-gray-700">
                <i class="fas fa-lock text-xl"></i>
                <span class="font-semibold">Torneo cerrado: solo se permite consultar e imprimir. Las acciones de modificación están deshabilitadas.</span>
            </div>
        </div>
    <?php endif; ?>

    <?php include __DIR__ . '/partials/panel/_acciones_torneo.php'; ?>

</div>

<!-- Modal Importación Masiva (solo torneos individuales) -->
<div class="modal fade lvd-import-modal" id="modalImportacionMasiva" tabindex="-1" aria-labelledby="modalImportacionMasivaLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-indigo-600 text-white">
                <h5 class="modal-title" id="modalImportacionMasivaLabel"><i class="fas fa-file-csv me-2"></i>Importación masiva</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Cargue un archivo <strong>Excel (.xls / 97-2003 o .xlsx)</strong> o CSV. Campos obligatorios: <strong>nacionalidad, cédula, nombre, club, organización</strong>. Si falta cualquiera, la fila se rechaza. Asigne cada columna al campo (entidad/organización se asocian a Organización).</p>
                <p class="small mb-2"><strong>Semáforo (tras Validar):</strong> <span class="badge" style="background:#3b82f6">Azul</span> Ya inscrito (omitir) · <span class="badge" style="background:#eab308;color:#000">Amarillo</span> Usuario existe (solo inscribir) · <span class="badge" style="background:#22c55e">Verde</span> Todo nuevo (crear e inscribir) · <span class="badge bg-danger">Rojo</span> Error de datos</p>
                <div class="mb-3">
                    <label class="form-label">Archivo CSV</label>
                    <input type="file" class="form-control" id="importMasivaFile" accept=".xls,.xlsx,.csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
                </div>
                <div id="importMasivaMapping" class="mb-3 d-none">
                    <h6 class="mb-2">Mapeo de columnas</h6>
                    <div class="row g-2 flex-wrap" id="importMasivaMappingRow"></div>
                </div>
                <div id="importMasivaPreviewWrap" class="mb-3 d-none">
                    <h6 class="mb-2">Vista previa <span class="badge bg-secondary" id="importMasivaPreviewCount">0</span> filas</h6>
                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm table-bordered" id="importMasivaPreviewTable"></table>
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnImportMasivaValidar"><i class="fas fa-check-double me-1"></i>Validar (semáforo)</button>
                        <button type="button" class="btn btn-success btn-sm ms-2" id="btnImportMasivaProcesar"><i class="fas fa-play me-1"></i>Procesar importación</button>
                    </div>
                </div>
                <div id="importMasivaLoading" class="d-none text-center py-3"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 mb-0">Procesando...</p></div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/panel-actions.js" defer></script>
