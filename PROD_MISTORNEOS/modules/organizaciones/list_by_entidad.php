<?php
$page_title = 'Organizaciones por entidad';
?>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3"><i class="fas fa-building text-primary me-2"></i>Organizaciones</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php?page=home">Inicio</a></li>
                    <li class="breadcrumb-item active">Organizaciones</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if (empty($por_entidad)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">No hay organizaciones registradas</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($por_entidad as $entidad_nombre => $organizaciones): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($entidad_nombre) ?></h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($organizaciones as $org): ?>
                            <?php
                            $logo_url = $org['logo']
                                ? AppHelpers::url('view_image.php', ['path' => $org['logo']])
                                : AppHelpers::url('view_image.php', ['path' => 'lib/Assets/mislogos/logo4.png']);
                            ?>
                            <li class="list-group-item list-group-item-action d-flex align-items-center">
                                <img src="<?= htmlspecialchars($logo_url) ?>" alt="" class="rounded me-3" style="width: 48px; height: 48px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <strong><?= htmlspecialchars($org['nombre']) ?></strong>
                                    <div class="small text-muted">
                                        <span class="me-3"><i class="fas fa-sitemap me-1"></i><?= (int)$org['total_clubes'] ?> clubes</span>
                                        <span class="me-3"><i class="fas fa-trophy me-1"></i><?= (int)$org['total_torneos'] ?> torneos</span>
                                        <?php if (!empty($org['responsable'])): ?>
                                            <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($org['responsable']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="index.php?page=organizaciones&id=<?= (int)$org['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-arrow-right me-1"></i>Ver detalle
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
