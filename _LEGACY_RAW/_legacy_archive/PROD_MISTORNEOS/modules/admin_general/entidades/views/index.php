<?php
/**
 * Vista: listado de entidades con resumen (solo datos; $resumen_entidades viene del action).
 */
$resumen_entidades = $resumen_entidades ?? [];
?>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3"><i class="fas fa-map-marked-alt text-primary me-2"></i>Entidades</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= htmlspecialchars(AppHelpers::dashboard()) ?>">Inicio</a></li>
                    <li class="breadcrumb-item active">Entidades</li>
                </ol>
            </nav>
        </div>
        <div class="col-auto">
            <a href="<?= htmlspecialchars(AppHelpers::dashboard('organizaciones')) ?>" class="btn btn-outline-primary">
                <i class="fas fa-building me-1"></i>Organizaciones
            </a>
        </div>
    </div>

    <?php if (empty($resumen_entidades)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-map-marked-alt fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">No hay entidades con organizaciones registradas</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Resumen por entidad</h5>
                <p class="text-muted small mb-0 mt-1">Organizaciones, clubes, afiliados y torneos por entidad territorial</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><i class="fas fa-map-marker-alt me-1"></i>Entidad</th>
                                <th class="text-center">Organizaciones</th>
                                <th class="text-center">Clubes</th>
                                <th class="text-center">Afiliados</th>
                                <th class="text-center">Torneos</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resumen_entidades as $row): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['entidad_nombre']) ?></strong></td>
                                    <td class="text-center"><span class="badge bg-primary"><?= (int)$row['total_organizaciones'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-secondary"><?= (int)$row['total_clubes'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-info"><?= (int)$row['total_afiliados'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-success"><?= (int)$row['total_torneos'] ?></span></td>
                                    <td class="text-end">
                                        <a href="<?= htmlspecialchars(AppHelpers::dashboard('entidades', ['action' => 'detail', 'id' => $row['entidad_id']])) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>Ver detalle
                                        </a>
                                        <a href="<?= htmlspecialchars(AppHelpers::dashboard('organizaciones', ['entidad_id' => $row['entidad_id']])) ?>" class="btn btn-sm btn-outline-secondary ms-1">
                                            <i class="fas fa-building me-1"></i>Organizaciones
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
