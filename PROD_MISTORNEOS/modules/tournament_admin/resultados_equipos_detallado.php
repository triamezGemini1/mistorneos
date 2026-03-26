<?php
/**
 * Resultados del Torneo por Equipos - Detallado
 * Muestra resultados detallados agrupados por equipo con rompe control
 * Similar a resultados_por_club pero agrupado por codigo_equipo
 */

require_once __DIR__ . '/../../lib/app_helpers.php';

// Asegurar que las posiciones estén actualizadas
if (function_exists('recalcularPosiciones')) {
    recalcularPosiciones($torneo_id);
}

// Configuración de paginación
$items_por_pagina = 10; // Equipos por página
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $items_por_pagina;

$pdo = DB::pdo();

// Función helper para generar HTML del paginador
function generarPaginador($pagina_actual, $total_paginas, $base_url, $parametros_get = []) {
    if ($total_paginas <= 1) {
        return '';
    }
    
    $html = '<div class="flex items-center justify-center gap-2 mt-6 mb-4">';
    $html .= '<div class="flex items-center gap-1">';
    
    // Botón Primera página
    if ($pagina_actual > 1) {
        $parametros_get['pagina'] = 1;
        $url = $base_url . '?' . http_build_query($parametros_get);
        $html .= '<a href="' . htmlspecialchars($url) . '" class="px-3 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition"><i class="fas fa-angle-double-left"></i></a>';
    } else {
        $html .= '<span class="px-3 py-2 bg-gray-300 text-gray-500 rounded cursor-not-allowed"><i class="fas fa-angle-double-left"></i></span>';
    }
    
    // Botón Página anterior
    if ($pagina_actual > 1) {
        $parametros_get['pagina'] = $pagina_actual - 1;
        $url = $base_url . '?' . http_build_query($parametros_get);
        $html .= '<a href="' . htmlspecialchars($url) . '" class="px-3 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition"><i class="fas fa-angle-left"></i></a>';
    } else {
        $html .= '<span class="px-3 py-2 bg-gray-300 text-gray-500 rounded cursor-not-allowed"><i class="fas fa-angle-left"></i></span>';
    }
    
    // Números de página
    $inicio = max(1, $pagina_actual - 2);
    $fin = min($total_paginas, $pagina_actual + 2);
    
    if ($inicio > 1) {
        $parametros_get['pagina'] = 1;
        $url = $base_url . '?' . http_build_query($parametros_get);
        $html .= '<a href="' . htmlspecialchars($url) . '" class="px-3 py-2 bg-white text-purple-600 rounded hover:bg-purple-50 transition">1</a>';
        if ($inicio > 2) {
            $html .= '<span class="px-2 text-gray-500">...</span>';
        }
    }
    
    for ($i = $inicio; $i <= $fin; $i++) {
        if ($i == $pagina_actual) {
            $html .= '<span class="px-3 py-2 bg-purple-600 text-white rounded font-bold">' . $i . '</span>';
        } else {
            $parametros_get['pagina'] = $i;
            $url = $base_url . '?' . http_build_query($parametros_get);
            $html .= '<a href="' . htmlspecialchars($url) . '" class="px-3 py-2 bg-white text-purple-600 rounded hover:bg-purple-50 transition">' . $i . '</a>';
        }
    }
    
    if ($fin < $total_paginas) {
        if ($fin < $total_paginas - 1) {
            $html .= '<span class="px-2 text-gray-500">...</span>';
        }
        $parametros_get['pagina'] = $total_paginas;
        $url = $base_url . '?' . http_build_query($parametros_get);
        $html .= '<a href="' . htmlspecialchars($url) . '" class="px-3 py-2 bg-white text-purple-600 rounded hover:bg-purple-50 transition">' . $total_paginas . '</a>';
    }
    
    // Botón Página siguiente
    if ($pagina_actual < $total_paginas) {
        $parametros_get['pagina'] = $pagina_actual + 1;
        $url = $base_url . '?' . http_build_query($parametros_get);
        $html .= '<a href="' . htmlspecialchars($url) . '" class="px-3 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition"><i class="fas fa-angle-right"></i></a>';
    } else {
        $html .= '<span class="px-3 py-2 bg-gray-300 text-gray-500 rounded cursor-not-allowed"><i class="fas fa-angle-right"></i></span>';
    }
    
    // Botón Última página
    if ($pagina_actual < $total_paginas) {
        $parametros_get['pagina'] = $total_paginas;
        $url = $base_url . '?' . http_build_query($parametros_get);
        $html .= '<a href="' . htmlspecialchars($url) . '" class="px-3 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition"><i class="fas fa-angle-double-right"></i></a>';
    } else {
        $html .= '<span class="px-3 py-2 bg-gray-300 text-gray-500 rounded cursor-not-allowed"><i class="fas fa-angle-double-right"></i></span>';
    }
    
    $html .= '</div>';
    $html .= '<div class="ml-4 text-sm text-gray-600">';
    $html .= 'Página ' . $pagina_actual . ' de ' . $total_paginas;
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// Obtener equipos con sus jugadores detallados
// LÓGICA: Leer primero equipos desde tabla equipos ordenados por ganados DESC, efectividad DESC, puntos DESC
// Luego para cada equipo buscar sus jugadores ordenados por los mismos criterios
$resultados_equipos = [];

try {
    // Paso 1: Leer equipos desde tabla equipos ordenados por clasificación (ganados DESC, efectividad DESC, puntos DESC)
    // LÓGICA INVERTIDA: Leer primero desde equipos para mostrar en orden de clasificación
    $sql_equipos = "
        SELECT 
            e.id as equipo_id,
            e.codigo_equipo,
            e.nombre_equipo,
            e.id_club,
            c.nombre as club_nombre,
            e.posicion,
            e.ganados,
            e.perdidos,
            e.efectividad,
            e.puntos,
            e.sancion,
            e.gff
        FROM equipos e
        LEFT JOIN clubes c ON e.id_club = c.id
        WHERE e.id_torneo = ? 
            AND e.estatus = 0
            AND e.codigo_equipo IS NOT NULL
            AND e.codigo_equipo != ''
        ORDER BY 
            e.ganados DESC,
            e.efectividad DESC,
            e.puntos DESC,
            e.codigo_equipo ASC
    ";
    
    // Contar total de equipos para paginación
    $sql_count = "
        SELECT COUNT(*) as total
        FROM equipos e
        WHERE e.id_torneo = ? 
            AND e.estatus = 0
            AND e.codigo_equipo IS NOT NULL
            AND e.codigo_equipo != ''
    ";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([$torneo_id]);
    $total_equipos = (int)$stmt_count->fetchColumn();
    
    // Si no hay equipos en la tabla equipos, contar desde inscritos para paginación en fallback
    if ($total_equipos == 0) {
        $sql_codigos_count = "
            SELECT COUNT(DISTINCT i.codigo_equipo) as total
            FROM inscritos i
            WHERE i.torneo_id = ? 
                AND i.codigo_equipo IS NOT NULL 
                AND i.codigo_equipo != ''
                AND i.estatus != 'retirado'
        ";
        $stmt_codigos_count = $pdo->prepare($sql_codigos_count);
        $stmt_codigos_count->execute([$torneo_id]);
        $total_equipos = (int)$stmt_codigos_count->fetchColumn();
    }
    
    $total_paginas = max(1, ceil($total_equipos / $items_por_pagina));
    
    // Ajustar página actual si excede el total
    if ($pagina_actual > $total_paginas) {
        $pagina_actual = $total_paginas;
        $offset = ($pagina_actual - 1) * $items_por_pagina;
    }
    
    // Obtener equipos con paginación (LIMIT y OFFSET deben ser enteros validados)
    $items_por_pagina_int = (int)$items_por_pagina;
    $offset_int = (int)$offset;
    $sql_equipos .= " LIMIT " . $items_por_pagina_int . " OFFSET " . $offset_int;
    $stmt_equipos = $pdo->prepare($sql_equipos);
    $stmt_equipos->execute([$torneo_id]);
    $equipos_todos = $stmt_equipos->fetchAll(PDO::FETCH_ASSOC);
    
    // Si no hay equipos en la tabla equipos, buscar códigos desde inscritos y crear estructura
    if (empty($equipos_todos)) {
        
        $sql_codigos = "
            SELECT DISTINCT i.codigo_equipo
            FROM inscritos i
            WHERE i.torneo_id = ? 
                AND i.codigo_equipo IS NOT NULL 
                AND i.codigo_equipo != ''
                AND i.estatus != 'retirado'
            ORDER BY i.codigo_equipo ASC
            LIMIT " . $items_por_pagina_int . " OFFSET " . $offset_int . "
        ";
        
        $stmt_codigos = $pdo->prepare($sql_codigos);
        $stmt_codigos->execute([$torneo_id]);
        $codigos_inscritos = $stmt_codigos->fetchAll(PDO::FETCH_COLUMN);
        
        // Crear estructura de equipos desde inscritos (con estadísticas calculadas)
        foreach ($codigos_inscritos as $codigo) {
            // Calcular estadísticas del equipo desde inscritos
            // NOTA: gff no existe en inscritos, solo en equipos
            $sql_stats_equipo = "
                SELECT 
                    SUM(i.ganados) as ganados,
                    SUM(i.perdidos) as perdidos,
                    SUM(i.efectividad) as efectividad,
                    SUM(i.puntos) as puntos,
                    SUM(i.sancion) as sancion,
                    MIN(i.id_club) as id_club
                FROM inscritos i
                WHERE i.torneo_id = ? 
                    AND i.codigo_equipo = ?
                    AND i.estatus != 'retirado'
            ";
            
            $stmt_stats = $pdo->prepare($sql_stats_equipo);
            $stmt_stats->execute([$torneo_id, $codigo]);
            $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
            
            if ($stats) {
                // Obtener club del primer jugador
                $sql_club = "
                    SELECT c.nombre as club_nombre
                    FROM inscritos i
                    LEFT JOIN clubes c ON i.id_club = c.id
                    WHERE i.torneo_id = ? 
                        AND i.codigo_equipo = ?
                        AND i.estatus != 'retirado'
                    LIMIT 1
                ";
                $stmt_club = $pdo->prepare($sql_club);
                $stmt_club->execute([$torneo_id, $codigo]);
                $club_data = $stmt_club->fetch(PDO::FETCH_ASSOC);
                
                $equipos_todos[] = [
                    'equipo_id' => null,
                    'codigo_equipo' => $codigo,
                    'nombre_equipo' => 'Equipo ' . $codigo,
                    'id_club' => (int)($stats['id_club'] ?? 0),
                    'club_nombre' => $club_data['club_nombre'] ?? 'Sin Club',
                    'posicion' => 0,
                    'ganados' => (int)($stats['ganados'] ?? 0),
                    'perdidos' => (int)($stats['perdidos'] ?? 0),
                    'efectividad' => (int)($stats['efectividad'] ?? 0),
                    'puntos' => (int)($stats['puntos'] ?? 0),
                    'sancion' => (int)($stats['sancion'] ?? 0),
                    'gff' => 0  // gff solo existe en tabla equipos, no en inscritos
                ];
            }
        }
        
        // Ordenar equipos calculados por clasificación (ganados DESC, efectividad DESC, puntos DESC)
        usort($equipos_todos, function($a, $b) {
            $ganados_a = (int)($a['ganados'] ?? 0);
            $ganados_b = (int)($b['ganados'] ?? 0);
            if ($ganados_a != $ganados_b) {
                return $ganados_b <=> $ganados_a;
            }
            
            $efec_a = (int)($a['efectividad'] ?? 0);
            $efec_b = (int)($b['efectividad'] ?? 0);
            if ($efec_a != $efec_b) {
                return $efec_b <=> $efec_a;
            }
            
            $pts_a = (int)($a['puntos'] ?? 0);
            $pts_b = (int)($b['puntos'] ?? 0);
            if ($pts_a != $pts_b) {
                return $pts_b <=> $pts_a;
            }
            
            return strcmp($a['codigo_equipo'] ?? '', $b['codigo_equipo'] ?? '');
        });
        
        // Asignar posiciones basadas en el orden de clasificación después de ordenar
        $posicion_actual = 1;
        foreach ($equipos_todos as &$equipo_temp) {
            $equipo_temp['posicion'] = $posicion_actual;
            $posicion_actual++;
        }
        unset($equipo_temp);
    } else {
        // Si hay equipos en la tabla, también asignar posiciones basadas en el orden
        $posicion_actual = 1;
        foreach ($equipos_todos as &$equipo_temp) {
            $equipo_temp['posicion'] = $posicion_actual;
            $posicion_actual++;
        }
        unset($equipo_temp);
    }
    
    // Paso 2: Para cada equipo, buscar sus jugadores ordenados por ganados DESC, efectividad DESC, puntos DESC
    foreach ($equipos_todos as $equipo_data) {
        $codigo_equipo = $equipo_data['codigo_equipo'];
        
        // Buscar jugadores del equipo ordenados por ganados DESC, efectividad DESC, puntos DESC
        // NOTA: gff no existe en inscritos, solo en equipos
        $sql_jugadores = "
            SELECT 
                i.id,
                i.id_usuario,
                i.posicion,
                i.ganados,
                i.perdidos,
                i.efectividad,
                i.puntos,
                i.ptosrnk,
                0 as gff,
                COALESCE(i.zapatos, 0) as zapatos,
                COALESCE(i.chancletas, 0) as chancletas,
                COALESCE(i.sancion, 0) as sancion,
                COALESCE(i.tarjeta, 0) as tarjeta,
                u.nombre as nombre_completo,
                u.cedula,
                c.nombre as club_nombre
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            LEFT JOIN clubes c ON i.id_club = c.id
            WHERE i.torneo_id = ? 
                AND i.codigo_equipo = ?
                AND i.estatus != 'retirado'
            ORDER BY 
                i.ganados DESC,
                i.efectividad DESC,
                i.puntos DESC,
                i.id_usuario ASC
        ";
        
        $stmt_jugadores = $pdo->prepare($sql_jugadores);
        $stmt_jugadores->execute([$torneo_id, $codigo_equipo]);
        $jugadores_equipo = $stmt_jugadores->fetchAll(PDO::FETCH_ASSOC);
        
        // Asignar posiciones dentro del equipo basadas en la clasificación (1, 2, 3, 4)
        $posicion_equipo = 1;
        foreach ($jugadores_equipo as &$jug) {
            $jug['posicion_equipo'] = $posicion_equipo;
            $jug['posicion_display'] = $posicion_equipo;
            $posicion_equipo++;
        }
        unset($jug);
        
        $resultados_equipos[] = [
            'equipo_id' => isset($equipo_data['equipo_id']) ? (int)$equipo_data['equipo_id'] : 0,
            'codigo_equipo' => $codigo_equipo,
            'nombre_equipo' => $equipo_data['nombre_equipo'],
            'id_club' => (int)($equipo_data['id_club'] ?? 0),
            'club_nombre' => $equipo_data['club_nombre'] ?? 'Sin Club',
            'posicion' => (int)($equipo_data['posicion'] ?? 0),
            'ganados' => (int)($equipo_data['ganados'] ?? 0),
            'perdidos' => (int)($equipo_data['perdidos'] ?? 0),
            'efectividad' => (int)($equipo_data['efectividad'] ?? 0),
            'puntos' => (int)($equipo_data['puntos'] ?? 0),
            'sancion' => (int)($equipo_data['sancion'] ?? 0),
            'gff' => (int)($equipo_data['gff'] ?? 0),
            'total_jugadores' => count($jugadores_equipo),
            'jugadores' => $jugadores_equipo
        ];
    }
    
} catch (Exception $e) {
    error_log("Error obteniendo resultados detallados de equipos: " . $e->getMessage());
    $resultados_equipos = [];
}

// Obtener información del club responsable con logo
$club_responsable = null;
$club_logo_url = null;

if (!empty($torneo['club_responsable'])) {
    $stmt = $pdo->prepare("
        SELECT id, nombre, logo, delegado
        FROM clubes
        WHERE id = ?
    ");
    $stmt->execute([$torneo['club_responsable']]);
    $club_responsable = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($club_responsable && !empty($club_responsable['logo'])) {
        $base_url = AppHelpers::getBaseUrl();
        $club_logo_url = AppHelpers::imageUrl($club_responsable['logo']);
    }
}

// Función helper para obtener URL del logo del club
function getClubLogoUrl($logo) {
    if (empty($logo)) return null;
    return AppHelpers::imageUrl($logo);
}

// Función helper para obtener texto de tarjeta
function getTarjetaTexto($tarjeta) {
    switch ((int)$tarjeta) {
        case 1: return '🟨 Amarilla';
        case 3: return '🟥 Roja';
        case 4: return '⬛ Negra';
        default: return 'Sin tarjeta';
    }
}
?>

<!-- Tailwind CSS (compilado localmente para mejor rendimiento) -->
<link rel="stylesheet" href="assets/dist/output.css">

<?php
// Obtener base URL para el botón de retorno
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url_return = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
?>

<div class="min-h-screen bg-gradient-to-br from-purple-600 via-purple-700 to-indigo-800 p-6">
    <!-- Botón de retorno al panel -->
    <div class="mb-4">
        <a href="<?php echo $base_url_return . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo_id; ?>" 
           class="inline-flex items-center px-6 py-3 bg-gray-800 hover:bg-gray-900 text-white rounded-lg shadow-lg transition-all transform hover:scale-105 font-bold">
            <i class="fas fa-arrow-left mr-2"></i>
            Volver al Panel de Control
        </a>
    </div>
    
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-2xl p-6 mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <?php if ($club_logo_url): ?>
                    <img src="<?php echo htmlspecialchars($club_logo_url); ?>" 
                         alt="<?php echo htmlspecialchars($club_responsable['nombre'] ?? ''); ?>" 
                         class="w-20 h-20 object-contain rounded-lg">
                <?php endif; ?>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-list-ul text-purple-600 mr-2"></i>
                        Resultados por Equipos - Detallado
                    </h1>
                    <h2 class="text-xl text-gray-600"><?php echo htmlspecialchars($torneo['nombre'] ?? 'Torneo'); ?></h2>
                    <div class="flex items-center gap-4 mt-2 text-sm text-gray-500">
                        <span><i class="fas fa-calendar-alt mr-1"></i> <?php echo date('d/m/Y', strtotime($torneo['fechator'] ?? 'now')); ?></span>
                        <span><i class="fas fa-building mr-1"></i> <?php echo htmlspecialchars($club_responsable['nombre'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <button onclick="window.print()" 
                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-lg transition-all transform hover:scale-105 font-bold">
                    <i class="fas fa-print mr-2"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
    
    <!-- Vista Detallada con Rompe Control por Equipo -->
    <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas fa-list-ul mr-2"></i> Resultados Detallados por Equipo
                </h3>
                <div class="flex gap-2">
                    <a href="<?php echo $base_url_return . ($use_standalone ? '?' : '&'); ?>action=resultados_equipos_resumido&torneo_id=<?php echo $torneo_id; ?>&vista=resumen" 
                       class="px-4 py-2 rounded-lg bg-purple-500 text-white hover:bg-purple-400 font-semibold transition-all">
                        Resumen
                    </a>
                    <a href="<?php echo $base_url_return . ($use_standalone ? '?' : '&'); ?>action=resultados_equipos_detallado&torneo_id=<?php echo $torneo_id; ?>&vista=detallada" 
                       class="px-4 py-2 rounded-lg bg-white text-purple-600 font-semibold transition-all">
                        Detallado
                    </a>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <?php 
            foreach ($resultados_equipos as $equipo): 
                $posicion_display = $equipo['posicion'] > 0 ? $equipo['posicion'] : '-';
            ?>
                <!-- Rompe Control por Equipo -->
                <div class="mb-8 border-l-4 border-purple-600 bg-gradient-to-r from-purple-50 to-indigo-50 rounded-lg p-4 shadow-md" style="page-break-inside: avoid;">
                    <!-- Subtítulos del Equipo (PRIMERO) -->
                    <div class="mb-4 bg-purple-200 rounded-lg p-4 border-b-2 border-purple-400">
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <div class="flex items-center gap-3 text-base">
                                <span class="bg-purple-600 text-white px-3 py-1 rounded font-bold">
                                    Pos. <?php echo $equipo['posicion'] > 0 ? $equipo['posicion'] : '-'; ?>
                                </span>
                                <span class="font-mono font-bold text-purple-900 text-lg"><?php echo htmlspecialchars($equipo['codigo_equipo']); ?></span>
                                <span class="text-purple-700">-</span>
                                <span class="font-bold text-purple-900 text-lg"><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></span>
                                <span class="text-purple-700">-</span>
                                <span class="text-purple-800"><i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($equipo['club_nombre']); ?></span>
                            </div>
                        </div>
                        
                        <!-- Estadísticas del equipo -->
                        <div class="mt-3 bg-purple-100 rounded p-3 border border-purple-300">
                            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2 text-sm">
                                <div class="text-green-700 font-semibold">
                                    <span class="font-bold">G:</span> <?php echo $equipo['ganados']; ?>
                                </div>
                                <div class="text-red-700 font-semibold">
                                    <span class="font-bold">P:</span> <?php echo $equipo['perdidos']; ?>
                                </div>
                                <div class="text-blue-700 font-semibold">
                                    <span class="font-bold">Efect:</span> <?php echo $equipo['efectividad']; ?>
                                </div>
                                <div class="text-purple-700 font-semibold">
                                    <span class="font-bold">Pts:</span> <?php echo $equipo['puntos']; ?>
                                </div>
                                <div class="text-indigo-700 font-semibold">
                                    <?php 
                                    $total_ptosrnk_equipo = 0;
                                    foreach ($equipo['jugadores'] as $j) {
                                        $total_ptosrnk_equipo += (int)($j['ptosrnk'] ?? 0);
                                    }
                                    echo '<span class="font-bold">Pts. Rnk:</span> ' . $total_ptosrnk_equipo;
                                    ?>
                                </div>
                                <div class="text-gray-700 font-semibold">
                                    <span class="font-bold">GFF:</span> <?php echo $equipo['gff']; ?>
                                </div>
                                <div class="text-gray-700 font-semibold">
                                    <span class="font-bold">Sanc:</span> <?php echo $equipo['sancion']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabla con headers de columna (DESPUÉS de los subtítulos) -->
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-purple-600 text-white">
                                    <th class="border border-purple-700 px-3 py-2 text-center font-bold">Pos. Torneo</th>
                                    <th class="border border-purple-700 px-3 py-2 text-center font-bold">ID Usuario</th>
                                    <th class="border border-purple-700 px-3 py-2 text-left font-bold">Jugador</th>
                                    <th class="border border-purple-700 px-3 py-2 text-center font-bold">G</th>
                                    <th class="border border-purple-700 px-3 py-2 text-center font-bold">P</th>
                                    <th class="border border-purple-700 px-3 py-2 text-center font-bold">Efec.</th>
                                    <th class="border border-purple-700 px-3 py-2 text-center font-bold">Puntos</th>
                                    <th class="border border-purple-700 px-3 py-2 text-center font-bold">Pts. Rnk.</th>
                                    <th class="border border-purple-700 px-3 py-2 text-center font-bold">GFF</th>
                                    <th class="border border-purple-700 px-3 py-2 text-center font-bold">Sanc.</th>
                                    <th class="border border-purple-700 px-3 py-2 text-center font-bold">Tarj.</th>
                                </tr>
                            </thead>
                            <tbody>
                                
                                <!-- Filas de jugadores del equipo obtenidos desde inscritos usando codigo_equipo -->
                                <?php 
                                // Calcular subtotales ANTES del loop sumando los valores de todos los jugadores
                                // NOTA: gff no existe en inscritos, solo se usa el valor de la tabla equipos
                                $subtotal_ganados = 0;
                                $subtotal_perdidos = 0;
                                $subtotal_efectividad = 0;
                                $subtotal_puntos = 0;
                                $subtotal_ptosrnk = 0;
                                $subtotal_sancion = 0;
                                
                                // Calcular subtotales primero (solo campos que existen en inscritos)
                                foreach ($equipo['jugadores'] as $j) {
                                    $subtotal_ganados += (int)($j['ganados'] ?? 0);
                                    $subtotal_perdidos += (int)($j['perdidos'] ?? 0);
                                    $subtotal_efectividad += (int)($j['efectividad'] ?? 0);
                                    $subtotal_puntos += (int)($j['puntos'] ?? 0);
                                    $subtotal_ptosrnk += (int)($j['ptosrnk'] ?? 0);
                                    $subtotal_sancion += (int)($j['sancion'] ?? 0);
                                }
                                
                                // gff solo existe en tabla equipos, no se suma de jugadores
                                $subtotal_gff = $equipo['gff'] ?? 0;
                                
                                // Los jugadores ya vienen ordenados por clasificación dentro del equipo (ganados DESC, efectividad DESC, puntos DESC)
                                // Mostrar posición en el torneo (no dentro del equipo)
                                foreach ($equipo['jugadores'] as $jugador): 
                                    $posicion_torneo = (int)($jugador['posicion'] ?? 0);
                                    $nombre_jugador = htmlspecialchars($jugador['nombre_completo'] ?? $jugador['nombre'] ?? 'N/A');
                                    $id_usuario = (int)($jugador['id_usuario'] ?? 0);
                                    
                                    // Obtener base URL para el link
                                    $base_url_link = $base_url_return;
                                    $action_param = $use_standalone ? '?' : '&';
                                ?>
                                    <tr class="hover:bg-purple-50 border-b border-purple-100">
                                        <td class="border border-purple-200 px-3 py-2 text-center font-semibold">
                                            <?php echo $posicion_torneo > 0 ? $posicion_torneo : '-'; ?>
                                        </td>
                                        <td class="border border-purple-200 px-3 py-2 text-center">
                                            <code><?php echo $id_usuario > 0 ? $id_usuario : 'N/A'; ?></code>
                                        </td>
                                        <td class="border border-purple-200 px-3 py-2 text-gray-800">
                                            <?php 
                                            if ($id_usuario > 0) {
                                                $url_resumen = $base_url_link . $action_param . 'action=resumen_individual&torneo_id=' . $torneo_id . '&inscrito_id=' . $id_usuario . '&from=resultados_equipos_detallado';
                                                echo '<a href="' . htmlspecialchars($url_resumen) . '" class="text-purple-600 hover:text-purple-800 hover:underline font-semibold">' . $nombre_jugador . ' <i class="fas fa-external-link-alt text-xs"></i></a>';
                                            } else {
                                                echo $nombre_jugador;
                                            }
                                            ?>
                                        </td>
                                        <td class="border border-purple-200 px-3 py-2 text-center font-semibold text-green-600">
                                            <?php echo (int)($jugador['ganados'] ?? 0); ?>
                                        </td>
                                        <td class="border border-purple-200 px-3 py-2 text-center font-semibold text-red-600">
                                            <?php echo (int)($jugador['perdidos'] ?? 0); ?>
                                        </td>
                                        <td class="border border-purple-200 px-3 py-2 text-center font-semibold text-blue-600">
                                            <?php echo (int)($jugador['efectividad'] ?? 0); ?>
                                        </td>
                                        <td class="border border-purple-200 px-3 py-2 text-center font-bold text-purple-600">
                                            <?php echo (int)($jugador['puntos'] ?? 0); ?>
                                        </td>
                                        <td class="border border-purple-200 px-3 py-2 text-center font-bold text-indigo-600">
                                            <?php echo (int)($jugador['ptosrnk'] ?? 0); ?>
                                        </td>
                                        <td class="border border-purple-200 px-3 py-2 text-center">
                                            <?php echo '-'; // gff no existe en inscritos, solo en equipos ?>
                                        </td>
                                        <td class="border border-purple-200 px-3 py-2 text-center">
                                            <?php echo (int)($jugador['sancion'] ?? 0); ?>
                                        </td>
                                        <td class="border border-purple-200 px-3 py-2 text-center text-xs">
                                            <?php echo getTarjetaTexto($jugador['tarjeta'] ?? 0); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <!-- Fila de resumen total del equipo: subtotal calculado sumando jugadores (debe coincidir con tabla equipos) -->
                                <tr class="bg-purple-300 font-bold border-t-2 border-purple-500">
                                    <td class="border border-purple-400 px-3 py-2 text-center text-purple-900" colspan="3">
                                        RESUMEN TOTAL EQUIPO (Suma jugadores)
                                    </td>
                                    <td class="border border-purple-400 px-3 py-2 text-center text-green-800">
                                        <?php echo $subtotal_ganados; ?>
                                        <?php if ($subtotal_ganados != $equipo['ganados']): ?>
                                            <span class="text-red-600 text-xs" title="Tabla equipos: <?php echo $equipo['ganados']; ?>">⚠</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border border-purple-400 px-3 py-2 text-center text-red-800">
                                        <?php echo $subtotal_perdidos; ?>
                                        <?php if ($subtotal_perdidos != $equipo['perdidos']): ?>
                                            <span class="text-red-600 text-xs" title="Tabla equipos: <?php echo $equipo['perdidos']; ?>">⚠</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border border-purple-400 px-3 py-2 text-center text-blue-800">
                                        <?php echo $subtotal_efectividad; ?>
                                        <?php if ($subtotal_efectividad != $equipo['efectividad']): ?>
                                            <span class="text-red-600 text-xs" title="Tabla equipos: <?php echo $equipo['efectividad']; ?>">⚠</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border border-purple-400 px-3 py-2 text-center text-purple-900">
                                        <?php echo $subtotal_puntos; ?>
                                        <?php if ($subtotal_puntos != $equipo['puntos']): ?>
                                            <span class="text-red-600 text-xs" title="Tabla equipos: <?php echo $equipo['puntos']; ?>">⚠</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border border-purple-400 px-3 py-2 text-center text-indigo-800">
                                        <?php echo $subtotal_ptosrnk; ?>
                                    </td>
                                    <td class="border border-purple-400 px-3 py-2 text-center text-purple-900">
                                        <?php echo $equipo['gff']; // gff solo existe en tabla equipos ?>
                                    </td>
                                    <td class="border border-purple-400 px-3 py-2 text-center text-purple-900">
                                        <?php echo $subtotal_sancion; ?>
                                        <?php if ($subtotal_sancion != $equipo['sancion']): ?>
                                            <span class="text-red-600 text-xs" title="Tabla equipos: <?php echo $equipo['sancion']; ?>">⚠</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border border-purple-400 px-3 py-2 text-center text-purple-900">
                                        -
                                    </td>
                                </tr>
                                
                                <!-- Fila adicional mostrando valores de tabla equipos para comparación -->
                                <tr class="bg-purple-100 font-semibold border-t border-purple-300 text-xs">
                                    <td class="border border-purple-300 px-3 py-1 text-center text-purple-700" colspan="3">
                                        Valores Tabla Equipos:
                                    </td>
                                    <td class="border border-purple-300 px-3 py-1 text-center text-green-700"><?php echo $equipo['ganados']; ?></td>
                                    <td class="border border-purple-300 px-3 py-1 text-center text-red-700"><?php echo $equipo['perdidos']; ?></td>
                                    <td class="border border-purple-300 px-3 py-1 text-center text-blue-700"><?php echo $equipo['efectividad']; ?></td>
                                    <td class="border border-purple-300 px-3 py-1 text-center text-purple-700"><?php echo $equipo['puntos']; ?></td>
                                    <td class="border border-purple-300 px-3 py-1 text-center text-indigo-700">-</td>
                                    <td class="border border-purple-300 px-3 py-1 text-center text-purple-700"><?php echo $equipo['gff']; ?></td>
                                    <td class="border border-purple-300 px-3 py-1 text-center text-purple-700"><?php echo $equipo['sancion']; ?></td>
                                    <td class="border border-purple-300 px-3 py-1 text-center text-purple-700">-</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <?php if (empty($equipo['jugadores'])): ?>
                        <div class="text-center py-4 text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-2"></i>
                            Este equipo no tiene jugadores registrados
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($resultados_equipos)): ?>
                <div class="bg-gray-50 rounded-lg p-8 text-center">
                    <i class="fas fa-info-circle text-4xl text-gray-400 mb-4"></i>
                    <p class="text-lg text-gray-600">No hay equipos registrados en este torneo</p>
                </div>
            <?php else: ?>
                <!-- Paginador -->
                <?php 
                // Construir URL base para el paginador
                $use_standalone_pag = $use_standalone;
                $base_url_pag = $base_url_return;
                $parametros_get = ['action' => 'resultados_equipos_detallado', 'torneo_id' => $torneo_id];
                // Preservar otros parámetros GET si existen
                foreach ($_GET as $key => $value) {
                    if ($key !== 'pagina' && $key !== 'action' && $key !== 'torneo_id') {
                        $parametros_get[$key] = $value;
                    }
                }
                echo generarPaginador($pagina_actual, $total_paginas, $base_url_pag, $parametros_get);
                ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media print {
    body { margin: 0; padding: 0; }
    .bg-gradient-to-br { background: white !important; }
    .mb-4, .mb-6, .mb-8 { margin-bottom: 1rem !important; }
    .p-6 { padding: 1rem !important; }
    button, a[onclick], a[href*="action="] { display: none !important; }
    /* Rompe control por equipo */
    div[style*="page-break-inside: avoid"] {
        page-break-inside: avoid;
        break-inside: avoid;
    }
    /* Asegurar que cada equipo empiece en nueva página si no cabe */
    .border-l-4.border-purple-600 {
        page-break-before: auto;
        page-break-after: auto;
    }
    table { page-break-inside: auto; }
    tr { page-break-inside: avoid; }
}
</style>


