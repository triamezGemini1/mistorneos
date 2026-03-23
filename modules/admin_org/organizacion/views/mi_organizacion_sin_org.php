<?php
/**
 * Vista: Sin organización asignada (admin_club)
 */
?>
<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <i class="fas fa-building fa-3x text-muted mb-3"></i>
        <h4>No tiene una organización asignada</h4>
        <p class="text-muted">Contacte al administrador general para crear su organización.</p>
        <a href="<?= htmlspecialchars(class_exists('AppHelpers') ? AppHelpers::dashboard('home') : 'index.php?page=home') ?>" class="btn btn-outline-secondary mt-3">
            <i class="fas fa-arrow-left me-1"></i>Regresar al inicio
        </a>
    </div>
</div>
