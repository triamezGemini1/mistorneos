<?php
/**
 * Rondas, mesas, cuadrícula, hojas/actas, asignación a operadores y reasignación.
 * Delegado desde modules/torneo_gestion.php (misma sesión y helpers del módulo).
 */

require_once __DIR__ . '/../../lib/Core/TorneoMesaAsignacionResolver.php';
/**
 * Obtiene datos de mesas de una ronda.
 * Si el usuario es operador, solo se devuelven las mesas de su ámbito (asignadas a él).
 */
function obtenerDatosMesas($torneo_id, $ronda, $user_id = 0, $user_role = '') {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener todas las mesas de la ronda
    $sql = "SELECT DISTINCT pr.mesa as numero,
                MAX(pr.registrado) as registrado,
                COUNT(DISTINCT pr.id_usuario) as total_jugadores
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
            GROUP BY pr.mesa
            ORDER BY pr.mesa ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda]);
    $todasLasMesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener jugadores agrupados por mesa
    $sql = "SELECT 
                pr.*,
                u.nombre as nombre_completo,
                u.nombre,
                u.sexo,
                c.nombre as club_nombre
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            LEFT JOIN inscritos i ON i.id_usuario = u.id AND i.torneo_id = pr.id_torneo
            LEFT JOIN clubes c ON i.id_club = c.id
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
            ORDER BY pr.mesa ASC, pr.secuencia ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por mesa con estructura completa
    $mesas = [];
    foreach ($resultados as $resultado) {
        $numMesa = (int)$resultado['mesa'];
        if (!isset($mesas[$numMesa])) {
            $mesas[$numMesa] = [
                'mesa' => $numMesa,
                'numero' => $numMesa,
                'registrado' => $resultado['registrado'] ?? 0,
                'tiene_resultados' => ($resultado['registrado'] ?? 0) > 0,
                'jugadores' => []
            ];
        }
        $mesas[$numMesa]['jugadores'][] = $resultado;
    }
    
    // Operador: limitar a sus mesas asignadas (ámbito)
    $mesas_operador = obtenerMesasAsignadasOperador($torneo_id, $ronda, $user_id, $user_role);
    if ($mesas_operador !== null && !empty($mesas_operador)) {
        $set_operador = array_flip($mesas_operador);
        $mesas = array_intersect_key($mesas, $set_operador);
        $mesas = array_values($mesas);
    } elseif ($mesas_operador !== null && empty($mesas_operador)) {
        $mesas = [];
    } else {
        $mesas = array_values($mesas);
    }
    
    // Obtener total de rondas
    $stmt = $pdo->prepare("SELECT rondas FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $totalRondas = $stmt->fetchColumn() ?? 0;
    
    return [
        'torneo' => $torneo,
        'ronda' => $ronda,
        'mesas' => $mesas,
        'totalRondas' => $totalRondas,
        'es_operador_ambito' => $mesas_operador !== null,
    ];
}

/**
 * Obtiene datos de rondas
 */
function obtenerDatosRondas($torneo_id) {
    $pdo = DB::pdo();
    
    $torneo = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $torneo->execute([$torneo_id]);
    $torneo = $torneo->fetch(PDO::FETCH_ASSOC);
    
    $rondas_generadas = obtenerRondasGeneradas($torneo_id);
    $ultima_ronda = !empty($rondas_generadas) ? max(array_column($rondas_generadas, 'num_ronda')) : 0;
    $proxima_ronda = $ultima_ronda + 1;
    
    return [
        'torneo' => $torneo,
        'rondas_generadas' => $rondas_generadas,
        'proxima_ronda' => $proxima_ronda
    ];
}
/**
 * Obtiene datos para la cuadrícula
 */
function obtenerDatosCuadricula($torneo_id, $ronda) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener asignaciones ordenadas por ID usuario ASC (incluye mesas reales y BYE con mesa=0)
    // La letra (A,C,B,D) se asigna según secuencia: 1=A, 2=C, 3=B, 4=D; BYE tiene mesa=0 y secuencia típicamente 1
    $sql = "SELECT 
                pr.id_usuario,
                pr.mesa,
                pr.secuencia,
                u.nombre as nombre_completo,
                u.username
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            WHERE pr.id_torneo = ? AND pr.partida = ?
            ORDER BY pr.id_usuario ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda]);
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'titulo' => 'Cuadrícula de Asignaciones - Ronda ' . $ronda,
        'torneo' => $torneo,
        'numRonda' => $ronda,
        'asignaciones' => $asignaciones,
        'totalAsignaciones' => count($asignaciones)
    ];
}

/**
 * Obtiene datos para hojas de anotación
 */
function obtenerDatosHojasAnotacion($torneo_id, $ronda) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $es_torneo_equipos = (int)($torneo['modalidad'] ?? 0) === 3;
    
    // Obtener inscritos con estadísticas (incluyendo tarjeta y codigo_equipo)
    $sql = "SELECT 
                id_usuario,
                codigo_equipo,
                posicion,
                ganados,
                perdidos,
                efectividad,
                puntos,
                sancion,
                tarjeta
            FROM inscritos
            WHERE torneo_id = ?
            ORDER BY posicion ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id]);
    $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $inscritosMap = [];
    foreach ($inscritos as $inscrito) {
        $inscritosMap[$inscrito['id_usuario']] = $inscrito;
    }
    
    // Obtener información de equipos si es torneo de equipos
    $equiposMap = [];
    $estadisticasEquipos = [];
    if ($es_torneo_equipos) {
        $sql = "SELECT 
                    e.codigo_equipo,
                    e.nombre_equipo,
                    e.id_club,
                    c.nombre as nombre_club
                FROM equipos e
                LEFT JOIN clubes c ON e.id_club = c.id
                WHERE e.id_torneo = ? AND e.estatus = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$torneo_id]);
        $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($equipos as $equipo) {
            $equiposMap[$equipo['codigo_equipo']] = $equipo;
        }
        
        // Estadísticas y posición de equipos desde tabla equipos (posicion/clasiequi ya calculada)
        $sql = "SELECT codigo_equipo, posicion, puntos, ganados, perdidos, efectividad
                FROM equipos
                WHERE id_torneo = ? AND estatus = 0 AND codigo_equipo IS NOT NULL AND codigo_equipo != '' AND codigo_equipo != '000-000'
                ORDER BY posicion ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$torneo_id]);
        $statsEquipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($statsEquipos as $stat) {
            $posicion = (int)($stat['posicion'] ?? 0);
            $estadisticasEquipos[$stat['codigo_equipo']] = [
                'posicion' => $posicion,
                'clasiequi' => $posicion,
                'puntos' => (int)($stat['puntos'] ?? 0),
                'ganados' => (int)($stat['ganados'] ?? 0),
                'perdidos' => (int)($stat['perdidos'] ?? 0),
                'efectividad' => (int)($stat['efectividad'] ?? 0),
                'total_jugadores' => 4
            ];
        }
    }
    
    // Obtener mesas
    $sql = "SELECT 
                pr.*,
                u.nombre as nombre_completo,
                i.codigo_equipo,
                c.nombre as nombre_club
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            LEFT JOIN inscritos i ON i.id_usuario = u.id AND i.torneo_id = pr.id_torneo
            LEFT JOIN clubes c ON i.id_club = c.id
            WHERE pr.id_torneo = ? AND pr.partida = ?
            ORDER BY pr.mesa ASC, pr.secuencia ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por mesa
    $mesas = [];
    foreach ($resultados as $resultado) {
        $numMesa = (int)$resultado['mesa'];
        if ($numMesa > 0) {
            if (!isset($mesas[$numMesa])) {
                $mesas[$numMesa] = [
                    'numero' => $numMesa,
                    'jugadores' => []
                ];
            }
            
            $idUsuario = $resultado['id_usuario'];
            $inscritoData = $inscritosMap[$idUsuario] ?? [
                'posicion' => 0, 'ganados' => 0, 'perdidos' => 0,
                'efectividad' => 0, 'puntos' => 0, 'sancion' => 0, 'tarjeta' => 0,
                'codigo_equipo' => null
            ];
            
            // Agregar información del equipo si es torneo de equipos
            $codigoEquipo = $resultado['codigo_equipo'] ?? $inscritoData['codigo_equipo'] ?? null;
            if ($es_torneo_equipos && $codigoEquipo) {
                $equipoData = $equiposMap[$codigoEquipo] ?? null;
                if ($equipoData) {
                    $resultado['nombre_equipo'] = $equipoData['nombre_equipo'];
                    $resultado['codigo_equipo_display'] = $equipoData['codigo_equipo'];
                }
                
                // Agregar estadísticas del equipo
                if (isset($estadisticasEquipos[$codigoEquipo])) {
                    $resultado['estadisticas_equipo'] = $estadisticasEquipos[$codigoEquipo];
                }
            }
            
            // Usar la tarjeta de inscritos (última tarjeta del jugador en el torneo)
            $resultado['tarjeta'] = (int)($inscritoData['tarjeta'] ?? 0);
            $resultado['inscrito'] = [
                'posicion' => (int)($inscritoData['posicion'] ?? 0),
                'ganados' => (int)($inscritoData['ganados'] ?? 0),
                'perdidos' => (int)($inscritoData['perdidos'] ?? 0),
                'efectividad' => (int)($inscritoData['efectividad'] ?? 0),
                'puntos' => (int)($inscritoData['puntos'] ?? 0),
                'sancion' => (int)($inscritoData['sancion'] ?? 0),
                'tarjeta' => (int)($inscritoData['tarjeta'] ?? 0)
            ];
            
            $mesas[$numMesa]['jugadores'][] = $resultado;
        }
    }
    
    return [
        'torneo' => $torneo,
        'ronda' => $ronda,
        'mesas' => array_values($mesas),
        'es_torneo_equipos' => $es_torneo_equipos
    ];
}

/**
 * Obtiene datos para asignar mesas a operadores (operadores del club del torneo, mesas de la ronda, asignaciones actuales).
 */
function obtenerDatosAsignarMesasOperador($torneo_id, $ronda) {
    $pdo = DB::pdo();
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$torneo) {
        throw new Exception('Torneo no encontrado');
    }
    $club_responsable = (int)($torneo['club_responsable'] ?? 0);
    require_once __DIR__ . '/../../lib/ClubHelper.php';
    $club_ids = ClubHelper::getClubesSupervised($club_responsable);
    if (empty($club_ids)) {
        $club_ids = [$club_responsable];
    }
    $placeholders = implode(',', array_fill(0, count($club_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT u.id, u.nombre, u.username
        FROM usuarios u
        WHERE u.role = 'operador' AND u.club_id IN ($placeholders) AND (u.status = 'approved' OR u.status = 1)
        ORDER BY u.nombre ASC
    ");
    $stmt->execute(array_values($club_ids));
    $operadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("
        SELECT DISTINCT CAST(pr.mesa AS UNSIGNED) as numero
        FROM partiresul pr
        WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
        ORDER BY numero ASC
    ");
    $stmt->execute([$torneo_id, $ronda]);
    $mesas_numeros = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'numero');
    $asignaciones = [];
    $table_exists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'operador_mesa_asignacion'");
        $table_exists = $stmt->rowCount() > 0;
    } catch (Exception $e) {}
    if ($table_exists) {
        $stmt = $pdo->prepare("SELECT mesa_numero, user_id_operador FROM operador_mesa_asignacion WHERE torneo_id = ? AND ronda = ?");
        $stmt->execute([$torneo_id, $ronda]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $asignaciones[(int)$row['mesa_numero']] = (int)$row['user_id_operador'];
        }
    }
    return [
        'torneo' => $torneo,
        'torneo_id' => $torneo_id,
        'ronda' => $ronda,
        'operadores' => $operadores,
        'mesas_numeros' => $mesas_numeros,
        'asignaciones' => $asignaciones,
        'tabla_existe' => $table_exists,
    ];
}
/**
 * Guarda la asignación de mesas a operadores (crea tabla si no existe).
 */
function guardarAsignacionMesasOperador($torneo_id, $ronda, $user_id, $is_admin_general) {
    verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
    $pdo = DB::pdo();
    $sql_file = __DIR__ . '/../../sql/operador_mesa_asignacion.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        if ($sql) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {
                // Tabla ya existe o error; continuar
            }
        }
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS operador_mesa_asignacion (
          torneo_id INT NOT NULL,
          ronda INT NOT NULL,
          mesa_numero INT NOT NULL,
          user_id_operador INT NOT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (torneo_id, ronda, mesa_numero),
          KEY idx_oma_operador (user_id_operador)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    $asignaciones = $_POST['asignacion'] ?? [];
    if (!is_array($asignaciones)) {
        $asignaciones = [];
    }
    $pdo->beginTransaction();
    try {
        $stmtDel = $pdo->prepare("DELETE FROM operador_mesa_asignacion WHERE torneo_id = ? AND ronda = ?");
        $stmtDel->execute([$torneo_id, $ronda]);
        $stmtIns = $pdo->prepare("INSERT INTO operador_mesa_asignacion (torneo_id, ronda, mesa_numero, user_id_operador) VALUES (?, ?, ?, ?)");
        foreach ($asignaciones as $mesa_numero => $user_id_op) {
            $mesa_numero = (int)$mesa_numero;
            $user_id_op = (int)$user_id_op;
            if ($mesa_numero > 0 && $user_id_op > 0) {
                $stmtIns->execute([$torneo_id, $ronda, $mesa_numero, $user_id_op]);
            }
        }
        $pdo->commit();
        $_SESSION['success'] = 'Asignación de mesas a operadores guardada correctamente.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error al guardar: ' . $e->getMessage();
    }
    $url = buildRedirectUrl('asignar_mesas_operador', ['torneo_id' => $torneo_id, 'ronda' => $ronda]);
    header('Location: ' . $url);
    exit;
}
/**
 * Obtiene datos para agregar mesa adicional.
 * Solo muestra jugadores NO asignados en esta ronda (excluye los que ya están en partiresul).
 */
function obtenerDatosAgregarMesa($torneo_id, $ronda) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener solo jugadores NO asignados en esta ronda (no están en partiresul para esta ronda)
    $sql = "SELECT i.id_usuario, u.nombre, u.nombre as nombre_completo
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            LEFT JOIN partiresul pr ON pr.id_torneo = i.torneo_id AND pr.id_usuario = i.id_usuario AND pr.partida = ?
            WHERE i.torneo_id = ? AND i.estatus IN ('confirmado', 'solvente')
              AND pr.id_usuario IS NULL
            ORDER BY u.nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ronda, $torneo_id]);
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'torneo' => $torneo,
        'ronda' => $ronda,
        'jugadores' => $jugadores
    ];
}

/**
 * Verifica si una mesa existe
 */
function verificarMesaExiste($torneo_id, $ronda, $mesa) {
    $pdo = DB::pdo();
    
    $sql = "SELECT COUNT(*) as total
            FROM partiresul
            WHERE id_torneo = ? AND partida = ? AND mesa = ? AND mesa > 0";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $ronda, $mesa]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return isset($resultado['total']) && (int)$resultado['total'] > 0;
}
/**
 * Genera una nueva ronda
 */
function generarRonda($torneo_id, $user_id, $is_admin_general) {
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        $pdo = DB::pdo();
        
        // Obtener torneo para verificar modalidad y nombre
        $stmt = $pdo->prepare("SELECT nombre, rondas, modalidad FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_rondas = (int)($torneo['rondas'] ?? 0);
        $modalidad = (int)($torneo['modalidad'] ?? 0);

        $mesaService = TorneoMesaAsignacionResolver::servicioPorModalidad($modalidad);
        
        // Verificar que la última ronda esté completa
        $ultima_ronda = $mesaService->obtenerUltimaRonda($torneo_id);
        
        if ($ultima_ronda > 0) {
            $todas_completas = $mesaService->todasLasMesasCompletas($torneo_id, $ultima_ronda);
            if (!$todas_completas) {
                $mesas_incompletas = $mesaService->contarMesasIncompletas($torneo_id, $ultima_ronda);
                $_SESSION['error'] = "No se puede generar una nueva ronda. Faltan resultados en {$mesas_incompletas} mesa(s) de la ronda {$ultima_ronda}";
                header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
                exit;
            }
        }
        
        // Actualizar estadísticas antes de generar nueva ronda
        try {
            actualizarEstadisticasInscritos($torneo_id);
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al actualizar estadísticas: ' . $e->getMessage();
            header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
            exit;
        }
        
        $proxima_ronda = $ultima_ronda + 1;
        $estrategia = TorneoMesaAsignacionResolver::estrategiaDesdeRequest($modalidad);
        
        // Generar ronda usando el servicio apropiado
        $resultado = $mesaService->generarAsignacionRonda(
            $torneo_id,
            $proxima_ronda,
            $total_rondas,
            $estrategia
        );
        
        if ($resultado['success']) {
            $mensaje = $resultado['message'];
            if (isset($resultado['total_mesas'])) {
                $mensaje .= ': ' . $resultado['total_mesas'] . ' mesas';
            }
            if (isset($resultado['total_equipos'])) {
                $mensaje .= ', ' . $resultado['total_equipos'] . ' equipos';
            }
            if (isset($resultado['jugadores_bye']) && $resultado['jugadores_bye'] > 0) {
                $mensaje .= ', ' . $resultado['jugadores_bye'] . ' jugadores BYE';
            }
            $_SESSION['success'] = $mensaje;

            // Actualizar estadísticas de nuevo para incluir los BYE recién generados en inscritos
            try {
                actualizarEstadisticasInscritos($torneo_id);
            } catch (Exception $e) {
                error_log('generarRonda: Error al actualizar estadísticas tras generar ronda: ' . $e->getMessage());
            }

            // Encolar notificaciones masivas (Telegram + campanita web) usando plantilla 'nueva_ronda'
            try {
                $stmtJug = $pdo->prepare("
                    SELECT u.id, u.nombre, u.telegram_chat_id,
                           COALESCE(i.posicion, 0) AS posicion, COALESCE(i.ganados, 0) AS ganados, COALESCE(i.perdidos, 0) AS perdidos,
                           COALESCE(i.efectividad, 0) AS efectividad, COALESCE(i.puntos, 0) AS puntos
                    FROM inscritos i
                    INNER JOIN usuarios u ON i.id_usuario = u.id
                    WHERE i.torneo_id = ? AND i.estatus != 4
                ");
                $stmtJug->execute([$torneo_id]);
                $jugadores = $stmtJug->fetchAll(PDO::FETCH_ASSOC);

                // Mesa y pareja para esta ronda (partiresul ya tiene la asignación recién generada)
                $mesaPareja = [];
                $stmtMesa = $pdo->prepare("
                    SELECT pr.id_usuario, pr.mesa, pr_p.id_usuario AS pareja_id, u_pareja.nombre AS pareja_nombre
                    FROM partiresul pr
                    LEFT JOIN partiresul pr_p ON pr_p.id_torneo = pr.id_torneo AND pr_p.partida = pr.partida AND pr_p.mesa = pr.mesa
                        AND pr_p.secuencia = CASE pr.secuencia WHEN 1 THEN 2 WHEN 2 THEN 1 WHEN 3 THEN 4 WHEN 4 THEN 3 END
                    LEFT JOIN usuarios u_pareja ON u_pareja.id = pr_p.id_usuario
                    WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
                ");
                $stmtMesa->execute([$torneo_id, $proxima_ronda]);
                while ($row = $stmtMesa->fetch(PDO::FETCH_ASSOC)) {
                    $mesaPareja[(int)$row['id_usuario']] = [
                        'mesa' => (string)$row['mesa'],
                        'pareja_id' => (int)($row['pareja_id'] ?? 0),
                        'pareja' => trim((string)($row['pareja_nombre'] ?? '')) ?: '—',
                    ];
                }

                require_once __DIR__ . '/../../lib/app_helpers.php';
                $urlSpaJugador = rtrim(AppHelpers::getPublicUrl(), '/') . '/perfil_jugador.php?torneo_id=' . $torneo_id;
                foreach ($jugadores as &$j) {
                    $uid = (int)$j['id'];
                    $j['mesa'] = $mesaPareja[$uid]['mesa'] ?? '—';
                    $j['pareja_id'] = $mesaPareja[$uid]['pareja_id'] ?? 0;
                    $j['pareja'] = $mesaPareja[$uid]['pareja'] ?? '—';
                    $j['url_resumen'] = $urlSpaJugador;
                    $j['url_clasificacion'] = AppHelpers::url('index.php', ['page' => 'torneo_gestion', 'action' => 'posiciones', 'torneo_id' => $torneo_id, 'from' => 'notificaciones']);
                }
                unset($j);

                $titulo = $torneo['nombre'] ?? 'Torneo';
                if (!empty($jugadores)) {
                    require_once __DIR__ . '/../../lib/NotificationManager.php';
                    $nm = new NotificationManager($pdo);
                    $nm->programarRondaMasiva($jugadores, $titulo, $proxima_ronda, null, 'nueva_ronda', $torneo_id);
                }
            } catch (Exception $e) {
                error_log("Notificaciones ronda: " . $e->getMessage());
            }
        } else {
            $_SESSION['error'] = $resultado['message'];
        }
        
    } catch (Throwable $e) {
        $detail = $e->getMessage();
        if ($e instanceof PDOException) {
            $info = $e->errorInfo ?? null;
            if (is_array($info)) {
                $detail .= ' | SQLSTATE=' . ($info[0] ?? '') . ' code=' . ($info[1] ?? '') . ' driver=' . ($info[2] ?? '');
            }
        }
        $prev = $e->getPrevious();
        if ($prev instanceof Throwable) {
            $detail .= ' | causa: ' . $prev->getMessage();
        }
        error_log('Error al generar ronda (torneo_id=' . (int)($torneo_id ?? 0) . '): ' . $detail . "\n" . $e->getTraceAsString());
        $_SESSION['error'] = 'Error al generar ronda: ' . $e->getMessage();
    }
    
    header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
    exit;
}
/**
 * Elimina la última ronda generada
 */
function eliminarUltimaRonda($torneo_id, $user_id, $is_admin_general) {
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT modalidad FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        $modalidad = (int)($torneo['modalidad'] ?? 0);
        $mesaService = TorneoMesaAsignacionResolver::servicioPorModalidad($modalidad);
        $ultima_ronda = $mesaService->obtenerUltimaRonda($torneo_id);
        
        if ($ultima_ronda === 0) {
            $_SESSION['error'] = 'No hay rondas generadas para eliminar';
            header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
            exit;
        }
        
        $eliminada = TorneoMesaAsignacionResolver::eliminarRonda($torneo_id, $ultima_ronda, $modalidad);
        
        if ($eliminada) {
            $_SESSION['success'] = "Ronda {$ultima_ronda} eliminada exitosamente";
        } else {
            $_SESSION['error'] = "Error al eliminar la ronda {$ultima_ronda}";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error al eliminar ronda: ' . $e->getMessage();
    }
    
    header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
    exit;
}
/**
 * Guarda una mesa adicional
 */
function guardarMesaAdicional($torneo_id, $ronda, $user_id, $is_admin_general) {
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        // Verificar que haya al menos 4 jugadores disponibles (no asignados en esta ronda)
        $pdo = DB::pdo();
        $sql = "SELECT COUNT(*) as total FROM inscritos i
                LEFT JOIN partiresul pr ON pr.id_torneo = i.torneo_id AND pr.id_usuario = i.id_usuario AND pr.partida = ?
                WHERE i.torneo_id = ? AND i.estatus IN (1, 2, 'confirmado', 'solvente') AND pr.id_usuario IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ronda, $torneo_id]);
        $disponibles = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        if ($disponibles < 4) {
            $_SESSION['error'] = 'No hay jugadores disponibles.';
            header('Location: ' . buildRedirectUrl('agregar_mesa', ['torneo_id' => $torneo_id, 'ronda' => $ronda]));
            exit;
        }
        
        $jugadores_ids = $_POST['jugadores'] ?? [];
        
        if (empty($jugadores_ids) || !is_array($jugadores_ids) || count($jugadores_ids) != 4) {
            throw new Exception('Debe seleccionar exactamente 4 jugadores');
        }
        
        // Verificar que los jugadores sean diferentes
        if (count($jugadores_ids) !== count(array_unique($jugadores_ids))) {
            throw new Exception('Los jugadores deben ser diferentes');
        }
        
        $pdo->beginTransaction();
        
        // Obtener el siguiente número de mesa
        $stmt = $pdo->prepare("SELECT MAX(mesa) as max_mesa 
                               FROM partiresul 
                               WHERE id_torneo = ? AND partida = ? AND mesa > 0");
        $stmt->execute([$torneo_id, $ronda]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nuevaMesa = ((int)($result['max_mesa'] ?? 0)) + 1;
        
        // Verificar que los jugadores estén inscritos y no estén ya asignados en esta ronda
        $stmt = $pdo->prepare("SELECT id_usuario FROM inscritos 
                               WHERE torneo_id = ? AND id_usuario = ? AND estatus IN ('confirmado', 'solvente')");
        $stmt2 = $pdo->prepare("SELECT COUNT(*) as existe 
                                FROM partiresul 
                                WHERE id_torneo = ? AND partida = ? AND id_usuario = ? AND mesa > 0");
        
        foreach ($jugadores_ids as $jugador_id) {
            $stmt->execute([$torneo_id, $jugador_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Uno de los jugadores seleccionados no está inscrito o no está disponible');
            }
            
            $stmt2->execute([$torneo_id, $ronda, $jugador_id]);
            $existe = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($existe && $existe['existe'] > 0) {
                throw new Exception('Uno de los jugadores seleccionados ya está asignado en esta ronda');
            }
        }
        
        // Insertar los jugadores en la nueva mesa
        $registrado_por = (int)$user_id ?: 1;
        $stmt = $pdo->prepare("INSERT INTO partiresul 
                               (id_torneo, id_usuario, partida, mesa, secuencia, fecha_partida, registrado, registrado_por)
                               VALUES (?, ?, ?, ?, ?, NOW(), 0, ?)");
        
        foreach ($jugadores_ids as $index => $jugador_id) {
            $stmt->execute([
                $torneo_id,
                $jugador_id,
                $ronda,
                $nuevaMesa,
                $index + 1,
                $registrado_por
            ]);
        }
        
        // Registrar parejas en historial_parejas (id_menor-id_mayor) para evitar que vuelvan a jugar juntos
        try {
            $stmtH = $pdo->prepare(
                "INSERT IGNORE INTO historial_parejas (torneo_id, ronda_id, jugador_1_id, jugador_2_id, llave) VALUES (?, ?, ?, ?, ?)"
            );
            $a = (int)$jugadores_ids[0];
            $c = (int)$jugadores_ids[1];
            $b = (int)$jugadores_ids[2];
            $d = (int)$jugadores_ids[3];
            if ($a > 0 && $c > 0) {
                $idMenor = min($a, $c);
                $idMayor = max($a, $c);
                $stmtH->execute([$torneo_id, $ronda, $idMenor, $idMayor, $idMenor . '-' . $idMayor]);
            }
            if ($b > 0 && $d > 0) {
                $idMenor = min($b, $d);
                $idMayor = max($b, $d);
                $stmtH->execute([$torneo_id, $ronda, $idMenor, $idMayor, $idMenor . '-' . $idMayor]);
            }
        } catch (Exception $e) {
            try {
                $stmtH = $pdo->prepare(
                    "INSERT IGNORE INTO historial_parejas (torneo_id, ronda_id, jugador_1_id, jugador_2_id) VALUES (?, ?, ?, ?)"
                );
                $a = (int)$jugadores_ids[0];
                $c = (int)$jugadores_ids[1];
                $b = (int)$jugadores_ids[2];
                $d = (int)$jugadores_ids[3];
                if ($a > 0 && $c > 0) $stmtH->execute([$torneo_id, $ronda, min($a, $c), max($a, $c)]);
                if ($b > 0 && $d > 0) $stmtH->execute([$torneo_id, $ronda, min($b, $d), max($b, $d)]);
            } catch (Exception $e2) {
                // Tabla puede no existir
            }
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "Mesa adicional {$nuevaMesa} creada exitosamente. Los jugadores han sido asignados y aparecen en la cuadrícula.";
        
        // Redirigir a cuadrícula para que el usuario vea la reconstrucción con los nuevos jugadores
        header('Location: ' . buildRedirectUrl('cuadricula', ['torneo_id' => $torneo_id, 'ronda' => $ronda]));
        exit;
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Error al crear mesa adicional: ' . $e->getMessage();
    }
    
    header('Location: ' . buildRedirectUrl('agregar_mesa', ['torneo_id' => $torneo_id, 'ronda' => $ronda]));
    exit;
}
/**
 * Obtiene datos para mostrar formulario de reasignación de mesa
 */
function obtenerDatosReasignarMesa($torneo_id, $ronda, $mesa) {
    $pdo = DB::pdo();
    
    // Obtener jugadores de la mesa actual
    $stmt = $pdo->prepare("SELECT 
                pr.*,
                u.nombre as nombre_completo,
                i.posicion,
                i.ganados,
                i.perdidos,
                i.efectividad
            FROM partiresul pr
            INNER JOIN usuarios u ON pr.id_usuario = u.id
            LEFT JOIN inscritos i ON i.id_usuario = u.id AND i.torneo_id = pr.id_torneo
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
            ORDER BY pr.secuencia ASC");
    $stmt->execute([$torneo_id, $ronda, $mesa]);
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener todas las mesas de la ronda
    $stmt = $pdo->prepare("SELECT DISTINCT 
                CAST(pr.mesa AS UNSIGNED) as numero,
                MAX(pr.registrado) as registrado,
                COUNT(DISTINCT pr.id_usuario) as total_jugadores
            FROM partiresul pr
            WHERE pr.id_torneo = ? 
              AND pr.partida = ? 
              AND pr.mesa IS NOT NULL 
              AND pr.mesa > 0 
              AND CAST(pr.mesa AS UNSIGNED) > 0
            GROUP BY CAST(pr.mesa AS UNSIGNED)
            ORDER BY CAST(pr.mesa AS UNSIGNED) ASC");
    $stmt->execute([$torneo_id, $ronda]);
    $todasLasMesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir numero a entero y ordenar
    foreach ($todasLasMesas as &$m) {
        $m['numero'] = (int)$m['numero'];
        $m['tiene_resultados'] = $m['registrado'];
    }
    usort($todasLasMesas, function($a, $b) {
        return $a['numero'] <=> $b['numero'];
    });
    
    // Obtener todas las rondas del torneo
    $stmt = $pdo->prepare("SELECT DISTINCT partida as ronda 
                          FROM partiresul 
                          WHERE id_torneo = ? AND partida > 0 
                          ORDER BY partida ASC");
    $stmt->execute([$torneo_id]);
    $todasLasRondas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Determinar mesa anterior y siguiente
    $mesaAnterior = null;
    $mesaSiguiente = null;
    foreach ($todasLasMesas as $index => $m) {
        if ($m['numero'] == $mesa) {
            if ($index > 0) {
                $mesaAnterior = $todasLasMesas[$index - 1]['numero'];
            }
            if ($index < count($todasLasMesas) - 1) {
                $mesaSiguiente = $todasLasMesas[$index + 1]['numero'];
            }
            break;
        }
    }
    
    return [
        'jugadores' => $jugadores,
        'todasLasMesas' => $todasLasMesas,
        'todasLasRondas' => $todasLasRondas,
        'mesaAnterior' => $mesaAnterior,
        'mesaSiguiente' => $mesaSiguiente
    ];
}

/**
 * Ejecuta la reasignación de una mesa
 */
function ejecutarReasignacion($torneo_id, $ronda, $mesa, $user_id, $is_admin_general) {
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        // Verificar CSRF
        $csrf_token = $_POST['csrf_token'] ?? '';
        $session_token = $_SESSION['csrf_token'] ?? '';
        if (!$csrf_token || !$session_token || !hash_equals($session_token, $csrf_token)) {
            throw new Exception('Token de seguridad inválido. Por favor, recarga la página e intenta nuevamente.');
        }
        
        $opcion = (int)($_POST['opcion_reasignacion'] ?? 0);
        
        if (!in_array($opcion, [1, 2, 3, 4, 5, 6])) {
            throw new Exception('Opción de reasignación no válida');
        }
        
        $pdo = DB::pdo();
        $stmtTorneo = $pdo->prepare("SELECT modalidad FROM tournaments WHERE id = ? LIMIT 1");
        $stmtTorneo->execute([$torneo_id]);
        $modalidad = (int)($stmtTorneo->fetchColumn() ?: 0);
        $esParejasFijas = ($modalidad === 4);
        if ($esParejasFijas && !in_array($opcion, [5, 6], true)) {
            throw new Exception('En Parejas Fijas solo se permiten movimientos en bloque de pareja (opciones 5 o 6).');
        }
        
        // Obtener jugadores actuales de la mesa
        $stmt = $pdo->prepare("SELECT * FROM partiresul 
                              WHERE id_torneo = ? AND partida = ? AND mesa = ?
                              ORDER BY secuencia ASC");
        $stmt->execute([$torneo_id, $ronda, $mesa]);
        $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($jugadores) != 4) {
            throw new Exception('La mesa debe tener exactamente 4 jugadores');
        }
        
        // Crear mapa de secuencias actuales
        $mapaActual = [];
        foreach ($jugadores as $jugador) {
            $mapaActual[$jugador['secuencia']] = $jugador;
        }
        
        // Definir cambios según la opción (un solo swap por par: [origen, destino] evita deshacer el cambio)
        $cambios = [];
        switch ($opcion) {
            case 1: // Intercambiar posición 1 con 3
                $cambios = [[1, 3]];
                break;
            case 2: // Intercambiar posición 1 con 4
                $cambios = [[1, 4]];
                break;
            case 3: // Intercambiar posición 2 con 3
                $cambios = [[2, 3]];
                break;
            case 4: // Intercambiar posición 2 con 4
                $cambios = [[2, 4]];
                break;
            case 5: // Intercambio completo de parejas (1↔3, 2↔4)
                $cambios = [[1, 3], [2, 4]];
                break;
            case 6: // Intercambio cruzado (1↔4, 2↔3)
                $cambios = [[1, 4], [2, 3]];
                break;
        }
        
        $pdo->beginTransaction();
        
        try {
            // Crear mapa de cambios finales
            $mapaFinal = [];
            foreach ($mapaActual as $seq => $jugador) {
                $mapaFinal[$seq] = $jugador['id_usuario'];
            }
            
            // Aplicar cambios
            foreach ($cambios as $cambio) {
                $secuenciaOrigen = $cambio[0];
                $secuenciaDestino = $cambio[1];
                $temp = $mapaFinal[$secuenciaOrigen];
                $mapaFinal[$secuenciaOrigen] = $mapaFinal[$secuenciaDestino];
                $mapaFinal[$secuenciaDestino] = $temp;
            }
            
            // Actualizar cada jugador a su nueva secuencia
            foreach ($mapaFinal as $nuevaSecuencia => $idUsuario) {
                $stmt = $pdo->prepare("UPDATE partiresul 
                                      SET secuencia = ? 
                                      WHERE id_torneo = ? 
                                        AND partida = ? 
                                        AND mesa = ? 
                                        AND id_usuario = ?");
                $stmt->execute([$nuevaSecuencia, $torneo_id, $ronda, $mesa, $idUsuario]);
            }
            
            $pdo->commit();
            $_SESSION['success'] = 'Mesa reasignada exitosamente. Los cambios se han aplicado correctamente.';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
        header('Location: ' . buildRedirectUrl('registrar_resultados', [
            'torneo_id' => $torneo_id, 
            'ronda' => $ronda, 
            'mesa' => $mesa
        ]));
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error al reasignar la mesa: ' . $e->getMessage();
        error_log("Error en reasignación de mesa: " . $e->getMessage());
        header('Location: ' . buildRedirectUrl('reasignar_mesa', [
            'torneo_id' => $torneo_id, 
            'ronda' => $ronda, 
            'mesa' => $mesa
        ]));
        exit;
    }
}

/**
 * POST: generar ronda, eliminar última, mesa adicional, reasignación, asignación manual a operadores.
 */
function torneo_gestion_rondas_mesas_handle_post(string $post_action, int $user_id, bool $is_admin_general): void
{
    switch ($post_action) {
        case 'generar_ronda':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            generarRonda($torneo_id, $user_id, $is_admin_general);
            break;

        case 'eliminar_ultima_ronda':
        case 'limpiar_ronda':
        case 'eliminar_ronda':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            eliminarUltimaRonda($torneo_id, $user_id, $is_admin_general);
            break;

        case 'guardar_mesa_adicional':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            $ronda = (int)($_POST['ronda'] ?? 0);
            if ($ronda >= 2) {
                $_SESSION['error'] = 'Agregar mesa solo está disponible en la ronda 1.';
                header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
                exit;
            }
            guardarMesaAdicional($torneo_id, $ronda, $user_id, $is_admin_general);
            break;

        case 'ejecutar_reasignacion':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            $ronda = (int)($_POST['ronda'] ?? 0);
            $mesa = (int)($_POST['mesa'] ?? 0);
            ejecutarReasignacion($torneo_id, $ronda, $mesa, $user_id, $is_admin_general);
            break;

        case 'guardar_asignacion_mesas_operador':
        case 'actualizar_mesa_manual':
            $torneo_id = (int)($_POST['torneo_id'] ?? 0);
            $ronda = (int)($_POST['ronda'] ?? 0);
            guardarAsignacionMesasOperador($torneo_id, $ronda, $user_id, $is_admin_general);
            break;

        default:
            $_SESSION['error'] = 'Acción POST de rondas/mesas no reconocida.';
            $tid = (int)($_POST['torneo_id'] ?? 0);
            if ($tid > 0) {
                header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $tid]));
            } else {
                header('Location: ' . buildRedirectUrl('index'));
            }
            exit;
    }
}

/**
 * GET: vistas y AJAX de mesas/rondas/impresión.
 *
 * @return array{view_file: string, view_data: array}|null
 */
function torneo_gestion_rondas_mesas_try_route_get(
    string $action,
    ?int $torneo_id,
    ?int $ronda,
    ?int $mesa,
    int $user_id,
    string $user_role,
    bool $is_admin_general
): ?array {
    $effective = $action;
    if ($action === 'gestionar_mesas') {
        $effective = 'mesas';
    }
    if ($action === 'imprimir_actas' || $action === 'reimprimir_todas') {
        $effective = 'hojas_anotacion';
    }

    $modulesDir = dirname(__DIR__);

    switch ($effective) {
        case 'mesas':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            return [
                'view_file' => $modulesDir . '/gestion_torneos/mesas.php',
                'view_data' => obtenerDatosMesas($torneo_id, $ronda, $user_id, $user_role),
            ];

        case 'asignar_mesas_operador':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            return [
                'view_file' => $modulesDir . '/gestion_torneos/asignar_mesas_operador.php',
                'view_data' => obtenerDatosAsignarMesasOperador($torneo_id, $ronda),
            ];

        case 'rondas':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            return [
                'view_file' => $modulesDir . '/gestion_torneos/rondas.php',
                'view_data' => obtenerDatosRondas($torneo_id),
            ];

        case 'cuadricula':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            return [
                'view_file' => $modulesDir . '/gestion_torneos/cuadricula.php',
                'view_data' => obtenerDatosCuadricula($torneo_id, $ronda),
            ];

        case 'hojas_anotacion':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            return [
                'view_file' => $modulesDir . '/gestion_torneos/hojas-anotacion.php',
                'view_data' => obtenerDatosHojasAnotacion($torneo_id, $ronda),
            ];

        case 'reasignar_mesa':
            if (!$torneo_id || !$ronda || !$mesa) {
                throw new Exception('Debe especificar torneo, ronda y mesa');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            $datos = obtenerDatosReasignarMesa($torneo_id, $ronda, $mesa);
            if (empty($datos['jugadores']) || count($datos['jugadores']) != 4) {
                throw new Exception('La mesa debe tener exactamente 4 jugadores para reasignar');
            }
            return [
                'view_file' => $modulesDir . '/gestion_torneos/reasignar-mesa.php',
                'view_data' => array_merge(
                    ['torneo' => $torneo, 'mesaActual' => $mesa, 'ronda' => $ronda],
                    $datos
                ),
            ];

        case 'agregar_mesa':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            $rondaVal = (int)$ronda;
            if ($rondaVal >= 2) {
                $_SESSION['error'] = 'Agregar mesa solo está disponible en la ronda 1.';
                header('Location: ' . buildRedirectUrl('panel', ['torneo_id' => $torneo_id]));
                exit;
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            return [
                'view_file' => $modulesDir . '/gestion_torneos/agregar-mesa.php',
                'view_data' => obtenerDatosAgregarMesa($torneo_id, $rondaVal),
            ];

        case 'verificar_mesa':
            if (!$torneo_id || !$ronda || !$mesa) {
                header('Content-Type: application/json');
                echo json_encode(['existe' => false]);
                exit;
            }
            header('Content-Type: application/json');
            echo json_encode(['existe' => verificarMesaExiste($torneo_id, $ronda, $mesa)]);
            exit;

        default:
            return null;
    }
}
