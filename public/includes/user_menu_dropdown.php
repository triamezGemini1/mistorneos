<?php
/**
 * Menú desplegable del usuario (perfil) — único punto de definición.
 * Se incluye desde el layout principal. Todas las URLs se generan con AppHelpers
 * para que el menú sea idéntico en cualquier página (usuarios, organizaciones,
 * inscripción en sitio, etc.) y el logout siempre funcione.
 *
 * Requiere: $user (array con username, role, etc.) — ya definido en layout.php
 */
if (!isset($user) || !is_array($user)) {
    return;
}
// Base absoluta para que el menú funcione en cualquier subruta (ej. /mistorneos_beta/public/)
$base = '';
if (class_exists('AppHelpers')) {
    $base = rtrim(AppHelpers::getPublicUrl(), '/');
    if ($base === '' && method_exists('AppHelpers', 'getRequestEntryUrl')) {
        $base = rtrim(AppHelpers::getRequestEntryUrl(), '/');
    }
}
$url_profile = $base ? $base . '/profile.php' : 'profile.php';
$url_change_password = class_exists('AppHelpers') ? AppHelpers::dashboard('users/change_password') : ($base ? $base . '/index.php?page=users/change_password' : 'index.php?page=users/change_password');
$url_logout = $base ? $base . '/logout.php' : 'logout.php';
$url_mi_organizacion = class_exists('AppHelpers') ? AppHelpers::dashboard('mi_organizacion') : 'index.php?page=mi_organizacion';
?>
<!-- Menú usuario: centralizado para que todas las opciones (incl. logout) estén siempre disponibles -->
<div class="dropdown" id="user-menu-dropdown" data-bs-boundary="viewport">
  <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true">
    <i class="fas fa-user me-2"></i>
    <?= htmlspecialchars($user['username'] ?? 'Usuario') ?>
  </button>
  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuButton">
    <li><span class="dropdown-item-text text-muted">Rol: <?= htmlspecialchars($user['role'] ?? '') ?></span></li>
    <?php if (($user['role'] ?? '') === 'admin_club'): ?>
    <li><a class="dropdown-item" href="<?= htmlspecialchars($url_mi_organizacion) ?>"><i class="fas fa-building me-2"></i>Perfil de la organización</a></li>
    <?php endif; ?>
    <li><a class="dropdown-item" href="<?= htmlspecialchars($url_profile) ?>"><i class="fas fa-id-card me-2"></i>Mi Perfil</a></li>
    <li><a class="dropdown-item" href="<?= htmlspecialchars($url_change_password) ?>"><i class="fas fa-key me-2"></i>Cambiar Contraseña</a></li>
    <li><hr class="dropdown-divider"></li>
    <li><a class="dropdown-item text-danger" href="<?= htmlspecialchars($url_logout) ?>" target="_self"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
  </ul>
</div>
