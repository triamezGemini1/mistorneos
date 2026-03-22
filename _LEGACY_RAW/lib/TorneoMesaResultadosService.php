<?php
declare(strict_types=1);

require_once __DIR__ . '/TorneoMesaReglas.php';

/**
 * Validaciones y lecturas de mesa/ronda para guardar resultados.
 * Reduce idas a BD: modalidad+puntos en una consulta; máximo de mesa + filas de la mesa en otra.
 */
final class TorneoMesaResultadosService
{
    /**
     * @return array{modalidad: int, puntos: int}
     */
    public static function obtenerModalidadYPuntos(PDO $pdo, int $torneoId): array
    {
        $st = $pdo->prepare('SELECT modalidad, puntos FROM tournaments WHERE id = ? LIMIT 1');
        $st->execute([$torneoId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Torneo no encontrado.');
        }
        return [
            'modalidad' => (int)($row['modalidad'] ?? 0),
            'puntos' => (int)($row['puntos'] ?? 100),
        ];
    }

    /**
     * @return array{max_mesa: int, filas_mesa: int}
     */
    public static function obtenerInfoMesaEnRonda(PDO $pdo, int $torneoId, int $ronda, int $mesa): array
    {
        $st = $pdo->prepare(
            'SELECT 
                COALESCE(MAX(CAST(pr.mesa AS UNSIGNED)), 0) AS max_mesa,
                COALESCE(SUM(CASE WHEN pr.mesa = ? THEN 1 ELSE 0 END), 0) AS filas_mesa
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0'
        );
        $st->execute([$mesa, $torneoId, $ronda]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'max_mesa' => (int)($row['max_mesa'] ?? 0),
            'filas_mesa' => (int)($row['filas_mesa'] ?? 0),
        ];
    }

    public static function validarMesaExisteEnRonda(PDO $pdo, int $torneoId, int $ronda, int $mesa): void
    {
        $info = self::obtenerInfoMesaEnRonda($pdo, $torneoId, $ronda, $mesa);
        $maxMesa = $info['max_mesa'];
        if ($info['filas_mesa'] === 0) {
            throw new Exception(
                "La mesa #{$mesa} no existe en la ronda {$ronda}. "
                . ($maxMesa > 0 ? "El número máximo de mesa asignada es {$maxMesa}." : 'No hay mesas asignadas para esta ronda.')
            );
        }
        if ($maxMesa > 0 && $mesa > $maxMesa) {
            throw new Exception("La mesa #{$mesa} no existe. El número máximo de mesa asignada es {$maxMesa}.");
        }
    }

    /**
     * POST con 4 ítems y misma cantidad de filas en partiresul para esa mesa.
     *
     * @param array<int, mixed> $jugadoresPost
     */
    public static function validarCuposMesa(PDO $pdo, array $jugadoresPost, int $torneoId, int $ronda, int $mesa): void
    {
        $esperado = TorneoMesaReglas::JUGADORES_POR_MESA;
        $n = is_array($jugadoresPost) ? count($jugadoresPost) : 0;
        if ($n !== $esperado) {
            throw new Exception('Debe haber exactamente ' . $esperado . ' jugadores por mesa.');
        }
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = ? AND mesa > 0'
        );
        $st->execute([$torneoId, $ronda, $mesa]);
        $cnt = (int)$st->fetchColumn();
        if ($cnt !== $esperado) {
            throw new Exception(
                'La mesa debe tener exactamente ' . $esperado . ' jugadores asignados en esta ronda (registrados: ' . $cnt . ').'
            );
        }
    }
}
