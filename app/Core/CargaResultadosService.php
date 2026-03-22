<?php

declare(strict_types=1);

/**
 * Lectura de mesa y actualización baja nivel de partiresul.
 * La entrada operativa para guardar resultados con validación e integridad es
 * {@see TournamentPersistenceService::grabarResultados()}.
 *
 * Modelo A: filas independientes. Modelo B: parejas (replica puntos/sets/extras a ambos integrantes).
 */
final class CargaResultadosService
{
    public static function puntosObjetivoMesa(): int
    {
        $v = (int) (getenv('MESA_PUNTOS_OBJETIVO') ?: 100);

        return $v > 0 ? $v : 100;
    }

    public static function tablaAuth(): string
    {
        $t = strtolower(trim((string) (getenv('DB_AUTH_TABLE') ?: 'usuarios')));

        return in_array($t, ['usuarios', 'users'], true) ? $t : 'usuarios';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function obtenerFilasMesa(PDO $pdo, int $torneoId, int $partida, int $mesa): array
    {
        $tabla = self::tablaAuth();
        $sql = <<<SQL
            SELECT p.id, p.id_usuario, p.secuencia, p.mesa, p.partida,
                   p.resultado1, p.resultado2, p.chancleta, p.zapato, p.registrado,
                   u.nombre, u.cedula
            FROM partiresul p
            INNER JOIN `{$tabla}` u ON u.id = p.id_usuario
            WHERE p.id_torneo = ? AND p.partida = ? AND p.mesa = ? AND p.mesa > 0
            ORDER BY p.secuencia ASC, p.id ASC
            SQL;

        try {
            $st = $pdo->prepare($sql);
            $st->execute([$torneoId, $partida, $mesa]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $sql2 = <<<SQL
                SELECT p.id, p.id_usuario, p.secuencia, p.mesa, p.partida,
                       p.resultado1, p.resultado2, p.registrado,
                       u.nombre, u.cedula
                FROM partiresul p
                INNER JOIN `{$tabla}` u ON u.id = p.id_usuario
                WHERE p.id_torneo = ? AND p.partida = ? AND p.mesa = ? AND p.mesa > 0
                ORDER BY p.secuencia ASC, p.id ASC
                SQL;
            try {
                $st2 = $pdo->prepare($sql2);
                $st2->execute([$torneoId, $partida, $mesa]);
                $rows = $st2->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as &$r) {
                    $r['chancleta'] = 0;
                    $r['zapato'] = 0;
                }
                unset($r);

                return $rows;
            } catch (Throwable $e2) {
                error_log('CargaResultadosService::obtenerFilasMesa: ' . $e->getMessage());

                return [];
            }
        }
    }

    /**
     * @param list<array{puntos:int|float,sets:int|float,chancleta:int|float,zapato:int|float}> $porFila
     * @param list<int> $idsOrden ids partiresul en el mismo orden que $porFila
     */
    public static function guardarEstandar(
        PDO $pdo,
        int $torneoId,
        array $idsOrden,
        array $porFila,
        int $adminUserId
    ): void {
        if (count($idsOrden) !== count($porFila)) {
            throw new InvalidArgumentException('Resultados inconsistentes.');
        }

        $tieneRegPor = self::columnaExiste($pdo, 'partiresul', 'registrado_por');
        $tieneCh = self::columnaExiste($pdo, 'partiresul', 'chancleta');
        $tieneZa = self::columnaExiste($pdo, 'partiresul', 'zapato');

        $pdo->beginTransaction();
        try {
            foreach ($idsOrden as $i => $pid) {
                $pid = (int) $pid;
                if ($pid <= 0 || !isset($porFila[$i])) {
                    continue;
                }
                $r = $porFila[$i];
                $puntos = (int) round((float) ($r['puntos'] ?? 0));
                $sets = (int) round((float) ($r['sets'] ?? 0));
                $ch = max(0, (int) round((float) ($r['chancleta'] ?? 0)));
                $za = max(0, (int) round((float) ($r['zapato'] ?? 0)));

                $setsSql = ['resultado1 = ?', 'resultado2 = ?', 'registrado = 1'];
                $bind = [$puntos, $sets];
                if ($tieneCh) {
                    $setsSql[] = 'chancleta = ?';
                    $bind[] = $ch;
                }
                if ($tieneZa) {
                    $setsSql[] = 'zapato = ?';
                    $bind[] = $za;
                }
                if ($tieneRegPor) {
                    $setsSql[] = 'registrado_por = ?';
                    $bind[] = $adminUserId > 0 ? $adminUserId : 1;
                }
                $bind[] = $pid;
                $bind[] = $torneoId;

                $sql = 'UPDATE partiresul SET ' . implode(', ', $setsSql) . ' WHERE id = ? AND id_torneo = ?';
                $up = $pdo->prepare($sql);
                $up->execute($bind);
                if ($up->rowCount() === 0) {
                    throw new RuntimeException('No se pudo actualizar la fila ' . $pid);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Replica los mismos puntos/sets/extras a los dos integrantes de cada pareja.
     *
     * @param list<int> $idsParejaA ids partiresul (2 filas)
     * @param list<int> $idsParejaB ids partiresul (2 filas)
     * @param array{puntos_A:float|int,sets_A:float|int,chancleta_A:float|int,zapato_A:float|int,puntos_B:float|int,sets_B:float|int,chancleta_B:float|int,zapato_B:float|int} $datos
     */
    public static function guardarParejas(
        PDO $pdo,
        int $torneoId,
        array $idsParejaA,
        array $idsParejaB,
        array $datos,
        int $adminUserId
    ): void {
        if (count($idsParejaA) !== 2 || count($idsParejaB) !== 2) {
            throw new InvalidArgumentException('Se requieren 2 filas por pareja.');
        }

        $rowA = [
            'puntos' => $datos['puntos_A'],
            'sets' => $datos['sets_A'],
            'chancleta' => $datos['chancleta_A'],
            'zapato' => $datos['zapato_A'],
        ];
        $rowB = [
            'puntos' => $datos['puntos_B'],
            'sets' => $datos['sets_B'],
            'chancleta' => $datos['chancleta_B'],
            'zapato' => $datos['zapato_B'],
        ];

        $idsOrden = [(int) $idsParejaA[0], (int) $idsParejaA[1], (int) $idsParejaB[0], (int) $idsParejaB[1]];
        $porFila = [$rowA, $rowA, $rowB, $rowB];

        self::guardarEstandar($pdo, $torneoId, $idsOrden, $porFila, $adminUserId);
    }

    private static function columnaExiste(PDO $pdo, string $tabla, string $columna): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $st->execute([$tabla, $columna]);

            return (int) $st->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}
