<?php

trait MesaAsignacionQueueTrait
{
    /**
     * Asigna mesas para rondas intermedias (3+) siguiendo el algoritmo:
     * 1. Jugador posición 1 escoge compañero (revisa 2, 3, 4... hasta encontrar uno con el que no haya jugado)
     * 2. Jugador posición 2 escoge compañero siguiendo el mismo procedimiento
     * 3. Completar mesa con 4 jugadores
     * 4. Repetir para siguientes mesas
     * 5. Si hay conflictos en últimas mesas, resolver intercambiando
     */
    private function asignarMesasRondaIntermedia($jugadores, $matrizCompañeros, $matrizEnfrentamientos)
    {
        $mesas = [];
        $asignados = [];
        $disponibles = array_values($jugadores); // Mantener orden de clasificación
        $indiceActual = 0;

        // Proceso de asignación mesa por mesa
        while ($indiceActual < count($disponibles)) {
            $mesaActual = [];
            
            // Obtener el primer jugador disponible (mejor clasificado no asignado)
            $jugador1 = null;
            while ($indiceActual < count($disponibles)) {
                if (!in_array($disponibles[$indiceActual]['id_usuario'], $asignados)) {
                    $jugador1 = $disponibles[$indiceActual];
                    break;
                }
                $indiceActual++;
            }
            
            if (!$jugador1) {
                break; // No hay más jugadores disponibles
            }
            
            $mesaActual[] = $jugador1;
            $asignados[] = $jugador1['id_usuario'];

            // Jugador 1 escoge su compañero (Pareja A - posición 2 de la mesa)
            $compañero1 = $this->buscarCompañero($jugador1, $disponibles, $asignados, $matrizCompañeros, $indiceActual + 1);
            
            if ($compañero1) {
                $mesaActual[] = $compañero1;
                $asignados[] = $compañero1['id_usuario'];
            } else {
                // Si no encuentra compañero, tomar el siguiente disponible
                $compañero1 = $this->obtenerSiguienteDisponible($disponibles, $asignados, $indiceActual + 1);
                if ($compañero1) {
                    $mesaActual[] = $compañero1;
                    $asignados[] = $compañero1['id_usuario'];
                }
            }

            // Obtener el siguiente jugador disponible para formar la Pareja B
            $jugador2 = $this->obtenerSiguienteDisponible($disponibles, $asignados, $indiceActual + 1);
            
            if (!$jugador2) {
                // Si no hay más jugadores, guardar lo que hay y terminar
                if (count($mesaActual) >= 2) {
                    $mesas[] = $mesaActual;
                }
                break;
            }

            $mesaActual[] = $jugador2;
            $asignados[] = $jugador2['id_usuario'];

            // Jugador 2 escoge su compañero (Pareja B - posición 4 de la mesa)
            $compañero2 = $this->buscarCompañero($jugador2, $disponibles, $asignados, $matrizCompañeros, $indiceActual + 1);
            
            if ($compañero2) {
                $mesaActual[] = $compañero2;
                $asignados[] = $compañero2['id_usuario'];
            } else {
                // Si no encuentra compañero, tomar el siguiente disponible
                $compañero2 = $this->obtenerSiguienteDisponible($disponibles, $asignados, $indiceActual + 1);
                if ($compañero2) {
                    $mesaActual[] = $compañero2;
                    $asignados[] = $compañero2['id_usuario'];
                }
            }

            // Si la mesa está completa (4 jugadores), guardarla
            if (count($mesaActual) >= self::JUGADORES_POR_MESA) {
                $mesas[] = $mesaActual;
            } elseif (count($mesaActual) >= 2) {
                // Si tiene al menos 2 jugadores pero no 4, guardarla igual
                $mesas[] = $mesaActual;
            }

            // Avanzar al siguiente jugador no asignado
            while ($indiceActual < count($disponibles) && 
                   in_array($disponibles[$indiceActual]['id_usuario'], $asignados)) {
                $indiceActual++;
            }
        }
        
        // Si quedan jugadores sin asignar, completar mesas incompletas
        // incluso si significa repetir compañeros (priorizar que todos jueguen)
        $jugadoresNoAsignados = array_filter($disponibles, function($j) use ($asignados) {
            return !in_array($j['id_usuario'], $asignados);
        });
        
        if (!empty($jugadoresNoAsignados)) {
            foreach ($jugadoresNoAsignados as $jugador) {
                $agregado = false;
                
                // Primero intentar agregar a una mesa incompleta sin repetir compañeros
                foreach ($mesas as &$mesa) {
                    if (count($mesa) >= self::JUGADORES_POR_MESA) {
                        continue;
                    }
                    
                    $puedeAgregar = true;
                    foreach ($mesa as $m) {
                        $idM = $m['id_usuario'];
                        $idJ = $jugador['id_usuario'];
                        if (isset($matrizCompañeros[$idM][$idJ]) || isset($matrizCompañeros[$idJ][$idM])) {
                            $puedeAgregar = false;
                            break;
                        }
                    }
                    
                    if ($puedeAgregar) {
                        $mesa[] = $jugador;
                        $asignados[] = $jugador['id_usuario'];
                        $agregado = true;
                        break;
                    }
                }
                unset($mesa);
                
                // Si no se pudo agregar sin repetir, agregar de todas formas a una mesa incompleta
                if (!$agregado) {
                    foreach ($mesas as &$mesa) {
                        if (count($mesa) < self::JUGADORES_POR_MESA) {
                            $mesa[] = $jugador;
                            $asignados[] = $jugador['id_usuario'];
                            $agregado = true;
                            break;
                        }
                    }
                    unset($mesa);
                }
                
                // Si aún no se pudo agregar, crear una nueva mesa
                if (!$agregado) {
                    $mesas[] = [$jugador];
                    $asignados[] = $jugador['id_usuario'];
                }
            }
        }
        
        // Limpiar duplicados antes de resolver conflictos
        $mesas = $this->limpiarMesasDuplicados($mesas);

        // Resolver conflictos en las últimas mesas
        return $this->resolverConflictosUltimasMesas($mesas, $jugadores, $matrizCompañeros);
    }

    /**
     * Busca un compañero para un jugador que no haya sido su compañero antes
     */
    private function buscarCompañero($jugador, $disponibles, $asignados, $matrizCompañeros, $inicioDesde = 0)
    {
        $idJugador = $jugador['id_usuario'];
        
        // Buscar desde la siguiente posición en clasificación
        for ($i = $inicioDesde; $i < count($disponibles); $i++) {
            $candidato = $disponibles[$i];
            
            // Saltar si ya está asignado
            if (in_array($candidato['id_usuario'], $asignados)) {
                continue;
            }
            
            // Verificar que no hayan sido compañeros antes
            if (!isset($matrizCompañeros[$idJugador][$candidato['id_usuario']]) &&
                !isset($matrizCompañeros[$candidato['id_usuario']][$idJugador])) {
                return $candidato;
            }
        }
        
        return null;
    }

    /**
     * Obtiene el siguiente jugador disponible no asignado
     */
    private function obtenerSiguienteDisponible($disponibles, $asignados, $inicioDesde = 0)
    {
        for ($i = $inicioDesde; $i < count($disponibles); $i++) {
            if (!in_array($disponibles[$i]['id_usuario'], $asignados)) {
                return $disponibles[$i];
            }
        }
        return null;
    }
    
    /**
     * Limpia mesas eliminando duplicados y redistribuyendo jugadores de mesas inválidas
     */
    private function limpiarMesasDuplicados($mesas)
    {
        $mesasLimpias = [];
        $idsAsignados = [];
        $jugadoresMesasInvalidas = [];
        
        foreach ($mesas as $mesa) {
            $mesaLimpia = [];
            $idsEnMesa = [];
            
            foreach ($mesa as $jugador) {
                $idUsuario = $jugador['id_usuario'];
                
                // Si el jugador ya está en otra mesa, no agregarlo a esta
                if (in_array($idUsuario, $idsAsignados)) {
                    continue;
                }
                
                // Si el jugador ya está en esta mesa, no duplicarlo
                if (in_array($idUsuario, $idsEnMesa)) {
                    continue;
                }
                
                $mesaLimpia[] = $jugador;
                $idsEnMesa[] = $idUsuario;
                $idsAsignados[] = $idUsuario;
            }
            
            // Solo agregar mesas con al menos 2 jugadores
            if (count($mesaLimpia) >= 2) {
                $mesasLimpias[] = $mesaLimpia;
            } else {
                // Guardar jugadores de mesas inválidas para redistribuir
                $jugadoresMesasInvalidas = array_merge($jugadoresMesasInvalidas, $mesaLimpia);
            }
        }
        
        // Redistribuir jugadores de mesas inválidas
        foreach ($jugadoresMesasInvalidas as $jugador) {
            $agregado = false;
            foreach ($mesasLimpias as &$mesa) {
                if (count($mesa) < self::JUGADORES_POR_MESA) {
                    // Verificar que no esté duplicado
                    $yaEnMesa = false;
                    foreach ($mesa as $m) {
                        if ($m['id_usuario'] == $jugador['id_usuario']) {
                            $yaEnMesa = true;
                            break;
                        }
                    }
                    if (!$yaEnMesa) {
                        $mesa[] = $jugador;
                        $agregado = true;
                        break;
                    }
                }
            }
            unset($mesa);
        }
        
        return $mesasLimpias;
    }
}

