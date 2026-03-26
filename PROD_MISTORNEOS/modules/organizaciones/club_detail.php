<?php
$logo_club = $club['logo']
    ? AppHelpers::url('view_image.php', ['path' => $club['logo']])
    : null;
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php?page=home">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php?page=organizaciones">Organizaciones</a></li>
            <li class="breadcrumb-item"><a href="index.php?page=organizaciones&id=<?= (int)$organizacion['id'] ?>"><?= htmlspecialchars($organizacion['nombre']) ?></a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($club['nombre']) ?></li>
        </ol>
    </nav>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <?php if ($logo_club): ?>
                    <div class="col-auto">
                        <img src="<?= htmlspecialchars($logo_club) ?>" alt="" class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                    </div>
                <?php endif; ?>
                <div class="col">
                    <h2 class="h4 mb-2"><?= htmlspecialchars($club['nombre']) ?></h2>
                    <p class="text-muted mb-1">Club de <?= htmlspecialchars($organizacion['nombre']) ?></p>
                    <?php if (!empty($club['delegado'])): ?>
                        <p class="mb-1"><i class="fas fa-user me-1"></i>Delegado: <?= htmlspecialchars($club['delegado']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($club['telefono'])): ?>
                        <p class="mb-1"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($club['telefono']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($club['direccion'])): ?>
                        <p class="mb-0 small"><i class="fas fa-address-card me-1"></i><?= htmlspecialchars($club['direccion']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Afiliados (<?= count($afiliados) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($afiliados)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <p class="mb-0">Este club no tiene afiliados registrados</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Cédula</th>
                                <th>Contacto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($afiliados as $a): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($a['nombre']) ?></strong></td>
                                    <td><?= htmlspecialchars($a['cedula'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($a['email'])): ?><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($a['email']) ?><br><?php endif; ?>
                                        <?php if (!empty($a['celular'])): ?><i class="fas fa-phone me-1"></i><?= htmlspecialchars($a['celular']) ?><?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= (int)($a['status'] ?? 1) === 0 ? 'success' : 'secondary' ?>">
                                            <?= (int)($a['status'] ?? 1) === 0 ? 'Activo' : 'Inactivo' ?>
                                        </span>
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
        <a href="index.php?page=organizaciones&id=<?= (int)$organizacion['id'] ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Volver a la organización</a>
    </div>
</div>
