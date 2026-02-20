<?php
$logo_url = $organizacion['logo']
    ? AppHelpers::url('view_image.php', ['path' => $organizacion['logo']])
    : AppHelpers::url('view_image.php', ['path' => 'lib/Assets/mislogos/logo4.png']);
$stats_clubes = count($clubes);
$stats_torneos = 0;
$stats_afiliados = 0;
foreach ($clubes as $c) {
    $stats_afiliados += (int)($c['total_afiliados'] ?? 0);
}
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tournaments WHERE club_responsable = ?");
    $stmt->execute([$organizacion['id']]);
    $stats_torneos = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
$stats_operadores = isset($stats_operadores) ? (int)$stats_operadores : 0;
$stats_admin_torneo = isset($stats_admin_torneo) ? (int)$stats_admin_torneo : 0;
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?page=home">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php?page=organizaciones">Organizaciones</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($organizacion['nombre']) ?></li>
        </ol>
    </nav>

    <?php
    $org_estatus = (int)($organizacion['estatus'] ?? 1);
    $org_desactivada = $org_estatus === 0;
    if ($org_desactivada && !empty($is_admin_general)): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-3">
            <i class="fas fa-ban me-2"></i>Esta organización está <strong>desactivada</strong>.
            <a href="index.php?page=mi_organizacion&action=reactivar&id=<?= (int)$organizacion['id'] ?>&return_to=organizaciones&entidad_id=<?= (int)($organizacion['entidad'] ?? 0) ?>" class="btn btn-sm btn-success ms-3" onclick="return confirm('¿Reactivar esta organización?');">
                <i class="fas fa-check-circle me-1"></i>Reactivar
            </a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <!-- Sección superior: Identificación en dos columnas -->
    <div class="row mb-4">
        <!-- Columna 1: Información de la organización -->
        <div class="col-md-6 mb-3 mb-md-0">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-building me-2"></i>Información de la Organización</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <img src="<?= htmlspecialchars($logo_url) ?>" alt="<?= htmlspecialchars($organizacion['nombre']) ?>" class="rounded me-3 flex-shrink-0" style="width: 80px; height: 80px; object-fit: cover;">
                        <div class="flex-grow-1">
                            <h4 class="mb-1"><?= htmlspecialchars($organizacion['nombre']) ?></h4>
                            <?php if (!empty($organizacion['entidad_nombre'])): ?>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($organizacion['entidad_nombre']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($organizacion['responsable'])): ?>
                                <p class="mb-1 small"><i class="fas fa-user me-1"></i><strong>Responsable:</strong> <?= htmlspecialchars($organizacion['responsable']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($organizacion['telefono'])): ?>
                                <p class="mb-1 small"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($organizacion['telefono']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($organizacion['email'])): ?>
                                <p class="mb-1 small"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($organizacion['email']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($organizacion['direccion'])): ?>
                                <p class="mb-0 small"><i class="fas fa-address-card me-1"></i><?= htmlspecialchars($organizacion['direccion']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Columna 2: Estadísticas -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Estadísticas</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6 col-md-4">
                            <div class="d-flex align-items-center p-2 bg-light rounded">
                                <i class="fas fa-sitemap fa-2x text-primary me-2"></i>
                                <div>
                                    <strong><?= $stats_clubes ?></strong>
                                    <span class="d-block small text-muted">Clubes</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="d-flex align-items-center p-2 bg-light rounded">
                                <i class="fas fa-trophy fa-2x text-success me-2"></i>
                                <div>
                                    <strong><?= $stats_torneos ?></strong>
                                    <span class="d-block small text-muted">Torneos</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="d-flex align-items-center p-2 bg-light rounded">
                                <i class="fas fa-users fa-2x text-info me-2"></i>
                                <div>
                                    <strong><?= $stats_afiliados ?></strong>
                                    <span class="d-block small text-muted">Afiliados</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="d-flex align-items-center p-2 bg-light rounded">
                                <i class="fas fa-user-cog fa-2x text-warning me-2"></i>
                                <div>
                                    <strong><?= $stats_admin_torneo ?></strong>
                                    <span class="d-block small text-muted">Admin. torneo</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="d-flex align-items-center p-2 bg-light rounded">
                                <i class="fas fa-user-tie fa-2x text-secondary me-2"></i>
                                <div>
                                    <strong><?= $stats_operadores ?></strong>
                                    <span class="d-block small text-muted">Operadores</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Clubes de la organización</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($clubes)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-sitemap fa-2x mb-2"></i>
                    <p class="mb-0">No hay clubes registrados en esta organización</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Delegado</th>
                                <th class="text-center">Afiliados</th>
                                <th class="text-center"><i class="fas fa-mars text-primary" title="Hombres"></i></th>
                                <th class="text-center"><i class="fas fa-venus text-danger" title="Mujeres"></i></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clubes as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                                    <td><?= htmlspecialchars($c['delegado'] ?? '-') ?></td>
                                    <td class="text-center"><span class="badge bg-info"><?= (int)($c['total_afiliados'] ?? 0) ?></span></td>
                                    <td class="text-center"><span class="badge bg-primary"><?= (int)($c['hombres'] ?? 0) ?></span></td>
                                    <td class="text-center"><span class="badge bg-danger"><?= (int)($c['mujeres'] ?? 0) ?></span></td>
                                    <td>
                                        <a href="index.php?page=organizaciones&id=<?= (int)$organizacion['id'] ?>&club_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>Ver detalle y afiliados
                                        </a>
                                        <a href="<?= htmlspecialchars(AppHelpers::dashboard('clubes_asociados', ['club_id' => $c['id']])) ?>" class="btn btn-sm btn-outline-secondary ms-1" title="Editar club">
                                            <i class="fas fa-edit me-1"></i>Editar Club
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-3">
        <a href="index.php?page=organizaciones" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Volver al listado</a>
        <?php if ($is_admin_general): ?>
            <a href="index.php?page=mi_organizacion&id=<?= (int)$organizacion['id'] ?>" class="btn btn-outline-primary ms-2"><i class="fas fa-edit me-1"></i>Editar organización</a>
        <?php endif; ?>
    </div>
</div>
