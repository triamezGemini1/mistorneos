<?php

declare(strict_types=1);

/**
 * Helper para torneos de Parejas Fijas (modalidad 4).
 * Inscripción en pares: nombre de equipo, código de equipo (mismo formato que equipos),
 * numero = consecutivo por club (1..N). No se permiten inscripciones incompletas.
 */
class ParejasFijasHelper
{
    public const MODALIDAD_PAREJAS_FIJAS = 4;
    public const JUGADORES_POR_PAREJA = 2;

    /**
     * Formato del código de equipo (igual que equipos): LPAD(club,3,'0')-LPAD(consecutivo,3,'0').
     */
    public static function formatoCodigoEquipo(int $clubId, int $consecutivo): string
    {
        return str_pad((string) $clubId, 3, '0', STR_PAD_LEFT) . '-'
            . str_pad((string) $consecutivo, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene el siguiente consecutivo por club para el torneo (para numero y codigo_equipo).
     */
    public static function obtenerConsecutivoSiguienteClub(PDO $pdo, int $torneoId, int $clubId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(consecutivo_club), 0) + 1 FROM equipos WHERE id_torneo = ? AND id_club = ?'
        );
        $stmt->execute([$torneoId, $clubId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Verifica que la pareja esté completa (exactamente 2 inscritos activos con ese codigo_equipo).
     */
    public static function validarParejaCompleta(PDO $pdo, int $torneoId, string $codigoEquipo): bool
    {
        require_once __DIR__ . '/InscritosHelper.php';
        $whereActivo = InscritosHelper::SQL_WHERE_ACTIVO;
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND codigo_equipo = ? AND {$whereActivo}"
        );
        $stmt->execute([$torneoId, $codigoEquipo]);
        return (int) $stmt->fetchColumn() === self::JUGADORES_POR_PAREJA;
    }

    /**
     * Crea una pareja (equipo de 2): fila en equipos + 2 inscritos con mismo codigo_equipo y numero.
     * No se permiten inscripciones incompletas: deben enviarse exactamente 2 id_usuario.
     *
     * @param array $idUsuarios [id_usuario1, id_usuario2] (2 elementos)
     * @return array ['success' => bool, 'id_equipo' => int|null, 'codigo_equipo' => string|null, 'numero' => int|null, 'message' => string]
     */
    /**
     * @param string|null $nombreEquipo Nombre de la pareja (opcional). Si null o vacío se asigna "Pareja {codigo}".
     */
    public static function crearPareja(
        PDO $pdo,
        int $torneoId,
        int $clubId,
        $nombreEquipo,
        array $idUsuarios,
        ?int $creadoPor = null
    ): array {
        if (count($idUsuarios) !== self::JUGADORES_POR_PAREJA) {
            return [
                'success' => false,
                'id_equipo' => null,
                'codigo_equipo' => null,
                'numero' => null,
                'message' => 'Debe indicar exactamente 2 jugadores por pareja. No se permiten inscripciones incompletas.',
            ];
        }

        $id1 = (int) $idUsuarios[0];
        $id2 = (int) $idUsuarios[1];
        if ($id1 <= 0 || $id2 <= 0 || $id1 === $id2) {
            return [
                'success' => false,
                'id_equipo' => null,
                'codigo_equipo' => null,
                'numero' => null,
                'message' => 'Los dos jugadores deben ser distintos y válidos.',
            ];
        }

        $stmt = $pdo->prepare('SELECT modalidad FROM tournaments WHERE id = ?');
        $stmt->execute([$torneoId]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$torneo || (int) $torneo['modalidad'] !== self::MODALIDAD_PAREJAS_FIJAS) {
            return [
                'success' => false,
                'id_equipo' => null,
                'codigo_equipo' => null,
                'numero' => null,
                'message' => 'El torneo no es de parejas fijas.',
            ];
        }

        $stmt = $pdo->prepare(
            'SELECT id FROM inscritos WHERE torneo_id = ? AND id_usuario IN (?, ?) AND estatus != 4'
        );
        $stmt->execute([$torneoId, $id1, $id2]);
        if ($stmt->rowCount() > 0) {
            return [
                'success' => false,
                'id_equipo' => null,
                'codigo_equipo' => null,
                'numero' => null,
                'message' => 'Uno o ambos jugadores ya están inscritos en este torneo.',
            ];
        }

        $nombreEquipoTrim = trim((string) $nombreEquipo);
        // Solo comprobar duplicado por nombre si el usuario indicó un nombre
        if ($nombreEquipoTrim !== '') {
            $stmt = $pdo->prepare(
                'SELECT id FROM equipos WHERE id_torneo = ? AND id_club = ? AND UPPER(nombre_equipo) = UPPER(?)'
            );
            $stmt->execute([$torneoId, $clubId, $nombreEquipoTrim]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'id_equipo' => null,
                    'codigo_equipo' => null,
                    'numero' => null,
                    'message' => 'Ya existe una pareja con ese nombre en este club.',
                ];
            }
        }

        try {
            $pdo->beginTransaction();

            // Si no se indica nombre, usamos un placeholder único; tras el INSERT el trigger asigna codigo_equipo y lo usamos para actualizar el nombre
            $nombreParaInsertar = $nombreEquipoTrim !== '' ? $nombreEquipoTrim : ('Pareja_' . $torneoId . '_' . $clubId . '_' . time());
            $stmt = $pdo->prepare(
                'INSERT INTO equipos (id_torneo, id_club, nombre_equipo, creado_por) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$torneoId, $clubId, $nombreParaInsertar, $creadoPor]);
            $idEquipo = (int) $pdo->lastInsertId() ?: 0;
            if ($idEquipo <= 0) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'id_equipo' => null,
                    'codigo_equipo' => null,
                    'numero' => null,
                    'message' => 'Error al crear el registro del equipo.',
                ];
            }

            $stmt = $pdo->prepare('SELECT consecutivo_club, codigo_equipo FROM equipos WHERE id = ?');
            $stmt->execute([$idEquipo]);
            $rowEq = $stmt->fetch(PDO::FETCH_ASSOC);
            $consecutivo = (int) ($rowEq['consecutivo_club'] ?? 0);
            $codigoEquipo = (string) ($rowEq['codigo_equipo'] ?? '');
            if ($codigoEquipo === '') {
                $consecutivo = self::obtenerConsecutivoSiguienteClub($pdo, $torneoId, $clubId);
                $codigoEquipo = self::formatoCodigoEquipo($clubId, $consecutivo);
                $pdo->prepare('UPDATE equipos SET consecutivo_club = ?, codigo_equipo = ? WHERE id = ?')->execute([$consecutivo, $codigoEquipo, $idEquipo]);
            }
            if ($nombreEquipoTrim === '') {
                $pdo->prepare('UPDATE equipos SET nombre_equipo = ? WHERE id = ?')->execute(['Pareja ' . $codigoEquipo, $idEquipo]);
            }

            require_once __DIR__ . '/InscritosHelper.php';
            $usuarios = [$id1, $id2];
            foreach ($usuarios as $idUsuario) {
                $stmtU = $pdo->prepare('SELECT nacionalidad, cedula FROM usuarios WHERE id = ?');
                $stmtU->execute([$idUsuario]);
                $u = $stmtU->fetch(PDO::FETCH_ASSOC);
                $nacionalidad = in_array($u['nacionalidad'] ?? 'V', ['V', 'E', 'J', 'P'], true) ? ($u['nacionalidad'] ?? 'V') : 'V';
                $cedula = preg_replace('/\D/', '', (string) ($u['cedula'] ?? ''));
                InscritosHelper::insertarInscrito($pdo, [
                    'id_usuario' => $idUsuario,
                    'torneo_id' => $torneoId,
                    'id_club' => $clubId,
                    'codigo_equipo' => $codigoEquipo,
                    'numero' => $consecutivo,
                    'estatus' => 1,
                    'inscrito_por' => $creadoPor,
                    'nacionalidad' => $nacionalidad,
                    'cedula' => $cedula,
                ]);
            }

            $pdo->commit();
            return [
                'success' => true,
                'id_equipo' => $idEquipo,
                'codigo_equipo' => $codigoEquipo,
                'numero' => $consecutivo,
                'message' => 'Pareja inscrita correctamente.',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'id_equipo' => null,
                'codigo_equipo' => null,
                'numero' => null,
                'message' => 'Error al crear la pareja: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Lista parejas del torneo (cada elemento: codigo_equipo, nombre_equipo, id_club, numero, jugadores[]).
     */
    public static function listarParejas(PDO $pdo, int $torneoId): array
    {
        $stmt = $pdo->prepare(
            'SELECT e.id, e.codigo_equipo, e.nombre_equipo, e.id_club, e.consecutivo_club AS numero
             FROM equipos e
             WHERE e.id_torneo = ? AND e.estatus = 0
             ORDER BY e.id_club ASC, e.consecutivo_club ASC'
        );
        $stmt->execute([$torneoId]);
        $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $parejas = [];
        foreach ($equipos as $eq) {
            $stmtJ = $pdo->prepare(
                'SELECT i.id_usuario, u.nombre FROM inscritos i INNER JOIN usuarios u ON u.id = i.id_usuario
                 WHERE i.torneo_id = ? AND i.codigo_equipo = ? AND i.estatus != 4 ORDER BY i.id'
            );
            $stmtJ->execute([$torneoId, $eq['codigo_equipo']]);
            $jugadores = $stmtJ->fetchAll(PDO::FETCH_ASSOC);
            if (count($jugadores) === self::JUGADORES_POR_PAREJA) {
                $parejas[] = [
                    'id_equipo' => (int) $eq['id'],
                    'codigo_equipo' => $eq['codigo_equipo'],
                    'nombre_equipo' => $eq['nombre_equipo'],
                    'id_club' => (int) $eq['id_club'],
                    'numero' => (int) $eq['numero'],
                    'jugadores' => $jugadores,
                ];
            }
        }
        return $parejas;
    }
}
