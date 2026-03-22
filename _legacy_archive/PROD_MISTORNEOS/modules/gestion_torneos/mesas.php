<?php
/**
 * Vista: Mesas de una Ronda
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-2">
                <i class="fas fa-chess text-primary"></i> Asignaciones - Mesas Ronda <?php echo $ronda; ?>
                <small class="text-muted">- <?php echo htmlspecialchars($torneo['nombre']); ?></small>
            </h1>
            <p class="text-muted mb-0">Todas las mesas asignadas para esta ronda. Use el selector para ir a una mesa en particular.</p>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>">Gestión de Torneos</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
                    <li class="breadcrumb-item active">Ronda <?php echo $ronda; ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" 
               class="btn btn-secondary btn-lg" 
               style="margin-right: 10px;">
                <i class="fas fa-arrow-left mr-2"></i> Volver al Panel de Control
            </a>
            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=rondas&torneo_id=<?php echo $torneo['id']; ?>" 
               class="btn btn-info btn-lg">
                <i class="fas fa-layer-group mr-2"></i> Ver Todas las Rondas
            </a>
        </div>
    </div>

    <?php if (!empty($es_operador_ambito) && !empty($mesas)): ?>
    <div class="alert alert-info py-2 mb-3">
        <i class="fas fa-user-cog me-2"></i>
        <strong>Su ámbito:</strong> solo se muestran las mesas asignadas a usted para esta ronda (<?php echo count($mesas); ?> mesas).
    </div>
    <?php endif; ?>

    <?php if (empty($mesas)): ?>
        <div class="alert alert-<?php echo !empty($es_operador_ambito) ? 'warning' : 'info'; ?>">
            <i class="fas fa-info-circle mr-2"></i>
            <?php if (!empty($es_operador_ambito)): ?>
                No tiene mesas asignadas para esta ronda. Contacte al administrador del torneo para que le asigne su ámbito de mesas.
            <?php else: ?>
                No hay mesas asignadas para esta ronda aún.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php
        // Separar mesas normales de BYE
        $mesas_normales = [];
        $mesas_bye = [];
        foreach ($mesas as $mesa_data) {
            if (isset($mesa_data['numero']) && $mesa_data['numero'] !== 'BYE') {
                $mesas_normales[] = $mesa_data;
            } elseif (isset($mesa_data['BYE'])) {
                $mesas_bye = $mesa_data['BYE'];
            }
        }
        usort($mesas_normales, function($a, $b) {
            return ($a['numero'] ?? 0) <=> ($b['numero'] ?? 0);
        });
        ?>

        <?php if (!empty($mesas_normales)): ?>
        <div class="card mb-3">
            <div class="card-body py-3">
                <label for="ir-a-mesa-select" class="form-label fw-bold me-2">Ir a mesa:</label>
                <select id="ir-a-mesa-select" class="form-select form-select-lg d-inline-block w-auto" onchange="irAMesa(this.value)">
                    <option value="">— Todas las mesas (<?php echo count($mesas_normales); ?>) —</option>
                    <?php foreach ($mesas_normales as $md): $n = (int)($md['numero'] ?? 0); if ($n <= 0) continue; ?>
                    <option value="mesa-<?php echo $n; ?>">Mesa <?php echo $n; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($mesas_normales)): ?>
            <div class="row">
                <?php foreach ($mesas_normales as $mesa_data): ?>
                    <?php 
                    $num_mesa = $mesa_data['numero'] ?? 0;
                    $jugadores = $mesa_data['jugadores'] ?? [];
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4" id="mesa-<?php echo (int)$num_mesa; ?>">
                        <div class="card">
                            <div class="card-header" style="background-color: #e3f2fd; color: #1565c0;">
                                <h5 class="mb-0">
                                    <i class="fas fa-chess-board mr-2"></i> Mesa <?php echo $num_mesa; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($jugadores) === 4): ?>
                                    <?php
                                    // Agrupar por pareja (secuencias 1-2 son Pareja A, 3-4 son Pareja B)
                                    $pareja_a = array_filter($jugadores, function($j) { 
                                        return is_array($j) && isset($j['secuencia']) && in_array((int)$j['secuencia'], [1, 2]); 
                                    });
                                    $pareja_b = array_filter($jugadores, function($j) { 
                                        return is_array($j) && isset($j['secuencia']) && in_array((int)$j['secuencia'], [3, 4]); 
                                    });
                                    ?>
                                    <div class="mb-3">
                                        <strong class="text-primary">Pareja A:</strong>
                                        <ul class="list-unstyled ml-3">
                                            <?php foreach ($pareja_a as $jugador): ?>
                                                <?php if (is_array($jugador)): ?>
                                                    <li>
                                                        <i class="fas fa-user mr-1"></i>
                                                        <?php echo htmlspecialchars($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'); ?>
                                                        <?php if (!empty($jugador['club_nombre'])): ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($jugador['club_nombre']); ?>)</small>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div>
                                        <strong class="text-success">Pareja B:</strong>
                                        <ul class="list-unstyled ml-3">
                                            <?php foreach ($pareja_b as $jugador): ?>
                                                <?php if (is_array($jugador)): ?>
                                                    <li>
                                                        <i class="fas fa-user mr-1"></i>
                                                        <?php echo htmlspecialchars($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'); ?>
                                                        <?php if (!empty($jugador['club_nombre'])): ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($jugador['club_nombre']); ?>)</small>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    
                                    <?php
                                    // Mostrar resultados si existen
                                    $tiene_resultados = false;
                                    foreach ($jugadores as $j) {
                                        if (is_array($j) && (!empty($j['resultado1']) || !empty($j['resultado2']))) {
                                            $tiene_resultados = true;
                                            break;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($tiene_resultados && !empty($jugadores) && is_array($jugadores[0])): ?>
                                        <hr>
                                        <div class="small">
                                            <strong>Resultados:</strong><br>
                                            <?php
                                            $primer_jugador = reset($jugadores);
                                            $resultado1 = (int)($primer_jugador['resultado1'] ?? 0);
                                            $resultado2 = (int)($primer_jugador['resultado2'] ?? 0);
                                            ?>
                                            Pareja A: <?php echo $resultado1; ?> | Pareja B: <?php echo $resultado2; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-muted">Mesas incompletas (<?php echo count($jugadores); ?> jugadores)</p>
                                    <ul class="list-unstyled">
                                        <?php foreach ($jugadores as $jugador): ?>
                                            <?php if (is_array($jugador)): ?>
                                                <li>
                                                    <i class="fas fa-user mr-1"></i>
                                                    <?php echo htmlspecialchars($jugador['nombre'] ?? $jugador['nombre_completo'] ?? 'Sin nombre'); ?>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($mesas_bye)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-ban mr-2"></i> Jugadores BYE (Descanso)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($mesas_bye as $jugador): ?>
                                    <div class="col-md-3 mb-2">
                                        <i class="fas fa-user mr-1"></i>
                                        <?php echo htmlspecialchars($jugador['nombre']); ?>
                                        <?php if (!empty($jugador['club_nombre'])): ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($jugador['club_nombre']); ?>)</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function() {
    function irAMesa(id) {
        if (!id) return;
        var el = document.getElementById(id);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    window.irAMesa = irAMesa;
})();
</script>