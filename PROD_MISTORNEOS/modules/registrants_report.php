<?php
/**
 * Reporte de Inscritos
 * Página dedicada para generar reportes con filtros y opciones de exportación
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Permitir acceso a usuarios registrados y administradores
$user = Auth::user();
if (!$user) {
    header('Location: ' . app_base_url() . '/public/login.php');
    exit;
}
Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club', 'usuario']);

$user_role = $user['role'] ?? '';
$user_club_id = $user['club_id'] ?? null;
$is_admin_torneo = ($user_role === 'admin_torneo');
$is_admin_club = ($user_role === 'admin_club');

// Obtener filtros
$filter_torneo = $_GET['filter_torneo'] ?? '';
$filter_clubs = $_GET['filter_clubs'] ?? [];
if (is_string($filter_clubs)) {
    $filter_clubs = [$filter_clubs];
}
$filter_sexo = $_GET['filter_sexo'] ?? '';
$search = $_GET['search'] ?? '';

// Obtener listas para filtros
$tournaments_filter = [];
$clubs_filter = [];

try {
    require_once __DIR__ . '/../lib/ClubHelper.php';
    
    // Torneos disponibles según rol
    if ($user_role === 'admin_general') {
        $stmt = DB::pdo()->query("SELECT id, nombre, fechator FROM tournaments ORDER BY fechator DESC");
        $tournaments_filter = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($is_admin_torneo && $user_club_id) {
        $stmt = DB::pdo()->prepare("SELECT id, nombre, fechator FROM tournaments WHERE club_responsable = ? ORDER BY fechator DESC");
        $stmt->execute([$user_club_id]);
        $tournaments_filter = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = DB::pdo()->query("SELECT id, nombre, fechator FROM tournaments ORDER BY fechator DESC");
        $tournaments_filter = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Clubes disponibles según rol
    if ($user_role === 'admin_general') {
        $stmt = DB::pdo()->query("SELECT id, nombre FROM clubes WHERE estatus = 1 ORDER BY nombre ASC");
        $clubs_filter = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($is_admin_torneo || $is_admin_club) {
        $clubs_filter = ClubHelper::getClubesSupervisedWithData($user_club_id);
    }
} catch (Exception $e) {
    error_log("Error al cargar filtros: " . $e->getMessage());
}

// Obtener información del torneo y club seleccionados para el encabezado
$torneo_info = null;
$club_info = null;
if (!empty($filter_torneo)) {
    $stmt = DB::pdo()->prepare("SELECT nombre, fechator FROM tournaments WHERE id = ?");
    $stmt->execute([(int)$filter_torneo]);
    $torneo_info = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!empty($filter_clubs) && count($filter_clubs) === 1) {
    $stmt = DB::pdo()->prepare("SELECT nombre FROM clubes WHERE id = ?");
    $stmt->execute([(int)$filter_clubs[0]]);
    $club_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Cargar estadísticas si hay filtros aplicados
$torneo_stats = null;
$resumen_por_club = [];
if (!empty($filter_torneo)) {
    try {
        $stmt = DB::pdo()->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN u.sexo = 1 OR UPPER(u.sexo) = 'M' THEN 1 ELSE 0 END) as hombres,
                SUM(CASE WHEN u.sexo = 2 OR UPPER(u.sexo) = 'F' THEN 1 ELSE 0 END) as mujeres
            FROM inscritos r
            LEFT JOIN usuarios u ON r.id_usuario = u.id
            WHERE r.torneo_id = ? AND r.estatus = 'confirmado'
        ");
        $stmt->execute([(int)$filter_torneo]);
        $torneo_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($torneo_stats) {
            $torneo_stats['total'] = (int)($torneo_stats['total'] ?? 0);
            $torneo_stats['hombres'] = (int)($torneo_stats['hombres'] ?? 0);
            $torneo_stats['mujeres'] = (int)($torneo_stats['mujeres'] ?? 0);
        }
        
        // Resumen por club
        $stmt = DB::pdo()->prepare("
            SELECT 
                c.id,
                c.nombre as club_nombre,
                COUNT(r.id) as total_inscritos,
                SUM(CASE WHEN u.sexo = 1 OR UPPER(u.sexo) = 'M' THEN 1 ELSE 0 END) as hombres,
                SUM(CASE WHEN u.sexo = 2 OR UPPER(u.sexo) = 'F' THEN 1 ELSE 0 END) as mujeres
            FROM clubes c
            LEFT JOIN inscritos r ON c.id = r.id_club AND r.torneo_id = ? AND r.estatus = 'confirmado'
            LEFT JOIN usuarios u ON r.id_usuario = u.id
            WHERE c.estatus = 1
            GROUP BY c.id, c.nombre
            HAVING total_inscritos > 0
            ORDER BY total_inscritos DESC, c.nombre ASC
        ");
        $stmt->execute([(int)$filter_torneo]);
        $resumen_por_club = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error al cargar estadísticas: " . $e->getMessage());
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-file-alt me-2"></i>Reporte de Inscritos
                    </h1>
                    <p class="text-muted mb-0">Genera reportes de jugadores inscritos con filtros personalizados</p>
                </div>
                <div>
                    <a href="index.php?page=registrants" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver a Inscritos
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Panel de Filtros y Exportación -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>Filtros y Reportes
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="index.php" id="filterForm">
            <input type="hidden" name="page" value="registrants_report">
            
            <div class="row g-3">
                <!-- Filtro por Torneo -->
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-trophy me-1"></i>Torneo</label>
                    <select name="filter_torneo" class="form-select" id="filterTorneo" onchange="this.form.submit()">
                        <option value="">-- Todos los Torneos --</option>
                        <?php foreach ($tournaments_filter as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $filter_torneo == $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre']) ?> - <?= date('d/m/Y', strtotime($t['fechator'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filtro por Clubs -->
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-building me-1"></i>Club(es)</label>
                    <select name="filter_clubs[]" class="form-select" id="filterClubs" multiple size="4">
                        <?php foreach ($clubs_filter as $club): ?>
                            <option value="<?= $club['id'] ?>" <?= in_array($club['id'], $filter_clubs) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($club['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Mantén presionado Ctrl (Cmd en Mac) para seleccionar múltiples clubes</small>
                </div>
                
                <!-- Filtro por Sexo -->
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-venus-mars me-1"></i>Sexo</label>
                    <select name="filter_sexo" class="form-select" id="filterSexo">
                        <option value="">-- Todos --</option>
                        <option value="M" <?= $filter_sexo === 'M' ? 'selected' : '' ?>>Masculino</option>
                        <option value="F" <?= $filter_sexo === 'F' ? 'selected' : '' ?>>Femenino</option>
                        <option value="O" <?= $filter_sexo === 'O' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>
                
                <!-- Búsqueda -->
                <div class="col-md-9">
                    <label class="form-label"><i class="fas fa-search me-1"></i>Buscar</label>
                    <input type="text" name="search" class="form-control" id="searchInput" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Buscar por nombre...">
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Aplicar Filtros
                        </button>
                        <a href="index.php?page=registrants_report" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Limpiar Filtros
                        </a>
                        
                        <div class="vr"></div>
                        
                        <button type="button" class="btn btn-success" onclick="exportarExcel()" 
                                id="btnExportarExcel" disabled>
                            <i class="fas fa-file-excel me-2"></i>Exportar Excel
                        </button>
                        <button type="button" class="btn btn-danger" onclick="exportarPDF()"
                                id="btnExportarPDF" disabled>
                            <i class="fas fa-file-pdf me-2"></i>Exportar PDF
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Encabezado del Reporte (Título y Subtítulo) -->
<?php if ($torneo_info || $club_info): ?>
<div class="card mb-4">
    <div class="card-body text-center">
        <?php if ($torneo_info): ?>
            <h2 class="mb-2"><?= htmlspecialchars($torneo_info['nombre']) ?></h2>
            <?php if ($torneo_info['fechator']): ?>
                <p class="text-muted mb-1">Fecha: <?= date('d/m/Y', strtotime($torneo_info['fechator'])) ?></p>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($club_info): ?>
            <h4 class="text-muted mt-2 mb-0"><?= htmlspecialchars($club_info['nombre']) ?></h4>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Estadísticas del Torneo -->
<?php if (!empty($filter_torneo) && $torneo_stats): ?>
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h3 class="text-primary mb-0"><?= number_format($torneo_stats['total']) ?></h3>
                <p class="text-muted mb-0">Total Inscritos</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info">
            <div class="card-body text-center">
                <h3 class="text-info mb-0"><?= number_format($torneo_stats['hombres']) ?></h3>
                <p class="text-muted mb-0">Hombres</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h3 class="text-danger mb-0"><?= number_format($torneo_stats['mujeres']) ?></h3>
                <p class="text-muted mb-0">Mujeres</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Resumen por Club -->
<?php if (!empty($filter_torneo) && !empty($resumen_por_club)): ?>
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0">
            <i class="fas fa-chart-bar me-2"></i>Resumen por Club
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Club</th>
                        <th class="text-center">Total Inscritos</th>
                        <th class="text-center">Hombres</th>
                        <th class="text-center">Mujeres</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumen_por_club as $club): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($club['club_nombre']) ?></strong></td>
                            <td class="text-center"><span class="badge bg-primary"><?= number_format($club['total_inscritos']) ?></span></td>
                            <td class="text-center"><span class="badge bg-info"><?= number_format($club['hombres']) ?></span></td>
                            <td class="text-center"><span class="badge bg-danger"><?= number_format($club['mujeres']) ?></span></td>
                            <td class="text-center">
                                <button onclick="exportarClubPDF(<?= $club['id'] ?>)" class="btn btn-sm btn-outline-danger" title="Exportar PDF">
                                    <i class="fas fa-file-pdf me-1"></i>PDF
                                </button>
                                <button onclick="exportarClubExcel(<?= $club['id'] ?>)" class="btn btn-sm btn-outline-success" title="Exportar Excel">
                                    <i class="fas fa-file-excel me-1"></i>Excel
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Función para exportar a PDF
function exportarPDF() {
    const params = new URLSearchParams();
    
    <?php if (!empty($filter_torneo)): ?>
    params.append('torneo_id', '<?= (int)$filter_torneo ?>');
    <?php endif; ?>
    
    <?php if (!empty($filter_clubs)): ?>
    <?php foreach ($filter_clubs as $club_id): ?>
    params.append('club_id[]', '<?= (int)$club_id ?>');
    <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($filter_sexo)): ?>
    params.append('sexo', '<?= htmlspecialchars($filter_sexo) ?>');
    <?php endif; ?>
    
    <?php if (!empty($search)): ?>
    params.append('q', '<?= htmlspecialchars($search) ?>');
    <?php endif; ?>
    
    window.location.href = 'modules/registrants/report_pdf.php?' + params.toString();
}

// Función para exportar a Excel
function exportarExcel() {
    const params = new URLSearchParams();
    
    <?php if (!empty($filter_torneo)): ?>
    params.append('torneo_id', '<?= (int)$filter_torneo ?>');
    <?php endif; ?>
    
    <?php if (!empty($filter_clubs)): ?>
    <?php foreach ($filter_clubs as $club_id): ?>
    params.append('club_id[]', '<?= (int)$club_id ?>');
    <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($filter_sexo)): ?>
    params.append('sexo', '<?= htmlspecialchars($filter_sexo) ?>');
    <?php endif; ?>
    
    <?php if (!empty($search)): ?>
    params.append('q', '<?= htmlspecialchars($search) ?>');
    <?php endif; ?>
    
    window.location.href = 'modules/registrants/export_excel.php?' + params.toString();
}

// Función para exportar PDF por club
function exportarClubPDF(club_id) {
    const params = new URLSearchParams();
    params.append('torneo_id', '<?= (int)$filter_torneo ?>');
    params.append('club_id', club_id);
    window.location.href = 'modules/registrants/report_pdf.php?' + params.toString();
}

// Función para exportar Excel por club
function exportarClubExcel(club_id) {
    const params = new URLSearchParams();
    params.append('torneo_id', '<?= (int)$filter_torneo ?>');
    params.append('club_id[]', club_id);
    window.location.href = 'modules/registrants/export_excel.php?' + params.toString();
}

// Habilitar botones de exportación si hay filtros aplicados
<?php if (!empty($filter_torneo)): ?>
document.getElementById('btnExportarPDF').disabled = false;
document.getElementById('btnExportarExcel').disabled = false;
<?php endif; ?>
</script>









