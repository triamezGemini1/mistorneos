<?php
/**
 * Clasificación, resultados por ronda/mesa, resumen individual, podios y reportes de resultados.
 * Cargado desde modules/torneo_gestion.php.
 */

require_once __DIR__ . '/../../lib/ResultadosPartidaEfectividad.php';
/**
 * Obtiene datos de posiciones
 */
function obtenerDatosPosiciones($torneo_id) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $es_modalidad_equipos = (int)($torneo['modalidad'] ?? 0) === 3;
    $modalidadTorneoPos = (int)($torneo['modalidad'] ?? 0);
    $es_parejas = in_array($modalidadTorneoPos, [2, 4], true);
    
    // SIEMPRE obtener TODOS los jugadores individuales con sus estadísticas, incluyendo el nombre del equipo
    // Asegurar que las posiciones estén actualizadas
    if ($es_modalidad_equipos) {
        if (function_exists('recalcularPosicionesEquipos')) {
            recalcularPosicionesEquipos($torneo_id);
        }
    }
    
    if (function_exists('recalcularPosiciones')) {
        recalcularPosiciones($torneo_id);
    }
    
    // Obtener TODOS los jugadores individuales con estadísticas completas
    $sql = "SELECT 
                i.*,
                u.nombre as nombre_completo,
                u.username,
                u.sexo,
                c.nombre as club_nombre,
                c.nombre as nombre_club,
                e.nombre_equipo,
                e.codigo_equipo as codigo_equipo_from_equipos,
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
                            -- Caso 1: Oponente tiene forfait
                            pr_oponente.ff = 1 OR
                            -- Caso 2: Compañero tiene forfait (el jugador ganó por forfait de su compañero)
                            pr_compañero.ff = 1
                        )
                ) as ganadas_por_forfait,
                (SELECT COUNT(*) FROM partiresul pr_bye
                    WHERE pr_bye.id_usuario = i.id_usuario
                        AND pr_bye.id_torneo = i.torneo_id
                        AND pr_bye.registrado = 1
                        AND pr_bye.mesa = 0
                        AND pr_bye.resultado1 > pr_bye.resultado2
                ) as partidas_bye
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            LEFT JOIN clubes c ON i.id_club = c.id
            LEFT JOIN equipos e ON i.torneo_id = e.id_torneo AND i.codigo_equipo = e.codigo_equipo AND e.estatus = 0
            WHERE i.torneo_id = ?
            ORDER BY i.estatus = 4 ASC, i.posicion ASC, i.ganados DESC, i.efectividad DESC, i.puntos DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $torneo_id]);
    $posiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Asegurar que todos los jugadores tengan el nombre del equipo si tienen codigo_equipo
    foreach ($posiciones as &$pos) {
        if (empty($pos['nombre_equipo']) && !empty($pos['codigo_equipo'])) {
            // Si no tiene nombre_equipo pero tiene codigo_equipo, construir uno
            $pos['nombre_equipo'] = 'Equipo ' . $pos['codigo_equipo'];
        }
    }
    unset($pos);

    // Parejas (2 y 4): una fila por codigo_equipo; nombres en dos líneas en la vista.
    if ($es_parejas) {
        $stmtParejas = $pdo->prepare("
            SELECT i.codigo_equipo, u.nombre AS nombre_completo
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            WHERE i.torneo_id = ?
              AND i.codigo_equipo IS NOT NULL
              AND i.codigo_equipo != ''
              AND i.codigo_equipo != '000-000'
              AND i.estatus != 'retirado'
            ORDER BY i.codigo_equipo ASC, u.nombre ASC
        ");
        $stmtParejas->execute([$torneo_id]);
        $nombresPorCodigo = [];
        foreach ($stmtParejas->fetchAll(PDO::FETCH_ASSOC) as $filaPareja) {
            $codigo = trim((string)($filaPareja['codigo_equipo'] ?? ''));
            $nombre = trim((string)($filaPareja['nombre_completo'] ?? ''));
            if ($codigo === '' || $nombre === '') {
                continue;
            }
            if (!isset($nombresPorCodigo[$codigo])) {
                $nombresPorCodigo[$codigo] = [];
            }
            $nombresPorCodigo[$codigo][] = $nombre;
        }
        $dedup = [];
        $vistos = [];
        foreach ($posiciones as $pos) {
            $codigo = trim((string)($pos['codigo_equipo'] ?? ''));
            $clave = ($codigo !== '' && $codigo !== '000-000')
                ? 'c:' . $codigo
                : 'u:' . (int)($pos['id_usuario'] ?? 0);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $nombres = [];
            if ($codigo !== '' && isset($nombresPorCodigo[$codigo])) {
                $nombres = array_values(array_unique($nombresPorCodigo[$codigo]));
            }
            $pos['pareja_nombre_1'] = $nombres[0] ?? '';
            $pos['pareja_nombre_2'] = $nombres[1] ?? '';
            $dedup[] = $pos;
        }
        $posiciones = $dedup;
    }
    
    return [
        'torneo' => $torneo,
        'posiciones' => $posiciones,
        'es_modalidad_equipos' => $es_modalidad_equipos,
        'es_parejas' => $es_parejas,
    ];
}
/**
 * Obtiene datos para registrar resultados (versión v2).
 * Si el usuario es operador, solo ve y puede operar las mesas asignadas (ámbito limitado).
 */
function obtenerDatosRegistroResultados($torneo_id, $ronda, $mesa, $user_id = 0, $user_role = '') {
    require_once __DIR__ . '/../../lib/RegistrarResultadosLecturaService.php';

    $pdo = DB::pdo();
    $mesas_operador = obtenerMesasAsignadasOperador($torneo_id, $ronda, $user_id, $user_role);

    $payload = RegistrarResultadosLecturaService::construirDatos(
        $pdo,
        (int)$torneo_id,
        (int)$ronda,
        (int)$mesa,
        $mesas_operador
    );

    $flash = $payload['flash'];
    unset($payload['flash']);
    if ($flash['warning'] !== null) {
        $_SESSION['warning'] = $flash['warning'];
    }
    if ($flash['error'] !== null) {
        $_SESSION['error'] = $flash['error'];
    }

    $payload['vieneDeResumen'] = isset($_GET['from']) && $_GET['from'] === 'resumen';
    $payload['inscritoId'] = isset($_GET['inscrito_id']) ? (int)$_GET['inscrito_id'] : null;
    $payload['es_operador_ambito'] = $mesas_operador !== null;
    $payload['mesas_ambito'] = $mesas_operador !== null ? $mesas_operador : [];

    return $payload;
}
/**
 * Obtiene datos para resumen individual
 */
function obtenerDatosResumenIndividual($torneo_id, $inscrito_id) {
    $pdo = DB::pdo();
    
    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener inscrito
    $sql = "SELECT i.*, u.nombre as nombre_completo, c.nombre as nombre_club
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            LEFT JOIN clubes c ON i.id_club = c.id
            WHERE i.torneo_id = ? AND i.id_usuario = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $inscrito_id]);
    $inscrito = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inscrito) {
        throw new Exception('Jugador no encontrado en este torneo');
    }
    
    // Obtener la última ronda asignada
    $stmtUltimaRonda = $pdo->prepare("SELECT MAX(partida) as ultima_ronda FROM partiresul WHERE id_torneo = ? AND mesa > 0");
    $stmtUltimaRonda->execute([$torneo_id]);
    $ultimaRonda = (int)$stmtUltimaRonda->fetchColumn() ?? 0;
    
    // Obtener resumen de participación con información de pareja y contrarios
    // Incluir solo registrados, pero también incluir la última ronda aunque no esté registrada
    $sql = "SELECT 
                pr.partida,
                pr.mesa,
                pr.secuencia,
                pr.resultado1,
                pr.resultado2,
                pr.efectividad,
                pr.sancion,
                pr.ff,
                pr.tarjeta,
                pr.registrado,
                CASE WHEN pr.resultado1 > pr.resultado2 THEN 1 ELSE 0 END as gano
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.id_usuario = ? AND pr.mesa > 0 
            AND (pr.registrado = 1 OR (pr.registrado = 0 AND pr.partida = ?))
            ORDER BY pr.partida ASC, pr.mesa ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$torneo_id, $inscrito_id, $ultimaRonda]);
    $partidasJugador = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada partida, obtener información de pareja y contrarios
    $resumenParticipacion = [];
    
    foreach ($partidasJugador as $partidaJugador) {
        $partida = (int)$partidaJugador['partida'];
        $mesa = (int)$partidaJugador['mesa'];
        $secuencia = (int)$partidaJugador['secuencia'];
        
        // Obtener todos los jugadores de esta mesa
        $sqlMesa = "SELECT 
                        pr.secuencia,
                        pr.id_usuario,
                        u.nombre as nombre_completo,
                        COALESCE(c.nombre, 'Sin Club') as club_nombre
                    FROM partiresul pr
                    INNER JOIN usuarios u ON pr.id_usuario = u.id
                    LEFT JOIN inscritos i ON i.id_usuario = pr.id_usuario AND i.torneo_id = pr.id_torneo
                    LEFT JOIN clubes c ON i.id_club = c.id
                    WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
                    ORDER BY pr.secuencia ASC";
        
        $stmtMesa = $pdo->prepare($sqlMesa);
        $stmtMesa->execute([$torneo_id, $partida, $mesa]);
        $jugadoresMesa = $stmtMesa->fetchAll(PDO::FETCH_ASSOC);
        
        // Identificar compañero y contrarios
        // Pareja A: secuencias 1-2, Pareja B: secuencias 3-4
        $compañero = null;
        $contrarios = [];
        
        $esParejaA = ($secuencia == 1 || $secuencia == 2);
        
        foreach ($jugadoresMesa as $jugador) {
            $seq = (int)$jugador['secuencia'];
            
            if ($seq == $secuencia) {
                // Es el jugador actual, saltar
                continue;
            }
            
            if ($esParejaA) {
                // Jugador está en Pareja A (secuencias 1-2)
                if ($seq == 1 || $seq == 2) {
                    // Es compañero
                    $compañero = [
                        'nombre' => $jugador['nombre_completo'],
                        'club' => $jugador['club_nombre'] ?? 'Sin Club',
                        'id_usuario' => (int)$jugador['id_usuario']
                    ];
                } elseif ($seq == 3 || $seq == 4) {
                    // Es contrario (Pareja B)
                    $contrarios[] = [
                        'nombre' => $jugador['nombre_completo'],
                        'club' => $jugador['club_nombre'] ?? 'Sin Club',
                        'id_usuario' => (int)$jugador['id_usuario']
                    ];
                }
            } else {
                // Jugador está en Pareja B (secuencias 3-4)
                if ($seq == 3 || $seq == 4) {
                    // Es compañero
                    $compañero = [
                        'nombre' => $jugador['nombre_completo'],
                        'club' => $jugador['club_nombre'] ?? 'Sin Club',
                        'id_usuario' => (int)$jugador['id_usuario']
                    ];
                } elseif ($seq == 1 || $seq == 2) {
                    // Es contrario (Pareja A)
                    $contrarios[] = [
                        'nombre' => $jugador['nombre_completo'],
                        'club' => $jugador['club_nombre'] ?? 'Sin Club',
                        'id_usuario' => (int)$jugador['id_usuario']
                    ];
                }
            }
        }
        
        $estaRegistrado = ((int)($partidaJugador['registrado'] ?? 0) == 1);
        
        // Solo calcular resultados si está registrado
        $hayForfait = false;
        $hayTarjetaGrave = false;
        $sancion = 0;
        $resultado1 = 0;
        $resultado2 = 0;
        $gano = false;
        
        if ($estaRegistrado) {
            // Determinar si ganó - evaluar individualmente considerando sanciones
            $hayForfait = ((int)$partidaJugador['ff'] == 1);
            $hayTarjetaGrave = ((int)$partidaJugador['tarjeta'] >= 3);
            $sancion = (int)($partidaJugador['sancion'] ?? 0);
            $resultado1 = (int)($partidaJugador['resultado1'] ?? 0);
            $resultado2 = (int)($partidaJugador['resultado2'] ?? 0);
            
            if ($hayForfait) {
                $gano = false; // El jugador con forfait pierde
            } elseif ($hayTarjetaGrave) {
                $gano = false; // El jugador con tarjeta grave pierde
            } elseif ($sancion > 0) {
                $puntosTorneo = (int)($torneo['puntos'] ?? 100);
                // Restar sanción del resultado1; ganado si (resultado1-sancion) > resultado2, perdido si <= resultado2
                $evaluacionSancion = ResultadosPartidaEfectividad::evaluarSancionIndividual($resultado1, $resultado2, $sancion, $puntosTorneo);
                $gano = $evaluacionSancion['gano'];
            } else {
                $gano = ($resultado1 > $resultado2);
            }
        }
        
        $resumenParticipacion[] = [
            'partida' => $partida,
            'mesa' => $mesa,
            'compañero' => $compañero,
            'contrarios' => $contrarios,
            'resultado1' => $estaRegistrado ? (int)($partidaJugador['resultado1'] ?? 0) : null,
            'resultado2' => $estaRegistrado ? (int)($partidaJugador['resultado2'] ?? 0) : null,
            'efectividad' => $estaRegistrado ? (int)($partidaJugador['efectividad'] ?? 0) : null,
            'sancion' => $estaRegistrado ? (int)($partidaJugador['sancion'] ?? 0) : null,
            'gano' => $estaRegistrado ? $gano : null,
            'ff' => $estaRegistrado ? $hayForfait : false,
            'tarjeta' => $estaRegistrado ? (int)($partidaJugador['tarjeta'] ?? 0) : null,
            'registrado' => $estaRegistrado
        ];
    }
    
    // Calcular totales
    $totales = [
        'resultado1' => 0,
        'resultado2' => 0,
        'efectividad' => 0,
        'sancion' => 0,
        'ganados' => 0,
        'perdidos' => 0
    ];
    
    foreach ($resumenParticipacion as $partida) {
        // Solo sumar a totales si está registrado
        if (!empty($partida['registrado']) && $partida['registrado']) {
            $totales['resultado1'] += (int)($partida['resultado1'] ?? 0);
            $totales['resultado2'] += (int)($partida['resultado2'] ?? 0);
            $totales['efectividad'] += (int)($partida['efectividad'] ?? 0);
            $totales['sancion'] += (int)($partida['sancion'] ?? 0);
            
            if ($partida['gano']) {
                $totales['ganados']++;
            } else {
                $totales['perdidos']++;
            }
        }
    }
    
    // Detectar desde dónde viene para construir el enlace de retorno
    $from = $_GET['from'] ?? 'panel';
    $vieneDePosiciones = ($from === 'posiciones');
    
    // Construir URL de retorno según el origen
    $use_standalone = (basename($_SERVER['PHP_SELF'] ?? '') === 'admin_torneo.php');
    $base_url_retorno = $use_standalone ? 'admin_torneo.php' : 'index.php?page=torneo_gestion';
    $action_param = $use_standalone ? '?' : '&';
    
    $urlRetorno = $base_url_retorno . $action_param . 'action=panel&torneo_id=' . $torneo_id;
    
    if ($from === 'posiciones') {
        $urlRetorno = $base_url_retorno . $action_param . 'action=posiciones&torneo_id=' . $torneo_id;
    } elseif ($from === 'resultados_equipos_detallado') {
        $urlRetorno = $base_url_retorno . $action_param . 'action=resultados_equipos_detallado&torneo_id=' . $torneo_id;
    } elseif ($from === 'resultados_equipos_resumido') {
        $urlRetorno = $base_url_retorno . $action_param . 'action=resultados_equipos_resumido&torneo_id=' . $torneo_id;
    } elseif ($from === 'resultados_general') {
        $urlRetorno = $base_url_retorno . $action_param . 'action=resultados_general&torneo_id=' . $torneo_id;
    } elseif ($from === 'resultados_por_club') {
        $urlRetorno = $base_url_retorno . $action_param . 'action=resultados_por_club&torneo_id=' . $torneo_id;
    }
    
    return [
        'torneo' => $torneo,
        'inscrito' => $inscrito,
        'resumenParticipacion' => $resumenParticipacion,
        'totales' => $totales,
        'vieneDePosiciones' => $vieneDePosiciones,
        'from' => $from,
        'urlRetorno' => $urlRetorno
    ];
}
/**
 * Guarda resultados de una mesa
 */
function guardarResultados($user_id, $is_admin_general) {
    $torneo_id = (int)($_POST['torneo_id'] ?? 0);
    $ronda = (int)($_POST['ronda'] ?? 0);
    $mesa = (int)($_POST['mesa'] ?? 0);
    
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
        
        if ($mesa <= 0) {
            $_SESSION['error'] = 'No hay una mesa válida asignada. Seleccione una mesa de la lista antes de guardar.';
            header('Location: ' . buildRedirectUrl('registrar_resultados', ['torneo_id' => $torneo_id, 'ronda' => $ronda]));
            exit;
        }
        
        // Operador: solo puede guardar resultados en mesas de su ámbito
        $current = Auth::user();
        $user_role = $current['role'] ?? '';
        if ($user_role === 'operador') {
            $mesas_operador = obtenerMesasAsignadasOperador($torneo_id, $ronda, $user_id, $user_role);
            if ($mesas_operador !== null && !in_array($mesa, $mesas_operador, true)) {
                throw new Exception("No tiene permiso para registrar resultados en la mesa #{$mesa}. Solo puede operar las mesas asignadas a su ámbito.");
            }
        }
        
        $pdo = DB::pdo();
        $jugadores = $_POST['jugadores'] ?? [];
        $observaciones = trim($_POST['observaciones'] ?? '');
        
        require_once __DIR__ . '/../../lib/TorneoMesaResultadosService.php';
        $torneoCfg = TorneoMesaResultadosService::obtenerModalidadYPuntos($pdo, $torneo_id);
        $modalidadTorneo = $torneoCfg['modalidad'];
        $puntosTorneo = $torneoCfg['puntos'];

        TorneoMesaResultadosService::validarCuposMesa($pdo, $jugadores, $torneo_id, $ronda, $mesa);
        TorneoMesaResultadosService::validarMesaExisteEnRonda($pdo, $torneo_id, $ronda, $mesa);

        $pdo->beginTransaction();

        // Flujo independiente exclusivo para torneos de parejas (unidad de cálculo: codigo_equipo).
        if (in_array($modalidadTorneo, [2, 4], true)) {
            require_once __DIR__ . '/../../lib/ParejasResultadosService.php';
            require_once __DIR__ . '/../../lib/InscritosHelper.php';

            $resultadoParejas = ParejasResultadosService::guardarResultadosMesa(
                $pdo,
                $torneo_id,
                $ronda,
                $mesa,
                $jugadores,
                $user_id,
                $puntosTorneo
            );

            if (!empty($observaciones)) {
                $stmtObs = $pdo->prepare("UPDATE partiresul SET observaciones = ? WHERE id_torneo = ? AND partida = ? AND mesa = ?");
                $stmtObs->execute([$observaciones, $torneo_id, $ronda, $mesa]);
            }

            $idsTarjetaNegra = array_values(array_unique(array_map('intval', (array)($resultadoParejas['ids_tarjeta_negra'] ?? []))));
            if (!empty($idsTarjetaNegra)) {
                $placeholders = implode(',', array_fill(0, count($idsTarjetaNegra), '?'));
                $stmtRetiro = $pdo->prepare("UPDATE inscritos SET estatus = ? WHERE torneo_id = ? AND id_usuario IN ($placeholders)");
                $stmtRetiro->execute(array_merge([InscritosHelper::ESTATUS_RETIRADO_NUM, $torneo_id], $idsTarjetaNegra));
                $n = count($idsTarjetaNegra);
                $_SESSION['info'] = $n === 1
                    ? 'Jugador marcado como retirado del torneo por tarjeta negra. No participará en rondas futuras (asumido como BYE).'
                    : "{$n} jugadores marcados como retirados del torneo por tarjeta negra. No participarán en rondas futuras (asumidos como BYE).";
            } elseif (!empty($resultadoParejas['es_empate_mano_nula'])) {
                $_SESSION['info'] = 'Empate en tranque registrado como Mano Nula: 0 puntos para ambas parejas.';
            }

            $pdo->commit();

            try {
                actualizarEstadisticasInscritos($torneo_id);
            } catch (Exception $e) {
                error_log("Error al actualizar estadísticas después de guardar resultados (parejas): " . $e->getMessage());
            }

            $_SESSION['limpiar_formulario'] = true;
            $_SESSION['resultados_guardados'] = true;
            $redirectUrl = buildRedirectUrl('registrar_resultados', ['torneo_id' => $torneo_id, 'ronda' => $ronda, 'mesa' => $mesa]) . '#formResultados';
            header('Location: ' . $redirectUrl);
            exit;
        }
        
        require_once __DIR__ . '/../../lib/IndividualEquiposResultadosService.php';
        require_once __DIR__ . '/../../lib/InscritosHelper.php';

        $meta = IndividualEquiposResultadosService::guardarResultadosMesa(
            $pdo,
            $torneo_id,
            $ronda,
            $mesa,
            array_values($jugadores),
            $user_id,
            $puntosTorneo
        );

        if (!empty($observaciones)) {
            $stmt = $pdo->prepare("UPDATE partiresul SET observaciones = ? WHERE id_torneo = ? AND partida = ? AND mesa = ?");
            $stmt->execute([$observaciones, $torneo_id, $ronda, $mesa]);
        }

        $idsTarjetaNegra = $meta['ids_tarjeta_negra'];
        if (!empty($idsTarjetaNegra)) {
            $placeholders = implode(',', array_fill(0, count($idsTarjetaNegra), '?'));
            $stmt = $pdo->prepare("UPDATE inscritos SET estatus = ? WHERE torneo_id = ? AND id_usuario IN ($placeholders)");
            $stmt->execute(array_merge([InscritosHelper::ESTATUS_RETIRADO_NUM, $torneo_id], $idsTarjetaNegra));
            $n = count($idsTarjetaNegra);
            $_SESSION['info'] = $n === 1
                ? 'Jugador marcado como retirado del torneo por tarjeta negra. No participará en rondas futuras (asumido como BYE).'
                : "{$n} jugadores marcados como retirados del torneo por tarjeta negra. No participarán en rondas futuras (asumidos como BYE).";
        }
        if (!empty($meta['es_empate_mano_nula'])) {
            $_SESSION['info'] = 'Empate en tranque registrado como Mano Nula: 0 puntos para ambas parejas.';
        }

        $pdo->commit();
        
        // Actualizar estadísticas de los jugadores involucrados y recalcular posiciones
        try {
            actualizarEstadisticasInscritos($torneo_id);
        } catch (Exception $e) {
            error_log("Error al actualizar estadísticas después de guardar resultados: " . $e->getMessage());
        }
        
        // No regenerar token CSRF aquí: causa "Token inválido" en doble clic o pestaña antigua.
        // La redirección carga una página nueva con el mismo token vigente.
        
        // Sin mensaje de éxito: si no hay error, no se muestra nada
        $_SESSION['limpiar_formulario'] = true;
        $_SESSION['resultados_guardados'] = true;
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Error al guardar resultados: ' . $e->getMessage();
    }
    
        $redirectUrl = buildRedirectUrl('registrar_resultados', ['torneo_id' => $torneo_id, 'ronda' => $ronda, 'mesa' => $mesa]) . '#formResultados';
        header('Location: ' . $redirectUrl);
        exit;
}

/**
 * POST: guardado de resultados de mesa (puntos/efectividad vía servicios de dominio).
 */
function torneo_gestion_resultados_posiciones_handle_post(string $post_action, int $user_id, bool $is_admin_general): void
{
    switch ($post_action) {
        case 'guardar_resultados':
        case 'actualizar_resultado_ajax':
            guardarResultados($user_id, $is_admin_general);
            break;

        default:
            $_SESSION['error'] = 'Acción POST de resultados/clasificación no reconocida.';
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
 * GET: posiciones, registro/ver resultados por ronda, resumen, podios, reportes.
 *
 * @return array{view_file: string, view_data: array, use_reportes_print_standalone?: bool}|null
 */
function torneo_gestion_resultados_posiciones_try_route_get(
    string $action,
    ?int $torneo_id,
    ?int $ronda,
    ?int $mesa,
    ?int $inscrito_id,
    int $user_id,
    string $user_role,
    bool $is_admin_general
): ?array {
    $effective = $action;
    if ($effective === 'cuadro_honor') {
        $effective = 'podios';
    }
    if ($effective === 'resultados_ronda' || $effective === 'ver_resultados') {
        $effective = 'registrar_resultados';
    }

    $modulesDir = dirname(__DIR__);

    switch ($effective) {
        case 'posiciones':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            return [
                'view_file' => $modulesDir . '/gestion_torneos/posiciones.php',
                'view_data' => obtenerDatosPosiciones($torneo_id),
            ];

        case 'registrar_resultados':
        case 'registrar_resultados_v2':
            if (!$torneo_id || !$ronda) {
                throw new Exception('Debe especificar torneo y ronda');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            return [
                'view_file' => $modulesDir . '/gestion_torneos/registrar-resultados-v2.php',
                'view_data' => obtenerDatosRegistroResultados($torneo_id, $ronda, $mesa ?? 0, $user_id, $user_role),
            ];

        case 'resumen_individual':
            if (!$torneo_id || !$inscrito_id) {
                throw new Exception('Debe especificar torneo e inscrito');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            return [
                'view_file' => $modulesDir . '/gestion_torneos/resumen-individual.php',
                'view_data' => obtenerDatosResumenIndividual($torneo_id, $inscrito_id),
            ];

        case 'podio':
        case 'podios':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            $es_modalidad_equipos = isset($torneo['modalidad']) && (int)$torneo['modalidad'] === 3;
            $viewFile = $es_modalidad_equipos
                ? $modulesDir . '/tournament_admin/podios_equipos.php'
                : $modulesDir . '/tournament_admin/podios.php';
            return [
                'view_file' => $viewFile,
                'view_data' => ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()],
            ];

        case 'podios_equipos':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            return [
                'view_file' => $modulesDir . '/tournament_admin/podios_equipos.php',
                'view_data' => ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()],
            ];

        case 'resultados_por_club':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            return [
                'view_file' => $modulesDir . '/tournament_admin/resultados_por_club.php',
                'view_data' => ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()],
            ];

        case 'resultados_equipos_resumido':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            if ((int)($torneo['modalidad'] ?? 0) !== 3) {
                throw new Exception('Este reporte solo está disponible para torneos por equipos');
            }
            return [
                'view_file' => $modulesDir . '/tournament_admin/resultados_equipos_resumido.php',
                'view_data' => ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()],
            ];

        case 'resultados_equipos_detallado':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            if ((int)($torneo['modalidad'] ?? 0) !== 3) {
                throw new Exception('Este reporte solo está disponible para torneos por equipos');
            }
            return [
                'view_file' => $modulesDir . '/tournament_admin/resultados_equipos_detallado.php',
                'view_data' => ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()],
            ];

        case 'resultados_general':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            $modalidad_resultados = (int)($torneo['modalidad'] ?? 0);
            if (!in_array($modalidad_resultados, [2, 3, 4], true)) {
                throw new Exception('Este reporte solo está disponible para torneos de parejas o equipos');
            }
            return [
                'view_file' => $modulesDir . '/tournament_admin/resultados_general.php',
                'view_data' => ['torneo' => $torneo, 'torneo_id' => $torneo_id, 'pdo' => DB::pdo()],
            ];

        case 'resultados_reportes':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            return [
                'view_file' => $modulesDir . '/tournament_admin/resultados_reportes.php',
                'view_data' => ['torneo' => $torneo, 'torneo_id' => $torneo_id],
            ];

        case 'resultados_reportes_print':
            if (!$torneo_id) {
                throw new Exception('Debe especificar un torneo');
            }
            verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
            $torneo = obtenerTorneo($torneo_id, $user_id, $is_admin_general);
            if (!$torneo) {
                throw new Exception('Torneo no encontrado o sin permisos');
            }
            return [
                'view_file' => $modulesDir . '/tournament_admin/resultados_reportes_print.php',
                'view_data' => ['torneo' => $torneo, 'torneo_id' => $torneo_id],
                'use_reportes_print_standalone' => true,
            ];

        default:
            return null;
    }
}
