<?php
/**
 * Punto único de resolución del servicio de mesas / generación de rondas según modalidad del torneo.
 * Evita repetir if/else (equipos / parejas fijas / individual) en módulos y desktop.
 *
 * - Web (MySQL): equipos → config/MesaAsignacionEquiposService; parejas 2 y 4 → MesaAsignacionParejasFijasService.
 * - Desktop (SQLite): equipos → desktop/core/MesaAsignacionEquiposService (no carga config/db.php).
 *   Modalidades 2/4 sin servicio desktop dedicado: se usa MesaAsignacionService (mismo criterio que logica_torneo.php antes).
 */
class TorneoMesaAsignacionResolver
{
    public const MODALIDAD_EQUIPOS = 3;
    /** Parejas fijas / interclubes (misma familia de servicio en web). */
    public const MODALIDAD_PAREJAS_FIJAS = [2, 4];

    private static function esDesktopCore(): bool
    {
        return defined('DESKTOP_CORE_DB_LOADED') && DESKTOP_CORE_DB_LOADED === true;
    }

    /**
     * Instancia el servicio de asignación según modalidad (0 u otra = individual estándar).
     */
    public static function servicioPorModalidad(int $modalidad): object
    {
        if ($modalidad === self::MODALIDAD_EQUIPOS) {
            if (!class_exists('MesaAsignacionEquiposService', false)) {
                $root = dirname(__DIR__, 2);
                if (self::esDesktopCore()) {
                    require_once $root . '/desktop/core/MesaAsignacionEquiposService.php';
                } else {
                    require_once $root . '/config/MesaAsignacionEquiposService.php';
                }
            }
            return new MesaAsignacionEquiposService();
        }
        if (in_array($modalidad, self::MODALIDAD_PAREJAS_FIJAS, true)) {
            if (self::esDesktopCore()) {
                require_once __DIR__ . '/MesaAsignacionService.php';
                return new MesaAsignacionService();
            }
            if (!class_exists('MesaAsignacionParejasFijasService', false)) {
                require_once dirname(__DIR__, 2) . '/config/MesaAsignacionParejasFijasService.php';
            }
            return new MesaAsignacionParejasFijasService();
        }
        require_once __DIR__ . '/MesaAsignacionService.php';
        return new MesaAsignacionService();
    }

    /**
     * Lee modalidad en tournaments y devuelve el servicio correspondiente.
     *
     * @throws RuntimeException si DB::pdo() no está disponible
     */
    public static function servicioPorTorneoId(int $torneoId): object
    {
        if (!class_exists('DB', false) || !method_exists('DB', 'pdo')) {
            throw new RuntimeException('TorneoMesaAsignacionResolver: DB::pdo() no disponible');
        }
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT modalidad FROM tournaments WHERE id = ? LIMIT 1');
        $stmt->execute([$torneoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $modalidad = (int) ($row['modalidad'] ?? 0);
        return self::servicioPorModalidad($modalidad);
    }

    /**
     * Estrategia POST por modalidad (mismos nombres de campo que torneo_gestion/rondas_mesas.php).
     *
     * @param array|null $post Si null, se usa $_POST.
     */
    public static function estrategiaDesdeRequest(int $modalidad, ?array $post = null): string
    {
        $p = $post ?? $_POST;
        if ($modalidad === self::MODALIDAD_EQUIPOS) {
            return isset($p['estrategia_asignacion']) ? (string) $p['estrategia_asignacion'] : 'secuencial';
        }
        if (in_array($modalidad, self::MODALIDAD_PAREJAS_FIJAS, true)) {
            return isset($p['estrategia_asignacion']) ? (string) $p['estrategia_asignacion'] : 'numero_aleatorio';
        }
        return isset($p['estrategia_ronda2']) ? (string) $p['estrategia_ronda2'] : 'separar';
    }

    /**
     * Borrado de ronda: parejas fijas (solo web) usa su implementación; equipos e individual MesaAsignacionService.
     */
    public static function eliminarRonda(int $torneoId, int $ronda, int $modalidad): bool
    {
        if (in_array($modalidad, self::MODALIDAD_PAREJAS_FIJAS, true) && !self::esDesktopCore()) {
            $svc = self::servicioPorModalidad($modalidad);
            if (method_exists($svc, 'eliminarRonda')) {
                return $svc->eliminarRonda($torneoId, $ronda);
            }
        }
        require_once __DIR__ . '/MesaAsignacionService.php';
        return (new MesaAsignacionService())->eliminarRonda($torneoId, $ronda);
    }

    /**
     * Todas las modalidades comparten partiresul; la lógica vive en MesaAsignacionService.
     */
    public static function rondaTieneResultadosEnMesas(int $torneoId, int $ronda): bool
    {
        require_once __DIR__ . '/MesaAsignacionService.php';
        return (new MesaAsignacionService())->rondaTieneResultadosEnMesas($torneoId, $ronda);
    }
}
