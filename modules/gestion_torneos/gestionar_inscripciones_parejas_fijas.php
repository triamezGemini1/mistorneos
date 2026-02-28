<?php
/**
 * Vista: Gestionar Inscripciones de Parejas Fijas (modalidad 4)
 * Listado por club y formulario para inscribir pareja completa (2 jugadores). No se permiten inscripciones incompletas.
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$solo_inscribir = !empty($solo_inscribir);

require_once __DIR__ . '/../../config/csrf.php';
$csrf_token = class_exists('CSRF') ? CSRF::token() : '';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=index">Gestión de Torneos</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo (int)$torneo['id']; ?>"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
            <li class="breadcrumb-item active"><?php echo $solo_inscribir ? 'Inscribir pareja' : 'Gestionar Inscripciones Parejas Fijas'; ?></li>
        </ol>
    </nav>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2 class="h4 mb-1"><i class="fas fa-handshake text-primary me-2"></i><?php echo $solo_inscribir ? 'Inscribir pareja en sitio' : 'Gestionar Inscripciones Parejas Fijas'; ?></h2>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($torneo['nombre']); ?></p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <?php if (!$solo_inscribir): ?>
                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=inscribir_pareja_sitio&torneo_id=<?php echo $torneo['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Inscribir pareja</a>
                    <?php endif; ?>
                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Panel</a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Formulario nueva pareja -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <strong>Nueva pareja</strong> — Nombre de equipo, código generado automático, número por club. Exactamente 2 jugadores (no se permiten inscripciones incompletas).
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=guardar_pareja_fija&torneo_id=<?php echo (int)$torneo['id']; ?>">
                <?php if ($csrf_token): ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Club *</label>
                        <select name="id_club" class="form-select" required>
                            <option value="">Seleccionar club...</option>
                            <?php foreach ($clubes as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nombre de la pareja (equipo) *</label>
                        <input type="text" name="nombre_equipo" class="form-control" required maxlength="100" placeholder="Ej: Los Duendes">
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-5">
                        <label class="form-label">Jugador 1 *</label>
                        <select name="id_usuario_1" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($jugadores_disponibles as $j): ?>
                            <option value="<?php echo (int)$j['id']; ?>"><?php echo htmlspecialchars($j['nombre']); ?> (<?php echo htmlspecialchars($j['cedula'] ?? ''); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Jugador 2 *</label>
                        <select name="id_usuario_2" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($jugadores_disponibles as $j): ?>
                            <option value="<?php echo (int)$j['id']; ?>"><?php echo htmlspecialchars($j['nombre']); ?> (<?php echo htmlspecialchars($j['cedula'] ?? ''); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Guardar pareja</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!$solo_inscribir && !empty($parejas)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header">Parejas inscritas (por club)</div>
        <div class="card-body p-0">
            <?php
            $por_club = [];
            foreach ($parejas as $p) {
                $cid = $p['id_club'];
                if (!isset($por_club[$cid])) {
                    $por_club[$cid] = ['id_club' => $cid, 'parejas' => []];
                }
                $por_club[$cid]['parejas'][] = $p;
            }
            foreach ($por_club as $grupo):
                $nombre_club = $grupo['parejas'][0]['nombre_club'] ?? 'Sin club';
            ?>
            <div class="border-bottom p-3">
                <strong class="text-primary"><?php echo htmlspecialchars($nombre_club); ?></strong>
                <ul class="list-unstyled mb-0 mt-2">
                    <?php foreach ($grupo['parejas'] as $pa): ?>
                    <li class="py-1">
                        <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($pa['codigo_equipo']); ?></span>
                        <span class="fw-bold"><?php echo htmlspecialchars($pa['nombre_equipo']); ?></span>
                        (número <?php echo (int)$pa['numero']; ?>)
                        — <?php echo htmlspecialchars($pa['jugadores'][0]['nombre'] ?? ''); ?> / <?php echo htmlspecialchars($pa['jugadores'][1]['nombre'] ?? ''); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif (!$solo_inscribir): ?>
    <div class="alert alert-info">Aún no hay parejas inscritas. Use el formulario superior para inscribir la primera pareja.</div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
