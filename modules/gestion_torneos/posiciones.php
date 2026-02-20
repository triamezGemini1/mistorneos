<?php
/**
 * Vista: Tabla de Posiciones
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
?>

<?php if (!$use_standalone): ?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-2">
                <i class="fas fa-trophy text-primary"></i> Tabla de Posiciones
                <small class="text-muted">- <?php echo htmlspecialchars($torneo['nombre']); ?></small>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>">Gestión de Torneos</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
                    <li class="breadcrumb-item active">Posiciones</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <?php
            $from_pos = $_GET['from'] ?? '';
            if ($from_pos === 'notificaciones') {
                $cu = function_exists('Auth') ? Auth::user() : null;
                $rol = $cu ? ($cu['role'] ?? '') : '';
                if ($rol === 'usuario') {
                    $urlVolver = rtrim(AppHelpers::getBaseUrl(), '/') . '/public/user_portal.php?section=notificaciones';
                } else {
                    $urlVolver = AppHelpers::dashboard('user_notificaciones');
                }
                $labelVolver = 'Volver a Notificaciones';
            } else {
                $urlVolver = $base_url . ($use_standalone ? '?' : '&') . 'action=panel&torneo_id=' . $torneo['id'];
                $labelVolver = 'Volver al Panel';
            }
            ?>
            <a href="<?php echo htmlspecialchars($urlVolver); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> <?php echo htmlspecialchars($labelVolver); ?>
            </a>
        </div>
    </div>
<?php else: ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div class="breadcrumb-modern mb-0">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>">Gestión de Torneos</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
            <li class="breadcrumb-item active">Posiciones</li>
        </ol>
    </div>
    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" class="btn btn-primary btn-sm flex-shrink-0">
        <i class="fas fa-arrow-left me-1"></i> Volver al Panel de Control
    </a>
</div>
<?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Clasificación General</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($posiciones)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i>
                            Aún no hay jugadores inscritos o no hay posiciones calculadas.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Pos</th>
                                        <th>ID Usuario</th>
                                        <th>Jugador</th>
                                        <th>Equipo</th>
                                        <th>Club</th>
                                        <th>G</th>
                                        <th>P</th>
                                        <th>GFF</th>
                                        <th>Efect.</th>
                                        <th>Puntos</th>
                                        <th>Pts. Rnk</th>
                                        <th>Sanc.</th>
                                        <th>Tarj.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Calcular paginación antes del loop
                                    if (!isset($items_por_pagina_pos)) {
                                        $items_por_pagina_pos = 30;
                                        $pagina_actual_pos = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
                                        $total_posiciones = count($posiciones);
                                        $total_paginas_pos = max(1, ceil($total_posiciones / $items_por_pagina_pos));
                                        $posiciones_paginadas = ($total_paginas_pos > 1) ? array_slice($posiciones, ($pagina_actual_pos - 1) * $items_por_pagina_pos, $items_por_pagina_pos) : $posiciones;
                                    }
                                    
                                    foreach ($posiciones_paginadas as $pos): 
                                        // Usar la posición calculada directamente desde la base de datos
                                        $posicion_actual = (int)($pos['posicion'] ?? 0);
                                        
                                        // Si la posición es 0 o no existe, calcularla basándose en el orden
                                        if ($posicion_actual == 0) {
                                            // Esto no debería pasar si recalcularPosiciones se ejecutó correctamente
                                            $posicion_actual = (int)($pos['posicion'] ?? 0);
                                        }
                                        
                                        $medalla_class = '';
                                        if ($posicion_actual == 1) $medalla_class = 'table-warning';
                                        elseif ($posicion_actual == 2) $medalla_class = 'table-secondary';
                                        elseif ($posicion_actual == 3) $medalla_class = 'table-light';
                                    ?>
                                        <tr class="<?php echo $medalla_class; ?>">
                                            <td>
                                                <strong><?php echo $posicion_actual; ?></strong>
                                                <?php if ($posicion_actual <= 3): ?>
                                                    <i class="fas fa-medal text-warning"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($pos['id_usuario'] ?? 'N/A'); ?></code></td>
                                            <td>
                                                <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=resumen_individual&torneo_id=<?php echo $torneo['id']; ?>&inscrito_id=<?php echo $pos['id_usuario']; ?>&from=posiciones" 
                                                   class="text-primary">
                                                    <i class="fas fa-user mr-1"></i>
                                                    <?php echo htmlspecialchars($pos['nombre_completo'] ?? $pos['nombre'] ?? 'N/A'); ?>
                                                </a>
                                                <?php 
                                                $es_retirado = (isset($pos['estatus']) && ((int)$pos['estatus'] === 4 || $pos['estatus'] === 'retirado'));
                                                if ($es_retirado): ?>
                                                    <span class="badge badge-dark ml-1">Retirado</span>
                                                <?php endif; ?>
                                                <?php if (!empty($pos['sexo'])): ?>
                                                    <small class="text-muted">(<?php echo $pos['sexo'] == 'M' || $pos['sexo'] == 1 ? '♂' : '♀'; ?>)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $nombre_equipo = $pos['nombre_equipo'] ?? '';
                                                $codigo_equipo = $pos['codigo_equipo'] ?? '';
                                                if (!empty($codigo_equipo)) {
                                                    echo '<i class="fas fa-users mr-1 text-purple-600"></i>';
                                                    if (!empty($nombre_equipo)) {
                                                        echo htmlspecialchars($nombre_equipo);
                                                        if (!empty($codigo_equipo)) {
                                                            echo ' <small class="text-muted">(' . htmlspecialchars($codigo_equipo) . ')</small>';
                                                        }
                                                    } else {
                                                        echo '<small class="text-muted">Equipo ' . htmlspecialchars($codigo_equipo) . '</small>';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted">-</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($pos['club_nombre'] ?? 'Sin Club'); ?></td>
                                            <td><strong><?php echo (int)($pos['ganados'] ?? 0); ?></strong></td>
                                            <td><?php echo (int)($pos['perdidos'] ?? 0); ?></td>
                                            <td>
                                                <span class="badge badge-danger" style="color: red !important; background-color: #f8d7da;"><?php echo (int)($pos['ganadas_por_forfait'] ?? $pos['gff'] ?? 0); ?></span>
                                                <?php 
                                                $partidas_bye = (int)($pos['partidas_bye'] ?? 0); 
                                                if ($partidas_bye > 0): 
                                                ?>
                                                    <span class="badge ml-1" style="background-color: #0d9488; color: #fff; font-weight: bold;" title="Partidas con descanso (BYE): partida ganada, 100% puntos, 50% efectividad"><?php echo $partidas_bye; ?> BYE</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo (int)($pos['efectividad'] ?? 0); ?></td>
                                            <td><strong><?php echo (int)($pos['puntos'] ?? 0); ?></strong></td>
                                            <td><strong class="text-primary"><?php echo (int)($pos['ptosrnk'] ?? 0); ?></strong></td>
                                            <td>
                                                <?php 
                                                $sancion = (int)($pos['sancion'] ?? 0);
                                                if ($sancion > 0) {
                                                    echo '<span class="badge badge-warning" style="color: orange !important;">' . $sancion . '</span>';
                                                } else {
                                                    echo '<span class="text-muted">-</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $tarjeta = (int)($pos['tarjeta'] ?? 0);
                                                if ($tarjeta > 0) {
                                                    if ($tarjeta == 1) {
                                                        echo '<span class="badge badge-warning" title="Tarjeta Amarilla">🟨</span>';
                                                    } elseif ($tarjeta == 3) {
                                                        echo '<span class="badge badge-danger" title="Tarjeta Roja">🟥</span>';
                                                    } elseif ($tarjeta == 4) {
                                                        echo '<span class="badge badge-dark" title="Tarjeta Negra">⬛</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted">-</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php 
                        // Mostrar paginador si hay más de una página
                        if ($total_paginas_pos > 1): 
                            // Construir URL base para el paginador
                            $parametros_get_pos = ['action' => 'posiciones', 'torneo_id' => $torneo['id']];
                            // Preservar otros parámetros GET si existen
                            foreach ($_GET as $key => $value) {
                                if ($key !== 'pagina' && $key !== 'action' && $key !== 'torneo_id') {
                                    $parametros_get_pos[$key] = $value;
                                }
                            }
                        ?>
                            <div class="mt-4 d-flex justify-content-center align-items-center gap-3">
                                <?php if ($pagina_actual_pos > 1): ?>
                                    <?php $parametros_get_pos['pagina'] = 1; ?>
                                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&') . http_build_query($parametros_get_pos); ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-angle-double-left"></i> Primera
                                    </a>
                                    <?php $parametros_get_pos['pagina'] = $pagina_actual_pos - 1; ?>
                                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&') . http_build_query($parametros_get_pos); ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-angle-left"></i> Anterior
                                    </a>
                                <?php else: ?>
                                    <span class="btn btn-sm btn-secondary disabled"><i class="fas fa-angle-double-left"></i> Primera</span>
                                    <span class="btn btn-sm btn-secondary disabled"><i class="fas fa-angle-left"></i> Anterior</span>
                                <?php endif; ?>
                                
                                <span class="badge badge-info">Página <?php echo $pagina_actual_pos; ?> de <?php echo $total_paginas_pos; ?></span>
                                
                                <?php if ($pagina_actual_pos < $total_paginas_pos): ?>
                                    <?php $parametros_get_pos['pagina'] = $pagina_actual_pos + 1; ?>
                                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&') . http_build_query($parametros_get_pos); ?>" class="btn btn-sm btn-secondary">
                                        Siguiente <i class="fas fa-angle-right"></i>
                                    </a>
                                    <?php $parametros_get_pos['pagina'] = $total_paginas_pos; ?>
                                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&') . http_build_query($parametros_get_pos); ?>" class="btn btn-sm btn-secondary">
                                        Última <i class="fas fa-angle-double-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="btn btn-sm btn-secondary disabled">Siguiente <i class="fas fa-angle-right"></i></span>
                                    <span class="btn btn-sm btn-secondary disabled">Última <i class="fas fa-angle-double-right"></i></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <strong>Leyenda:</strong>
                                <span class="badge badge-warning">1°</span> Oro |
                                <span class="badge badge-secondary">2°</span> Plata |
                                <span class="badge badge-light">3°</span> Bronce
                                <br>
                                <strong>G:</strong> Ganados | <strong>P:</strong> Perdidos | <strong>GFF:</strong> Ganadas por Forfait | <strong>BYE:</strong> Partidas con descanso (información) | <strong>Efect.:</strong> Efectividad | <strong>Puntos:</strong> Puntos del torneo | <strong>Pts. Rnk:</strong> Puntos de Ranking | <strong>Sanc.:</strong> Sanciones | <strong>Tarj.:</strong> Estado de tarjeta en el torneo (🟨 Amarilla, 🟥 Roja, ⬛ Negra)
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

