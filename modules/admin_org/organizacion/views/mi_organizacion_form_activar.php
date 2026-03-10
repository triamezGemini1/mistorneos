<?php
/**
 * Vista: Activar organización inactiva — asignar usuario administrador y contraseña
 */
$org_id = (int)($organizacion['id'] ?? 0);
$return_extra = '';
$entidad_id = (int)($_GET['entidad_id'] ?? 0);
if (($_GET['return_to'] ?? '') === 'organizaciones' && $entidad_id > 0) {
    $return_extra = '&return_to=organizaciones&entidad_id=' . $entidad_id;
}
$url_volver = $return_extra !== '' ? 'index.php?page=organizaciones&entidad_id=' . $entidad_id : 'index.php?page=mi_organizacion&id=' . $org_id;
?>
<div class="card shadow-sm">
    <div class="card-header bg-warning text-dark">
        <i class="fas fa-unlock-alt me-2"></i>Activar organización
    </div>
    <div class="card-body">
        <p class="text-muted">Esta organización está inactiva. Asigne un usuario administrador y defina su contraseña para activarla.</p>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="index.php?page=mi_organizacion&id=<?= $org_id ?><?= $return_extra ?>">
            <input type="hidden" name="action" value="activar_guardar">
            <input type="hidden" name="organizacion_id" value="<?= $org_id ?>">
            <div class="mb-3">
                <label class="form-label">Organización</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($organizacion['nombre'] ?? '') ?>" readonly disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Usuario administrador <span class="text-danger">*</span></label>
                <select name="admin_user_id" class="form-select" required>
                    <option value="">Seleccionar usuario admin_club</option>
                    <?php foreach (($admin_sin_organizacion ?? []) as $adm): ?>
                        <option value="<?= (int)$adm['id'] ?>" <?= (int)($_POST['admin_user_id'] ?? 0) === (int)$adm['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($adm['nombre']) ?> (<?= htmlspecialchars($adm['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($admin_sin_organizacion)): ?>
                    <small class="text-warning">No hay usuarios admin_club sin organización activa. Cree o apruebe un usuario con rol admin_club.</small>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" minlength="6" required placeholder="Mínimo 6 caracteres">
            </div>
            <div class="mb-3">
                <label class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                <input type="password" name="password_confirm" class="form-control" minlength="6" required placeholder="Repetir contraseña">
            </div>
            <div class="d-flex justify-content-between">
                <a href="<?= htmlspecialchars($url_volver) ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Volver</a>
                <button type="submit" class="btn btn-success" <?= empty($admin_sin_organizacion) ? 'disabled' : '' ?>><i class="fas fa-check-circle me-1"></i>Activar organización</button>
            </div>
        </form>
    </div>
</div>
