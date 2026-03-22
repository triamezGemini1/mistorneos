<?php
declare(strict_types=1);

require_once __DIR__ . '/SancionesHelper.php';
require_once __DIR__ . '/ResultadosPartidaEfectividad.php';

/**
 * Guardado de resultados de mesa para modalidad individual (1) y equipos (3).
 * No incluye parejas (2/4): ese flujo es ParejasResultadosService.
 */
final class IndividualEquiposResultadosService
{
    /**
     * @param array<int, array<string, mixed>> $jugadoresPost
     * @return array{es_empate_mano_nula: bool, ids_tarjeta_negra: int[]}
     */
    public static function guardarResultadosMesa(
        PDO $pdo,
        int $torneoId,
        int $ronda,
        int $mesa,
        array $jugadoresPost,
        int $userId,
        int $puntosTorneo
    ): array {
        $jugadores = array_values($jugadoresPost);
        $jugadoresOrdenadosSec = $jugadores;
        usort($jugadoresOrdenadosSec, static function ($a, $b) {
            return ((int)($a['secuencia'] ?? 0)) <=> ((int)($b['secuencia'] ?? 0));
        });
        $nJugMesa = count($jugadoresOrdenadosSec);
        $mitadJugMesa = (int)($nJugMesa / 2);
        $secuenciasLadoA = [];
        for ($hi = 0; $hi < $mitadJugMesa; $hi++) {
            $secuenciasLadoA[(int)($jugadoresOrdenadosSec[$hi]['secuencia'] ?? 0)] = true;
        }

        $idsUsuariosMesa = array_map(static function ($j) {
            return (int)($j['id_usuario'] ?? 0);
        }, $jugadores);
        $idsUsuariosMesa = array_values(array_filter($idsUsuariosMesa));
        $tarjetaPreviaPorUsuario = SancionesHelper::getTarjetaPreviaDesdePartidasAnteriores(
            $pdo,
            $torneoId,
            $ronda,
            $idsUsuariosMesa
        );

        $hayForfaitEnMesa = false;
        foreach ($jugadores as $jugador) {
            $ffTemp = isset($jugador['ff']) && ($jugador['ff'] == '1' || $jugador['ff'] === true || $jugador['ff'] === 'on') ? 1 : 0;
            if ($ffTemp === 1) {
                $hayForfaitEnMesa = true;
                break;
            }
        }

        $hayTarjetaGraveEnMesa = false;
        foreach ($jugadores as $jugador) {
            $tarjetaTemp = (int)($jugador['tarjeta'] ?? 0);
            if ($tarjetaTemp === 3 || $tarjetaTemp === 4) {
                $hayTarjetaGraveEnMesa = true;
                break;
            }
        }

        $esEmpateManoNula = false;
        if (!$hayForfaitEnMesa && !$hayTarjetaGraveEnMesa) {
            $puntosParejaA = null;
            $puntosParejaB = null;
            if ($nJugMesa >= 2 && $mitadJugMesa >= 1) {
                $puntosParejaA = (int)($jugadoresOrdenadosSec[0]['resultado1'] ?? 0);
                $puntosParejaB = (int)($jugadoresOrdenadosSec[$mitadJugMesa]['resultado1'] ?? 0);
            }
            if ($puntosParejaA !== null && $puntosParejaB !== null && $puntosParejaA > 0 && $puntosParejaA === $puntosParejaB) {
                $esEmpateManoNula = true;
            }
        }

        $maximoPermitido = ResultadosPartidaEfectividad::maximoPuntosPermitidos($puntosTorneo);
        $datosJugadores = [];
        foreach ($jugadores as $index => $jugador) {
            $id = (int)($jugador['id'] ?? 0);
            $idUsuario = (int)($jugador['id_usuario'] ?? 0);
            $secuencia = (int)($jugador['secuencia'] ?? 0);
            $resultado1 = (int)($jugador['resultado1'] ?? 0);
            $resultado2 = (int)($jugador['resultado2'] ?? 0);
            $ff = isset($jugador['ff']) && ($jugador['ff'] == '1' || $jugador['ff'] === true || $jugador['ff'] === 'on') ? 1 : 0;
            $tarjeta = (int)($jugador['tarjeta'] ?? 0);
            $sancion = (int)($jugador['sancion'] ?? 0);
            $chancleta = (int)($jugador['chancleta'] ?? 0);
            $zapato = (int)($jugador['zapato'] ?? 0);

            if ($idUsuario === 0 || $secuencia === 0) {
                throw new Exception('Datos incompletos para el jugador ' . ($index + 1) . ": id_usuario=$idUsuario, secuencia=$secuencia");
            }
            if ($resultado1 > $maximoPermitido) {
                throw new Exception("El resultado1 del jugador " . ($index + 1) . " ($resultado1) excede el máximo permitido ($maximoPermitido = puntos del torneo + 60%)");
            }
            if ($resultado2 > $maximoPermitido) {
                throw new Exception("El resultado2 del jugador " . ($index + 1) . " ($resultado2) excede el máximo permitido ($maximoPermitido = puntos del torneo + 60%)");
            }

            $esParejaA = isset($secuenciasLadoA[$secuencia]);
            $tarjetaInscritos = (int)($tarjetaPreviaPorUsuario[$idUsuario] ?? 0);
            if ($sancion > 0 || $tarjeta > 0) {
                $procesado = SancionesHelper::procesar($sancion, $tarjeta, $tarjetaInscritos);
                $sancionParaCalculo = (int)$procesado['sancion_para_calculo'];
                $tarjeta = (int)$procesado['tarjeta'];
                $sancion = (int)$procesado['sancion_guardar'];
            } else {
                $sancionParaCalculo = 0;
            }
            $resultado1Ajustado = max(0, $resultado1 - $sancionParaCalculo);

            $datosJugadores[] = [
                'id' => $id,
                'id_usuario' => $idUsuario,
                'secuencia' => $secuencia,
                'resultado1' => $resultado1,
                'resultado2' => $resultado2,
                'resultado1Ajustado' => $resultado1Ajustado,
                'ff' => $ff,
                'tarjeta' => $tarjeta,
                'sancion' => $sancion,
                'sancion_para_calculo' => $sancionParaCalculo,
                'chancleta' => $chancleta,
                'zapato' => $zapato,
                'esParejaA' => $esParejaA,
                'index' => $index,
            ];
        }

        $sqlById = 'UPDATE partiresul SET 
                        resultado1 = ?,
                        resultado2 = ?,
                        efectividad = ?,
                        ff = ?,
                        tarjeta = ?,
                        sancion = ?,
                        chancleta = ?,
                        zapato = ?,
                        fecha_partida = NOW(),
                        registrado_por = ?,
                        registrado = 1
                        WHERE id = ?';
        $sqlByClave = 'UPDATE partiresul SET 
                        resultado1 = ?,
                        resultado2 = ?,
                        efectividad = ?,
                        ff = ?,
                        tarjeta = ?,
                        sancion = ?,
                        chancleta = ?,
                        zapato = ?,
                        fecha_partida = NOW(),
                        registrado_por = ?,
                        registrado = 1
                        WHERE id_torneo = ? AND partida = ? AND mesa = ? 
                        AND id_usuario = ? AND secuencia = ?';

        $stmtById = $pdo->prepare($sqlById);
        $stmtByClave = $pdo->prepare($sqlByClave);

        foreach ($datosJugadores as $jugador) {
            $id = $jugador['id'];
            $idUsuario = $jugador['id_usuario'];
            $secuencia = $jugador['secuencia'];
            $resultado1 = $jugador['resultado1'];
            $resultado2 = $jugador['resultado2'];
            $resultado1Ajustado = $jugador['resultado1Ajustado'];
            $ff = $jugador['ff'];
            $tarjeta = $jugador['tarjeta'];
            $sancion = $jugador['sancion'];
            $chancleta = $jugador['chancleta'];
            $zapato = $jugador['zapato'];
            $idx = (int)$jugador['index'];

            if ($esEmpateManoNula) {
                $efectividad = 0;
                $resultado1 = 0;
                $resultado2 = 0;
            } elseif ($hayForfaitEnMesa) {
                $calculoForfait = ResultadosPartidaEfectividad::calcularEfectividadForfait($ff == 1, $puntosTorneo);
                $efectividad = $calculoForfait['efectividad'];
                $resultado1 = $calculoForfait['resultado1'];
                $resultado2 = $calculoForfait['resultado2'];
            } elseif ($hayTarjetaGraveEnMesa) {
                $calculoTarjeta = ResultadosPartidaEfectividad::calcularEfectividadTarjetaGrave($tarjeta == 3 || $tarjeta == 4, $puntosTorneo);
                $efectividad = $calculoTarjeta['efectividad'];
                $resultado1 = $calculoTarjeta['resultado1'];
                $resultado2 = $calculoTarjeta['resultado2'];
            } else {
                $sancionParaCalc = (int)($jugador['sancion_para_calculo'] ?? $jugador['sancion'] ?? 0);
                if ($sancionParaCalc > 0) {
                    $evaluacionSancion = ResultadosPartidaEfectividad::evaluarSancionIndividual(
                        $resultado1,
                        $resultado2,
                        $sancionParaCalc,
                        $puntosTorneo
                    );
                    $efectividad = $evaluacionSancion['efectividad'];
                } else {
                    $efectividad = ResultadosPartidaEfectividad::calcularEfectividad(
                        $resultado1Ajustado,
                        $resultado2,
                        $puntosTorneo,
                        $ff,
                        $tarjeta,
                        0
                    );
                }
            }

            if ($id > 0) {
                $result = $stmtById->execute([
                    $resultado1, $resultado2, $efectividad, $ff, $tarjeta,
                    $sancion, $chancleta, $zapato, $userId, $id,
                ]);
                if (!$result || $stmtById->rowCount() === 0) {
                    throw new Exception('No se pudo actualizar el registro del jugador ' . ($idx + 1) . " (ID: $id)");
                }
            } else {
                $result = $stmtByClave->execute([
                    $resultado1, $resultado2, $efectividad, $ff, $tarjeta,
                    $sancion, $chancleta, $zapato, $userId,
                    $torneoId, $ronda, $mesa, $idUsuario, $secuencia,
                ]);
                if (!$result || $stmtByClave->rowCount() === 0) {
                    throw new Exception('No se pudo actualizar el registro del jugador ' . ($idx + 1) . " (usuario: $idUsuario, secuencia: $secuencia)");
                }
            }
        }

        $idsTarjetaNegra = [];
        foreach ($datosJugadores as $j) {
            if ((int)($j['tarjeta'] ?? 0) === SancionesHelper::TARJETA_NEGRA) {
                $idsTarjetaNegra[] = (int)$j['id_usuario'];
            }
        }

        return [
            'es_empate_mano_nula' => $esEmpateManoNula,
            'ids_tarjeta_negra' => $idsTarjetaNegra,
        ];
    }
}
