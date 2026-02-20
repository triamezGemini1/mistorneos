<?php
/**
 * Página de Eventos por Entidad/Club
 * Muestra torneos programados según la entidad/club del usuario autenticado
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Incluir función getEntidadesOptions si no está disponible
if (!function_exists('getEntidadesOptions')) {
    require_once __DIR__ . '/../modules/users.php';
}

// Verificar autenticación
Auth::requireLogin();

$current_user = Auth::user();
$user_role = $current_user['role'] ?? 'usuario';
$user_entidad = (int)($current_user['entidad'] ?? 0);
$user_club_id = (int)($current_user['club_id'] ?? 0);

$pdo = DB::pdo();
$base_url = app_base_url();

// Obtener nombre de la entidad del usuario
$entidad_nombre = 'No especificada';
if ($user_entidad > 0) {
    try {
        $stmt = $pdo->prepare("SELECT nombre FROM entidad WHERE id = ? LIMIT 1");
        $stmt->execute([$user_entidad]);
        $entidad_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($entidad_data && !empty($entidad_data['nombre'])) {
            $entidad_nombre = $entidad_data['nombre'];
        }
    } catch (Exception $e) {
        error_log("Error obteniendo nombre de entidad: " . $e->getMessage());
    }
}

// Obtener nombre del club del usuario
$club_nombre = null;
if ($user_club_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT nombre FROM clubes WHERE id = ? LIMIT 1");
        $stmt->execute([$user_club_id]);
        $club_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($club_data && !empty($club_data['nombre'])) {
            $club_nombre = $club_data['nombre'];
        }
    } catch (Exception $e) {
        error_log("Error obteniendo nombre de club: " . $e->getMessage());
    }
}

// Determinar filtro según el rol del usuario
$torneos = [];
$filtro_aplicado = '';
$clubes_ids = [];
$mostrar_todos = false; // Flag para mostrar todos sin filtro de clubes

if ($user_role === 'admin_general') {
    // Admin general: mostrar todos los torneos, pero puede filtrar por entidad
    $entidad_filtro = isset($_GET['entidad']) ? (int)$_GET['entidad'] : null;
    
    if ($entidad_filtro && $entidad_filtro > 0) {
        // Obtener clubes de la entidad seleccionada
        $stmt = $pdo->prepare("
            SELECT DISTINCT club_id 
            FROM usuarios 
            WHERE role IN ('admin_club', 'admin_torneo') 
              AND entidad = ? 
              AND club_id IS NOT NULL 
              AND club_id > 0
        ");
        $stmt->execute([$entidad_filtro]);
        $clubes_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'club_id');
        
        // Obtener nombre de la entidad filtrada
        $stmt_ent = $pdo->prepare("SELECT nombre FROM entidad WHERE id = ? LIMIT 1");
        $stmt_ent->execute([$entidad_filtro]);
        $ent_filtro_data = $stmt_ent->fetch(PDO::FETCH_ASSOC);
        $ent_filtro_nombre = $ent_filtro_data['nombre'] ?? 'N/A';
        
        if (!empty($clubes_ids)) {
            $filtro_aplicado = "de la entidad: " . htmlspecialchars($ent_filtro_nombre);
        } else {
            $filtro_aplicado = "No hay clubes en la entidad: " . htmlspecialchars($ent_filtro_nombre);
        }
    } else {
        // Sin filtro: todos los torneos
        $mostrar_todos = true;
        $filtro_aplicado = "Todos los torneos (sin filtro de entidad)";
    }
} elseif ($user_role === 'admin_club' || $user_role === 'admin_torneo') {
    // Admin club/torneo: mostrar torneos de su entidad (todos los clubes de esa entidad)
    if ($user_entidad > 0) {
        // Obtener todos los clubes de la misma entidad
        $stmt = $pdo->prepare("
            SELECT DISTINCT club_id 
            FROM usuarios 
            WHERE role IN ('admin_club', 'admin_torneo') 
              AND entidad = ? 
              AND club_id IS NOT NULL 
              AND club_id > 0
        ");
        $stmt->execute([$user_entidad]);
        $clubes_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'club_id');
        
        $filtro_aplicado = "de la entidad: $entidad_nombre";
    } else {
        // Si no tiene entidad, solo su club
        if ($user_club_id > 0) {
            $clubes_ids = [$user_club_id];
            $filtro_aplicado = "de su club: " . ($club_nombre ?? 'N/A');
        } else {
            $filtro_aplicado = "No tiene entidad o club asignado";
        }
    }
} else {
    // Usuario normal: mostrar torneos de su club o entidad
    if ($user_club_id > 0) {
        // Prioridad: torneos de su club específico
        $clubes_ids = [$user_club_id];
        $filtro_aplicado = "de su club: " . ($club_nombre ?? 'N/A');
    } elseif ($user_entidad > 0) {
        // Si no tiene club, mostrar de su entidad
        $stmt = $pdo->prepare("
            SELECT DISTINCT club_id 
            FROM usuarios 
            WHERE role IN ('admin_club', 'admin_torneo') 
              AND entidad = ? 
              AND club_id IS NOT NULL 
              AND club_id > 0
        ");
        $stmt->execute([$user_entidad]);
        $clubes_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'club_id');
        
        $filtro_aplicado = "de la entidad: $entidad_nombre";
    } else {
        $filtro_aplicado = "No tiene club ni entidad asignada";
    }
}

// Obtener torneos según el filtro aplicado
try {
    if ($mostrar_todos) {
        // Admin general sin filtro: todos los torneos
        $sql = "
            SELECT 
                t.*,
                c.nombre as club_nombre,
                c.delegado as club_delegado,
                c.telefono as club_telefono,
                (SELECT COUNT(*) FROM inscritos WHERE torneo_id = t.id AND estatus != 'retirado') as total_inscritos,
                e.nombre as entidad_nombre
            FROM tournaments t
            LEFT JOIN clubes c ON t.club_responsable = c.id
            LEFT JOIN usuarios u_admin ON u_admin.club_id = c.id AND u_admin.role IN ('admin_club', 'admin_torneo')
            LEFT JOIN entidad e ON u_admin.entidad = e.id
            WHERE t.estatus = 1 AND t.fechator >= CURDATE()
            ORDER BY t.fechator ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } elseif (!empty($clubes_ids)) {
        // Filtrar por lista de clubes
        $placeholders = str_repeat('?,', count($clubes_ids) - 1) . '?';
        $sql = "
            SELECT 
                t.*,
                c.nombre as club_nombre,
                c.delegado as club_delegado,
                c.telefono as club_telefono,
                (SELECT COUNT(*) FROM inscritos WHERE torneo_id = t.id AND estatus != 'retirado') as total_inscritos,
                e.nombre as entidad_nombre
            FROM tournaments t
            LEFT JOIN clubes c ON t.club_responsable = c.id
            LEFT JOIN usuarios u_admin ON u_admin.club_id = c.id AND u_admin.role IN ('admin_club', 'admin_torneo')
            LEFT JOIN entidad e ON u_admin.entidad = e.id
            WHERE t.estatus = 1 
              AND t.fechator >= CURDATE()
              AND t.club_responsable IN ($placeholders)
            ORDER BY t.fechator ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($clubes_ids);
    } else {
        // Sin clubes: lista vacía
        $torneos = [];
        $stmt = null;
    }
    
    if ($stmt) {
        $torneos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error obteniendo torneos: " . $e->getMessage());
    $torneos = [];
}

// Obtener lista de entidades para filtro (solo para admin_general)
$entidades_options = [];
if ($user_role === 'admin_general') {
    try {
        $entidades_options = getEntidadesOptions();
    } catch (Exception $e) {
        error_log("Error obteniendo entidades: " . $e->getMessage());
    }
}

// Función helper para formatear fecha
function formatearFecha($fecha) {
    if (empty($fecha)) return 'Por definir';
    $timestamp = strtotime($fecha);
    if (!$timestamp) return $fecha;
    
    $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
              'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    return $dias[date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' de ' . 
           $meses[date('n', $timestamp)] . ' de ' . date('Y', $timestamp);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos de Mi Entidad/Club - Mi Torneo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .evento-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: box-shadow 0.3s;
        }
        .evento-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .fecha-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            text-align: center;
            min-width: 120px;
        }
        .info-badge {
            background-color: #e9ecef;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="row">
            <div class="col-12">
                <h1 class="h3 mb-3">
                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                    Eventos Programados
                </h1>
                <p class="text-muted mb-4">
                    Mostrando torneos <strong><?= htmlspecialchars($filtro_aplicado) ?></strong>
                </p>
            </div>
        </div>

        <!-- Filtro por entidad (solo admin_general) -->
        <?php if ($user_role === 'admin_general' && !empty($entidades_options)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Filtrar por Entidad:</label>
                                <select name="entidad" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- Todas las entidades --</option>
                                    <?php foreach ($entidades_options as $ent): ?>
                                        <option value="<?= $ent['codigo'] ?>" 
                                                <?= (isset($_GET['entidad']) && $_GET['entidad'] == $ent['codigo']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ent['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <a href="<?= $base_url ?>/public/eventos_mi_entidad.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Limpiar Filtro
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lista de Eventos -->
        <div class="row">
            <?php if (empty($torneos)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay eventos programados <?= htmlspecialchars($filtro_aplicado) ?> en este momento.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($torneos as $torneo): ?>
                    <div class="col-12">
                        <div class="evento-card">
                            <div class="row align-items-center">
                                <div class="col-md-2 text-center mb-3 mb-md-0">
                                    <div class="fecha-badge">
                                        <div class="fw-bold" style="font-size: 1.25rem;">
                                            <?= date('d', strtotime($torneo['fechator'])) ?>
                                        </div>
                                        <div style="font-size: 0.875rem;">
                                            <?= date('M', strtotime($torneo['fechator'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <h5 class="mb-2">
                                        <?= htmlspecialchars($torneo['nombre']) ?>
                                    </h5>
                                    <div class="mb-2">
                                        <span class="info-badge me-2">
                                            <i class="fas fa-building me-1"></i>
                                            <?= htmlspecialchars($torneo['club_nombre'] ?? 'N/A') ?>
                                        </span>
                                        <?php if (!empty($torneo['entidad_nombre'])): ?>
                                        <span class="info-badge me-2">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?= htmlspecialchars($torneo['entidad_nombre']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($torneo['lugar'])): ?>
                                        <span class="info-badge me-2">
                                            <i class="fas fa-location-dot me-1"></i>
                                            <?= htmlspecialchars($torneo['lugar']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted mb-0 small">
                                        <i class="far fa-calendar me-1"></i>
                                        <?= formatearFecha($torneo['fechator']) ?>
                                    </p>
                                    <?php if (!empty($torneo['club_delegado']) || !empty($torneo['club_telefono'])): ?>
                                    <p class="text-muted mb-0 small mt-1">
                                        <?php if (!empty($torneo['club_delegado'])): ?>
                                            <i class="fas fa-user me-1"></i>Delegado: <?= htmlspecialchars($torneo['club_delegado']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($torneo['club_telefono'])): ?>
                                            <span class="ms-2">
                                                <i class="fas fa-phone me-1"></i><?= htmlspecialchars($torneo['club_telefono']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3 text-md-end">
                                    <div class="mb-2">
                                        <span class="badge bg-primary">
                                            <i class="fas fa-users me-1"></i>
                                            <?= number_format($torneo['total_inscritos'] ?? 0) ?> inscritos
                                        </span>
                                    </div>
                                    <?php if ($torneo['costo'] > 0): ?>
                                    <div class="mb-2">
                                        <span class="badge bg-success">
                                            <i class="fas fa-dollar-sign me-1"></i>
                                            Bs. <?= number_format($torneo['costo'], 2, ',', '.') ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="mt-3">
                                        <a href="<?= $base_url ?>/public/torneo_info.php?id=<?= $torneo['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-info-circle me-1"></i>Ver Detalles
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Información adicional -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Información sobre los Eventos
                        </h6>
                        <p class="card-text mb-0 small">
                            <?php if ($user_role === 'admin_club' || $user_role === 'admin_torneo'): ?>
                                Se muestran todos los eventos de torneos organizados por clubes de su entidad: <strong><?= htmlspecialchars($entidad_nombre) ?></strong>
                            <?php elseif ($user_club_id > 0): ?>
                                Se muestran los eventos de torneos organizados por su club: <strong><?= htmlspecialchars($club_nombre ?? 'N/A') ?></strong>
                            <?php elseif ($user_entidad > 0): ?>
                                Se muestran los eventos de torneos organizados por clubes de su entidad: <strong><?= htmlspecialchars($entidad_nombre) ?></strong>
                            <?php else: ?>
                                No tiene una entidad o club asignado. Contacte al administrador para obtener acceso a eventos específicos.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>
