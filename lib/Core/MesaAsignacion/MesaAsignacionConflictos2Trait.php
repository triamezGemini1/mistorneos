<?php

trait MesaAsignacionConflictos2Trait
{
    private function resolverConflictosMesa($mesaActual, $disponibles, $asignados, $matrizCompañeros, $jugadoresOrdenados)
    {
        $necesarios = self::JUGADORES_POR_MESA - count($mesaActual);
        $idsEnMesa = array_column($mesaActual, 'id_usuario');
        $candidatos = [];

        // Buscar jugadores disponibles que no sean compañeros de los ya en la mesa
        foreach ($disponibles as $candidato) {
            if (in_array($candidato['id_usuario'], $asignados)) {
                continue;
            }

            $puedeAgregar = true;
            foreach ($idsEnMesa as $idMesa) {
                if (isset($matrizCompañeros[$candidato['id_usuario']][$idMesa]) || 
                    isset($matrizCompañeros[$idMesa][$candidato['id_usuario']])) {
                    $puedeAgregar = false;
                    break;
                }
            }

            if ($puedeAgregar) {
                $candidatos[] = $candidato;
            }
        }

        // Agregar candidatos válidos
        for ($i = 0; $i < min($necesarios, count($candidatos)); $i++) {
            $mesaActual[] = $candidatos[$i];
        }

        return $mesaActual;
    }

    /**
     * Optimiza las últimas mesas intercambiando jugadores para evitar compañeros repetidos
     */
    private function optimizarUltimasMesas($mesas, $jugadoresOrdenados, $matrizCompañeros)
    {
        if (count($mesas) < 2) {
            return $mesas;
        }

        // Procesar de atrás hacia adelante (últimas mesas)
        for ($i = count($mesas) - 1; $i >= max(0, count($mesas) - 3); $i--) {
            $mesa = $mesas[$i];
            
            if (count($mesa) < self::JUGADORES_POR_MESA) {
                continue;
            }

            // Verificar si hay compañeros repetidos en la mesa
            $idsMesa = array_column($mesa, 'id_usuario');
            $tieneConflicto = false;
            
            for ($j = 0; $j < count($idsMesa); $j++) {
                for ($k = $j + 1; $k < count($idsMesa); $k++) {
                    $id1 = $idsMesa[$j];
                    $id2 = $idsMesa[$k];
                    
                    // Verificar si fueron pareja (secuencia 1-2 o 3-4)
                    $fueronPareja = false;
                    if (($j < 2 && $k < 2) || ($j >= 2 && $k >= 2)) {
                        $fueronPareja = isset($matrizCompañeros[$id1][$id2]) || 
                                       isset($matrizCompañeros[$id2][$id1]);
                    }
                    
                    if ($fueronPareja) {
                        $tieneConflicto = true;
                        break 2;
                    }
                }
            }

            if ($tieneConflicto) {
                // Intentar intercambiar dentro de la misma mesa primero
                $mesa = $this->intercambiarEnMismaMesa($mesa, $matrizCompañeros);
                
                // Si aún hay conflicto, intercambiar con otras mesas
                if ($this->tieneConflictoParejas($mesa, $matrizCompañeros)) {
                    $mesa = $this->intercambiarConOtrasMesas($mesa, $mesas, $i, $jugadoresOrdenados, $matrizCompañeros);
                }
                
                $mesas[$i] = $mesa;
            }
        }

        return $mesas;
    }

    /**
     * Intercambia jugadores dentro de la misma mesa para evitar compañeros repetidos
     */
    private function intercambiarEnMismaMesa($mesa, $matrizCompañeros)
    {
        $idsMesa = array_column($mesa, 'id_usuario');
        
        // Intercambiar entre pareja A (posiciones 0-1) y pareja B (posiciones 2-3)
        // Si hay conflicto en pareja A, intercambiar con pareja B
        for ($p = 0; $p < 2; $p++) {
            $id1 = $idsMesa[$p];
            $id2 = $idsMesa[1 - $p];
            
            if (isset($matrizCompañeros[$id1][$id2]) || isset($matrizCompañeros[$id2][$id1])) {
                // Intercambiar con pareja B
                $temp = $mesa[$p];
                $mesa[$p] = $mesa[2];
                $mesa[2] = $temp;
                
                // Revalidar
                $idsMesa = array_column($mesa, 'id_usuario');
                if (!isset($matrizCompañeros[$idsMesa[0]][$idsMesa[1]]) && 
                    !isset($matrizCompañeros[$idsMesa[1]][$idsMesa[0]]) &&
                    !isset($matrizCompañeros[$idsMesa[2]][$idsMesa[3]]) && 
                    !isset($matrizCompañeros[$idsMesa[3]][$idsMesa[2]])) {
                    return $mesa;
                }
            }
        }

        // Si hay conflicto en pareja B, intercambiar con pareja A
        for ($p = 2; $p < 4; $p++) {
            $id1 = $idsMesa[$p];
            $id2 = $idsMesa[5 - $p];
            
            if (isset($matrizCompañeros[$id1][$id2]) || isset($matrizCompañeros[$id2][$id1])) {
                $temp = $mesa[$p];
                $mesa[$p] = $mesa[0];
                $mesa[0] = $temp;
            }
        }

        return $mesa;
    }

    /**
     * Intercambia jugadores con otras mesas según clasificación
     */
    private function intercambiarConOtrasMesas($mesa, $todasMesas, $indiceMesaActual, $jugadoresOrdenados, $matrizCompañeros)
    {
        $idsMesa = array_column($mesa, 'id_usuario');
        $posicionesJugadores = [];
        
        // Crear mapa de posiciones de clasificación
        foreach ($jugadoresOrdenados as $idx => $j) {
            $posicionesJugadores[$j['id_usuario']] = $idx;
        }

        // Buscar intercambio en mesas anteriores (mejor clasificación) o posteriores (peor clasificación)
        for ($i = 0; $i < count($todasMesas); $i++) {
            if ($i === $indiceMesaActual || count($todasMesas[$i]) < self::JUGADORES_POR_MESA) {
                continue;
            }

            $otraMesa = $todasMesas[$i];
            $idsOtraMesa = array_column($otraMesa, 'id_usuario');

            // Intentar intercambiar cada jugador de la mesa actual
            foreach ($mesa as $idxJugador => $jugador) {
                $idJugador = $jugador['id_usuario'];
                $posicionJugador = $posicionesJugadores[$idJugador] ?? 9999;

                // Buscar jugador en otra mesa para intercambiar
                foreach ($otraMesa as $idxOtro => $otroJugador) {
                    $idOtro = $otroJugador['id_usuario'];
                    $posicionOtro = $posicionesJugadores[$idOtro] ?? 9999;

                    // Intercambiar solo si mejora la situación y respeta la clasificación
                    if (($i < $indiceMesaActual && $posicionOtro < $posicionJugador) ||
                        ($i > $indiceMesaActual && $posicionOtro > $posicionJugador)) {
                        
                        // Verificar si el intercambio resuelve conflictos
                        $mesaPrueba = $mesa;
                        $mesaPrueba[$idxJugador] = $otroJugador;
                        
                        $otraMesaPrueba = $otraMesa;
                        $otraMesaPrueba[$idxOtro] = $jugador;

                        if (!$this->tieneConflictoParejas($mesaPrueba, $matrizCompañeros) &&
                            !$this->tieneConflictoParejas($otraMesaPrueba, $matrizCompañeros)) {
                            $mesa = $mesaPrueba;
                            $todasMesas[$i] = $otraMesaPrueba;
                            return $mesa;
                        }
                    }
                }
            }
        }

        return $mesa;
    }

    /**
     * Verifica si una mesa tiene conflictos de parejas repetidas
     */
    private function tieneConflictoParejas($mesa, $matrizCompañeros)
    {
        if (count($mesa) < 2) {
            return false;
        }

        $idsMesa = array_column($mesa, 'id_usuario');
        
        // Verificar pareja A (posiciones 0-1)
        if (count($idsMesa) > 1) {
            $id1 = $idsMesa[0];
            $id2 = $idsMesa[1];
            if (isset($matrizCompañeros[$id1][$id2]) || isset($matrizCompañeros[$id2][$id1])) {
                return true;
            }
        }

        // Verificar pareja B (posiciones 2-3)
        if (count($idsMesa) > 3) {
            $id3 = $idsMesa[2];
            $id4 = $idsMesa[3];
            if (isset($matrizCompañeros[$id3][$id4]) || isset($matrizCompañeros[$id4][$id3])) {
                return true;
            }
        }

        return false;
    }

    private function obtenerJugadoresSobrantes($todosJugadores, $mesas)
    {
        $asignados = [];
        foreach ($mesas as $mesa) {
            foreach ($mesa as $jugador) {
                $asignados[] = $jugador['id_usuario'];
            }
        }

        return array_values(array_filter($todosJugadores, function($j) use ($asignados) {
            return !in_array($j['id_usuario'], $asignados);
        }));
    }

    /**
     * Con pocas mesas (ej. 17 jugadores = 4 mesas + 1 BYE) hay que garantizar exactamente numMesas mesas de 4.
     * Solo redistribuye si hay mesas de más o alguna mesa con distinto de 4 jugadores.
     */
    private function ajustarMesasExactas(array $mesas, int $numMesas, array $jugadoresParaMesas): array
    {
        $correcto = count($mesas) === $numMesas;
        if ($correcto) {
            foreach ($mesas as $mesa) {
                if (count($mesa) !== self::JUGADORES_POR_MESA) {
                    $correcto = false;
                    break;
                }
            }
        }
        if ($correcto) {
            return $mesas;
        }

        $todos = [];
        foreach ($mesas as $mesa) {
            foreach ($mesa as $j) {
                $todos[$j['id_usuario']] = $j;
            }
        }
        $ordenados = [];
        foreach ($jugadoresParaMesas as $j) {
            $id = (int)($j['id_usuario'] ?? 0);
            if (isset($todos[$id])) {
                $ordenados[] = $j;
            }
        }
        if (count($ordenados) < $numMesas * self::JUGADORES_POR_MESA) {
            return $mesas;
        }
        $resultado = [];
        for ($m = 0; $m < $numMesas; $m++) {
            $resultado[] = array_slice($ordenados, $m * self::JUGADORES_POR_MESA, self::JUGADORES_POR_MESA);
        }
        return $resultado;
    }
}

