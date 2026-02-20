<?php
/**
 * Vista: Gestión de Equipos (Administrador)
 * Variables $torneo, $equipos, $equipos_por_club, $total_equipos vienen de extract($view_data) en torneo_gestion.php
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
?>

<link rel="stylesheet" href="assets/dist/output.css">

<div class="p-4 max-w-7xl mx-auto">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="flex items-center space-x-2 text-sm text-gray-500">
            <li><a href="<?php echo $base_url; ?>" class="hover:text-blue-600">Gestión de Torneos</a></li>
            <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
            <li><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" class="hover:text-blue-600"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
            <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
            <li class="text-gray-700 font-medium">Gestión de Equipos</li>
        </ol>
    </nav>

    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-700 px-6 py-8 text-white">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">
                        <i class="fas fa-users mr-3"></i>Equipos Registrados
                    </h1>
                    <p class="text-indigo-100 flex items-center">
                        <i class="fas fa-trophy mr-2"></i>
                        <?php echo htmlspecialchars($torneo['nombre']); ?>
                    </p>
                </div>
                <div class="bg-white/10 backdrop-blur-md px-6 py-3 rounded-xl border border-white/20">
                    <div class="text-3xl font-bold"><?php echo $total_equipos; ?></div>
                    <div class="text-xs uppercase tracking-wider opacity-80">Total Equipos</div>
                </div>
            </div>
        </div>

        <div class="p-6">
            <?php if (empty($equipos)): ?>
                <div class="text-center py-16">
                    <div class="text-gray-300 mb-4">
                        <i class="fas fa-users-slash text-7xl"></i>
                    </div>
                    <h3 class="text-xl font-medium text-gray-600">No hay equipos registrados</h3>
                    <p class="text-gray-500 mt-2">Los clubes aún no han inscrito sus equipos.</p>
                </div>
            <?php else: ?>
                <?php foreach ($equipos_por_club as $club_info): ?>
                    <div class="mb-10">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center border-b pb-2">
                            <i class="fas fa-building text-indigo-500 mr-2"></i>
                            <?php echo htmlspecialchars($club_info['nombre']); ?>
                            <span class="ml-3 bg-indigo-100 text-indigo-700 text-xs px-2.5 py-0.5 rounded-full">
                                <?php echo count($club_info['equipos']); ?> equipos
                            </span>
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($club_info['equipos'] as $equipo): 
                                $jugadores = EquiposHelper::getJugadoresEquipo($equipo['id']);
                            ?>
                                <div class="bg-gray-50 rounded-xl border border-gray-200 hover:shadow-md transition-shadow overflow-hidden">
                                    <div class="bg-white p-4 border-b flex justify-between items-center">
                                        <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></h3>
                                        <span class="text-xs font-mono bg-gray-200 px-2 py-1 rounded text-gray-600">
                                            #<?php echo $equipo['codigo_equipo']; ?>
                                        </span>
                                    </div>
                                    <div class="p-4">
                                        <div class="space-y-2">
                                            <?php if (empty($jugadores)): ?>
                                                <p class="text-sm text-gray-400 italic text-center py-4">Sin jugadores asignados</p>
                                            <?php else: ?>
                                                <?php foreach ($jugadores as $jugador): ?>
                                                    <div class="flex items-center text-sm">
                                                        <div class="w-6 h-6 rounded-full <?php echo $jugador['es_capitan'] ? 'bg-amber-100 text-amber-700' : 'bg-gray-200 text-gray-600'; ?> flex items-center justify-center text-[10px] font-bold mr-3 shrink-0">
                                                            <?php echo $jugador['es_capitan'] ? '★' : $jugador['posicion_equipo']; ?>
                                                        </div>
                                                        <div class="flex-1 truncate">
                                                            <div class="font-medium text-gray-700 truncate"><?php echo htmlspecialchars($jugador['nombre']); ?></div>
                                                            <div class="text-[10px] text-gray-500"><?php echo htmlspecialchars($jugador['cedula']); ?></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mt-4 pt-4 border-t flex justify-between items-center">
                                            <span class="text-xs <?php echo count($jugadores) == 4 ? 'text-green-600' : 'text-amber-600'; ?> font-medium">
                                                <i class="fas <?php echo count($jugadores) == 4 ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-1"></i>
                                                <?php echo count($jugadores); ?>/4 Jugadores
                                            </span>
                                            
                                            <div class="flex gap-2">
                                                <!-- Podríamos agregar botones de edición rápida aquí si fuera necesario -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Footer / Acciones -->
        <div class="bg-gray-50 px-6 py-4 border-t flex justify-between items-center">
            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" 
               class="text-gray-600 hover:text-gray-900 flex items-center text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i> Volver al Panel
            </a>
            
            <button onclick="window.print()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center text-sm font-medium">
                <i class="fas fa-print mr-2"></i> Imprimir Reporte
            </button>
        </div>
    </div>
</div>







