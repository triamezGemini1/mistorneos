<?php
declare(strict_types=1);

require_once __DIR__ . '/SancionesHelper.php';
require_once __DIR__ . '/TorneoMesaReglas.php';

final class ParejasResultadosService
{
    public static function guardarResultadosMesa(
        PDO $pdo,
        int $torneoId,
        int $ronda,
        int $mesa,
        array $jugadoresPost,
        int $userId,
        int $puntosTorneo
    ): array {
        $jugadoresPost = array_values($jugadoresPost);
        $nMesa = TorneoMesaReglas::JUGADORES_POR_MESA;
        if (count($jugadoresPost) !== $nMesa) {
            throw new Exception('En parejas deben existir ' . $nMesa . ' jugadores por mesa.');
        }

        $idsUsuarios = array_values(array_filter(array_map(static function ($j) {
            return (int)($j['id_usuario'] ?? 0);
        }, $jugadoresPost)));
        if (count($idsUsuarios) !== $nMesa) {
            throw new Exception('No se pudo identificar a los ' . $nMesa . ' jugadores de la mesa.');
        }

        $ph = implode(',', array_fill(0, count($idsUsuarios), '?'));
        $stmtCod = $pdo->prepare("SELECT id_usuario, codigo_equipo FROM inscritos WHERE torneo_id = ? AND id_usuario IN ($ph)");
        $stmtCod->execute(array_merge([$torneoId], $idsUsuarios));
        $codigoPorUsuario = [];
        foreach ($stmtCod->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $codigoPorUsuario[(int)$row['id_usuario']] = trim((string)($row['codigo_equipo'] ?? ''));
        }

        // Parejas por slot de mesa: A = secuencias 1-2, B = secuencias 3-4.
        // La captura se toma por pareja (fila líder: secuencia 1 y 3), nunca por jugador individual.
        $parejas = [
            'A' => ['slot' => 'A', 'codigo' => '', 'puntos' => 0, 'sancion' => 0, 'ff' => 0, 'tarjeta' => 0, 'chancleta' => 0, 'zapato' => 0],
            'B' => ['slot' => 'B', 'codigo' => '', 'puntos' => 0, 'sancion' => 0, 'ff' => 0, 'tarjeta' => 0, 'chancleta' => 0, 'zapato' => 0],
        ];
        foreach ($jugadoresPost as $j) {
            $secuencia = (int)($j['secuencia'] ?? 0);
            $slotPareja = in_array($secuencia, [1, 2], true) ? 'A' : 'B';
            if ($secuencia === 1 || $secuencia === 3) {
                $parejas[$slotPareja]['puntos'] = (int)($j['resultado1'] ?? 0);
                $parejas[$slotPareja]['sancion'] = (int)($j['sancion'] ?? 0);
                $parejas[$slotPareja]['ff'] = (isset($j['ff']) && ($j['ff'] == '1' || $j['ff'] === true || $j['ff'] === 'on')) ? 1 : 0;
                $parejas[$slotPareja]['tarjeta'] = (int)($j['tarjeta'] ?? 0);
                $parejas[$slotPareja]['chancleta'] = (int)($j['chancleta'] ?? 0);
                $parejas[$slotPareja]['zapato'] = (int)($j['zapato'] ?? 0);
            }
        }

        $stmtCodPareja = $pdo->prepare("
            SELECT
                CASE WHEN pr.secuencia IN (1,2) THEN 'A' ELSE 'B' END AS slot_pareja,
                i.codigo_equipo
            FROM partiresul pr
            INNER JOIN inscritos i ON i.torneo_id = pr.id_torneo AND i.id_usuario = pr.id_usuario
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ?
            GROUP BY slot_pareja, i.codigo_equipo
            ORDER BY slot_pareja ASC
        ");
        $stmtCodPareja->execute([$torneoId, $ronda, $mesa]);
        $codigosMesa = $stmtCodPareja->fetchAll(PDO::FETCH_ASSOC);
        foreach ($codigosMesa as $filaCodigo) {
            $slot = (string)($filaCodigo['slot_pareja'] ?? '');
            $codigo = trim((string)($filaCodigo['codigo_equipo'] ?? ''));
            if (!isset($parejas[$slot])) {
                continue;
            }
            if ($codigo === '' || $codigo === '000-000') {
                throw new Exception("La pareja {$slot} no tiene codigo_equipo válido.");
            }
            if ($parejas[$slot]['codigo'] !== '' && $parejas[$slot]['codigo'] !== $codigo) {
                throw new Exception("Se detectaron múltiples codigos en la pareja {$slot}.");
            }
            $parejas[$slot]['codigo'] = $codigo;
        }
        if ($parejas['A']['codigo'] === '' || $parejas['B']['codigo'] === '') {
            throw new Exception('No se pudo determinar el codigo_equipo de ambas parejas en la mesa.');
        }
        if ($parejas['A']['codigo'] === $parejas['B']['codigo']) {
            throw new Exception('Ambas parejas comparten el mismo codigo_equipo; la mesa está inconsistente.');
        }

        $tarjetaPreviaPorUsuario = SancionesHelper::getTarjetaPreviaDesdePartidasAnteriores($pdo, $torneoId, $ronda, $idsUsuarios);
        $tarjetaPreviaPorEquipo = [];
        foreach ($tarjetaPreviaPorUsuario as $idUsuario => $tarjetaPrev) {
            $codigo = trim((string)($codigoPorUsuario[(int)$idUsuario] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $tarjetaPreviaPorEquipo[$codigo] = max((int)($tarjetaPreviaPorEquipo[$codigo] ?? 0), (int)$tarjetaPrev);
        }

        foreach ($parejas as $slot => &$eq) {
            $codigo = (string)$eq['codigo'];
            if ($eq['sancion'] > 0 || $eq['tarjeta'] > 0) {
                $procesado = SancionesHelper::procesar(
                    (int)$eq['sancion'],
                    (int)$eq['tarjeta'],
                    (int)($tarjetaPreviaPorEquipo[$codigo] ?? 0)
                );
                $eq['sancion_guardar'] = (int)$procesado['sancion_guardar'];
                $eq['sancion_calc'] = (int)$procesado['sancion_para_calculo'];
                $eq['tarjeta'] = (int)$procesado['tarjeta'];
            } else {
                $eq['sancion_guardar'] = 0;
                $eq['sancion_calc'] = 0;
            }
            $eq['puntos'] = max(0, min((int)round($puntosTorneo * 1.6), (int)$eq['puntos']));
        }
        unset($eq);

        $hayForfait = ((int)$parejas['A']['ff'] === 1 || (int)$parejas['B']['ff'] === 1);
        $hayTarjetaGrave = (in_array((int)$parejas['A']['tarjeta'], [3, 4], true) || in_array((int)$parejas['B']['tarjeta'], [3, 4], true));
        $esEmpateManoNula = (!$hayForfait && !$hayTarjetaGrave && $parejas['A']['puntos'] > 0 && $parejas['A']['puntos'] === $parejas['B']['puntos']);

        $resultadoPareja = [];
        foreach (['A', 'B'] as $slot) {
            $opSlot = $slot === 'A' ? 'B' : 'A';
            $eq = $parejas[$slot];
            $op = $parejas[$opSlot];
            $r1 = (int)$eq['puntos'];
            $r2 = (int)$op['puntos'];
            $ef = 0;

            if ($esEmpateManoNula) {
                $r1 = 0;
                $r2 = 0;
                $ef = 0;
            } elseif ($hayForfait) {
                if ((int)$eq['ff'] === 1) {
                    $r1 = 0;
                    $r2 = $puntosTorneo;
                    $ef = -$puntosTorneo;
                } else {
                    $r1 = $puntosTorneo;
                    $r2 = 0;
                    $ef = (int)($puntosTorneo / 2);
                }
            } elseif ($hayTarjetaGrave) {
                if (in_array((int)$eq['tarjeta'], [3, 4], true)) {
                    $r1 = 0;
                    $r2 = $puntosTorneo;
                    $ef = -$puntosTorneo;
                } else {
                    $r1 = $puntosTorneo;
                    $r2 = 0;
                    $ef = $puntosTorneo;
                }
            } else {
                $r1Ajust = max(0, $r1 - (int)$eq['sancion_calc']);
                $ef = self::calcularEfectividadNormal($r1Ajust, $r2, $puntosTorneo);
            }

            $resultadoPareja[$slot] = [
                'resultado1' => $r1,
                'resultado2' => $r2,
                'efectividad' => $ef,
                'ff' => (int)$eq['ff'],
                'tarjeta' => (int)$eq['tarjeta'],
                'sancion' => (int)$eq['sancion_guardar'],
                'chancleta' => (int)$eq['chancleta'],
                'zapato' => (int)$eq['zapato'],
            ];
        }

        // ACTUALIZACION POR PAREJA EN LA MESA:
        // pareja A = secuencias 1 y 2, pareja B = secuencias 3 y 4.
        // Se aplica el mismo bloque de valores a los dos jugadores de la pareja.
        $stmtUpdPareja = $pdo->prepare("
            UPDATE partiresul pr
            SET pr.resultado1 = ?,
                pr.resultado2 = ?,
                pr.efectividad = ?,
                pr.ff = ?,
                pr.tarjeta = ?,
                pr.sancion = ?,
                pr.chancleta = ?,
                pr.zapato = ?,
                pr.fecha_partida = NOW(),
                pr.registrado_por = ?,
                pr.registrado = 1
            WHERE pr.id_torneo = ?
              AND pr.partida = ?
              AND pr.mesa = ?
              AND pr.secuencia IN (?, ?)
        ");
        $stmtCountPareja = $pdo->prepare("
            SELECT COUNT(*) 
            FROM partiresul pr
            WHERE pr.id_torneo = ?
              AND pr.partida = ?
              AND pr.mesa = ?
              AND pr.secuencia IN (?, ?)
        ");
        $stmtIdsPareja = $pdo->prepare("
            SELECT DISTINCT pr.id_usuario
            FROM partiresul pr
            WHERE pr.id_torneo = ?
              AND pr.partida = ?
              AND pr.mesa = ?
              AND pr.secuencia IN (?, ?)
        ");

        $idsTarjetaNegra = [];
        foreach ($resultadoPareja as $slotPareja => $res) {
            $secuencia1 = $slotPareja === 'A' ? 1 : 3;
            $secuencia2 = $slotPareja === 'A' ? 2 : 4;

            $stmtCountPareja->execute([$torneoId, $ronda, $mesa, $secuencia1, $secuencia2]);
            $totalFilasPareja = (int)$stmtCountPareja->fetchColumn();
            if ($totalFilasPareja !== 2) {
                throw new Exception("La mesa no tiene exactamente 2 jugadores para la pareja {$slotPareja} (secuencias {$secuencia1}-{$secuencia2}).");
            }

            $stmtUpdPareja->execute([
                $res['resultado1'], $res['resultado2'], $res['efectividad'], $res['ff'], $res['tarjeta'],
                $res['sancion'], $res['chancleta'], $res['zapato'], $userId,
                $torneoId, $ronda, $mesa, $secuencia1, $secuencia2
            ]);

            if ((int)$res['tarjeta'] === SancionesHelper::TARJETA_NEGRA) {
                $stmtIdsPareja->execute([$torneoId, $ronda, $mesa, $secuencia1, $secuencia2]);
                $idsTarjetaNegra = array_merge(
                    $idsTarjetaNegra,
                    array_map('intval', array_column($stmtIdsPareja->fetchAll(PDO::FETCH_ASSOC), 'id_usuario'))
                );
            }
        }

        return [
            'es_empate_mano_nula' => $esEmpateManoNula,
            'ids_tarjeta_negra' => array_values(array_unique($idsTarjetaNegra)),
        ];
    }

    public static function recalcularInscritosPorCodigoEquipo(PDO $pdo, int $torneoId): void
    {
        $stmt = $pdo->prepare("
            SELECT DISTINCT codigo_equipo
            FROM inscritos
            WHERE torneo_id = ?
              AND estatus != 4
              AND codigo_equipo IS NOT NULL
              AND codigo_equipo != ''
              AND codigo_equipo != '000-000'
        ");
        $stmt->execute([$torneoId]);
        $codigos = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'codigo_equipo');
        if (empty($codigos)) {
            return;
        }

        $stmtRondas = $pdo->prepare("
            SELECT DISTINCT pr.partida, pr.mesa
            FROM partiresul pr
            INNER JOIN inscritos i ON i.torneo_id = pr.id_torneo AND i.id_usuario = pr.id_usuario
            WHERE pr.id_torneo = ? AND i.codigo_equipo = ? AND pr.registrado = 1
            ORDER BY pr.partida ASC, pr.mesa ASC
        ");
        $stmtMesa = $pdo->prepare("
            SELECT pr.resultado1, pr.efectividad, pr.ff, pr.tarjeta, pr.sancion, pr.chancleta, pr.zapato, i.codigo_equipo
            FROM partiresul pr
            LEFT JOIN inscritos i ON i.torneo_id = pr.id_torneo AND i.id_usuario = pr.id_usuario
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ? AND pr.registrado = 1
        ");
        $stmtUpd = $pdo->prepare("
            UPDATE inscritos SET ganados=?, perdidos=?, efectividad=?, puntos=?, sancion=?, chancletas=?, zapatos=?, tarjeta=?
            WHERE torneo_id = ? AND codigo_equipo = ? AND estatus != 4
        ");

        foreach ($codigos as $codigo) {
            $stmtRondas->execute([$torneoId, $codigo]);
            $partidas = $stmtRondas->fetchAll(PDO::FETCH_ASSOC);
            $g = 0; $p = 0; $ef = 0; $pts = 0; $san = 0; $ch = 0; $za = 0; $tar = 0;
            foreach ($partidas as $pm) {
                $partida = (int)$pm['partida'];
                $mesa = (int)$pm['mesa'];
                $stmtMesa->execute([$torneoId, $partida, $mesa]);
                $filas = $stmtMesa->fetchAll(PDO::FETCH_ASSOC);
                if (empty($filas)) {
                    continue;
                }
                $eq = [];
                $op = [];
                foreach ($filas as $f) {
                    if (trim((string)($f['codigo_equipo'] ?? '')) === $codigo) {
                        $eq[] = $f;
                    } else {
                        $op[] = $f;
                    }
                }
                if (empty($eq)) {
                    continue;
                }
                $r = $eq[0];
                $resultado1 = (int)$r['resultado1'];
                $efectividad = (int)$r['efectividad'];
                $sancion = (int)$r['sancion'];
                $ffEq = false; $ffOp = false; $tgEq = false; $tgOp = false;
                foreach ($eq as $x) { $ffEq = $ffEq || ((int)$x['ff'] === 1); $tgEq = $tgEq || in_array((int)$x['tarjeta'], [3,4], true); $ch = max($ch, (int)$x['chancleta']); $za = max($za, (int)$x['zapato']); $tar = max($tar, (int)$x['tarjeta']); }
                foreach ($op as $x) { $ffOp = $ffOp || ((int)$x['ff'] === 1); $tgOp = $tgOp || in_array((int)$x['tarjeta'], [3,4], true); }

                $gano = false;
                if ($mesa === 0) {
                    $gano = true;
                } elseif ($ffEq || $ffOp) {
                    $gano = (!$ffEq && $ffOp);
                } elseif ($tgEq || $tgOp) {
                    $gano = (!$tgEq && $tgOp);
                } else {
                    $resultado2 = isset($op[0]) ? (int)$op[0]['resultado1'] : 0;
                    $gano = $sancion > 0 ? (max(0, $resultado1 - $sancion) > $resultado2) : ($resultado1 > $resultado2);
                }

                if ($gano) { $g++; } else { $p++; }
                $ef += $efectividad;
                $pts += $resultado1;
                $san += $sancion;
            }
            $stmtUpd->execute([$g, $p, $ef, $pts, $san, $ch, $za, $tar, $torneoId, $codigo]);
        }
    }

    private static function calcularEfectividadNormal(int $resultado1, int $resultado2, int $puntosTorneo): int
    {
        if ($resultado1 === $resultado2) {
            return 0;
        }
        $mayor = max($resultado1, $resultado2);
        if ($mayor >= $puntosTorneo) {
            if ($resultado1 > $resultado2) {
                return $puntosTorneo - $resultado2;
            }
            return -($puntosTorneo - $resultado1);
        }
        if ($resultado1 > $resultado2) {
            return $resultado1 - $resultado2;
        }
        return -($resultado2 - $resultado1);
    }
}

