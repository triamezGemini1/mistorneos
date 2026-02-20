<?php
/**
 * Estado de sincronización: pendientes, último sync, enlace a import/export.
 */
declare(strict_types=1);
require_once __DIR__ . '/desktop_auth.php';
require_once __DIR__ . '/db_local.php';

$pending = 0;
try {
    $pending = (int)DB_Local::pdo()->query("SELECT COUNT(*) FROM usuarios WHERE sync_status = 0")->fetchColumn();
} catch (Throwable $e) {
}

$pageTitle = 'Estado de Sincronización';
$desktopActive = 'sync';
require_once __DIR__ . '/desktop_layout.php';
?>
<div class="container-fluid py-3">
    <h2 class="h4 mb-3"><i class="fas fa-sync text-primary me-2"></i>Estado de Sincronización</h2>
    <div class="row">
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Registros pendientes de subir</h5>
                    <p class="display-4 mb-0"><?= $pending ?></p>
                    <p class="text-muted small mb-0">Se enviarán al hacer clic en "Sincronizar con la web".</p>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="export_to_web.php" class="btn btn-success"><i class="fas fa-cloud-upload-alt me-1"></i>Enviar a la web</a>
        <a href="import_from_web.php" class="btn btn-outline-primary"><i class="fas fa-cloud-download-alt me-1"></i>Importar desde web</a>
    </div>
</div>
</main></body></html>
