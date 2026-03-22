<?php

trait MesaAsignacionConflictos1Trait
{
    /**
     * Completa mesas incompletas con jugadores sobrantes
     * Prioriza evitar repeticiones, pero si es necesario, permite repeticiones mínimas
     */
    private function completarMesasIncompletas($mesas, $jugadoresSobrantes, $matrizCompañeros)
    {
        $asignados = [];
        foreach ($mesas as $mesa) {
            foreach ($mesa as $jugador) {
                $asignados[] = $jugador['id_usuario'];
            }
        }
        
        foreach ($jugadoresSobrantes as $jugador) {
            if (in_array($jugador['id_usuario'], $asignados)) {
                continue;
            }
            
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
        }
        
        return $mesas;
    }

    /**
     * Resuelve conflictos en las últimas mesas
     * Si un jugador repite compañero en las últimas mesas:
     * 1. Intentar cambiarlo en la misma mesa
     * 2. Si ya jugó con todos en esa mesa, moverlo a la mesa anterior y hacer intercambios
     */
    private function resolverConflictosUltimasMesas($mesas, $jugadoresOrdenados, $matrizCompañeros)
    {
        if (count($mesas) < 2) {
            return $mesas;
        }

        // Procesar de atrás hacia adelante (últimas mesas primero)
        for ($i = count($mesas) - 1; $i >= 0; $i--) {
            $mesa = $mesas[$i];
            
            if (count($mesa) < 4) {
                continue; // Mesa incompleta, saltar
            }

            $idsMesa = array_column($mesa, 'id_usuario');
            $conflictos = $this->detectarConflictosMesa($mesa, $matrizCompañeros);
            
            if (empty($conflictos)) {
                continue; // No hay conflictos en esta mesa
            }

            // Intentar resolver conflictos en la misma mesa primero
            $mesaResuelta = $this->resolverConflictosEnMismaMesa($mesa, $matrizCompañeros);
            
            // Si aún hay conflictos y no es la primera mesa, mover a mesa anterior
            if ($i > 0 && $this->detectarConflictosMesa($mesaResuelta, $matrizCompañeros)) {
                $mesaResuelta = $this->moverAMesaAnterior($mesaResuelta, $mesas, $i, $matrizCompañeros);
            }
            
            $mesas[$i] = $mesaResuelta;
        }

        return $mesas;
    }

    /**
     * Resuelve conflictos en últimas mesas de Ronda 2
     * Formato mesa: [a,b,c,d] donde Pareja A = indices 0,2 (a,c), Pareja B = indices 1,3 (b,d)
     * Mismo principio que rondas 3 a N-1: en misma mesa o con mesa anterior
     */
    private function resolverConflictosUltimasMesasRonda2($mesas, $jugadoresOrdenados, $matrizCompañeros)
    {
        if (count($mesas) < 2) {
            return $mesas;
        }

        for ($i = count($mesas) - 1; $i >= 0; $i--) {
            $mesa = $mesas[$i];
            if (count($mesa) < 4) {
                continue;
            }

            if (empty($this->detectarConflictosMesaRonda2($mesa, $matrizCompañeros))) {
                continue;
            }

            $mesaResuelta = $this->resolverConflictosEnMismaMesaRonda2($mesa, $matrizCompañeros);
            if ($i > 0 && !empty($this->detectarConflictosMesaRonda2($mesaResuelta, $matrizCompañeros))) {
                list($mesaResuelta, $mesaAnteriorResuelta) = $this->moverAMesaAnteriorRonda2($mesaResuelta, $mesas[$i - 1], $matrizCompañeros);
                if ($mesaAnteriorResuelta !== null) {
                    $mesas[$i - 1] = $mesaAnteriorResuelta;
                }
            }
            $mesas[$i] = $mesaResuelta;
        }

        return $mesas;
    }

    /**
     * Detecta conflictos en mesa formato Ronda 2: orden [a,c,b,d] → Pareja 1 = 0,1; Pareja 2 = 2,3
     */
    private function detectarConflictosMesaRonda2($mesa, $matrizCompañeros)
    {
        $conflictos = [];
        if (count($mesa) < 4) return $conflictos;

        $ids = array_column($mesa, 'id_usuario');
        if (isset($matrizCompañeros[$ids[0]][$ids[1]]) || isset($matrizCompañeros[$ids[1]][$ids[0]])) {
            $conflictos[] = ['pareja' => 'A', 'idx1' => 0, 'idx2' => 1];
        }
        if (isset($matrizCompañeros[$ids[2]][$ids[3]]) || isset($matrizCompañeros[$ids[3]][$ids[2]])) {
            $conflictos[] = ['pareja' => 'B', 'idx1' => 2, 'idx2' => 3];
        }
        return $conflictos;
    }

    /**
     * Resuelve conflictos intercambiando dentro de la misma mesa (formato Ronda 2)
     */
    private function resolverConflictosEnMismaMesaRonda2($mesa, $matrizCompañeros)
    {
        $conflictos = $this->detectarConflictosMesaRonda2($mesa, $matrizCompañeros);
        if (empty($conflictos)) return $mesa;

        foreach ($conflictos as $c) {
            $idx1 = $c['idx1'];
            $idx2 = $c['idx2'];
            $otros = array_values(array_diff([0,1,2,3], [$idx1, $idx2]));
            foreach ([$idx1, $idx2] as $idxConflicto) {
                foreach ($otros as $o) {
                    $mesaPrueba = $mesa;
                    $temp = $mesaPrueba[$idxConflicto];
                    $mesaPrueba[$idxConflicto] = $mesaPrueba[$o];
                    $mesaPrueba[$o] = $temp;
                    if (empty($this->detectarConflictosMesaRonda2($mesaPrueba, $matrizCompañeros))) {
                        return $mesaPrueba;
                    }
                }
            }
        }
        return $mesa;
    }

    /**
     * Intercambia con mesa anterior para resolver conflictos (formato Ronda 2)
     * Retorna [mesaActualResuelta, mesaAnteriorResuelta] o [mesaActual, null] si no se resolvió
     */
    private function moverAMesaAnteriorRonda2($mesaActual, $mesaAnterior, $matrizCompañeros)
    {
        if (count($mesaActual) < 4 || count($mesaAnterior) < 4) {
            return [$mesaActual, null];
        }

        $conflictos = $this->detectarConflictosMesaRonda2($mesaActual, $matrizCompañeros);
        if (empty($conflictos)) return [$mesaActual, null];

        foreach ($conflictos as $c) {
            foreach ([$c['idx1'], $c['idx2']] as $idxConflicto) {
                for ($o = 0; $o < 4; $o++) {
                    $mesaActualPrueba = $mesaActual;
                    $mesaAnteriorPrueba = $mesaAnterior;
                    $temp = $mesaActualPrueba[$idxConflicto];
                    $mesaActualPrueba[$idxConflicto] = $mesaAnteriorPrueba[$o];
                    $mesaAnteriorPrueba[$o] = $temp;
                    if (empty($this->detectarConflictosMesaRonda2($mesaActualPrueba, $matrizCompañeros)) &&
                        empty($this->detectarConflictosMesaRonda2($mesaAnteriorPrueba, $matrizCompañeros))) {
                        return [$mesaActualPrueba, $mesaAnteriorPrueba];
                    }
                }
            }
        }
        return [$mesaActual, null];
    }

    /**
     * Detecta conflictos de compañeros repetidos en una mesa
     */
    private function detectarConflictosMesa($mesa, $matrizCompañeros)
    {
        $conflictos = [];
        
        if (count($mesa) < 4) {
            return $conflictos;
        }

        $idsMesa = array_column($mesa, 'id_usuario');
        
        // Verificar Pareja A (posiciones 0-1)
        $id1 = $idsMesa[0];
        $id2 = $idsMesa[1];
        if (isset($matrizCompañeros[$id1][$id2]) || isset($matrizCompañeros[$id2][$id1])) {
            $conflictos[] = ['pareja' => 'A', 'jugador1' => $id1, 'jugador2' => $id2];
        }

        // Verificar Pareja B (posiciones 2-3)
        $id3 = $idsMesa[2];
        $id4 = $idsMesa[3];
        if (isset($matrizCompañeros[$id3][$id4]) || isset($matrizCompañeros[$id4][$id3])) {
            $conflictos[] = ['pareja' => 'B', 'jugador1' => $id3, 'jugador2' => $id4];
        }

        return $conflictos;
    }

    /**
     * Resuelve conflictos intercambiando dentro de la misma mesa
     */
    private function resolverConflictosEnMismaMesa($mesa, $matrizCompañeros)
    {
        $conflictos = $this->detectarConflictosMesa($mesa, $matrizCompañeros);
        
        if (empty($conflictos)) {
            return $mesa;
        }

        $idsMesa = array_column($mesa, 'id_usuario');
        
        // Si hay conflicto en Pareja A, intercambiar con Pareja B
        foreach ($conflictos as $conflicto) {
            if ($conflicto['pareja'] === 'A') {
                // Intercambiar jugador de Pareja A (posición 1) con jugador de Pareja B (posición 2 o 3)
                // Probar intercambio con posición 2
                $mesaPrueba = $mesa;
                $temp = $mesaPrueba[1];
                $mesaPrueba[1] = $mesaPrueba[2];
                $mesaPrueba[2] = $temp;
                
                if (empty($this->detectarConflictosMesa($mesaPrueba, $matrizCompañeros))) {
                    return $mesaPrueba;
                }
                
                // Si no funcionó, probar con posición 3
                $mesaPrueba = $mesa;
                $temp = $mesaPrueba[1];
                $mesaPrueba[1] = $mesaPrueba[3];
                $mesaPrueba[3] = $temp;
                
                if (empty($this->detectarConflictosMesa($mesaPrueba, $matrizCompañeros))) {
                    return $mesaPrueba;
                }
            } elseif ($conflicto['pareja'] === 'B') {
                // Intercambiar jugador de Pareja B (posición 3) con jugador de Pareja A (posición 0 o 1)
                $mesaPrueba = $mesa;
                $temp = $mesaPrueba[3];
                $mesaPrueba[3] = $mesaPrueba[0];
                $mesaPrueba[0] = $temp;
                
                if (empty($this->detectarConflictosMesa($mesaPrueba, $matrizCompañeros))) {
                    return $mesaPrueba;
                }
            }
        }

        return $mesa; // No se pudo resolver en la misma mesa
    }

    /**
     * Mueve un jugador con conflicto a la mesa anterior y hace intercambios
     */
    private function moverAMesaAnterior($mesaActual, $todasMesas, $indiceMesaActual, $matrizCompañeros)
    {
        if ($indiceMesaActual <= 0) {
            return $mesaActual; // No hay mesa anterior
        }

        $mesaAnterior = $todasMesas[$indiceMesaActual - 1];
        
        if (count($mesaAnterior) < 4) {
            return $mesaActual; // Mesa anterior incompleta
        }

        $conflictos = $this->detectarConflictosMesa($mesaActual, $matrizCompañeros);
        
        if (empty($conflictos)) {
            return $mesaActual; // Ya no hay conflictos
        }

        // Para cada conflicto, intentar intercambiar con la mesa anterior
        foreach ($conflictos as $conflicto) {
            $jugadorConflictivo = $conflicto['jugador1'];
            $idxJugador = null;
            
            // Encontrar índice del jugador conflictivo en la mesa actual
            foreach ($mesaActual as $idx => $jugador) {
                if ($jugador['id_usuario'] == $jugadorConflictivo) {
                    $idxJugador = $idx;
                    break;
                }
            }
            
            if ($idxJugador === null) {
                continue;
            }

            // Intentar intercambiar con cada jugador de la mesa anterior
            foreach ($mesaAnterior as $idxAnterior => $jugadorAnterior) {
                $mesaActualPrueba = $mesaActual;
                $mesaAnteriorPrueba = $mesaAnterior;
                
                // Intercambiar
                $temp = $mesaActualPrueba[$idxJugador];
                $mesaActualPrueba[$idxJugador] = $mesaAnteriorPrueba[$idxAnterior];
                $mesaAnteriorPrueba[$idxAnterior] = $temp;
                
                // Verificar que ambas mesas queden sin conflictos
                if (empty($this->detectarConflictosMesa($mesaActualPrueba, $matrizCompañeros)) &&
                    empty($this->detectarConflictosMesa($mesaAnteriorPrueba, $matrizCompañeros))) {
                    // Actualizar ambas mesas
                    $todasMesas[$indiceMesaActual] = $mesaActualPrueba;
                    $todasMesas[$indiceMesaActual - 1] = $mesaAnteriorPrueba;
                    return $mesaActualPrueba;
                }
            }
        }

        return $mesaActual; // No se pudo resolver
    }
}

