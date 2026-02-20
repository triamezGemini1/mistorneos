<?php
/**
 * Vista de detalle del club con lista de afiliados
 * Accesible para admin_general y admin_club (solo sus clubes)
 */

if (!defined('APP_BOOTSTRAPPED')) { 
    require_once __DIR__ . '/../../config/bootstrap.php'; 
}
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/ClubHelper.php';

$current_user = Auth::user();
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$club_id) {
    header('Location: index.php?page=clubs&error=' . urlencode('ID de club no válido'));
    exit;
}

// Verificar permisos para admin_club
if ($current_user['role'] === 'admin_club') {
    $can_access = ClubHelper::isClubManagedByAdmin((int)$current_user['id'], $club_id)
        || (!empty($current_user['club_id']) && ClubHelper::isClubSupervised($current_user['club_id'], $club_id));
    if (!$can_access) {
        header('Location: index.php?page=clubs&error=' . urlencode('No tiene permisos para ver este club'));
        exit;
    }
} else {
    Auth::requireRole(['admin_general', 'admin_torneo']);
}

try {
    // Obtener datos del club
    $stmt = DB::pdo()->prepare("SELECT * FROM clubes WHERE id = ?");
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$club) {
        header('Location: index.php?page=clubs&error=' . urlencode('Club no encontrado'));
        exit;
    }
    
    // Obtener afiliados del club
    $stmt = DB::pdo()->prepare("
        SELECT 
            u.id,
            u.cedula,
            u.nombre,
            u.email,
            u.celular,
            u.sexo,
            u.fechnac,
            u.created_at,
            COUNT(DISTINCT i.torneo_id) as total_torneos
        FROM usuarios u
        LEFT JOIN inscripciones i ON i.cedula = u.cedula
        WHERE u.club_id = ? AND u.role = 'usuario' AND u.status = 1
        GROUP BY u.id
        ORDER BY u.nombre ASC
    ");
    $stmt->execute([$club_id]);
    $club_afiliados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas del club
    $stmt = DB::pdo()->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_afiliados,
            SUM(CASE WHEN u.sexo = 'M' THEN 1 ELSE 0 END) as hombres,
            SUM(CASE WHEN u.sexo = 'F' THEN 1 ELSE 0 END) as mujeres
        FROM usuarios u
        WHERE u.club_id = ? AND u.role = 'usuario' AND u.status = 1
    ");
    $stmt->execute([$club_id]);
    $club_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    header('Location: index.php?page=clubs&error=' . urlencode('Error al cargar datos: ' . $e->getMessage()));
    exit;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php?page=clubs" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="fas fa-arrow-left me-1"></i>Volver a la Lista
            </a>
            <h2 class="mb-0">
                <i class="fas fa-building me-2"></i><?= htmlspecialchars($club['nombre']) ?>
            </h2>
            <small class="text-muted">Lista de Afiliados</small>
        </div>
    </div>
    
    <!-- Estadísticas del Club -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center bg-primary text-white">
                <div class="card-body">
                    <h2 class="mb-0"><?= (int)($club_stats['total_afiliados'] ?? 0) ?></h2>
                    <small>Total Afiliados</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center bg-info text-white">
                <div class="card-body">
                    <h2 class="mb-0"><?= (int)($club_stats['hombres'] ?? 0) ?></h2>
                    <small>Hombres</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center bg-danger text-white">
                <div class="card-body">
                    <h2 class="mb-0"><?= (int)($club_stats['mujeres'] ?? 0) ?></h2>
                    <small>Mujeres</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista de Afiliados -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Afiliados del Club</h5>
        </div>
        <div class="card-body">
            <?php if (empty($club_afiliados)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Este club no tiene afiliados registrados</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID Usuario</th>
                                <th>Nombre</th>
                                <th>Sexo</th>
                                <th>Email</th>
                                <th>Celular</th>
                                <th>Torneos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($club_afiliados as $afiliado): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($afiliado['id'] ?? 'N/A') ?></code></td>
                                    <td><strong><?= htmlspecialchars($afiliado['nombre']) ?></strong></td>
                                    <td>
                                        <?php if ($afiliado['sexo'] === 'M'): ?>
                                            <span class="badge bg-primary">Masculino</span>
                                        <?php elseif ($afiliado['sexo'] === 'F'): ?>
                                            <span class="badge bg-danger">Femenino</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($afiliado['sexo'] ?? 'N/A') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($afiliado['email'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($afiliado['celular'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= (int)($afiliado['total_torneos'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <a href="index.php?page=clubs&action=afiliado_detail&club_id=<?= $club['id'] ?>&user_id=<?= $afiliado['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>Ver Detalle
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

