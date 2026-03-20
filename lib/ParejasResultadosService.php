<?php
declare(strict_types=1);

require_once __DIR__ . '/SancionesHelper.php';

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
        if (count($jugadoresPost) !== 4) {
            throw new Exception('En parejas deben existir 4 jugadores por mesa.');
        }

        $idsUsuarios = array_values(array_filter(array_map(static function ($j) {
            return (int)($j['id_usuario'] ?? 0);
        }, $jugadoresPost)));
        if (count($idsUsuarios) !== 4) {
            throw new Exception('No se pudo identificar a los 4 jugadores de la mesa.');
        }

        $ph = implode(',', array_fill(0, count($idsUsuarios), '?'));
        $stmtCod = $pdo->prepare("SELECT id_usuario, codigo_equipo FROM inscritos WHERE torneo_id = ? AND id_usuario IN ($ph)");
        $stmtCod->execute(array_merge([$torneoId], $idsUsuarios));
        $codigoPorUsuario = [];
        foreach ($stmtCod->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $codigoPorUsuario[(int)$row['id_usuario']] = trim((string)($row['codigo_equipo'] ?? ''));
        }

        $equipos = [];
        foreach ($jugadoresPost as $j) {
            $idUsuario = (int)($j['id_usuario'] ?? 0);
            $codigo = trim((string)($codigoPorUsuario[$idUsuario] ?? ''));
            if ($codigo === '' || $codigo === '000-000') {
                throw new Exception('Todos los jugadores de parejas deben tener codigo_equipo válido.');
            }
            $secuencia = (int)($j['secuencia'] ?? 0);
            $slotPareja = in_array($secuencia, [1, 2], true) ? 'A' : 'B';
            if (!isset($equipos[$codigo])) {
                $equipos[$codigo] = [
                    'codigo' => $codigo,
                    'slot' => $slotPareja,
                    'jugadores' => [],
                    'puntos' => 0,
                    'sancion' => (int)($j['sancion'] ?? 0),
                    'ff' => (isset($j['ff']) && ($j['ff'] == '1' || $j['ff'] === true || $j['ff'] === 'on')) ? 1 : 0,
                    'tarjeta' => (int)($j['tarjeta'] ?? 0),
                    'chancleta' => (int)($j['chancleta'] ?? 0),
                    'zapato' => (int)($j['zapato'] ?? 0),
                ];
            }
            if (($equipos[$codigo]['slot'] ?? $slotPareja) !== $slotPareja) {
                throw new Exception("Inconsistencia detectada: el codigo_equipo {$codigo} aparece en ambas parejas de la mesa.");
            }
            $equipos[$codigo]['jugadores'][] = $j;
            if ($secuencia === 1 || $secuencia === 3) {
                $equipos[$codigo]['puntos'] = (int)($j['resultado1'] ?? 0);
            }
        }

        if (count($equipos) !== 2) {
            throw new Exception('La mesa de parejas debe tener exactamente 2 codigos de equipo.');
        }
        $codigos = array_keys($equipos);
        $codigoA = $codigos[0];
        $codigoB = $codigos[1];

        $tarjetaPreviaPorUsuario = SancionesHelper::getTarjetaPreviaDesdePartidasAnteriores($pdo, $torneoId, $ronda, $idsUsuarios);
        $tarjetaPreviaPorEquipo = [];
        foreach ($tarjetaPreviaPorUsuario as $idUsuario => $tarjetaPrev) {
            $codigo = trim((string)($codigoPorUsuario[(int)$idUsuario] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $tarjetaPreviaPorEquipo[$codigo] = max((int)($tarjetaPreviaPorEquipo[$codigo] ?? 0), (int)$tarjetaPrev);
        }

        foreach ($equipos as $codigo => &$eq) {
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

        $hayForfait = ((int)$equipos[$codigoA]['ff'] === 1 || (int)$equipos[$codigoB]['ff'] === 1);
        $hayTarjetaGrave = (in_array((int)$equipos[$codigoA]['tarjeta'], [3, 4], true) || in_array((int)$equipos[$codigoB]['tarjeta'], [3, 4], true));
        $esEmpateManoNula = (!$hayForfait && !$hayTarjetaGrave && $equipos[$codigoA]['puntos'] > 0 && $equipos[$codigoA]['puntos'] === $equipos[$codigoB]['puntos']);

        $resultadoEquipo = [];
        foreach ($equipos as $codigo => $eq) {
            $codigoOponente = $codigo === $codigoA ? $codigoB : $codigoA;
            $op = $equipos[$codigoOponente];
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

            $resultadoEquipo[$codigo] = [
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
        foreach ($resultadoEquipo as $codigo => $res) {
            $slotPareja = (string)($equipos[$codigo]['slot'] ?? '');
            if (!in_array($slotPareja, ['A', 'B'], true)) {
                throw new Exception("No se pudo determinar la pareja (A/B) para el codigo_equipo {$codigo}.");
            }
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

