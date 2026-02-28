<?php
/**
 * Activar todos los usuarios que participan en el torneo para que puedan acceder al sistema
 * (login, perfil, notificaciones).
 */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['confirmar'])) {
    require_once __DIR__ . '/../../lib/UserActivationHelper.php';
    $activados = UserActivationHelper::activateTournamentParticipants($pdo, $torneo_id);
    $msg = $activados > 0
        ? "Se activaron {$activados} participante(s). Ya pueden acceder al sistema y recibir notificaciones."
        : "No había participantes por activar o ya estaban activos.";
    header('Location: index.php?page=tournament_admin&torneo_id=' . (int)$torneo_id . '&action=activar_participantes&success=' . urlencode($msg));
    exit;
}

$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
?>

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
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-check me-1"></i> Activar todos los participantes del torneo
            </button>
        </form>
    </div>
</div>
