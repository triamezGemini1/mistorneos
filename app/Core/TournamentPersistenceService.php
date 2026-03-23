<?php

declare(strict_types=1);

require_once __DIR__ . '/TournamentEngineService.php';
require_once __DIR__ . '/CargaResultadosService.php';
require_once __DIR__ . '/MesaRepository.php';
require_once dirname(__DIR__, 2) . '/lib/InscritosHelper.php';

/**
 * Punto único de escritura crítica: inscripciones, filas partiresul por ronda y resultados por mesa.
 * Las operaciones abren transacción cuando aplica para mantener consistencia.
 */
final class TournamentPersistenceService
{
    /**
     * Inscripción desde padrón o manual. Valida que el torneo exista y (si aplica) pertenezca a la organización.
     *
     * @param array<string, mixed> $datos Contrato {@see InscritosHelper::registrarInscripcion()}
     * @return int id inscritos
     */
    public static function registrarInscrito(PDO $pdo, array $datos, ?int $organizacionScope = null): int
    {
        $torneoId = (int) ($datos['torneo_id'] ?? 0);
        if ($torneoId <= 0) {
            throw new InvalidArgumentException('torneo_id inválido.');
        }
        $torneo = TournamentEngineService::getTorneo($pdo, $torneoId, $organizacionScope);
        if ($torneo === null) {
            throw new RuntimeException('Torneo no encontrado o no pertenece a su organización.');
        }

        $pdo->beginTransaction();
        try {
            $id = InscritosHelper::registrarInscripcion($pdo, $datos);
            $pdo->commit();

            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Crea registros partiresul (y historial de parejas) para una ronda. Delega en MesaRepository dentro de la misma transacción lógica.
     *
     * @param list<list<array<string, mixed>>> $mesas cada mesa: lista de jugadores con id_usuario
     */
    public static function grabarPartiResul(
        PDO $pdo,
        int $torneoId,
        int $ronda,
        array $mesas,
        ?int $organizacionScope = null,
        ?int $registradoPorUsuarioId = null
    ): void {
        $torneo = TournamentEngineService::getTorneo($pdo, $torneoId, $organizacionScope);
        if ($torneo === null) {
            throw new RuntimeException('Torneo no encontrado o no pertenece a su organización.');
        }

        $repo = new MesaRepository($pdo);
        $repo->guardarAsignacionRonda($torneoId, $ronda, $mesas, $registradoPorUsuarioId);
    }

    /**
     * Entrada maestra para guardar resultados de mesa según tipo de torneo.
     *
     * @param array<string, mixed> $torneo Fila tournaments (id, tipo_torneo, …)
     * @param 'estandar'|'parejas' $modoPayload Debe coincidir con tipo_torneo (parejas solo parejas)
     * @param array<string, mixed> $payload guardar_estandar: lineas[][]; guardar_parejas: pid_a1, puntos_A, …
     */
    public static function grabarResultados(
        PDO $pdo,
        array $torneo,
        int $partida,
        int $mesaId,
        string $modoPayload,
        array $payload,
        int $adminUserId,
        ?int $organizacionScope = null
    ): void {
        $torneoId = (int) ($torneo['id'] ?? 0);
        if ($torneoId <= 0) {
            throw new InvalidArgumentException('Torneo inválido.');
        }

        $torneoDb = TournamentEngineService::getTorneo($pdo, $torneoId, $organizacionScope);
        if ($torneoDb === null) {
            throw new RuntimeException('Torneo no encontrado o no pertenece a su organización.');
        }

        $tipo = strtolower(trim((string) ($torneo['tipo_torneo'] ?? 'individual')));
        if (!in_array($tipo, ['individual', 'parejas', 'equipos'], true)) {
            $tipo = 'individual';
        }

        if ($tipo === 'parejas' && $modoPayload !== 'parejas') {
            throw new InvalidArgumentException('Este torneo es por parejas: use la carga vinculada.');
        }
        if ($tipo !== 'parejas' && $modoPayload !== 'estandar') {
            throw new InvalidArgumentException('Este torneo requiere carga independiente (cuatro registros).');
        }

        $puntosObjetivo = CargaResultadosService::puntosObjetivoMesa();

        if ($modoPayload === 'parejas') {
            self::procesarResultadoParejas($pdo, $torneoId, $partida, $mesaId, $payload, $adminUserId, $puntosObjetivo);
        } else {
            self::procesarResultadoEstandar($pdo, $torneoId, $partida, $mesaId, $payload, $adminUserId, $puntosObjetivo);
        }
    }

    /**
     * @param array<string, mixed> $payload lineas[id][partiresul_id|puntos|sets|chancleta|zapato]
     */
    private static function procesarResultadoEstandar(
        PDO $pdo,
        int $torneoId,
        int $partida,
        int $mesaId,
        array $payload,
        int $adminUserId,
        int $puntosObjetivo
    ): void {
        $lineas = $payload['lineas'] ?? null;
        if (!is_array($lineas)) {
            throw new InvalidArgumentException('Datos de formulario inválidos (lineas).');
        }

        $idsOrden = [];
        $porFila = [];
        $sumP = 0.0;

        foreach ($lineas as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pid = (int) ($row['partiresul_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $p = (float) ($row['puntos'] ?? 0);
            $sumP += $p;
            $idsOrden[] = $pid;
            $porFila[] = [
                'puntos' => $p,
                'sets' => (float) ($row['sets'] ?? 0),
                'chancleta' => (float) ($row['chancleta'] ?? 0),
                'zapato' => (float) ($row['zapato'] ?? 0),
            ];
        }

        if ($idsOrden === []) {
            throw new InvalidArgumentException('No hay líneas válidas para guardar.');
        }

        if ((int) round($sumP) !== $puntosObjetivo) {
            throw new InvalidArgumentException(
                'La suma de puntos debe ser exactamente ' . $puntosObjetivo . ' (reglamento de mesa).'
            );
        }

        self::validarPartiresulMesaYInscritos($pdo, $torneoId, $partida, $mesaId, $idsOrden);

        CargaResultadosService::guardarEstandar($pdo, $torneoId, $idsOrden, $porFila, $adminUserId);
    }

    /**
     * @param array<string, mixed> $payload pid_a1, pid_a2, pid_b1, pid_b2, puntos_A, sets_A, …
     */
    private static function procesarResultadoParejas(
        PDO $pdo,
        int $torneoId,
        int $partida,
        int $mesaId,
        array $payload,
        int $adminUserId,
        int $puntosObjetivo
    ): void {
        $pa1 = (int) ($payload['pid_a1'] ?? 0);
        $pa2 = (int) ($payload['pid_a2'] ?? 0);
        $pb1 = (int) ($payload['pid_b1'] ?? 0);
        $pb2 = (int) ($payload['pid_b2'] ?? 0);
        if ($pa1 <= 0 || $pa2 <= 0 || $pb1 <= 0 || $pb2 <= 0) {
            throw new InvalidArgumentException('Faltan identificadores de partiresul para las parejas.');
        }

        $idsTodas = [$pa1, $pa2, $pb1, $pb2];
        self::validarPartiresulMesaYInscritos($pdo, $torneoId, $partida, $mesaId, $idsTodas);

        $pA = (float) ($payload['puntos_A'] ?? 0);
        $pB = (float) ($payload['puntos_B'] ?? 0);
        if ((int) round($pA + $pB) !== $puntosObjetivo) {
            throw new InvalidArgumentException(
                'Puntos pareja 1 + puntos pareja 2 deben sumar ' . $puntosObjetivo . '.'
            );
        }

        $datos = [
            'puntos_A' => $pA,
            'sets_A' => (float) ($payload['sets_A'] ?? 0),
            'chancleta_A' => (float) ($payload['chancleta_A'] ?? 0),
            'zapato_A' => (float) ($payload['zapato_A'] ?? 0),
            'puntos_B' => $pB,
            'sets_B' => (float) ($payload['sets_B'] ?? 0),
            'chancleta_B' => (float) ($payload['chancleta_B'] ?? 0),
            'zapato_B' => (float) ($payload['zapato_B'] ?? 0),
        ];

        CargaResultadosService::guardarParejas(
            $pdo,
            $torneoId,
            [$pa1, $pa2],
            [$pb1, $pb2],
            $datos,
            $adminUserId
        );
    }

    /**
     * Exige 4 filas activas en la mesa y que cada id partiresul pertenezca a esa mesa/partida/torneo
     * con jugador confirmado en inscritos.
     *
     * @param list<int> $partiresulIds
     */
    private static function validarPartiresulMesaYInscritos(
        PDO $pdo,
        int $torneoId,
        int $partida,
        int $mesaId,
        array $partiresulIds
    ): void {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = ? AND mesa > 0'
        );
        $st->execute([$torneoId, $partida, $mesaId]);
        $nMesa = (int) $st->fetchColumn();
        if ($nMesa !== 4) {
            throw new RuntimeException(
                'La mesa debe tener exactamente 4 jugadores asignados (partiresul) para registrar resultados.'
            );
        }

        $ids = array_values(array_unique(array_map('intval', $partiresulIds)));
        foreach ($ids as $id) {
            if ($id <= 0) {
                throw new InvalidArgumentException('Identificador partiresul inválido.');
            }
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $whereInsc = InscritosHelper::sqlWhereSoloConfirmadoConAlias('i');
        $sql = "SELECT COUNT(*) AS c FROM partiresul p
                INNER JOIN inscritos i ON i.torneo_id = p.id_torneo AND i.id_usuario = p.id_usuario
                WHERE p.id_torneo = ? AND p.partida = ? AND p.mesa = ? AND p.mesa > 0
                AND p.id IN ($placeholders)
                AND ($whereInsc)";
        $params = array_merge([$torneoId, $partida, $mesaId], $ids);
        $q = $pdo->prepare($sql);
        $q->execute($params);
        $ok = (int) $q->fetchColumn();
        if ($ok !== count($ids)) {
            throw new RuntimeException(
                'Los registros no coinciden con la mesa o los jugadores no están confirmados en el torneo.'
            );
        }
    }
}
