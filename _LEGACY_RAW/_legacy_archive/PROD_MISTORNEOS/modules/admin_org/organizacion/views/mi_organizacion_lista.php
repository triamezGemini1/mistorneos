<?php
/**
 * Vista: Lista de organizaciones (solo admin_general)
 */
?>
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Lista de Organizaciones</span>
        <a href="index.php?page=mi_organizacion&action=new" class="btn btn-light btn-sm">
            <i class="fas fa-plus me-1"></i>Nueva Organización
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($lista_organizaciones)): ?>
            <div class="text-center py-5">
                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                <p class="text-muted">No hay organizaciones registradas</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Logo</th>
                            <th>Nombre</th>
                            <th>Entidad</th>
                            <th class="text-center">Estado</th>
                            <th>Responsable</th>
                            <th class="text-center">Clubes</th>
                            <th class="text-center">Torneos</th>
                            <th>Administrador</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista_organizaciones as $org):
                            $org_activa = (int)($org['estatus'] ?? 1) === 1;
                        ?>
                            <tr class="<?= $org_activa ? '' : 'table-secondary' ?>">
                                <td>
                                    <?php if ($org['logo']): 
                                        $logo_tbl = AppHelpers::url('view_image.php', ['path' => $org['logo']]);
                                    ?>
                                        <img src="<?= htmlspecialchars($logo_tbl) ?>" alt="Logo" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-building text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($org['nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($org['entidad_nombre'] ?? '-') ?></td>
                                <td class="text-center">
                                    <?php if ($org_activa): ?>
                                        <span class="badge bg-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Desactivada</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($org['responsable'] ?? '-') ?></td>
                                <td class="text-center"><span class="badge bg-info"><?= $org['total_clubes'] ?></span></td>
                                <td class="text-center"><span class="badge bg-success"><?= $org['total_torneos'] ?></span></td>
                                <td><?= htmlspecialchars($org['admin_nombre'] ?? '-') ?></td>
                                <td>
                                    <a href="index.php?page=mi_organizacion&id=<?= $org['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <?php if ($org_activa): ?>
                                        <a href="index.php?page=mi_organizacion&action=desactivar&id=<?= $org['id'] ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('¿Desactivar esta organización?');">
                                            <i class="fas fa-ban"></i> Desactivar
                                        </a>
                                    <?php else: ?>
                                        <a href="index.php?page=mi_organizacion&action=reactivar&id=<?= $org['id'] ?>" class="btn btn-sm btn-outline-success ms-1" onclick="return confirm('¿Reactivar esta organización?');">
                                            <i class="fas fa-check-circle"></i> Reactivar
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
