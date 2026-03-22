<?php

declare(strict_types=1);

/**
 * OrganizacionesData - Estadísticas de entidades y organizaciones para el dashboard admin_general.
 * Separa la lógica SQL de las vistas. Usado por el home simplificado (solo tarjetas).
 */
class OrganizacionesData
{
    /**
     * Estadísticas globales para el dashboard admin_general (solo tarjetas).
     * Retorna: total_entidades, total_organizaciones, total_usuarios, total_admin_clubs,
     *          total_clubs, total_afiliados, total_hombres, total_mujeres,
     *          total_admin_torneo, total_operadores.
     *
     * @return array<string, int>
     */
    public static function loadStatsGlobales(): array
    {
        $stats = [
            'total_entidades' => 0,
            'total_organizaciones' => 0,
            'total_users' => 0,
            'total_admin_clubs' => 0,
            'total_clubs' => 0,
            'total_afiliados' => 0,
            'total_hombres' => 0,
            'total_mujeres' => 0,
            'total_admin_torneo' => 0,
            'total_operadores' => 0,
        ];

        try {
            $pdo = DB::pdo();

            $stmt = $pdo->query("
                SELECT COUNT(DISTINCT entidad) FROM organizaciones
                WHERE entidad IS NOT NULL AND entidad != 0
            ");
            $stats['total_entidades'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM organizaciones WHERE estatus = 1");
            $stats['total_organizaciones'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
            $stats['total_users'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE role = 'admin_club' AND status = 0");
            $stats['total_admin_clubs'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM clubes WHERE estatus = 1");
            $stats['total_clubs'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE role = 'usuario' AND status = 0");
            $stats['total_afiliados'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("
                SELECT COUNT(*) FROM usuarios
                WHERE role = 'usuario' AND status = 0 AND (sexo = 'M' OR UPPER(sexo) = 'M')
            ");
            $stats['total_hombres'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("
                SELECT COUNT(*) FROM usuarios
                WHERE role = 'usuario' AND status = 0 AND (sexo = 'F' OR UPPER(sexo) = 'F')
            ");
            $stats['total_mujeres'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE role = 'admin_torneo' AND status = 0");
            $stats['total_admin_torneo'] = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE role = 'operador' AND status = 0");
            $stats['total_operadores'] = (int)$stmt->fetchColumn();

            require_once __DIR__ . '/StatisticsHelper.php';
            $helperStats = StatisticsHelper::generateStatistics();
            if (!isset($helperStats['error'])) {
                $stats['total_admin_clubs'] = (int)($helperStats['total_admin_clubs'] ?? $stats['total_admin_clubs']);
                $stats['total_admin_torneo'] = (int)($helperStats['total_admin_torneo'] ?? $stats['total_admin_torneo']);
                $stats['total_operadores'] = (int)($helperStats['total_operadores'] ?? $stats['total_operadores']);
                $stats['total_clubs'] = (int)($helperStats['total_clubs'] ?? $stats['total_clubs']);
            }
        } catch (Exception $e) {
            error_log("OrganizacionesData loadStatsGlobales: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Mapa id/codigo => nombre de entidades (para selects y listados).
     *
     * @return array<int|string, string>
     */
    public static function loadEntidadMap(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $map = [];
        try {
            $stmt = DB::pdo()->query("SELECT id AS codigo, nombre FROM entidad ORDER BY nombre ASC");
            $map = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            try {
                $stmt = DB::pdo()->query("SELECT codigo, nombre FROM entidad ORDER BY nombre ASC");
                $map = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e2) {
                error_log("OrganizacionesData loadEntidadMap: " . $e->getMessage());
            }
        }
        $cached = $map;
        return $map;
    }

    /**
     * Listado de entidades con resumen (organizaciones, clubes, afiliados, torneos).
     * Para página Entidades > index.
     *
     * @return list<array{entidad_id: int|string, entidad_nombre: string, total_organizaciones: int, total_clubes: int, total_afiliados: int, total_torneos: int}>
     */
    public static function loadResumenEntidades(): array
    {
        $resumen = [];
        try {
            $pdo = DB::pdo();
        } catch (Exception $e) {
            return [];
        }
        try {
            $stmt = $pdo->query("SELECT DISTINCT entidad FROM organizaciones WHERE entidad IS NOT NULL AND entidad != 0 ORDER BY entidad ASC");
            $entidad_codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
        $entidad_nombres = [];
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM entidad")->fetchAll(PDO::FETCH_ASSOC);
            $codeCol = $nameCol = null;
            foreach ($cols as $c) {
                $f = strtolower($c['Field'] ?? '');
                if (!$codeCol && in_array($f, ['codigo', 'cod_entidad', 'id', 'code'], true)) {
                    $codeCol = $f;
                }
                if (!$nameCol && in_array($f, ['nombre', 'descripcion', 'entidad', 'nombre_entidad'], true)) {
                    $nameCol = $f;
                }
            }
            if ($codeCol && $nameCol && $entidad_codes) {
                $placeholders = implode(',', array_fill(0, count($entidad_codes), '?'));
                $stmt = $pdo->prepare("SELECT {$codeCol} AS cod, {$nameCol} AS nombre FROM entidad WHERE {$codeCol} IN ($placeholders)");
                $stmt->execute($entidad_codes);
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $entidad_nombres[$r['cod']] = $r['nombre'];
                }
            }
        } catch (Exception $e) {
        }
        foreach ($entidad_codes as $cod) {
            $nombre = $entidad_nombres[$cod] ?? ('Entidad ' . $cod);
            $stmt = $pdo->prepare("SELECT id FROM organizaciones WHERE entidad = ?");
            $stmt->execute([$cod]);
            $org_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $total_organizaciones = count($org_ids);
            $total_clubes = $total_afiliados = $total_torneos = 0;
            if ($org_ids) {
                $ph = implode(',', array_fill(0, count($org_ids), '?'));
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM clubes WHERE organizacion_id IN ($ph) AND estatus = 1");
                $stmt->execute($org_ids);
                $total_clubes = (int)$stmt->fetchColumn();
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tournaments WHERE club_responsable IN ($ph)");
                $stmt->execute($org_ids);
                $total_torneos = (int)$stmt->fetchColumn();
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM usuarios u
                    INNER JOIN clubes c ON u.club_id = c.id
                    WHERE c.organizacion_id IN ($ph) AND c.estatus = 1 AND u.role = 'usuario' AND u.status = 0
                ");
                $stmt->execute($org_ids);
                $total_afiliados = (int)$stmt->fetchColumn();
            }
            $resumen[] = [
                'entidad_id' => $cod,
                'entidad_nombre' => $nombre,
                'total_organizaciones' => $total_organizaciones,
                'total_clubes' => $total_clubes,
                'total_afiliados' => $total_afiliados,
                'total_torneos' => $total_torneos,
            ];
        }
        return $resumen;
    }
}
