<?php

declare(strict_types=1);

/**
 * Datos para el cintillo / breadcrumbs institucionales (entidad → organización → club → torneo).
 */
final class InstitucionalContextService
{
    /**
     * @return array{entidad: array{nombre:string,logo:?string,id:int},organizacion: array{nombre:string,logo:?string,id:int},club: ?array{nombre:string,id:int},torneo: ?array{nombre:string,id:int}}
     */
    public static function forAdmin(?PDO $pdo, ?array $adminSession, ?array $torneoRow = null, ?array $clubRow = null): array
    {
        $out = self::emptyContext();

        if (!is_array($adminSession) || empty($adminSession['id'])) {
            return $out;
        }

        if ($pdo === null) {
            $out['organizacion'] = [
                'id' => (int) ($adminSession['organizacion_id'] ?? 0),
                'nombre' => trim((string) ($adminSession['organizacion_nombre'] ?? '')),
                'logo' => null,
            ];
            $out['entidad'] = [
                'id' => (int) ($adminSession['entidad_id'] ?? 0),
                'nombre' => trim((string) ($adminSession['entidad_nombre'] ?? '')),
                'logo' => null,
            ];
            if (is_array($clubRow) && !empty($clubRow['nombre'])) {
                $out['club'] = [
                    'id' => (int) ($clubRow['id'] ?? 0),
                    'nombre' => (string) $clubRow['nombre'],
                ];
            }
            if (is_array($torneoRow) && !empty($torneoRow['nombre'])) {
                $out['torneo'] = [
                    'id' => (int) ($torneoRow['id'] ?? 0),
                    'nombre' => (string) $torneoRow['nombre'],
                ];
            }

            return $out;
        }

        $orgId = isset($adminSession['organizacion_id']) ? (int) $adminSession['organizacion_id'] : 0;
        if ($orgId > 0) {
            $org = self::cargarOrganizacion($pdo, $orgId);
            if ($org !== null) {
                $out['organizacion'] = [
                    'id' => $orgId,
                    'nombre' => (string) ($org['nombre'] ?? 'Organización'),
                    'logo' => isset($org['logo']) && $org['logo'] !== '' ? (string) $org['logo'] : null,
                ];
                $eid = (int) ($org['entidad_id'] ?? $org['entidad'] ?? 0);
                if ($eid > 0) {
                    $ent = self::cargarEntidad($pdo, $eid);
                    if ($ent !== null) {
                        $out['entidad'] = [
                            'id' => $eid,
                            'nombre' => (string) ($ent['nombre'] ?? 'Entidad'),
                            'logo' => isset($ent['logo']) && $ent['logo'] !== '' ? (string) $ent['logo'] : null,
                        ];
                    }
                }
            }
        }

        if (is_array($clubRow) && !empty($clubRow['nombre'])) {
            $out['club'] = [
                'id' => (int) ($clubRow['id'] ?? 0),
                'nombre' => (string) $clubRow['nombre'],
            ];
        }

        if (is_array($torneoRow) && !empty($torneoRow['nombre'])) {
            $out['torneo'] = [
                'id' => (int) ($torneoRow['id'] ?? 0),
                'nombre' => (string) $torneoRow['nombre'],
            ];
        }

        if ($out['entidad']['nombre'] === '' && !empty($adminSession['entidad_nombre'])) {
            $out['entidad']['nombre'] = (string) $adminSession['entidad_nombre'];
            $out['entidad']['id'] = (int) ($adminSession['entidad_id'] ?? 0);
        }
        if ($out['organizacion']['nombre'] === '' && !empty($adminSession['organizacion_nombre'])) {
            $out['organizacion']['nombre'] = (string) $adminSession['organizacion_nombre'];
            $out['organizacion']['id'] = $orgId;
        }

        return $out;
    }

    /**
     * Panel atleta: intenta cadena club → organización → entidad si el usuario tiene club_id en BD.
     *
     * @param array<string, mixed> $userSession
     */
    public static function forAtleta(PDO $pdo, array $userSession): array
    {
        $out = self::emptyContext();
        $uid = (int) ($userSession['id'] ?? 0);
        if ($uid <= 0) {
            return $out;
        }

        $tabla = getenv('DB_AUTH_TABLE') ?: 'usuarios';
        $tabla = in_array(strtolower(trim((string) $tabla)), ['usuarios', 'users'], true) ? strtolower(trim((string) $tabla)) : 'usuarios';

        try {
            $st = $pdo->prepare("SELECT club_id FROM `{$tabla}` WHERE id = ? LIMIT 1");
            $st->execute([$uid]);
            $clubId = (int) $st->fetchColumn();
        } catch (Throwable $e) {
            return $out;
        }

        if ($clubId <= 0) {
            return $out;
        }

        try {
            $st = $pdo->prepare('SELECT id, nombre, organizacion_id, entidad_id FROM clubes WHERE id = ? LIMIT 1');
            $st->execute([$clubId]);
            $club = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return $out;
        }

        if ($club === false) {
            return $out;
        }

        $out['club'] = [
            'id' => (int) $club['id'],
            'nombre' => (string) ($club['nombre'] ?? 'Club'),
        ];

        $orgId = (int) ($club['organizacion_id'] ?? 0);
        if ($orgId > 0) {
            $org = self::cargarOrganizacion($pdo, $orgId);
            if ($org !== null) {
                $out['organizacion'] = [
                    'id' => $orgId,
                    'nombre' => (string) ($org['nombre'] ?? 'Organización'),
                    'logo' => isset($org['logo']) && $org['logo'] !== '' ? (string) $org['logo'] : null,
                ];
            }
        }

        $eid = (int) ($club['entidad_id'] ?? 0);
        if ($eid <= 0 && $orgId > 0) {
            $org = self::cargarOrganizacion($pdo, $orgId);
            $eid = (int) ($org['entidad_id'] ?? $org['entidad'] ?? 0);
        }
        if ($eid > 0) {
            $ent = self::cargarEntidad($pdo, $eid);
            if ($ent !== null) {
                $out['entidad'] = [
                    'id' => $eid,
                    'nombre' => (string) ($ent['nombre'] ?? 'Entidad'),
                    'logo' => isset($ent['logo']) && $ent['logo'] !== '' ? (string) $ent['logo'] : null,
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyContext(): array
    {
        return [
            'entidad' => ['id' => 0, 'nombre' => '', 'logo' => null],
            'organizacion' => ['id' => 0, 'nombre' => '', 'logo' => null],
            'club' => null,
            'torneo' => null,
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function cargarOrganizacion(PDO $pdo, int $id): ?array
    {
        try {
            $st = $pdo->prepare('SELECT nombre, logo, entidad_id, entidad FROM organizaciones WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r !== false ? $r : null;
        } catch (Throwable $e) {
            try {
                $st = $pdo->prepare('SELECT nombre, logo, entidad FROM organizaciones WHERE id = ? LIMIT 1');
                $st->execute([$id]);
                $r = $st->fetch(PDO::FETCH_ASSOC);

                return $r !== false ? $r : null;
            } catch (Throwable $e2) {
                return null;
            }
        }
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function cargarEntidad(PDO $pdo, int $id): ?array
    {
        try {
            $st = $pdo->prepare('SELECT nombre, logo FROM entidades WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r !== false ? $r : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
