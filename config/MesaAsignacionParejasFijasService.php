<?php

declare(strict_types=1);

/**
 * Servicio de asignación de mesas para torneos de Parejas Fijas (modalidad 4).
 *
 * - Ronda 1: Parejas clasificadas por numero (consecutivo por club); emparejar al azar 1 con 1, 2 con 2, etc.
 *   Sin bloques por club (aleatorio en código).
 * - Rondas 2+: Según tipo (interclubes / suizo / suizo_puro); por defecto suizo por rendimiento.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../lib/InscritosHelper.php';
require_once __DIR__ . '/../lib/ParejasFijasHelper.php';

class MesaAsignacionParejasFijasService
{
    private PDO $pdo;
    private const JUGADORES_POR_PAREJA = 2;
    private const JUGADORES_POR_MESA = 4;
    /** Máximo jugadores en BYE por ronda (hasta 2 parejas = 4 jugadores). */
    private const MAX_JUGADORES_BYE = 4;

    public function __construct()
    {
        $this->pdo = DB::pdo();
    }

    /**
     * Genera la asignación de mesas para la ronda indicada.
     *
     * @param int $torneoId
     * @param int $numRonda
     * @param int $totalRondas
     * @param string $estrategia Ignorado; se usa tipo de torneo si existe.
     * @return array { success, message, total_mesas, jugadores_bye?, mesas? }
     */
    public function generarAsignacionRonda(
        int $torneoId,
        int $numRonda,
        int $totalRondas,
        string $estrategia = 'numero_aleatorio'
    ): array {
        if ($numRonda === 1) {
            return $this->generarRonda1($torneoId);
        }
        return $this->generarRonda2Plus($torneoId, $numRonda);
    }

    /**
     * Ronda 1: clasificar parejas por numero; emparejar al azar 1-1, 2-2, etc. (sin bloques por club).
     */
    private function generarRonda1(int $torneoId): array
    {
        $parejas = $this->obtenerParejasConJugadores($torneoId);
        if (empty($parejas)) {
            return [
                'success' => false,
                'message' => 'No hay parejas inscritas completas en el torneo.',
            ];
        }

        foreach ($parejas as $p) {
            if (count($p['jugadores']) !== self::JUGADORES_POR_PAREJA) {
                return [
                    'success' => false,
                    'message' => "La pareja '{$p['nombre_equipo']}' no tiene exactamente 2 jugadores.",
                ];
            }
        }

        // Agrupar por numero (consecutivo por club)
        $porNumero = [];
        foreach ($parejas as $pareja) {
            $n = (int) $pareja['numero'];
            $porNumero[$n][] = $pareja;
        }
        ksort($porNumero, SORT_NUMERIC);

        $mesasArray = [];
        $jugadoresBye = [];

        foreach ($porNumero as $numero => $lista) {
            // Mezclar al azar (no en bloque por club)
            shuffle($lista);
            $i = 0;
            while ($i + 1 < count($lista)) {
                $parejaA = $lista[$i];
                $parejaB = $lista[$i + 1];
                $mesa = [
                    $parejaA['jugadores'][0],
                    $parejaA['jugadores'][1],
                    $parejaB['jugadores'][0],
                    $parejaB['jugadores'][1],
                ];
                $mesasArray[] = $mesa;
                $i += 2;
            }
            if ($i < count($lista)) {
                // Pareja sin rival → BYE (ambos jugadores)
                $jugadoresBye[] = $lista[$i]['jugadores'][0];
                $jugadoresBye[] = $lista[$i]['jugadores'][1];
            }
        }

        $jugadoresBye = array_slice($jugadoresBye, 0, self::MAX_JUGADORES_BYE);

        $this->guardarAsignacionRonda($torneoId, 1, $mesasArray);
        if (!empty($jugadoresBye)) {
            $this->aplicarBye($torneoId, 1, $jugadoresBye);
        }

        return [
            'success' => true,
            'message' => 'Ronda 1 generada (parejas por número, emparejamiento al azar).',
            'total_inscritos' => count($parejas) * self::JUGADORES_POR_PAREJA,
            'total_mesas' => count($mesasArray),
            'jugadores_bye' => count($jugadoresBye),
            'mesas' => $mesasArray,
        ];
    }

    /**
     * Rondas 2+: emparejar por rendimiento (puntos de pareja). Siempre mejores en primeros lugares.
     * Tipo interclubes/suizo/suizo_puro se puede leer del torneo más adelante.
     */
    private function generarRonda2Plus(int $torneoId, int $numRonda): array
    {
        $parejas = $this->obtenerParejasConJugadoresYClasificacion($torneoId);
        if (empty($parejas)) {
            return [
                'success' => false,
                'message' => 'No hay parejas con clasificación para esta ronda.',
            ];
        }

        $mesasArray = [];
        $jugadoresBye = [];
        $lista = $parejas;
        $i = 0;
        while ($i + 1 < count($lista)) {
            $parejaA = $lista[$i];
            $parejaB = $lista[$i + 1];
            $mesa = [
                $parejaA['jugadores'][0],
                $parejaA['jugadores'][1],
                $parejaB['jugadores'][0],
                $parejaB['jugadores'][1],
            ];
            $mesasArray[] = $mesa;
            $i += 2;
        }
        if ($i < count($lista)) {
            $jugadoresBye[] = $lista[$i]['jugadores'][0];
            $jugadoresBye[] = $lista[$i]['jugadores'][1];
        }
        $jugadoresBye = array_slice($jugadoresBye, 0, self::MAX_JUGADORES_BYE);

        $this->guardarAsignacionRonda($torneoId, $numRonda, $mesasArray);
        if (!empty($jugadoresBye)) {
            $this->aplicarBye($torneoId, $numRonda, $jugadoresBye);
        }

        return [
            'success' => true,
            'message' => "Ronda {$numRonda} generada por rendimiento.",
            'total_mesas' => count($mesasArray),
            'jugadores_bye' => count($jugadoresBye),
            'mesas' => $mesasArray,
        ];
    }

    /**
     * Parejas con jugadores (solo datos básicos para ronda 1).
     */
    private function obtenerParejasConJugadores(int $torneoId): array
    {
        $parejas = ParejasFijasHelper::listarParejas($this->pdo, $torneoId);
        $out = [];
        foreach ($parejas as $p) {
            $jugadores = $this->obtenerJugadoresInscritosPorCodigo($torneoId, $p['codigo_equipo']);
            $out[] = [
                'codigo_equipo' => $p['codigo_equipo'],
                'nombre_equipo' => $p['nombre_equipo'],
                'id_club' => $p['id_club'],
                'numero' => $p['numero'],
                'jugadores' => $jugadores,
            ];
        }
        return $out;
    }

    /**
     * Parejas con jugadores y clasificación (puntos/ganados/efectividad) para rondas 2+.
     */
    private function obtenerParejasConJugadoresYClasificacion(int $torneoId): array
    {
        $sql = "
            SELECT e.codigo_equipo, e.nombre_equipo, e.id_club, e.consecutivo_club AS numero,
                   COALESCE(SUM(i.puntos), 0) AS puntos_equipo,
                   COALESCE(SUM(i.ganados), 0) AS ganados_equipo,
                   COALESCE(AVG(i.efectividad), 0) AS efectividad_equipo
            FROM equipos e
            LEFT JOIN inscritos i ON i.torneo_id = e.id_torneo AND i.codigo_equipo = e.codigo_equipo
                AND (i.estatus IN (0,1,2,3) OR i.estatus IN ('pendiente','confirmado','solvente','no_solvente'))
            WHERE e.id_torneo = ? AND e.estatus = 0
            GROUP BY e.id, e.codigo_equipo, e.nombre_equipo, e.id_club, e.consecutivo_club
            ORDER BY puntos_equipo DESC, ganados_equipo DESC, efectividad_equipo DESC, e.codigo_equipo ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$torneoId]);
        $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($equipos as $eq) {
            $jugadores = $this->obtenerJugadoresInscritosPorCodigo($torneoId, $eq['codigo_equipo']);
            if (count($jugadores) === self::JUGADORES_POR_PAREJA) {
                $out[] = [
                    'codigo_equipo' => $eq['codigo_equipo'],
                    'nombre_equipo' => $eq['nombre_equipo'],
                    'id_club' => (int) $eq['id_club'],
                    'numero' => (int) $eq['numero'],
                    'jugadores' => $jugadores,
                ];
            }
        }
        return $out;
    }

    private function obtenerJugadoresInscritosPorCodigo(int $torneoId, string $codigoEquipo): array
    {
        $sql = "
            SELECT i.id AS id_inscrito, i.id_usuario, i.codigo_equipo, u.nombre,
                   i.puntos, i.ganados, i.perdidos, i.efectividad, i.posicion
            FROM inscritos i
            INNER JOIN usuarios u ON u.id = i.id_usuario
            WHERE i.torneo_id = ? AND i.codigo_equipo = ?
                AND (i.estatus IN (0,1,2,3) OR i.estatus IN ('pendiente','confirmado','solvente','no_solvente'))
            ORDER BY i.id ASC
            LIMIT " . self::JUGADORES_POR_PAREJA . "
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$torneoId, $codigoEquipo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function guardarAsignacionRonda(int $torneoId, int $ronda, array $mesas): void
    {
        $this->pdo->beginTransaction();
        try {
            $numeroMesa = 1;
            foreach ($mesas as $mesa) {
                $secuencia = 1;
                foreach ($mesa as $jugador) {
                    $idUsuario = (int) ($jugador['id_usuario'] ?? 0);
                    if ($idUsuario <= 0) {
                        continue;
                    }
                    $registrado_por = (class_exists('Auth') && method_exists('Auth', 'id')) ? ((int) Auth::id() ?: 1) : 1;
                    $sql = "INSERT INTO partiresul
                            (id_torneo, id_usuario, partida, mesa, secuencia, fecha_partida, registrado, registrado_por)
                            VALUES (?, ?, ?, ?, ?, NOW(), 0, ?)
                            ON DUPLICATE KEY UPDATE mesa = VALUES(mesa), secuencia = VALUES(secuencia)";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$torneoId, $idUsuario, $ronda, $numeroMesa, $secuencia, $registrado_por]);
                    $secuencia++;
                }
                $numeroMesa++;
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function aplicarBye(int $torneoId, int $ronda, array $jugadoresBye): void
    {
        $jugadoresBye = array_slice($jugadoresBye, 0, self::MAX_JUGADORES_BYE);
        if (empty($jugadoresBye)) {
            return;
        }
        $puntosTorneo = 200;
        try {
            $stmt = $this->pdo->prepare("SELECT COALESCE(NULLIF(puntos, 0), 200) AS puntos FROM tournaments WHERE id = ?");
            $stmt->execute([$torneoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false && isset($row['puntos'])) {
                $puntosTorneo = (int) $row['puntos'];
            }
            if ($puntosTorneo <= 0) {
                $puntosTorneo = 200;
            }
        } catch (Exception $e) {
            // mantener default
        }
        $efectividadBye = (int) round($puntosTorneo * 0.5);

        $this->pdo->prepare("DELETE FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = 0")
            ->execute([$torneoId, $ronda]);
        $registrado_por = (class_exists('Auth') && method_exists('Auth', 'id')) ? ((int) Auth::id() ?: 1) : 1;
        $stmt = $this->pdo->prepare("
            INSERT INTO partiresul (id_torneo, id_usuario, partida, mesa, secuencia, fecha_partida, registrado, registrado_por)
            VALUES (?, ?, ?, 0, 1, NOW(), 0, ?)
        ");
        foreach ($jugadoresBye as $jugador) {
            $idUsuario = (int) ($jugador['id_usuario'] ?? 0);
            if ($idUsuario <= 0) {
                continue;
            }
            $stmt->execute([$torneoId, $idUsuario, $ronda, $registrado_por]);
        }
        $this->pdo->prepare("
            UPDATE partiresul
            SET resultado1 = ?, resultado2 = 0, efectividad = ?, registrado = 1
            WHERE id_torneo = ? AND partida = ? AND mesa = 0
        ")->execute([$puntosTorneo, $efectividadBye, $torneoId, $ronda]);
    }

    public function obtenerUltimaRonda(int $torneoId): int
    {
        $stmt = $this->pdo->prepare("SELECT MAX(partida) AS ultima_ronda FROM partiresul WHERE id_torneo = ?");
        $stmt->execute([$torneoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['ultima_ronda'] ?? 0);
    }

    public function obtenerProximaRonda(int $torneoId): int
    {
        return $this->obtenerUltimaRonda($torneoId) + 1;
    }

    public function todasLasMesasCompletas(int $torneoId, int $ronda): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT mesa) AS mesas_incompletas
            FROM partiresul
            WHERE id_torneo = ? AND partida = ? AND mesa > 0 AND (registrado = 0 OR registrado IS NULL)
        ");
        $stmt->execute([$torneoId, $ronda]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['mesas_incompletas'] ?? 0) === 0;
    }

    public function contarMesasIncompletas(int $torneoId, int $ronda): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT mesa) AS mesas_incompletas
            FROM partiresul
            WHERE id_torneo = ? AND partida = ? AND mesa > 0 AND (registrado = 0 OR registrado IS NULL)
        ");
        $stmt->execute([$torneoId, $ronda]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['mesas_incompletas'] ?? 0);
    }

    /**
     * Elimina una ronda: borra partiresul y opcionalmente historial_parejas de esa ronda.
     */
    public function eliminarRonda(int $torneoId, int $ronda): bool
    {
        try {
            $this->pdo->beginTransaction();
            try {
                $stmt = $this->pdo->prepare("DELETE FROM historial_parejas WHERE torneo_id = ? AND ronda_id = ?");
                $stmt->execute([$torneoId, $ronda]);
            } catch (Throwable $e) {
                // Tabla puede no existir
            }
            $stmt = $this->pdo->prepare("DELETE FROM partiresul WHERE id_torneo = ? AND partida = ?");
            $stmt->execute([$torneoId, $ronda]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}
