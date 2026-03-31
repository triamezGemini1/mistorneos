<?php
/**
 * Vista: descarga de reportes de inscritos al torneo (Excel y PDF, simple y estructurado).
 */
if (!class_exists('AppHelpers', false)) {
    require_once __DIR__ . '/../../lib/app_helpers.php';
}

$torneo = $torneo ?? ['id' => 0, 'nombre' => 'Torneo'];
$torneo_id = (int)($torneo_id ?? $torneo['id'] ?? 0);

$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php'], true);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$url_panel = $base_url . ($use_standalone ? '?' : '&') . 'action=panel&torneo_id=' . $torneo_id;

$tid = $torneo_id;
if ($tid > 0 && class_exists('AppHelpers')) {
    $url_xls_simple = AppHelpers::torneoGestionUrl('inscripciones_export_xls', $tid);
    $url_xls_estruct = AppHelpers::torneoGestionUrl('inscripciones_reporte_detallado_xls', $tid);
    $url_pdf_simple = AppHelpers::torneoGestionUrl('inscripciones_export_pdf', $tid);
    $url_pdf_estruct = AppHelpers::torneoGestionUrl('inscripciones_reporte_detallado_pdf', $tid);
} else {
    $q = $base_url . ($use_standalone ? '?' : '&');
    $url_xls_simple = $q . 'action=inscripciones_export_xls&torneo_id=' . $tid;
    $url_xls_estruct = $q . 'action=inscripciones_reporte_detallado_xls&torneo_id=' . $tid;
    $url_pdf_simple = $q . 'action=inscripciones_export_pdf&torneo_id=' . $tid;
    $url_pdf_estruct = $q . 'action=inscripciones_reporte_detallado_pdf&torneo_id=' . $tid;
}
?>

<nav aria-label="breadcrumb" class="breadcrumb-modern mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>">Gestión de Torneos</a></li>
        <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($url_panel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)($torneo['nombre'] ?? 'Torneo'), ENT_QUOTES, 'UTF-8'); ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Reportes de inscritos</li>
    </ol>
</nav>

<div class="card-modern mb-4" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); color: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <div class="p-4">
        <h2 class="mb-1" style="color: white; font-weight: 700;">
            <i class="fas fa-file-alt me-2"></i>
            Reportes de inscritos
        </h2>
        <p class="mb-0" style="opacity: 0.95; font-size: 0.95rem;">
            <?php echo htmlspecialchars((string)($torneo['nombre'] ?? 'Torneo'), ENT_QUOTES, 'UTF-8'); ?>
            <span class="ms-2" style="opacity: 0.85;">(Torneo #<?php echo $tid; ?>)</span>
        </p>
    </div>
</div>

<?php if ($tid <= 0): ?>
    <div class="alert alert-warning">No hay torneo válido.</div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h3 class="h6 mb-0 text-success"><i class="fas fa-file-excel me-2"></i>Microsoft Excel</h3>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <p class="text-muted small mb-2">Descarga en formato .xls (abre en Excel o LibreOffice).</p>
                    <a href="<?php echo htmlspecialchars($url_xls_simple, ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn btn-success w-100 text-start d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-table me-2"></i>Simple</span>
                        <small class="opacity-75">Por asociación y equipo</small>
                    </a>
                    <a href="<?php echo htmlspecialchars($url_xls_estruct, ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn btn-outline-success w-100 text-start d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-sitemap me-2"></i>Estructurado</span>
                        <small class="text-muted">Organización, encabezado y detalle</small>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h3 class="h6 mb-0 text-danger"><i class="fas fa-file-pdf me-2"></i>PDF</h3>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <p class="text-muted small mb-2">Listo para imprimir o archivar (requiere Dompdf si está instalado).</p>
                    <a href="<?php echo htmlspecialchars($url_pdf_simple, ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn btn-danger w-100 text-start d-flex align-items-center justify-content-between"
                       target="_blank" rel="noopener">
                        <span><i class="fas fa-file-pdf me-2"></i>Simple</span>
                        <small class="opacity-75">Por asociación y equipo</small>
                    </a>
                    <a href="<?php echo htmlspecialchars($url_pdf_estruct, ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn btn-outline-danger w-100 text-start d-flex align-items-center justify-content-between"
                       target="_blank" rel="noopener">
                        <span><i class="fas fa-file-invoice me-2"></i>Estructurado</span>
                        <small class="text-muted">Logo, organización y metadatos</small>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="<?php echo htmlspecialchars($url_panel, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Volver al panel del torneo
        </a>
    </div>
<?php endif; ?>
