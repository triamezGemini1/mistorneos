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
    private const TIPO_INTERCLUBES = 1;
    private const TIPO_SUIZO_PURO = 2;
    private const TIPO_SUIZO_SIN_REPETIR = 3;
    private const JUGADORES_POR_PAREJA = 2;
    private const JUGADORES_POR_MESA = 4;
    /** Máximo jugadores en BYE por ronda (hasta 2 parejas = 4 jugadores). */
    private const MAX_JUGADORES_BYE = 4;
    /** Máximo de parejas BYE por ronda. */
    private const MAX_PAREJAS_BYE = 2;

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
        return $this->generarRonda2Plus($torneoId, $numRonda, $estrategia);
    }

    /**
     * Ronda 1: clasificar parejas por numero; emparejar al azar 1-1, 2-2, etc. (sin bloques por club).
     */
    private function generarRonda1(int $torneoId): array
    {
        $representantes = $this->obtenerRepresentantesPareja($torneoId, 'ronda1');
        if (empty($representantes)) {
            return [
                'success' => false,
                'message' => 'No hay parejas inscritas completas en el torneo.',
            ];
        }

        $jugadoresPorCodigo = $this->obtenerMapaJugadoresPorCodigo($torneoId);

        // Agrupar por numero (consecutivo por club)
        $porNumero = [];
        foreach ($representantes as $pareja) {
            $n = (int)($pareja['numero'] ?? 0);
            if ($n <= 0) {
                $n = 999999;
            }
            $porNumero[$n][] = $pareja;
        }
        ksort($porNumero, SORT_NUMERIC);

        $mesasArray = [];
        $mesasRespuesta = [];
        $codigosBye = [];

        foreach ($porNumero as $numero => $lista) {
            // Mezclar al azar (no en bloque por club)
            shuffle($lista);
            $i = 0;
            while ($i + 1 < count($lista)) {
                $parejaA = $lista[$i];
                $parejaB = $lista[$i + 1];
                $codigoA = (string)($parejaA['codigo_equipo'] ?? '');
                $codigoB = (string)($parejaB['codigo_equipo'] ?? '');
                $mesasArray[] = ['codigo_ac' => $codigoA, 'codigo_bd' => $codigoB];
                $mesasRespuesta[] = $this->crearMesaDesdeCodigos($codigoA, $codigoB, $jugadoresPorCodigo);
                $i += 2;
            }
            if ($i < count($lista)) {
                // Pareja sin rival → BYE (ambos jugadores)
                $codigoBye = (string)($lista[$i]['codigo_equipo'] ?? '');
                if ($codigoBye !== '') {
                    $codigosBye[] = $codigoBye;
                }
            }
        }
        $jugadoresBye = $this->expandirByesPorCodigo($codigosBye, $jugadoresPorCodigo);

        $this->guardarAsignacionRondaPorCodigos($torneoId, 1, $mesasArray, $jugadoresPorCodigo);
        if (!empty($jugadoresBye)) {
            $this->aplicarBye($torneoId, 1, $jugadoresBye);
        }

        return [
            'success' => true,
            'message' => 'Ronda 1 generada (parejas por número, emparejamiento al azar).',
            'total_inscritos' => count($representantes) * self::JUGADORES_POR_PAREJA,
            'total_mesas' => count($mesasArray),
            'jugadores_bye' => count($jugadoresBye),
            'mesas' => $mesasRespuesta,
        ];
    }

    /**
     * Rondas 2+: emparejar por rendimiento (puntos de pareja). Siempre mejores en primeros lugares.
     * Tipo interclubes/suizo/suizo_puro se puede leer del torneo más adelante.
     */
    private function generarRonda2Plus(int $torneoId, int $numRonda, string $estrategia = ''): array
    {
        $representantes = $this->obtenerRepresentantesPareja($torneoId, 'ranking');
        if (empty($representantes)) {
            return [
                'success' => false,
                'message' => 'No hay parejas con clasificación para esta ronda.',
            ];
        }
        $jugadoresPorCodigo = $this->obtenerMapaJugadoresPorCodigo($torneoId);

        $tipoEmparejamiento = $this->resolverTipoEmparejamiento($torneoId, $estrategia);
        $historialEnfrentamientos = $this->obtenerHistorialEnfrentamientosParejas($torneoId, $numRonda - 1);

        $evitarRepeticion = ($tipoEmparejamiento === self::TIPO_INTERCLUBES || $tipoEmparejamiento === self::TIPO_SUIZO_SIN_REPETIR);
        $preferirOtroClub = ($tipoEmparejamiento === self::TIPO_INTERCLUBES);

        $parejasEmparejadas = $this->emparejarParejasPorRanking(
            $representantes,
            $historialEnfrentamientos,
            $evitarRepeticion,
            $preferirOtroClub
        );

        $mesasArray = [];
        $mesasRespuesta = [];
        foreach ($parejasEmparejadas['matches'] as $match) {
            $codigoA = (string)($match['a']['codigo_equipo'] ?? '');
            $codigoB = (string)($match['b']['codigo_equipo'] ?? '');
            $mesasArray[] = ['codigo_ac' => $codigoA, 'codigo_bd' => $codigoB];
            $mesasRespuesta[] = $this->crearMesaDesdeCodigos($codigoA, $codigoB, $jugadoresPorCodigo);
        }

        $codigosBye = [];
        foreach ($parejasEmparejadas['byes'] as $parejaBye) {
            $codigoBye = (string)($parejaBye['codigo_equipo'] ?? '');
            if ($codigoBye !== '') {
                $codigosBye[] = $codigoBye;
            }
        }
        $jugadoresBye = $this->expandirByesPorCodigo($codigosBye, $jugadoresPorCodigo);

        $this->guardarAsignacionRondaPorCodigos($torneoId, $numRonda, $mesasArray, $jugadoresPorCodigo);
        if (!empty($jugadoresBye)) {
            $this->aplicarBye($torneoId, $numRonda, $jugadoresBye);
        }

        $modoTxt = 'suizo por rendimiento';
        if ($tipoEmparejamiento === self::TIPO_INTERCLUBES) {
            $modoTxt = 'interclubes';
        } elseif ($tipoEmparejamiento === self::TIPO_SUIZO_PURO) {
            $modoTxt = 'suizo puro';
        } elseif ($tipoEmparejamiento === self::TIPO_SUIZO_SIN_REPETIR) {
            $modoTxt = 'suizo sin repetir';
        }

        return [
            'success' => true,
            'message' => "Ronda {$numRonda} generada ({$modoTxt}).",
            'total_mesas' => count($mesasArray),
            'jugadores_bye' => count($jugadoresBye),
            'mesas' => $mesasRespuesta,
        ];
    }

    /**
     * Define el tipo de emparejamiento para rondas 2+.
     * Prioridad: parámetro de estrategia -> tournaments.tipo_torneo -> default suizo sin repetir.
     */
    private function resolverTipoEmparejamiento(int $torneoId, string $estrategia): int
    {
        $estrategia = strtolower(trim($estrategia));
        if ($estrategia === 'interclubes') {
            return self::TIPO_INTERCLUBES;
        }
        if ($estrategia === 'suizo_puro') {
            return self::TIPO_SUIZO_PURO;
        }
        if ($estrategia === 'suizo_sin_repetir' || $estrategia === 'suizo') {
            return self::TIPO_SUIZO_SIN_REPETIR;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT COALESCE(tipo_torneo, 0) AS tipo_torneo FROM tournaments WHERE id = ? LIMIT 1");
            $stmt->execute([$torneoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $tipo = (int)($row['tipo_torneo'] ?? 0);
            if (in_array($tipo, [self::TIPO_INTERCLUBES, self::TIPO_SUIZO_PURO, self::TIPO_SUIZO_SIN_REPETIR], true)) {
                return $tipo;
            }
        } catch (Throwable $e) {
            // Compatibilidad: si no existe la columna, usar default.
        }

        return self::TIPO_SUIZO_SIN_REPETIR;
    }

    /**
     * Historial de enfrentamientos entre parejas por codigo_equipo usando partiresul + inscritos.
     * No crea tablas nuevas y reutiliza la data existente de rondas anteriores.
     *
     * @return array<string,bool> mapa de llave ordenada "codigoA|codigoB" => true
     */
    private function obtenerHistorialEnfrentamientosParejas(int $torneoId, int $hastaRonda): array
    {
        if ($hastaRonda <= 0) {
            return [];
        }

        $sql = "
            SELECT DISTINCT
                ia.codigo_equipo AS codigo_a,
                ib.codigo_equipo AS codigo_b
            FROM partiresul p1
            INNER JOIN partiresul p2
                ON p2.id_torneo = p1.id_torneo
                AND p2.partida = p1.partida
                AND p2.mesa = p1.mesa
                AND p2.secuencia = CASE
                    WHEN p1.secuencia IN (1,2) THEN 3
                    ELSE 1
                END
            INNER JOIN inscritos ia
                ON ia.torneo_id = p1.id_torneo AND ia.id_usuario = p1.id_usuario
            INNER JOIN inscritos ib
                ON ib.torneo_id = p2.id_torneo AND ib.id_usuario = p2.id_usuario
            WHERE p1.id_torneo = ?
              AND p1.partida <= ?
              AND p1.mesa > 0
              AND p1.secuencia IN (1,3)
              AND ia.codigo_equipo IS NOT NULL AND ia.codigo_equipo != ''
              AND ib.codigo_equipo IS NOT NULL AND ib.codigo_equipo != ''
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$torneoId, $hastaRonda]);

        $historial = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $a = (string)($row['codigo_a'] ?? '');
            $b = (string)($row['codigo_b'] ?? '');
            if ($a === '' || $b === '' || $a === $b) {
                continue;
            }
            $historial[$this->llaveMatchParejas($a, $b)] = true;
        }
        return $historial;
    }

    /**
     * Empareja parejas por ranking con estrategia modular:
     * - Interclubes: salto al siguiente mejor de otro club (prioridad), evitando repetición cuando sea posible.
     * - Suizo sin repetir: evita repetir enfrentamientos usando historial actual.
     * - Suizo puro: estrictamente por ranking (fallback natural).
     *
     * @return array{matches: array<int, array{a: array, b: array}>, byes: array<int, array>}
     */
    private function emparejarParejasPorRanking(
        array $parejasOrdenadas,
        array $historialEnfrentamientos,
        bool $evitarRepeticion,
        bool $preferirOtroClub
    ): array {
        $disponibles = array_values($parejasOrdenadas);
        $matches = [];
        $byes = [];

        while (count($disponibles) >= 2) {
            $parejaBase = array_shift($disponibles);
            $indiceRival = $this->seleccionarRival(
                $parejaBase,
                $disponibles,
                $historialEnfrentamientos,
                $evitarRepeticion,
                $preferirOtroClub
            );
            if ($indiceRival < 0) {
                $indiceRival = 0;
            }

            $rival = $disponibles[$indiceRival];
            array_splice($disponibles, $indiceRival, 1);

            $matches[] = ['a' => $parejaBase, 'b' => $rival];
            $historialEnfrentamientos[$this->llaveMatchParejas(
                (string)$parejaBase['codigo_equipo'],
                (string)$rival['codigo_equipo']
            )] = true;
        }

        if (!empty($disponibles)) {
            $byes[] = $disponibles[0];
        }

        return ['matches' => $matches, 'byes' => $byes];
    }

    /**
     * Devuelve una sola fila (representante) por codigo_equipo.
     * No se usa id_usuario como entidad de decisión; solo codigo_equipo.
     */
    private function obtenerRepresentantesPareja(int $torneoId, string $modo = 'ranking'): array
    {
        $orden = "ORDER BY puntos_equipo DESC, ganados_equipo DESC, efectividad_equipo DESC, t.codigo_equipo ASC";
        if ($modo === 'ronda1') {
            $orden = "ORDER BY t.numero ASC, t.id_club ASC, t.codigo_equipo ASC";
        }
        $sql = "
            SELECT t.*
            FROM (
                SELECT
                    i.codigo_equipo,
                    MIN(i.id_usuario) AS id_usuario_representante,
                    COALESCE(MAX(i.numero), 999999) AS numero,
                    COALESCE(MAX(i.id_club), 0) AS id_club,
                    COALESCE(MAX(e.nombre_equipo), MAX(i.codigo_equipo)) AS nombre_equipo,
                    COALESCE(SUM(i.puntos), 0) AS puntos_equipo,
                    COALESCE(SUM(i.ganados), 0) AS ganados_equipo,
                    COALESCE(AVG(i.efectividad), 0) AS efectividad_equipo,
                    COUNT(*) AS jugadores_activos
                FROM inscritos i
                LEFT JOIN equipos e ON e.id_torneo = i.torneo_id AND e.codigo_equipo = i.codigo_equipo
                WHERE i.torneo_id = ?
                  AND i.codigo_equipo IS NOT NULL AND i.codigo_equipo != ''
                  AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('i') . "
                GROUP BY i.codigo_equipo
                HAVING jugadores_activos = 2
            ) t
            {$orden}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$torneoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Construye mapa codigo_equipo => [jugador1, jugador2] (exactamente 2 por pareja).
     */
    private function obtenerMapaJugadoresPorCodigo(int $torneoId): array
    {
        $sql = "
            SELECT i.id AS id_inscrito, i.id_usuario, i.codigo_equipo, u.nombre,
                   i.puntos, i.ganados, i.perdidos, i.efectividad, i.posicion
            FROM inscritos i
            INNER JOIN usuarios u ON u.id = i.id_usuario
            WHERE i.torneo_id = ?
              AND i.codigo_equipo IS NOT NULL AND i.codigo_equipo != ''
              AND " . InscritosHelper::sqlWhereSoloConfirmadoConAlias('i') . "
            ORDER BY i.codigo_equipo ASC, i.id ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$torneoId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $codigo = (string)($row['codigo_equipo'] ?? '');
            if ($codigo === '') {
                continue;
            }
            $map[$codigo][] = $row;
        }
        // Normalizar: solo parejas completas
        foreach ($map as $codigo => $jugadores) {
            if (count($jugadores) < self::JUGADORES_POR_PAREJA) {
                unset($map[$codigo]);
                continue;
            }
            $map[$codigo] = array_values(array_slice($jugadores, 0, self::JUGADORES_POR_PAREJA));
        }
        return $map;
    }

    /**
     * Expande una mesa usando códigos de pareja (base entidad) a atletas concretos.
     */
    private function crearMesaDesdeCodigos(string $codigoA, string $codigoB, array $jugadoresPorCodigo): array
    {
        $parejaA = $this->obtenerJugadoresDeCodigo($codigoA, $jugadoresPorCodigo);
        $parejaB = $this->obtenerJugadoresDeCodigo($codigoB, $jugadoresPorCodigo);
        // Estructura fija de mesa: primera pareja = A-C, segunda pareja = B-D.
        return [$parejaA[0], $parejaA[1], $parejaB[0], $parejaB[1]];
    }

    /**
     * Devuelve los 2 atletas de la pareja por codigo_equipo.
     */
    private function obtenerJugadoresDeCodigo(string $codigoEquipo, array $jugadoresPorCodigo): array
    {
        $codigo = trim($codigoEquipo);
        $jugadores = $jugadoresPorCodigo[$codigo] ?? [];
        if (count($jugadores) !== self::JUGADORES_POR_PAREJA) {
            throw new RuntimeException("La pareja {$codigo} no tiene 2 atletas activos para asignación.");
        }
        return $jugadores;
    }

    /**
     * Convierte BYE por codigo_equipo en lista de jugadores manteniendo unidad de pareja.
     */
    private function expandirByesPorCodigo(array $codigosBye, array $jugadoresPorCodigo): array
    {
        $codigosBye = array_values(array_unique(array_map('strval', $codigosBye)));
        $codigosBye = array_slice($codigosBye, 0, self::MAX_PAREJAS_BYE);

        $out = [];
        foreach ($codigosBye as $codigo) {
            foreach ($this->obtenerJugadoresDeCodigo($codigo, $jugadoresPorCodigo) as $jug) {
                $out[] = $jug;
            }
        }
        // Respaldo de seguridad por límite legado.
        return array_slice($out, 0, self::MAX_JUGADORES_BYE);
    }

    /**
     * Guarda asignación por unidad pareja (codigo_equipo): cada mesa define AC y BD por código,
     * y luego actualiza ambos atletas de cada código con la misma mesa y secuencias 1-2 / 3-4.
     */
    private function guardarAsignacionRondaPorCodigos(int $torneoId, int $ronda, array $mesas, array $jugadoresPorCodigo): void
    {
        $this->pdo->beginTransaction();
        try {
            $registrado_por = (class_exists('Auth') && method_exists('Auth', 'id')) ? ((int) Auth::id() ?: 1) : 1;
            $sql = "INSERT INTO partiresul
                    (id_torneo, id_usuario, partida, mesa, secuencia, fecha_partida, registrado, registrado_por)
                    VALUES (?, ?, ?, ?, ?, NOW(), 0, ?)
                    ON DUPLICATE KEY UPDATE mesa = VALUES(mesa), secuencia = VALUES(secuencia)";
            $stmt = $this->pdo->prepare($sql);

            $numeroMesa = 1;
            foreach ($mesas as $mesaCodigos) {
                if (!is_array($mesaCodigos) || count($mesaCodigos) < 2) {
                    throw new RuntimeException('Estructura de mesa inválida para asignación por pareja.');
                }
                $codigoAC = (string)($mesaCodigos['codigo_ac'] ?? $mesaCodigos[0] ?? '');
                $codigoBD = (string)($mesaCodigos['codigo_bd'] ?? $mesaCodigos[1] ?? '');
                $jugAC = $this->obtenerJugadoresDeCodigo($codigoAC, $jugadoresPorCodigo);
                $jugBD = $this->obtenerJugadoresDeCodigo($codigoBD, $jugadoresPorCodigo);

                // AC
                $stmt->execute([$torneoId, (int)$jugAC[0]['id_usuario'], $ronda, $numeroMesa, 1, $registrado_por]);
                $stmt->execute([$torneoId, (int)$jugAC[1]['id_usuario'], $ronda, $numeroMesa, 2, $registrado_por]);
                // BD
                $stmt->execute([$torneoId, (int)$jugBD[0]['id_usuario'], $ronda, $numeroMesa, 3, $registrado_por]);
                $stmt->execute([$torneoId, (int)$jugBD[1]['id_usuario'], $ronda, $numeroMesa, 4, $registrado_por]);

                $numeroMesa++;
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function seleccionarRival(
        array $parejaBase,
        array $candidatas,
        array $historialEnfrentamientos,
        bool $evitarRepeticion,
        bool $preferirOtroClub
    ): int {
        $clubBase = (int)($parejaBase['id_club'] ?? 0);
        $codigoBase = (string)($parejaBase['codigo_equipo'] ?? '');

        $reglas = [];
        if ($preferirOtroClub && $evitarRepeticion) {
            $reglas = [
                ['otro_club' => true,  'sin_repetir' => true],
                ['otro_club' => true,  'sin_repetir' => false],
                ['otro_club' => false, 'sin_repetir' => true],
                ['otro_club' => false, 'sin_repetir' => false],
            ];
        } elseif ($preferirOtroClub) {
            $reglas = [
                ['otro_club' => true,  'sin_repetir' => false],
                ['otro_club' => false, 'sin_repetir' => false],
            ];
        } elseif ($evitarRepeticion) {
            $reglas = [
                ['otro_club' => false, 'sin_repetir' => true],
                ['otro_club' => false, 'sin_repetir' => false],
            ];
        } else {
            $reglas = [['otro_club' => false, 'sin_repetir' => false]];
        }

        foreach ($reglas as $regla) {
            foreach ($candidatas as $idx => $cand) {
                $clubCand = (int)($cand['id_club'] ?? 0);
                $codigoCand = (string)($cand['codigo_equipo'] ?? '');

                if ($regla['otro_club'] && $clubBase > 0 && $clubCand > 0 && $clubBase === $clubCand) {
                    continue;
                }

                if ($regla['sin_repetir']) {
                    if (isset($historialEnfrentamientos[$this->llaveMatchParejas($codigoBase, $codigoCand)])) {
                        continue;
                    }
                }
                return $idx;
            }
        }

        return -1;
    }

    private function llaveMatchParejas(string $codigoA, string $codigoB): string
    {
        $a = trim($codigoA);
        $b = trim($codigoB);
        if ($a <= $b) {
            return $a . '|' . $b;
        }
        return $b . '|' . $a;
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
