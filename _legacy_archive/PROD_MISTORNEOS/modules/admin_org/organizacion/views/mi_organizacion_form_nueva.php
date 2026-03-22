<?php
/**
 * Vista: Formulario nueva organización (solo admin_general)
 */
?>
<div class="card shadow-sm">
    <div class="card-header bg-success text-white">
        <i class="fas fa-plus me-2"></i>Nueva Organización
    </div>
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="crear">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre de la Organización <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Administrador <span class="text-danger">*</span></label>
                    <select name="admin_user_id" class="form-select" required>
                        <option value="">Seleccionar usuario admin_club</option>
                        <?php foreach ($admin_sin_organizacion as $adm): ?>
                            <option value="<?= (int)$adm['id'] ?>" <?= (int)($_POST['admin_user_id'] ?? 0) === (int)$adm['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($adm['nombre']) ?> (<?= htmlspecialchars($adm['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($admin_sin_organizacion)): ?>
                        <small class="text-warning">No hay usuarios admin_club sin organización. Apruebe una solicitud de afiliación para crear uno.</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Responsable / Presidente</label>
                    <input type="text" name="responsable" class="form-control" value="<?= htmlspecialchars($_POST['responsable'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Entidad</label>
                    <select name="entidad" class="form-select">
                        <option value="0">Sin especificar</option>
                        <?php foreach ($entidades_options as $ent): ?>
                            <option value="<?= (int)$ent['id'] ?>" <?= (int)($_POST['entidad'] ?? 0) === (int)$ent['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ent['nombre'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Dirección</label>
                <textarea name="direccion" class="form-control" rows="2"><?= htmlspecialchars($_POST['direccion'] ?? '') ?></textarea>
            </div>
            <div class="d-flex justify-content-between">
                <a href="index.php?page=mi_organizacion" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Volver</a>
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Crear Organización</button>
            </div>
        </form>
    </div>
</div>
