<?php
/**
 * Vista: Activar participantes del torneo (dentro de torneo_gestion para mantener layout).
 * Las variables $torneo, $torneo_id vienen de $view_data (extract).
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$sep = $use_standalone ? '?' : '&';
$url_panel = $base_url . $sep . 'action=panel&torneo_id=' . (int)$torneo_id;
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
?>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($base_url . $sep . 'action=index') ?>">Gestión de Torneos</a></li>
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($url_panel) ?>"><?= htmlspecialchars($torneo['nombre'] ?? 'Panel') ?></a></li>
            <li class="breadcrumb-item active">Activar participantes</li>
        </ol>
    </nav>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-user-check me-2"></i>Activar participantes para acceso al sistema</h5>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <p class="text-muted">
                Los usuarios que participan en este torneo (inscritos no retirados) podrán iniciar sesión,
                consultar su perfil y recibir notificaciones. Use esta acción si añadió participantes que aún no tenían cuenta activa.
            </p>
            <form method="post" action="">
                <input type="hidden" name="confirmar" value="1" />
                <input type="hidden" name="torneo_id" value="<?= (int)$torneo_id ?>" />
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-check me-1"></i> Activar todos los participantes del torneo
                </button>
                <a href="<?= htmlspecialchars($url_panel) ?>" class="btn btn-outline-secondary ms-2">Volver al panel</a>
            </form>
        </div>
    </div>
</div>
