<?php

declare(strict_types=1);

/**
 * LandingDataService - Servicio único para datos de torneos en el landing público.
 * Centraliza queries: club_responsable = ID de tabla organizaciones (no clubes).
 * Datos de contacto (nombre, responsable, telefono, email) provienen de organizaciones.
 */
class LandingDataService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Verifica si existe la tabla club_photos.
     */
    private function hasClubPhotos(): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'club_photos'");
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verifica si existe la columna publicar_landing en tournaments.
     */
    private function hasPublicarLanding(): bool
    {
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM tournaments LIKE 'publicar_landing'")->fetchAll();
            return !empty($cols);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene los próximos eventos (fechator >= CURDATE(), estatus = 1).
     * Usa JOIN con organizaciones (club_responsable = org.id).
     *
     * @param int $limit
     * @return list<array>
     */
    public function getProximosEventos(int $limit = 500): array
    {
        $where_publicar = $this->hasPublicarLanding()
            ? ' AND (t.publicar_landing = 1 OR t.publicar_landing IS NULL)'
            : '';

        $sql = "
            SELECT
                t.*,
                o.nombre as organizacion_nombre,
                o.logo as organizacion_logo,
                o.responsable as club_delegado,
                o.telefono as club_telefono,
                o.email as organizacion_email,
                u.nombre as admin_nombre,
                u.username as admin_username,
                u.celular as admin_celular,
                COALESCE(o.entidad, t.entidad, 0) as entidad_torneo,
                (SELECT COUNT(*) FROM inscritos WHERE torneo_id = t.id AND (estatus IS NULL OR estatus != 'retirado')) as total_inscritos
            FROM tournaments t
            LEFT JOIN organizaciones o ON t.club_responsable = o.id AND o.estatus = 1
            LEFT JOIN usuarios u ON o.admin_user_id = u.id AND u.role = 'admin_club'
            WHERE t.estatus = 1 AND t.fechator >= CURDATE()
            {$where_publicar}
            ORDER BY t.fechator ASC
            LIMIT " . max(1, min($limit, 500));
        try {
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('LandingDataService getProximosEventos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene eventos realizados (fechator < CURDATE(), estatus = 1, publicar_landing = 1).
     * JOIN exclusivo con organizaciones (club_responsable = o.id) para nombre, logo y contacto.
     * No usa clubes: los datos del responsable provienen de la tabla organizaciones.
     *
     * @param int $limit
     * @param int|null $anio Filtro por año (opcional)
     * @param int|null $tipo_evento Filtro por es_evento_masivo: 0,1,2,3,4 (opcional)
     * @return list<array>
     */
    public function getEventosRealizados(int $limit = 50, ?int $anio = null, ?int $tipo_evento = null): array
    {
        $where_publicar = $this->hasPublicarLanding()
            ? ' AND (t.publicar_landing = 1 OR t.publicar_landing IS NULL)'
            : '';
        $where_anio = $anio !== null ? ' AND YEAR(t.fechator) = ' . (int)$anio : '';
        $where_tipo = $tipo_evento !== null ? ' AND COALESCE(t.es_evento_masivo, 0) = ' . (int)$tipo_evento : '';

        $subquery_fotos = $this->hasClubPhotos()
            ? ", (SELECT cp.ruta_imagen FROM club_photos cp WHERE cp.torneo_id = t.id ORDER BY cp.orden ASC, cp.fecha_subida ASC LIMIT 1) as primera_foto, (SELECT COUNT(*) FROM club_photos WHERE torneo_id = t.id) as total_fotos"
            : ", NULL as primera_foto, 0 as total_fotos";

        $sql = "
            SELECT
                t.*,
                o.nombre as organizacion_nombre,
                o.logo as organizacion_logo,
                o.responsable as organizacion_responsable,
                o.responsable as club_delegado,
                o.telefono as club_telefono,
                o.email as organizacion_email,
                u.nombre as admin_nombre,
                u.username as admin_username,
                u.celular as admin_celular,
                (SELECT COUNT(*) FROM inscritos WHERE torneo_id = t.id AND (estatus IS NULL OR estatus = 'confirmado')) as total_inscritos
                {$subquery_fotos}
            FROM tournaments t
            LEFT JOIN organizaciones o ON t.club_responsable = o.id AND o.estatus = 1
            LEFT JOIN usuarios u ON o.admin_user_id = u.id AND u.role = 'admin_club'
            WHERE t.estatus = 1 AND t.fechator < CURDATE()
            {$where_publicar}{$where_anio}{$where_tipo}
            ORDER BY t.fechator DESC
            LIMIT " . max(1, min($limit, 200));
        try {
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$ev) {
                $ev['total_fotos'] = (int)($ev['total_fotos'] ?? 0);
                $ev['primera_foto'] = isset($ev['primera_foto']) ? trim((string)$ev['primera_foto']) : null;
                if ($ev['primera_foto'] === '') {
                    $ev['primera_foto'] = null;
                }
            }
            unset($ev);
            return $rows;
        } catch (Exception $e) {
            error_log('LandingDataService getEventosRealizados: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene próximos eventos filtrados por entidad (organizaciones con esa entidad).
     *
     * @param int $entidad_id
     * @param int $limit
     * @return list<array>
     */
    public function getProximosEventosPorEntidad(int $entidad_id, int $limit = 12): array
    {
        if ($entidad_id <= 0) {
            return [];
        }

        $where_publicar = $this->hasPublicarLanding()
            ? ' AND (t.publicar_landing = 1 OR t.publicar_landing IS NULL)'
            : '';

        $sql = "
            SELECT
                t.*,
                o.nombre as organizacion_nombre,
                o.logo as organizacion_logo,
                o.responsable as club_delegado,
                o.telefono as club_telefono,
                o.email as organizacion_email,
                u.nombre as admin_nombre,
                u.username as admin_username,
                u.celular as admin_celular,
                COALESCE(o.entidad, t.entidad, 0) as entidad_torneo,
                (SELECT COUNT(*) FROM inscritos WHERE torneo_id = t.id AND (estatus IS NULL OR estatus != 'retirado')) as total_inscritos,
                e.nombre as entidad_nombre
            FROM tournaments t
            LEFT JOIN organizaciones o ON t.club_responsable = o.id AND o.estatus = 1
            LEFT JOIN usuarios u ON o.admin_user_id = u.id AND u.role = 'admin_club'
            LEFT JOIN entidad e ON COALESCE(o.entidad, t.entidad) = e.id
            WHERE t.estatus = 1 AND t.fechator >= CURDATE()
              AND (o.entidad = ? OR t.entidad = ?)
              {$where_publicar}
            ORDER BY t.fechator ASC
            LIMIT " . max(1, min($limit, 100));
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$entidad_id, $entidad_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('LandingDataService getProximosEventosPorEntidad: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene próximos eventos filtrados por IDs de organización (club_responsable IN org_ids).
     *
     * @param array<int> $org_ids
     * @param int $limit
     * @return list<array>
     */
    public function getProximosEventosPorOrganizaciones(array $org_ids, int $limit = 12): array
    {
        if (empty($org_ids)) {
            return [];
        }

        $org_ids = array_values(array_map('intval', array_filter($org_ids)));
        if (empty($org_ids)) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($org_ids), '?'));
        $where_publicar = $this->hasPublicarLanding()
            ? ' AND (t.publicar_landing = 1 OR t.publicar_landing IS NULL)'
            : '';

        $sql = "
            SELECT
                t.*,
                o.nombre as organizacion_nombre,
                o.logo as organizacion_logo,
                o.responsable as club_delegado,
                o.telefono as club_telefono,
                o.email as organizacion_email,
                u.nombre as admin_nombre,
                u.username as admin_username,
                u.celular as admin_celular,
                COALESCE(o.entidad, t.entidad, 0) as entidad_torneo,
                (SELECT COUNT(*) FROM inscritos WHERE torneo_id = t.id AND (estatus IS NULL OR estatus != 'retirado')) as total_inscritos
            FROM tournaments t
            LEFT JOIN organizaciones o ON t.club_responsable = o.id AND o.estatus = 1
            LEFT JOIN usuarios u ON o.admin_user_id = u.id AND u.role = 'admin_club'
            WHERE t.estatus = 1 AND t.fechator >= CURDATE()
              AND t.club_responsable IN ({$ph})
              {$where_publicar}
            ORDER BY t.fechator ASC
            LIMIT " . max(1, min($limit, 100));
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($org_ids);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('LandingDataService getProximosEventosPorOrganizaciones: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene IDs de organización para una entidad (orgs cuya entidad = X).
     */
    public function getOrgIdsPorEntidad(int $entidad_id): array
    {
        if ($entidad_id <= 0) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM organizaciones WHERE entidad = ? AND estatus = 1");
            $stmt->execute([$entidad_id]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene IDs de organización para el club del usuario (club.organizacion_id).
     */
    public function getOrgIdPorClub(int $club_id): ?int
    {
        if ($club_id <= 0) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT organizacion_id FROM clubes WHERE id = ? AND estatus = 1");
            $stmt->execute([$club_id]);
            $val = $stmt->fetchColumn();
            return $val !== false && $val !== null ? (int)$val : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene eventos para el calendario (todos los futuros y recientes).
     */
    public function getEventosCalendario(): array
    {
        $where_publicar = $this->hasPublicarLanding()
            ? ' AND (t.publicar_landing = 1 OR t.publicar_landing IS NULL)'
            : '';

        $sql = "
            SELECT
                t.*,
                o.nombre as organizacion_nombre,
                o.logo as organizacion_logo,
                o.responsable as club_delegado,
                o.telefono as club_telefono,
                o.email as organizacion_email,
                u.nombre as admin_nombre,
                u.celular as admin_celular,
                (SELECT COUNT(*) FROM inscritos WHERE torneo_id = t.id AND (estatus IS NULL OR estatus != 'retirado')) as total_inscritos
            FROM tournaments t
            LEFT JOIN organizaciones o ON t.club_responsable = o.id AND o.estatus = 1
            LEFT JOIN usuarios u ON o.admin_user_id = u.id AND u.role = 'admin_club'
            WHERE t.estatus = 1
            {$where_publicar}
            ORDER BY t.fechator ASC";
        try {
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('LandingDataService getEventosCalendario: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene entidades con eventos (para filtros).
     */
    public function getEntidadesConEventos(): array
    {
        try {
            return $this->pdo->query("
                SELECT
                    e.id,
                    e.nombre,
                    COUNT(DISTINCT u.club_id) as total_clubes,
                    COUNT(DISTINCT CASE WHEN t.estatus = 1 AND t.fechator >= CURDATE() THEN t.id END) as total_eventos_futuros,
                    COUNT(DISTINCT CASE WHEN t.estatus = 1 THEN t.id END) as total_eventos_todos
                FROM entidad e
                INNER JOIN usuarios u ON u.entidad = e.id
                LEFT JOIN organizaciones o ON o.admin_user_id = u.id
                LEFT JOIN tournaments t ON t.club_responsable = o.id
                WHERE u.role IN ('admin_club', 'admin_torneo')
                  AND u.club_id IS NOT NULL
                  AND u.club_id > 0
                  AND u.status = 0
                GROUP BY e.id, e.nombre
                HAVING total_clubes > 0
                ORDER BY e.nombre ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('LandingDataService getEntidadesConEventos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el podio (1°, 2°, 3°) de un torneo a partir de inscritos ordenados por ptosrnk.
     *
     * @param int $torneo_id
     * @return list<array> [{posicion, nombre, club_nombre}, ...]
     */
    public function getPodioPorTorneo(int $torneo_id): array
    {
        if ($torneo_id <= 0) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    i.posicion,
                    COALESCE(u.nombre, u.username, 'N/A') as nombre,
                    c.nombre as club_nombre
                FROM inscritos i
                LEFT JOIN usuarios u ON i.id_usuario = u.id
                LEFT JOIN clubes c ON i.id_club = c.id
                WHERE i.torneo_id = ? AND (i.estatus IS NULL OR i.estatus = 'confirmado')
                ORDER BY i.ptosrnk DESC, i.efectividad DESC, i.ganados DESC, i.puntos DESC, i.posicion ASC
                LIMIT 3
            ");
            $stmt->execute([$torneo_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $i => $row) {
                $rows[$i]['posicion_display'] = $i + 1;
            }
            return $rows;
        } catch (Exception $e) {
            error_log('LandingDataService getPodioPorTorneo: ' . $e->getMessage());
            return [];
        }
    }
}
