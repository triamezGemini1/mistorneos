<?php
/**
 * Resultados del Torneo Agrupados por Club
 * Muestra resultados resumidos y detallados agrupados por club
 * Usa el campo pareclub como parámetro para determinar si agrupar por club
 */

require_once __DIR__ . '/../../lib/app_helpers.php';

// Configuración de paginación
$items_por_pagina_club = 10; // Clubes por página
$pagina_actual_club = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset_club = ($pagina_actual_club - 1) * $items_por_pagina_club;

// Función helper para generar HTML del paginador
function generarPaginadorClubs($pagina_actual, $total_paginas, $base_url, $parametros_get = []) {
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

/**
 * Obtiene los mejores N jugadores de cada club en un torneo
 * 
 * Regla: Se toman los topN primeros CLASIFICADOS de cada club (por ganados, efectividad, puntos).
 * Se consideran TODOS los jugadores que estén dentro de ese límite.
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $torneo_id ID del torneo
 * @param int $topN Número máximo de jugadores a considerar por club (pareclub del torneo)
 * @return array Array con 'estadisticas' y 'detalle'
 */
function obtenerTopJugadoresPorClub($pdo, $torneo_id, $topN) {
    // Obtener TODOS los jugadores del torneo, ordenados por club y clasificación real
    $sql = "SELECT 
                i.*,
                i.id_club as codigo_club,
                u.nombre as nombre_completo,
                u.username,
                u.sexo,
                u.cedula,
                c.id as club_id_from_join,
                c.nombre as club_nombre,
                c.logo as club_logo,
                (
                    SELECT COUNT(DISTINCT pr1.partida, pr1.mesa)
                    FROM `partiresul` pr1
                    LEFT JOIN `partiresul` pr_oponente ON pr1.id_torneo = pr_oponente.id_torneo 
                        AND pr1.partida = pr_oponente.partida 
                        AND pr1.mesa = pr_oponente.mesa
                        AND pr_oponente.id_usuario != pr1.id_usuario
                        AND (
                            (pr1.secuencia IN (1, 2) AND pr_oponente.secuencia IN (3, 4)) OR
                            (pr1.secuencia IN (3, 4) AND pr_oponente.secuencia IN (1, 2))
                        )
                    LEFT JOIN `partiresul` pr_compañero ON pr1.id_torneo = pr_compañero.id_torneo 
                        AND pr1.partida = pr_compañero.partida 
                        AND pr1.mesa = pr_compañero.mesa
                        AND pr_compañero.id_usuario != pr1.id_usuario
                        AND (
                            (pr1.secuencia IN (1, 2) AND pr_compañero.secuencia IN (1, 2) AND pr_compañero.secuencia != pr1.secuencia) OR
                            (pr1.secuencia IN (3, 4) AND pr_compañero.secuencia IN (3, 4) AND pr_compañero.secuencia != pr1.secuencia)
                        )
                    WHERE pr1.id_usuario = i.id_usuario
                        AND pr1.id_torneo = ?
                        AND pr1.registrado = 1
                        AND pr1.ff = 0
                        AND pr1.resultado1 = 200
                        AND pr1.efectividad = 100
                        AND pr1.resultado1 > pr1.resultado2
                        AND (
                            pr_oponente.ff = 1 OR
                            pr_compañero.ff = 1
                        )
                ) as ganadas_por_forfait
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            LEFT JOIN clubes c ON i.id_club = c.id
            WHERE i.torneo_id = ? 
                AND i.estatus != 'retirado'
            ORDER BY COALESCE(i.id_club, -1) ASC, 
                     CAST(i.ganados AS SIGNED) DESC, 
                     CAST(i.efectividad AS SIGNED) DESC, 
                     CAST(i.puntos AS SIGNED) DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $torneo_id]);
    $todos_jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por club y tomar SOLO los topN primeros clasificados de cada club
    $jugadores_por_club = [];
    foreach ($todos_jugadores as $jugador) {
        $codigo_club = isset($jugador['id_club']) && $jugador['id_club'] !== null 
            ? (int)$jugador['id_club'] 
            : ((int)($jugador['codigo_club'] ?? 0));
        
        if ($codigo_club == 0) $codigo_club = -1; // Sin club
        
        if (!isset($jugadores_por_club[$codigo_club])) {
            $stmt_club = $pdo->prepare("SELECT id, nombre, logo FROM clubes WHERE id = ?");
            $stmt_club->execute([$codigo_club]);
            $club_info = $stmt_club->fetch(PDO::FETCH_ASSOC);
            $nombre_final = $club_info ? $club_info['nombre'] : ($jugador['club_nombre'] ?? 'Sin Club');
            
            $jugadores_por_club[$codigo_club] = [
                'codigo_club' => $codigo_club,
                'club_nombre' => $nombre_final,
                'club_logo' => $club_info ? $club_info['logo'] : ($jugador['club_logo'] ?? null),
                'jugadores' => []
            ];
        }
        
        // Solo agregar si aún no hemos alcanzado el límite topN para este club
        $cantidad_actual = count($jugadores_por_club[$codigo_club]['jugadores']);
        if ($cantidad_actual < $topN) {
            $jugadores_por_club[$codigo_club]['jugadores'][] = $jugador;
        }
    }
    
    // Los jugadores ya vienen ordenados por clasificación (ganados, efectividad, puntos) y limitados a topN por club
    // IMPORTANTE: NO reordenar - mantener el orden original del torneo (ganados DESC, efectividad DESC, puntos DESC)
    $estadisticas = [];
    $detalle = [];
    
    foreach ($jugadores_por_club as $codigo_club => $club_data) {
        // Jugadores ya limitados a topN primeros clasificados por club (ganados, efectividad, puntos)
        $jugadores_seleccionados = $club_data['jugadores'];
        
        // Calcular estadísticas del grupo
        $total_puntos_grupo = 0;
        $total_efectividad = 0;
        $total_ganados = 0;
        $total_perdidos = 0;
        $total_ptosrnk = 0;
        $total_gff = 0;
        $mejor_posicion = 999;
        $cantidad_jugadores = count($jugadores_seleccionados);
        
        foreach ($jugadores_seleccionados as $index => $jugador) {
            $ganados = (int)($jugador['ganados'] ?? 0);
            $perdidos = (int)($jugador['perdidos'] ?? 0);
            $efectividad = (int)($jugador['efectividad'] ?? 0);
            $puntos = (int)($jugador['puntos'] ?? 0);
            $ptosrnk = (int)($jugador['ptosrnk'] ?? 0);
            $gff = (int)($jugador['ganadas_por_forfait'] ?? 0);
            $posicion = (int)($jugador['posicion'] ?? 0);
            
            $total_puntos_grupo += $puntos;
            $total_efectividad += $efectividad;
            $total_ganados += $ganados;
            $total_perdidos += $perdidos;
            $total_ptosrnk += $ptosrnk;
            $total_gff += $gff;
            
            if ($posicion > 0 && $posicion < $mejor_posicion) {
                $mejor_posicion = $posicion;
            }
            
            // Agregar al detalle
            // IMPORTANTE: Mantener la posición original del torneo (no reasignar)
            $detalle[] = [
                'codigo_club' => $codigo_club,
                'club_nombre' => $club_data['club_nombre'],
                'ranking' => $index + 1, // Ranking dentro del club (1, 2, 3...) - solo para referencia
                'nombre' => $jugador['nombre_completo'] ?? $jugador['nombre'] ?? 'N/A',
                'id_usuario' => (int)$jugador['id_usuario'],
                'cedula' => $jugador['cedula'] ?? '',
                'ganados' => $ganados,
                'perdidos' => $perdidos,
                'efectividad' => $efectividad,
                'puntos' => $puntos,
                'ptosrnk' => $ptosrnk,
                'gff' => $gff,
                'posicion' => $posicion, // Posición original del torneo (NO reasignar)
                'zapato' => (int)($jugador['zapato'] ?? $jugador['zapatos'] ?? 0),
                'chancletas' => (int)($jugador['chancletas'] ?? 0),
                'sancion' => (int)($jugador['sancion'] ?? 0),
                'tarjeta' => (int)($jugador['tarjeta'] ?? 0)
            ];
        }
        
        // Calcular promedio de efectividad
        $promedio_efectividad = $cantidad_jugadores > 0 
            ? (int)round($total_efectividad / $cantidad_jugadores) 
            : 0;
        
        // Agregar estadísticas del club
        error_log("obtenerTopJugadoresPorClub: Agregando estadísticas para club ID: $codigo_club, Nombre: {$club_data['club_nombre']}, Jugadores: $cantidad_jugadores");
        $estadisticas[] = [
            'codigo_club' => $codigo_club,
            'club_nombre' => $club_data['club_nombre'],
            'club_logo' => $club_data['club_logo'],
            'total_puntos_grupo' => $total_puntos_grupo,
            'promedio_efectividad' => $promedio_efectividad,
            'total_ganados' => $total_ganados,
            'total_perdidos' => $total_perdidos,
            'total_efectividad' => $total_efectividad,
            'total_ptosrnk' => $total_ptosrnk,
            'total_gff' => $total_gff,
            'mejor_posicion' => $mejor_posicion == 999 ? 0 : $mejor_posicion,
            'cantidad_jugadores' => $cantidad_jugadores
        ];
    }
    
    error_log("obtenerTopJugadoresPorClub: Total estadísticas generadas: " . count($estadisticas));
    
    // Ordenar estadísticas: partidos ganados DESC, efectividad DESC, puntos DESC
    usort($estadisticas, function($a, $b) {
        if ($a['total_ganados'] != $b['total_ganados']) {
            return $b['total_ganados'] <=> $a['total_ganados'];
        }
        if ($a['total_efectividad'] != $b['total_efectividad']) {
            return $b['total_efectividad'] <=> $a['total_efectividad'];
        }
        return $b['total_puntos_grupo'] <=> $a['total_puntos_grupo'];
    });
    
    return [
        'estadisticas' => $estadisticas,
        'detalle' => $detalle
    ];
}

// Asegurar que las posiciones estén actualizadas
if (function_exists('recalcularPosiciones')) {
    recalcularPosiciones($torneo_id);
}

// Obtener información del torneo
$pareclub = (int)($torneo['pareclub'] ?? 0);

// Si pareclub es 0, usar límite fijo de 8 jugadores por club
$limite_jugadores_por_club = ($pareclub > 0) ? $pareclub : 8;

// Filtrar clubes específicos por ID (opcional, desde parámetro URL)
$clubes_filtro_ids = [];
$club_seleccionado_id = null;
error_log("resultados_por_club: Verificando parámetros GET - club_id: " . (isset($_GET['club_id']) ? $_GET['club_id'] : 'NO EXISTE'));
if (isset($_GET['club_id']) && !empty($_GET['club_id'])) {
    $club_id_param = (int)$_GET['club_id'];
    if ($club_id_param > 0) {
        $clubes_filtro_ids = [$club_id_param];
        $club_seleccionado_id = $club_id_param;
        error_log("resultados_por_club: Filtro activado para club_id: $club_id_param");
    }
} else {
    error_log("resultados_por_club: NO hay filtro de club - se mostrarán TODOS los clubes");
}
error_log("resultados_por_club: clubes_filtro_ids contiene: " . json_encode($clubes_filtro_ids));
// Si no hay filtro, se mostrarán TODOS los clubes del torneo

// Construir URL base para enlaces (detectar panel_torneo o admin_torneo)
$script_actual_rpc = basename($_SERVER['PHP_SELF'] ?? '');
$base_script_rpc = in_array($script_actual_rpc, ['admin_torneo.php', 'panel_torneo.php']) ? $script_actual_rpc : 'index.php?page=torneo_gestion';
$url_base = $base_script_rpc . (strpos($base_script_rpc, '?') !== false ? '&' : '?') . "action=resultados_por_club&torneo_id=" . urlencode($torneo_id);

// Obtener información de la organización responsable (club_responsable = id de organizacion)
$organizacion_responsable = null;
$org_logo_url = null;
$admin_organizacion = null;

if (!empty($torneo['club_responsable'])) {
    $stmt = $pdo->prepare("
        SELECT o.id, o.nombre, o.logo, o.responsable, o.admin_user_id
        FROM organizaciones o
        WHERE o.id = ?
    ");
    $stmt->execute([$torneo['club_responsable']]);
    $organizacion_responsable = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($organizacion_responsable) {
        if (!empty($organizacion_responsable['logo'])) {
            $org_logo_url = AppHelpers::imageUrl($organizacion_responsable['logo']);
        }
        // Obtener datos del administrador de la organización
        if (!empty($organizacion_responsable['admin_user_id'])) {
            $stmt_admin = $pdo->prepare("SELECT id, nombre, username FROM usuarios WHERE id = ?");
            $stmt_admin->execute([$organizacion_responsable['admin_user_id']]);
            $admin_organizacion = $stmt_admin->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Obtener los mejores N jugadores por club usando la función helper
$resultados_por_club = [];
$resultados_detallados = [];

try {
    // Usar la función obtenerTopJugadoresPorClub
    $data = obtenerTopJugadoresPorClub($pdo, $torneo_id, $limite_jugadores_por_club);
    
    // Convertir el formato de la función al formato esperado por la vista
    error_log("resultados_por_club: Procesando " . count($data['estadisticas']) . " estadísticas");
    error_log("resultados_por_club: clubes_filtro_ids ANTES del foreach: " . json_encode($clubes_filtro_ids));
    error_log("resultados_por_club: club_seleccionado_id: " . ($club_seleccionado_id ?? 'NULL'));
    error_log("=== INICIO CONVERSIÓN DE FORMATO ===");
    error_log("Total estadísticas recibidas: " . count($data['estadisticas']));
    
    foreach ($data['estadisticas'] as $idx => $stat) {
        $club_id = (int)$stat['codigo_club'];
        
        error_log("--- Estadística #" . ($idx + 1) . " ---");
        error_log("  Club ID desde stat: $club_id");
        error_log("  Nombre desde stat: '{$stat['club_nombre']}'");
        error_log("  Tipo de codigo_club: " . gettype($stat['codigo_club']));
        
        // Verificar nuevamente el nombre del club desde la BD para asegurar que sea correcto
        error_log("  Consultando BD para club ID: $club_id");
        $stmt_verificar = $pdo->prepare("SELECT id, nombre FROM clubes WHERE id = ?");
        $stmt_verificar->execute([$club_id]);
        $club_verificado = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
        
        if ($club_verificado) {
            error_log("  ✓ Club encontrado en BD: ID={$club_verificado['id']}, Nombre='{$club_verificado['nombre']}'");
        } else {
            error_log("  ✗ Club ID $club_id NO encontrado en BD");
        }
        
        $nombre_verificado = $club_verificado ? $club_verificado['nombre'] : $stat['club_nombre'];
        error_log("  Nombre final que se usará: '$nombre_verificado'");
        
        error_log("resultados_por_club: Verificando filtro - clubes_filtro_ids está vacío? " . (empty($clubes_filtro_ids) ? 'SÍ' : 'NO'));
        if (!empty($clubes_filtro_ids)) {
            error_log("resultados_por_club: clubes_filtro_ids contiene: " . json_encode($clubes_filtro_ids));
            error_log("resultados_por_club: club_id $club_id está en array? " . (in_array($club_id, $clubes_filtro_ids) ? 'SÍ' : 'NO'));
        }
        
        // Si hay filtro de clubes, verificar
        if (!empty($clubes_filtro_ids) && !in_array($club_id, $clubes_filtro_ids)) {
            error_log("resultados_por_club: Club ID $club_id filtrado (no está en clubes_filtro_ids)");
            continue;
        }
        
        error_log("resultados_por_club: Agregando club ID: $club_id al array resultados_por_club");
        
        $resultados_por_club[$club_id] = [
            'club_id' => $club_id,
            'club_nombre' => $nombre_verificado,
            'club_logo' => $stat['club_logo'],
            'total_jugadores' => $stat['cantidad_jugadores'],
            'total_ganados' => $stat['total_ganados'],
            'total_perdidos' => $stat['total_perdidos'],
            'total_efectividad' => $stat['total_efectividad'],
            'total_puntos' => $stat['total_puntos_grupo'],
            'total_ptosrnk' => $stat['total_ptosrnk'],
            'total_gff' => $stat['total_gff'],
            'mejor_posicion' => $stat['mejor_posicion'],
            'promedio_efectividad' => $stat['promedio_efectividad'],
            'promedio_puntos' => $stat['cantidad_jugadores'] > 0 
                ? (int)round($stat['total_puntos_grupo'] / $stat['cantidad_jugadores']) 
                : 0,
            'jugadores' => []
        ];
        
        // Verificar inmediatamente después de asignar
        $nombre_verificado_despues = $resultados_por_club[$club_id]['club_nombre'];
        error_log("  - ✓ Club agregado al array: ID=$club_id");
        error_log("    Nombre asignado: '$nombre_verificado'");
        error_log("    Nombre verificado después de asignar: '$nombre_verificado_despues'");
        error_log("    ¿Son iguales? " . ($nombre_verificado === $nombre_verificado_despues ? 'SÍ' : 'NO'));
        
        // Verificar si ya existe otro club con el mismo nombre en el array
        foreach ($resultados_por_club as $otro_club_id => $otro_club_data) {
            if ($otro_club_id != $club_id && $otro_club_data['club_nombre'] === $nombre_verificado) {
                error_log("    ⚠ ADVERTENCIA: Otro club (ID=$otro_club_id) ya tiene el mismo nombre: '{$otro_club_data['club_nombre']}'");
            }
        }
    }
    
    error_log("resultados_por_club: DESPUÉS de agregar todos los clubes - Total: " . count($resultados_por_club));
    foreach ($resultados_por_club as $club_id => $club_data) {
        error_log("  - Club ID: $club_id, Nombre: '{$club_data['club_nombre']}', Jugadores: {$club_data['total_jugadores']}");
    }
    
    error_log("resultados_por_club: DESPUÉS de convertir formato - ANTES de agregar jugadores");
    foreach ($resultados_por_club as $club_id => $club_data) {
        error_log("  - Club ID: $club_id, Nombre: '{$club_data['club_nombre']}'");
    }
    
    // Agregar jugadores al detalle de cada club
    foreach ($data['detalle'] as $jugador) {
        $club_id = (int)$jugador['codigo_club'];
        
        // Si hay filtro de clubes, verificar
        if (!empty($clubes_filtro_ids) && !in_array($club_id, $clubes_filtro_ids)) {
            continue;
        }
        
        if (isset($resultados_por_club[$club_id])) {
            $resultados_por_club[$club_id]['jugadores'][] = [
                'id_usuario' => $jugador['id_usuario'],
                'cedula' => $jugador['cedula'],
                'nombre' => $jugador['nombre'],
                'posicion' => $jugador['posicion'],
                'ganados' => $jugador['ganados'],
                'perdidos' => $jugador['perdidos'],
                'efectividad' => $jugador['efectividad'],
                'puntos' => $jugador['puntos'],
                'ptosrnk' => $jugador['ptosrnk'],
                'gff' => $jugador['gff'],
                'zapato' => $jugador['zapato'],
                'chancletas' => $jugador['chancletas'],
                'sancion' => $jugador['sancion'],
                'tarjeta' => $jugador['tarjeta']
            ];
        }
    }
    
    error_log("resultados_por_club: DESPUÉS de agregar jugadores - ANTES de ordenar");
    foreach ($resultados_por_club as $club_id => $club_data) {
        error_log("  - Club ID: $club_id, Nombre: '{$club_data['club_nombre']}', Jugadores: " . count($club_data['jugadores']));
    }
    
    // IMPORTANTE: NO ordenar jugadores dentro de cada club
    // Los jugadores ya vienen en el orden correcto del torneo (ganados DESC, efectividad DESC, puntos DESC)
    // y deben mantener su posición original del torneo
    
    error_log("resultados_por_club: Jugadores mantienen orden original del torneo (NO reordenados)");
    foreach ($resultados_por_club as $club_id => $club_data) {
        error_log("  - Club ID: $club_id, Nombre: '{$club_data['club_nombre']}', Jugadores: " . count($club_data['jugadores']));
    }
    
    // Ordenar clubes: partidos ganados DESC, efectividad DESC, puntos DESC
    uasort($resultados_por_club, function($a, $b) {
        if ($a['total_ganados'] != $b['total_ganados']) {
            return $b['total_ganados'] <=> $a['total_ganados'];
        }
        if ($a['total_efectividad'] != $b['total_efectividad']) {
            return $b['total_efectividad'] <=> $a['total_efectividad'];
        }
        return $b['total_puntos'] <=> $a['total_puntos'];
    });
    
    error_log("resultados_por_club: DESPUÉS de ordenar clubes");
    foreach ($resultados_por_club as $club_id => $club_data) {
        error_log("  - Club ID: $club_id, Nombre: '{$club_data['club_nombre']}'");
    }
    
    // Calcular paginación para clubes
    $total_clubes = count($resultados_por_club);
    $total_paginas_club = max(1, ceil($total_clubes / $items_por_pagina_club));
    
    // Ajustar página actual si excede el total
    if ($pagina_actual_club > $total_paginas_club) {
        $pagina_actual_club = $total_paginas_club;
        $offset_club = ($pagina_actual_club - 1) * $items_por_pagina_club;
    }
    
    // Aplicar paginación a los clubes (convertir a array numérico, paginar, luego convertir de nuevo a asociativo)
    $clubes_array = array_values($resultados_por_club);
    $clubes_paginados = array_slice($clubes_array, $offset_club, $items_por_pagina_club);
    
    // Reconstruir array asociativo con club_id como key para mantener compatibilidad
    $resultados_por_club_paginados = [];
    foreach ($clubes_paginados as $club_data) {
        $club_id = $club_data['club_id'];
        $resultados_por_club_paginados[$club_id] = $club_data;
    }
    
    $resultados_detallados = $resultados_por_club_paginados;
    
    // Usar resultados paginados para las vistas
    $resultados_por_club_mostrar = $resultados_por_club_paginados;
    
    // Debug final: Verificar qué se va a mostrar
    error_log("resultados_por_club: ANTES DE MOSTRAR - Total clubes: " . count($resultados_por_club));
    error_log("resultados_por_club: ANTES DE MOSTRAR - Total clubes paginados a mostrar: " . count($resultados_por_club_mostrar));
    
} catch (Exception $e) {
    error_log("Error obteniendo resultados por club: " . $e->getMessage());
    // En caso de error, dejar arrays vacíos
    $resultados_por_club = [];
    $resultados_por_club_mostrar = [];
    $resultados_detallados = [];
    $total_clubes = 0;
    $total_paginas_club = 1;
    $pagina_actual_club = 1;
}

// Si no se definió antes (por error), definir valores por defecto
if (!isset($resultados_por_club_mostrar)) {
    $resultados_por_club_mostrar = $resultados_por_club ?? [];
}
if (!isset($total_clubes)) {
    $total_clubes = count($resultados_por_club ?? []);
}
if (!isset($total_paginas_club)) {
    $total_paginas_club = max(1, ceil($total_clubes / ($items_por_pagina_club ?? 10)));
}
if (!isset($pagina_actual_club)) {
    $pagina_actual_club = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
}
if (!isset($resultados_detallados)) {
    $resultados_detallados = $resultados_por_club_mostrar;
}

// Función helper para obtener URL del logo del club (usa función central de imágenes)
function getClubLogoUrl($logo) {
    if (empty($logo)) return null;
    return AppHelpers::imageUrl($logo);
}

// Función helper para obtener texto de tarjeta
function getTarjetaTexto($tarjeta) {
    switch ($tarjeta) {
        case 1: return '🟨 Amarilla';
        case 3: return '🟥 Roja';
        case 4: return '⬛ Negra';
        default: return 'Sin tarjeta';
    }
}

// Determinar vista (resumida o detallada)
// Si hay un club seleccionado, mostrar vista detallada por defecto
$vista = $_GET['vista'] ?? ($club_seleccionado_id ? 'detallada' : 'resumen');
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
    <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
        <div class="flex items-start justify-between">
            <!-- Logo, nombre de la organización y enlaces a la izquierda -->
            <?php if ($organizacion_responsable): ?>
            <div class="flex flex-col gap-3 flex-shrink-0">
                <div class="flex items-center gap-4">
                    <?php 
                    $url_org = AppHelpers::dashboard('organizaciones', ['id' => (int)$organizacion_responsable['id']]);
                    if ($org_logo_url): ?>
                    <a href="<?= htmlspecialchars($url_org) ?>" title="Ver perfil de la organización">
                        <img src="<?= htmlspecialchars($org_logo_url) ?>" 
                             alt="<?= htmlspecialchars($organizacion_responsable['nombre']) ?>" 
                             class="w-24 h-24 rounded-full border-4 border-purple-500 shadow-lg object-cover hover:opacity-90 transition">
                    </a>
                    <?php endif; ?>
                    <div>
                        <a href="<?= htmlspecialchars($url_org) ?>" class="text-3xl font-bold text-gray-800 hover:text-purple-600 transition">
                            <?= htmlspecialchars($organizacion_responsable['nombre']) ?>
                        </a>
                        <div class="flex flex-wrap gap-3 mt-2 text-sm">
                            <a href="<?= htmlspecialchars($url_org) ?>" class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-700 rounded-full hover:bg-purple-200 transition">
                                <i class="fas fa-building mr-1"></i>Ver perfil de la organización
                            </a>
                            <?php if ($admin_organizacion): ?>
                            <a href="<?= htmlspecialchars($url_org) ?>" class="inline-flex items-center px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full hover:bg-indigo-200 transition" title="Ver datos del administrador en la organización">
                                <i class="fas fa-user-cog mr-1"></i>Administrador: <?= htmlspecialchars($admin_organizacion['nombre']) ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Título y nombre del torneo centrados -->
            <div class="flex-1 flex flex-col items-center justify-center text-center">
                <h1 class="text-4xl font-extrabold text-purple-700 tracking-tight mb-2">
                    RESULTADOS POR CLUB
                </h1>
                <h3 class="text-2xl font-semibold text-gray-600">
                    <i class="fas fa-trophy mr-2"></i>
                    <?= htmlspecialchars($torneo['nombre']) ?>
                </h3>
                <span class="mt-2 inline-block px-4 py-2 bg-purple-100 text-purple-700 rounded-full text-sm font-semibold">
                    <i class="fas fa-users mr-1"></i>Límite: <?= $limite_jugadores_por_club ?> mejor(es) jugador(es) por club
                </span>
                <?php if (!empty($clubes_filtro_ids)): ?>
                <span class="mt-2 ml-2 inline-block px-4 py-2 bg-blue-100 text-blue-700 rounded-full text-sm font-semibold">
                    <i class="fas fa-filter mr-1"></i>Mostrando solo clubes específicos
                </span>
                <?php endif; ?>
            </div>
            
            <!-- Espacio vacío a la derecha para balance -->
            <div class="flex-shrink-0" style="width: 300px;"></div>
        </div>
    </div>
    
    <!-- Tabs para cambiar entre vista resumida y detallada -->
    <div class="flex justify-center gap-4 mb-6">
        <button onclick="mostrarVista('resumen')" 
                class="px-8 py-3 rounded-full font-bold text-white bg-gradient-to-r <?= $vista === 'resumen' ? 'from-blue-600 to-blue-800' : 'from-gray-500 to-gray-700' ?> hover:from-blue-600 hover:to-blue-800 shadow-lg transition-all transform hover:scale-105">
            <i class="fas fa-chart-bar mr-2"></i>Vista Resumida
        </button>
        <button onclick="mostrarVista('detallada')" 
                class="px-8 py-3 rounded-full font-bold text-white bg-gradient-to-r <?= $vista === 'detallada' ? 'from-green-600 to-green-800' : 'from-gray-500 to-gray-700' ?> hover:from-green-600 hover:to-green-800 shadow-lg transition-all transform hover:scale-105">
            <i class="fas fa-list-ul mr-2"></i>Vista Detallada
        </button>
    </div>
    
    <?php if (empty($resultados_por_club)): ?>
    <div class="bg-white rounded-xl shadow-lg p-6 text-center">
        <div class="text-gray-600">
            <i class="fas fa-info-circle text-4xl mb-4"></i>
            <p class="text-lg">Aún no hay resultados disponibles para este torneo.</p>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Botón para volver a vista completa (solo si hay club seleccionado) -->
    <?php if ($club_seleccionado_id): ?>
    <div class="mb-4">
        <a href="<?= $url_base ?>" 
           class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-lg">
            <i class="fas fa-arrow-left mr-2"></i>
            Volver a todos los clubes
        </a>
    </div>
    <?php endif; ?>
    
    <!-- Vista Resumida -->
    <div id="vistaResumen" class="<?= $vista === 'resumen' ? '' : 'hidden' ?>">
        <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-3xl font-bold text-purple-700 mb-4 text-center">
                <i class="fas fa-chart-bar mr-3"></i>Resultados Resumidos por Club
                <span class="text-lg text-blue-600 block mt-2 font-semibold">
                    <i class="fas fa-info-circle mr-2"></i>Total de clubes: <?= $total_clubes ?? count($resultados_por_club ?? []) ?>
                </span>
                <?php if ($club_seleccionado_id): ?>
                    <span class="text-lg text-gray-600 block mt-2">
                        <i class="fas fa-filter mr-2"></i>Mostrando solo un club
                    </span>
                <?php endif; ?>
            </h2>
            <div class="mb-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Nota:</strong> Solo se están considerando los <strong><?= $limite_jugadores_por_club ?></strong> mejor(es) jugador(es) de cada club para el cálculo de estadísticas.
                    <?php if (!empty($clubes_filtro_ids)): ?>
                    <br>Mostrando resultados de clubes específicos del torneo.
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gradient-to-r from-purple-600 to-indigo-700 text-white">
                            <th class="px-4 py-4 text-left font-bold">Pos</th>
                            <th class="px-4 py-4 text-left font-bold">Club</th>
                            <th class="px-4 py-4 text-center font-bold">Jugadores</th>
                            <th class="px-4 py-4 text-center font-bold">Ganados</th>
                            <th class="px-4 py-4 text-center font-bold">Perdidos</th>
                            <th class="px-4 py-4 text-center font-bold">GFF</th>
                            <th class="px-4 py-4 text-center font-bold">Efect. Prom.</th>
                            <th class="px-4 py-4 text-center font-bold">Puntos Prom.</th>
                            <th class="px-4 py-4 text-center font-bold">Pts. Rnk Total</th>
                            <th class="px-4 py-4 text-center font-bold">Mejor Pos.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $posicion_club = 1;
                        error_log("HTML Vista Resumida: Total clubes a mostrar: " . count($resultados_por_club));
                        foreach ($resultados_por_club_mostrar as $club_id => $club_data): 
                            error_log("HTML Vista Resumida: Mostrando club ID: $club_id, Nombre: {$club_data['club_nombre']}, Posición: $posicion_club");
                        ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50 <?= $posicion_club <= 3 ? ($posicion_club == 1 ? 'bg-yellow-50' : ($posicion_club == 2 ? 'bg-gray-100' : 'bg-orange-50')) : '' ?>">
                            <td class="px-4 py-4 font-bold text-center">
                                <?= $posicion_club ?>
                                <?php if ($posicion_club == 1): ?>
                                    <i class="fas fa-trophy text-yellow-500 ml-1"></i>
                                <?php elseif ($posicion_club <= 3): ?>
                                    <i class="fas fa-medal text-gray-500 ml-1"></i>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <?php if ($club_data['club_logo']): ?>
                                    <img src="<?= htmlspecialchars(getClubLogoUrl($club_data['club_logo'])) ?>" 
                                         alt="<?= htmlspecialchars($club_data['club_nombre']) ?>" 
                                         class="w-12 h-12 rounded-full border-2 border-purple-300 object-cover">
                                    <?php endif; ?>
                                    <a href="<?= $url_base ?>&club_id=<?= $club_data['club_id'] ?>" 
                                       class="font-semibold text-lg text-purple-700 hover:text-purple-900 hover:underline transition-colors">
                                        <?= htmlspecialchars($club_data['club_nombre']) ?>
                                        <i class="fas fa-external-link-alt ml-2 text-sm"></i>
                                    </a>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center font-bold"><?= $club_data['total_jugadores'] ?></td>
                            <td class="px-4 py-4 text-center font-bold text-green-600"><?= $club_data['total_ganados'] ?></td>
                            <td class="px-4 py-4 text-center font-bold text-red-600"><?= $club_data['total_perdidos'] ?></td>
                            <td class="px-4 py-4 text-center"><?= $club_data['total_gff'] ?></td>
                            <td class="px-4 py-4 text-center font-bold"><?= $club_data['promedio_efectividad'] ?></td>
                            <td class="px-4 py-4 text-center font-bold"><?= $club_data['promedio_puntos'] ?></td>
                            <td class="px-4 py-4 text-center font-bold text-purple-600"><?= $club_data['total_ptosrnk'] ?></td>
                            <td class="px-4 py-4 text-center">
                                <?php if ($club_data['mejor_posicion'] < 999): ?>
                                    <span class="font-bold text-lg"><?= $club_data['mejor_posicion'] ?>°</span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                        $posicion_club++;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginador para vista resumida -->
            <?php 
            if (!empty($resultados_por_club_mostrar) && isset($total_paginas_club) && $total_paginas_club > 1) {
                // Construir URL base para el paginador
                $parametros_get_club = ['action' => 'resultados_por_club', 'torneo_id' => $torneo_id, 'vista' => 'resumen'];
                // Preservar otros parámetros GET si existen
                foreach ($_GET as $key => $value) {
                    if ($key !== 'pagina' && $key !== 'action' && $key !== 'torneo_id' && $key !== 'vista') {
                        $parametros_get_club[$key] = $value;
                    }
                }
                $use_standalone_club = $use_standalone;
                $base_url_club = $base_url_return;
                echo generarPaginadorClubs($pagina_actual_club, $total_paginas_club, $base_url_club, $parametros_get_club);
            }
            ?>
        </div>
    </div>
    
    <!-- Vista Detallada -->
    <div id="vistaDetallada" class="<?= $vista === 'detallada' ? '' : 'hidden' ?>">
        <div class="mb-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 rounded">
            <p class="text-sm text-yellow-800">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Nota:</strong> Solo se están mostrando los <strong><?= $limite_jugadores_por_club ?></strong> mejor(es) jugador(es) de cada club.
                <?php if (!empty($clubes_filtro_ids)): ?>
                <br>Mostrando resultados de clubes específicos del torneo.
                <?php endif; ?>
            </p>
        </div>
        <?php 
        // Calcular posición inicial considerando la paginación
        $posicion_club_inicial = (($pagina_actual_club - 1) * $items_por_pagina_club) + 1;
        $posicion_club = $posicion_club_inicial;
        error_log("HTML Vista Detallada: Total clubes a mostrar: " . count($resultados_por_club_mostrar) . " (página $pagina_actual_club de $total_paginas_club)");
        foreach ($resultados_por_club_mostrar as $club_id => $club_data): 
            error_log("HTML Vista Detallada: Mostrando club ID: $club_id, Nombre: {$club_data['club_nombre']}, Posición: $posicion_club");
        ?>
        <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
            <!-- Encabezado del Club -->
            <div class="flex items-center justify-between mb-6 pb-4 border-b-2 border-gray-200">
                <div class="flex items-center gap-4">
                    <?php if ($club_data['club_logo']): ?>
                    <img src="<?= htmlspecialchars(getClubLogoUrl($club_data['club_logo'])) ?>" 
                         alt="<?= htmlspecialchars($club_data['club_nombre']) ?>" 
                         class="w-20 h-20 rounded-full border-4 border-purple-500 shadow-lg object-cover">
                    <?php endif; ?>
                    <div>
                        <h3 class="text-3xl font-bold text-purple-700">
                            <?= $posicion_club ?>° - <?= htmlspecialchars($club_data['club_nombre']) ?>
                        </h3>
                        <p class="text-gray-600 mt-1">
                            <i class="fas fa-users mr-2"></i><?= $club_data['total_jugadores'] ?> jugador(es)
                        </p>
                        <?php if (!$club_seleccionado_id): ?>
                        <a href="<?= $url_base ?>&club_id=<?= $club_data['club_id'] ?>" 
                           class="mt-2 inline-block text-sm text-purple-600 hover:text-purple-800 hover:underline">
                            <i class="fas fa-eye mr-1"></i>Ver solo este club
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Estadísticas resumidas del club -->
                <div class="flex gap-4">
                    <div class="text-center bg-green-50 rounded-lg p-3 min-w-[100px]">
                        <div class="text-sm text-gray-600">Ganados</div>
                        <div class="text-2xl font-bold text-green-600"><?= $club_data['total_ganados'] ?></div>
                    </div>
                    <div class="text-center bg-red-50 rounded-lg p-3 min-w-[100px]">
                        <div class="text-sm text-gray-600">Perdidos</div>
                        <div class="text-2xl font-bold text-red-600"><?= $club_data['total_perdidos'] ?></div>
                    </div>
                    <div class="text-center bg-purple-50 rounded-lg p-3 min-w-[100px]">
                        <div class="text-sm text-gray-600">Pts. Rnk</div>
                        <div class="text-2xl font-bold text-purple-600"><?= $club_data['total_ptosrnk'] ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de jugadores del club -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gradient-to-r from-gray-600 to-gray-700 text-white">
                            <th class="px-4 py-3 text-left font-bold">Pos</th>
                            <th class="px-4 py-3 text-center font-bold">ID Usuario</th>
                            <th class="px-4 py-3 text-left font-bold">Jugador</th>
                            <th class="px-4 py-3 text-center font-bold">G</th>
                            <th class="px-4 py-3 text-center font-bold">P</th>
                            <th class="px-4 py-3 text-center font-bold">GFF</th>
                            <th class="px-4 py-3 text-center font-bold">Efect.</th>
                            <th class="px-4 py-3 text-center font-bold">Puntos</th>
                            <th class="px-4 py-3 text-center font-bold">Pts. Rnk</th>
                            <th class="px-4 py-3 text-center font-bold">Zapato</th>
                            <th class="px-4 py-3 text-center font-bold">Chancleta</th>
                            <th class="px-4 py-3 text-center font-bold">Sanciones</th>
                            <th class="px-4 py-3 text-center font-bold">Tarjeta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($club_data['jugadores'] as $jugador): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-3 font-bold text-center">
                                <?= $jugador['posicion'] > 0 ? $jugador['posicion'] : '-' ?>
                                <?php if ($jugador['posicion'] == 1): ?>
                                    <i class="fas fa-trophy text-yellow-500 ml-1"></i>
                                <?php elseif ($jugador['posicion'] <= 3 && $jugador['posicion'] > 0): ?>
                                    <i class="fas fa-medal text-gray-500 ml-1"></i>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <code><?= htmlspecialchars($jugador['id_usuario'] ?? 'N/A') ?></code>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold"><?= htmlspecialchars($jugador['nombre']) ?></div>
                            </td>
                            <td class="px-4 py-3 text-center font-bold text-green-600"><?= $jugador['ganados'] ?></td>
                            <td class="px-4 py-3 text-center font-bold text-red-600"><?= $jugador['perdidos'] ?></td>
                            <td class="px-4 py-3 text-center"><?= $jugador['gff'] ?></td>
                            <td class="px-4 py-3 text-center"><?= $jugador['efectividad'] ?></td>
                            <td class="px-4 py-3 text-center"><?= $jugador['puntos'] ?></td>
                            <td class="px-4 py-3 text-center font-bold text-purple-600"><?= $jugador['ptosrnk'] ?></td>
                            <td class="px-4 py-3 text-center"><?= $jugador['zapato'] ?></td>
                            <td class="px-4 py-3 text-center"><?= $jugador['chancletas'] ?></td>
                            <td class="px-4 py-3 text-center"><?= $jugador['sancion'] ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-sm"><?= getTarjetaTexto($jugador['tarjeta']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php 
        $posicion_club++;
        endforeach; 
        ?>
        
        <!-- Paginador para vista detallada -->
        <?php 
        if (!empty($resultados_por_club_mostrar) && isset($total_paginas_club) && $total_paginas_club > 1) {
            // Construir URL base para el paginador
            $parametros_get_club = ['action' => 'resultados_por_club', 'torneo_id' => $torneo_id, 'vista' => 'detallada'];
            // Preservar otros parámetros GET si existen
            foreach ($_GET as $key => $value) {
                if ($key !== 'pagina' && $key !== 'action' && $key !== 'torneo_id' && $key !== 'vista') {
                    $parametros_get_club[$key] = $value;
                }
            }
            $use_standalone_club = (basename($_SERVER['PHP_SELF'] ?? '') === 'admin_torneo.php');
            $base_url_club = $use_standalone_club ? 'admin_torneo.php' : 'index.php?page=torneo_gestion';
            echo generarPaginadorClubs($pagina_actual_club, $total_paginas_club, $base_url_club, $parametros_get_club);
        }
        ?>
    </div>
    
    <?php endif; ?>
</div>

<script>
function mostrarVista(vista) {
    // Ocultar ambas vistas
    document.getElementById('vistaResumen').classList.add('hidden');
    document.getElementById('vistaDetallada').classList.add('hidden');
    
    // Mostrar la vista seleccionada
    if (vista === 'resumen') {
        document.getElementById('vistaResumen').classList.remove('hidden');
    } else {
        document.getElementById('vistaDetallada').classList.remove('hidden');
    }
    
    // Actualizar URL sin recargar
    const url = new URL(window.location);
    url.searchParams.set('vista', vista);
    window.history.pushState({}, '', url);
}
</script>

