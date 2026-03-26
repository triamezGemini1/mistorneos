<?php
/**
 * Vista: Hub de Organización - Resumen para admin_club
 * Muestra logo, nombre, estadísticas y accesos rápidos.
 */
$logo_url = !empty($organizacion['logo'])
    ? AppHelpers::url('view_image.php', ['path' => $organizacion['logo']])
    : AppHelpers::url('view_image.php', ['path' => 'lib/Assets/mislogos/logo4.png']);
$url_gestionar_clubes = AppHelpers::dashboard('clubes_asociados');
$url_ver_torneos = 'index.php?page=torneo_gestion&action=index';
$url_ver_estructura = AppHelpers::dashboard('organizaciones', ['id' => $organizacion['id']]);
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?page=home">Inicio</a></li>
            <li class="breadcrumb-item active">Mi Organización</li>
        </ol>
    </nav>

    <div class="card shadow-sm mb-4">
        <div class="card-body text-center py-4">
            <?php if ($organizacion['logo']): ?>
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="<?= htmlspecialchars($organizacion['nombre']) ?>" class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
            <?php else: ?>
                <div class="bg-light rounded-circle mx-auto mb-3 d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                    <i class="fas fa-building fa-4x text-muted"></i>
                </div>
            <?php endif; ?>
            <h2 class="h4 mb-1"><?= htmlspecialchars($organizacion['nombre']) ?></h2>
            <?php if (!empty($organizacion['entidad_nombre'])): ?>
                <p class="text-muted mb-0"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($organizacion['entidad_nombre']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fas fa-sitemap fa-3x me-3 opacity-75"></i>
                    <div>
                        <h3 class="mb-0"><?= (int)$stats['clubes'] ?></h3>
                        <span class="small">Total de Clubes</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fas fa-users fa-3x me-3 opacity-75"></i>
                    <div>
                        <h3 class="mb-0"><?= (int)$stats['afiliados'] ?></h3>
                        <span class="small">Total de Afiliados</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fas fa-trophy fa-3x me-3 opacity-75"></i>
                    <div>
                        <h3 class="mb-0"><?= (int)$stats['torneos_activos'] ?></h3>
                        <span class="small">Torneos Activos</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Accesos Rápidos</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6 col-lg-4">
                    <a href="<?= htmlspecialchars($url_gestionar_clubes) ?>" class="btn btn-outline-primary btn-lg w-100 py-3">
                        <i class="fas fa-sitemap fa-2x d-block mb-2"></i>
                        Gestionar Clubes
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= htmlspecialchars($url_ver_torneos) ?>" class="btn btn-outline-success btn-lg w-100 py-3">
                        <i class="fas fa-trophy fa-2x d-block mb-2"></i>
                        Ver Torneos
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= htmlspecialchars($url_ver_estructura) ?>" class="btn btn-outline-secondary btn-lg w-100 py-3">
                        <i class="fas fa-project-diagram fa-2x d-block mb-2"></i>
                        Ver Estructura Completa
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= htmlspecialchars(AppHelpers::dashboard('mi_organizacion')) ?>" class="btn btn-outline-info btn-lg w-100 py-3">
                        <i class="fas fa-edit fa-2x d-block mb-2"></i>
                        Editar Perfil de la Organización
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
