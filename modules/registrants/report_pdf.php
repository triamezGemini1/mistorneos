<?php
/**
 * Reporte de Inscritos en PDF - FIXED VERSION
 * Genera un reporte completo de los jugadores inscritos
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/report_generator.php';

Auth::requireRole(['admin_general', 'admin_torneo', 'admin_club']);

try {
    $pdo = DB::pdo();
    
    // Obtener filtros
    $tournament_filter = isset($_GET['torneo_id']) && $_GET['torneo_id'] !== '' ? (int)$_GET['torneo_id'] : null;
    $club_filter = isset($_GET['club_id']) && $_GET['club_id'] !== '' ? (int)$_GET['club_id'] : null;
    $sexo_filter = $_GET['sexo'] ?? '';
    $search = $_GET['q'] ?? '';
    
    // Construir query
    $where = ["1=1"];
    $params = [];
    
    if ($tournament_filter) {
        $where[] = "r.torneo_id = ?";
        $params[] = $tournament_filter;
    }
    
    if ($club_filter) {
        $where[] = "r.id_club = ?";
        $params[] = $club_filter;
    }
    
    if ($sexo_filter && in_array($sexo_filter, ['M', 'F', 'O'])) {
        $where[] = "u.sexo = ?";
        $params[] = $sexo_filter;
    }
    
    if ($search) {
        $where[] = "(u.nombre LIKE ? OR u.cedula LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Obtener inscritos - ordenar por torneo, club, y usuario id
    $stmt = $pdo->prepare("
        SELECT 
            r.id as inscrito_id,
            r.torneo_id,
            r.id_usuario,
            r.id_club,
            r.celular,
            r.estatus,
            u.nombre, 
            u.sexo,
            t.nombre as torneo_nombre,
            t.fechator as torneo_fecha,
            c.nombre as club_nombre
        FROM inscritos r
        LEFT JOIN usuarios u ON r.id_usuario = u.id
        INNER JOIN tournaments t ON r.torneo_id = t.id
        LEFT JOIN clubes c ON r.id_club = c.id
        WHERE {$where_clause}
        ORDER BY 
            t.nombre ASC,
            COALESCE(c.nombre, 'Sin Club') ASC,
            r.id_usuario ASC
    ");
    $stmt->execute($params);
    $registrants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas
    $stats_where = $tournament_filter ? "WHERE r.torneo_id = {$tournament_filter}" : "";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN u.sexo = 'M' OR u.sexo = 1 THEN 1 ELSE 0 END) as masculino,
            SUM(CASE WHEN u.sexo = 'F' OR u.sexo = 2 THEN 1 ELSE 0 END) as femenino,
            COUNT(DISTINCT r.id_club) as clubes
        FROM inscritos r
        LEFT JOIN usuarios u ON r.id_usuario = u.id
        {$stats_where}
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Determinar título del reporte
    $report_title = 'Reporte de Jugadores Inscritos';
    if ($tournament_filter) {
        $stmt = $pdo->prepare("SELECT nombre FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_filter]);
        $t_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($t_info) {
            $report_title = htmlspecialchars($t_info['nombre']);
        }
    }
    
    // Crear generador de reporte
    $report = new ReportGenerator($report_title, 'landscape');
    
    // Construir contenido HTML
    $content = '';
    
    // Encabezado del reporte
    $subtitle = 'Lista completa de jugadores inscritos';
    
    // Obtener nombre del torneo si hay filtro
    if ($tournament_filter) {
        $stmt = $pdo->prepare("SELECT nombre, fechator FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_filter]);
        $tournament_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tournament_info) {
            $subtitle = htmlspecialchars($tournament_info['nombre']);
            if ($tournament_info['fechator']) {
                $subtitle .= ' - ' . ReportGenerator::formatDate($tournament_info['fechator']);
            }
            
            // Si hay un solo club filtrado, agregarlo al subtítulo
            if ($club_filter) {
                $stmt2 = $pdo->prepare("SELECT nombre FROM clubes WHERE id = ?");
                $stmt2->execute([$club_filter]);
                $club_info_subtitle = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($club_info_subtitle) {
                    $subtitle .= ' - ' . htmlspecialchars($club_info_subtitle['nombre']);
                }
            } else {
                // Verificar si todos los inscritos pertenecen a un solo club
                if (!empty($registrants)) {
                    $clubes_unicos = array_unique(array_filter(array_column($registrants, 'id_club')));
                    if (count($clubes_unicos) === 1 && !empty($clubes_unicos[0])) {
                        $stmt2 = $pdo->prepare("SELECT nombre FROM clubes WHERE id = ?");
                        $stmt2->execute([$clubes_unicos[0]]);
                        $club_info_subtitle = $stmt2->fetch(PDO::FETCH_ASSOC);
                        if ($club_info_subtitle) {
                            $subtitle .= ' - ' . htmlspecialchars($club_info_subtitle['nombre']);
                        }
                    }
                }
            }
        }
    }
    
    if ($search) {
        $subtitle .= ' - Búsqueda: "' . htmlspecialchars($search) . '"';
    }
    
    $content .= $report->addReportHeader($subtitle);
    
    // Estadísticas
    $content .= $report->generateStatsBoxes([
        ['number' => $stats['total'], 'label' => 'Total Inscritos'],
        ['number' => $stats['masculino'], 'label' => 'Masculino'],
        ['number' => $stats['femenino'], 'label' => 'Femenino'],
        ['number' => $stats['clubes'], 'label' => 'Clubes']
    ]);
    
    // Tabla de inscritos
    $content .= '<h2>Listado de Jugadores Inscritos</h2>';
    
    if (empty($registrants)) {
        $content .= '<p style="text-align: center; color: #999; padding: 20px;">No se encontraron jugadores inscritos con los filtros aplicados</p>';
    } else {
        // Agrupar inscritos por torneo y luego por club para ruptura de control
        // Orden: torneo -> club -> id_usuario (ascendente)
        // Primero agrupar TODOS los datos sin perder ninguno
        $inscritos_agrupados_temp = [];
        $total_inscritos_agrupados = 0;
        
        foreach ($registrants as $registrant) {
            $torneo_id = (int)($registrant['torneo_id'] ?? 0);
            $torneo_nombre = $registrant['torneo_nombre'] ?? 'Sin Torneo';
            $club_id = $registrant['id_club'] !== null ? (int)$registrant['id_club'] : null;
            $club_nombre = $registrant['club_nombre'] ?? 'Sin Club';
            
            // Usar clave única: combinar torneo_id y club_id para evitar conflictos
            // Si el mismo club aparece en diferentes torneos, cada uno debe ser tratado por separado
            $club_key = $club_id !== null ? "t{$torneo_id}_c{$club_id}" : "t{$torneo_id}_null_" . md5($club_nombre);
            
            if (!isset($inscritos_agrupados_temp[$torneo_id])) {
                $inscritos_agrupados_temp[$torneo_id] = [
                    'id' => $torneo_id,
                    'nombre' => $torneo_nombre,
                    'clubes' => []
                ];
            }
            
            if (!isset($inscritos_agrupados_temp[$torneo_id]['clubes'][$club_key])) {
                $inscritos_agrupados_temp[$torneo_id]['clubes'][$club_key] = [
                    'id' => $club_id,
                    'nombre' => $club_nombre,
                    'inscritos' => []
                ];
            }
            
            // Agregar TODOS los inscritos - asegurar que no se pierda ninguno
            $inscritos_agrupados_temp[$torneo_id]['clubes'][$club_key]['inscritos'][] = $registrant;
            $total_inscritos_agrupados++;
        }
        
        // Verificar que no se hayan perdido registros
        if ($total_inscritos_agrupados !== count($registrants)) {
            error_log("ADVERTENCIA: Se perdieron registros durante la agrupación. Esperados: " . count($registrants) . ", Agrupados: " . $total_inscritos_agrupados);
        }
        
        // Ordenar torneos por nombre (ascendente)
        uasort($inscritos_agrupados_temp, function($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });
        
        // Convertir arrays asociativos a indexados para mantener orden y ordenar clubes por nombre
        $inscritos_agrupados = [];
        foreach ($inscritos_agrupados_temp as $torneo_id => $torneo) {
            // Ordenar clubes por nombre (ascendente)
            uasort($torneo['clubes'], function($a, $b) {
                $cmp = strcmp($a['nombre'], $b['nombre']);
                if ($cmp === 0) {
                    // Si los nombres son iguales, ordenar por ID
                    $id_a = $a['id'] ?? 0;
                    $id_b = $b['id'] ?? 0;
                    return $id_a <=> $id_b;
                }
                return $cmp;
            });
            
            // Convertir clubes a array indexado y ordenar inscritos
            // IMPORTANTE: Mantener TODOS los inscritos de cada club sin perder ninguno
            $clubes_ordenados = [];
            foreach ($torneo['clubes'] as $club_key => $club) {
                // Verificar que tenemos inscritos
                if (empty($club['inscritos'])) {
                    continue; // Saltar clubes sin inscritos
                }
                
                // Guardar el conteo antes de ordenar para verificación
                $count_before = count($club['inscritos']);
                
                // Ordenar los inscritos por id_usuario (ascendente)
                // NO eliminar duplicados ni filtrar - mantener TODOS
                usort($club['inscritos'], function($a, $b) {
                    $id_a = (int)($a['id_usuario'] ?? 0);
                    $id_b = (int)($b['id_usuario'] ?? 0);
                    if ($id_a === $id_b) {
                        // Si el id_usuario es el mismo, ordenar por inscrito_id para mantener orden consistente
                        $insc_a = (int)($a['inscrito_id'] ?? 0);
                        $insc_b = (int)($b['inscrito_id'] ?? 0);
                        return $insc_a <=> $insc_b;
                    }
                    return $id_a <=> $id_b;
                });
                
                // Verificar que no se hayan perdido inscritos durante el ordenamiento
                $count_after = count($club['inscritos']);
                if ($count_before !== $count_after) {
                    error_log("ERROR: Se perdieron inscritos durante ordenamiento. Club: {$club['nombre']}, Antes: $count_before, Después: $count_after");
                }
                
                // Agregar el club con TODOS sus inscritos
                $clubes_ordenados[] = $club;
            }
            
            $torneo['clubes'] = $clubes_ordenados;
            $inscritos_agrupados[] = $torneo;
        }
        
        $headers = ['ID', 'Nombre', 'Sexo', 'Celular'];
        
        // Generar tabla agrupada por torneo y club con rupturas de control
        foreach ($inscritos_agrupados as $datos_torneo) {
            // Encabezado del torneo (solo si hay múltiples torneos)
            if (count($inscritos_agrupados) > 1) {
                $content .= '<div style="margin-top: 30px; margin-bottom: 15px; page-break-before: auto;">';
                $content .= '<h2 style="background-color: #007bff; color: white; padding: 15px; margin: 0 0 20px 0; border-radius: 5px;">';
                $content .= '<i class="fas fa-trophy"></i> ' . htmlspecialchars($datos_torneo['nombre']);
                $content .= '</h2>';
                $content .= '</div>';
            }
            
            // Iterar por clubes del torneo (ya están ordenados)
            foreach ($datos_torneo['clubes'] as $datos_club) {
                // Encabezado del club (ruptura de control)
                $content .= '<div style="margin-top: 20px; margin-bottom: 10px; page-break-inside: avoid;">';
                $content .= '<h3 style="background-color: #f0f0f0; padding: 10px; margin: 0; border-left: 4px solid #007bff;">';
                $content .= '<i class="fas fa-building"></i> ' . htmlspecialchars($datos_club['nombre']);
                $content .= ' <span style="background-color: #007bff; color: white; padding: 3px 8px; border-radius: 3px; font-size: 0.9em; margin-left: 10px;">';
                $content .= count($datos_club['inscritos']) . ' inscrito(s)';
                $content .= '</span></h3>';
                $content .= '</div>';
                
                // Verificar que tenemos todos los inscritos antes de generar la tabla
                $total_inscritos_club = count($datos_club['inscritos']);
                
                $rows = [];
                foreach ($datos_club['inscritos'] as $registrant) {
                    $sexo_badge = '';
                    $sexo = $registrant['sexo'] ?? '';
                    if ($sexo === 'M' || $sexo == 1) {
                        $sexo_badge = ReportGenerator::badge('M', 'info');
                    } elseif ($sexo === 'F' || $sexo == 2) {
                        $sexo_badge = ReportGenerator::badge('F', 'warning');
                    } else {
                        $sexo_badge = ReportGenerator::badge('O', 'secondary');
                    }
                    
                    $rows[] = [
                        $registrant['inscrito_id'] ?? 'N/A', // ID del inscrito
                        htmlspecialchars($registrant['nombre'] ?? 'N/A'),
                        $sexo_badge,
                        htmlspecialchars($registrant['celular'] ?? 'N/A')
                    ];
                }
                
                $content .= $report->generateTable($headers, $rows);
            }
        }
    }
    
    // Establecer contenido y generar PDF
    $report->setContent($content);
    
    // Nombre del archivo
    $filename = 'reporte_inscritos_' . date('Y-m-d_His') . '.pdf';
    
    // Generar y descargar
    $report->generate($filename, true);
    
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

