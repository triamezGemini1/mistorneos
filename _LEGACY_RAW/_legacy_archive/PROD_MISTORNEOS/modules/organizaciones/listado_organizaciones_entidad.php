<?php
/**
 * Listado de organizaciones de una entidad con resumen. Enlace "Ver detalle" → organizaciones&id=org_id
 */
$page_title = 'Organizaciones - ' . ($entidad_nombre ?? 'Entidad');
?>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3"><i class="fas fa-building text-primary me-2"></i>Organizaciones</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php?page=home">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="index.php?page=organizaciones">Organizaciones</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($entidad_nombre ?? 'Entidad') ?></li>
                </ol>
            </nav>
        </div>
        <div class="col-auto">
            <a href="index.php?page=organizaciones" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Volver al listado</a>
            <a href="<?= htmlspecialchars(AppHelpers::dashboard('notificaciones_masivas')) ?>" class="btn btn-outline-primary ms-2"><i class="fas fa-bell me-1"></i>Enviar notificaciones</a>
        </div>
    </div>

    <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($organizaciones)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">No hay organizaciones en esta entidad</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($entidad_nombre ?? 'Entidad') ?></h5>
                <p class="text-muted small mb-0 mt-1">Organizaciones con resumen de clubes, afiliados y torneos</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Organización</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Clubes</th>
                                <th class="text-center">Afiliados</th>
                                <th class="text-center">Torneos</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $entidad_id_attr = isset($entidad_id) ? (int)$entidad_id : 0;
                            $return_entidad = $entidad_id_attr > 0 ? '&return_to=organizaciones&entidad_id=' . $entidad_id_attr : '';
                            foreach ($organizaciones as $org):
                                $esta_activa = (int)($org['estatus'] ?? 1) === 1;
                            ?>
                                <tr class="<?= $esta_activa ? '' : 'table-secondary' ?>">
                                    <td><strong><?= htmlspecialchars($org['nombre']) ?></strong></td>
                                    <td class="text-center">
                                        <?php if ($esta_activa): ?>
                                            <span class="badge bg-success">Activa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Desactivada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="badge bg-secondary"><?= (int)($org['total_clubes'] ?? 0) ?></span></td>
                                    <td class="text-center"><span class="badge bg-info"><?= (int)($org['total_afiliados'] ?? 0) ?></span></td>
                                    <td class="text-center"><span class="badge bg-success"><?= (int)($org['total_torneos'] ?? 0) ?></span></td>
                                    <td class="text-end">
                                        <a href="index.php?page=organizaciones&id=<?= (int)$org['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                            <i class="fas fa-eye me-1"></i>Ver detalle
                                        </a>
                                        <a href="index.php?page=mi_organizacion&id=<?= (int)$org['id'] ?>" class="btn btn-sm btn-outline-secondary ms-1" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($esta_activa): ?>
                                            <a href="index.php?page=mi_organizacion&action=desactivar&id=<?= (int)$org['id'] ?><?= $return_entidad ?>" class="btn btn-sm btn-outline-danger ms-1" title="Desactivar" onclick="return confirm('¿Desactivar esta organización?');">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="index.php?page=mi_organizacion&action=reactivar&id=<?= (int)$org['id'] ?><?= $return_entidad ?>" class="btn btn-sm btn-outline-success ms-1" title="Reactivar" onclick="return confirm('¿Reactivar esta organización?');">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
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
