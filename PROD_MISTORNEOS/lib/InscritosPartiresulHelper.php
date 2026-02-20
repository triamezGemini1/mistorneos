<?php
/**
 * Helper para validar y manejar la relación entre inscritos y partiresul
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/InscritosHelper.php';

class InscritosPartiresulHelper {
    
    /**
     * Verifica si existe un inscrito antes de insertar en partiresul
     * 
     * @param int $id_usuario ID del usuario
     * @param int $torneo_id ID del torneo
     * @return array|null Datos del inscrito o null si no existe
     */
    public static function verificarInscrito(int $id_usuario, int $torneo_id): ?array {
        $pdo = DB::pdo();
        
        $stmt = $pdo->prepare("
            SELECT * FROM inscritos 
            WHERE id_usuario = ? AND torneo_id = ?
        ");
        $stmt->execute([$id_usuario, $torneo_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Verifica si el inscrito puede jugar partidas (estatus válido)
     * 
     * @param int $id_usuario ID del usuario
     * @param int $torneo_id ID del torneo
     * @return bool True si puede jugar, False si no
     */
    public static function puedeJugar(int $id_usuario, int $torneo_id): bool {
        $inscrito = self::verificarInscrito($id_usuario, $torneo_id);
        
        if (!$inscrito) {
            return false;
        }
        
        // Estatus válido para partidas: confirmado
        $estatus_validos = [1, 2];
        
        return in_array((int)$inscrito['estatus'], $estatus_validos);
    }
    
    /**
     * Obtiene estadísticas de un inscrito desde partiresul
     * 
     * @param int $id_usuario ID del usuario
     * @param int $torneo_id ID del torneo
     * @return array Estadísticas calculadas
     */
    public static function obtenerEstadisticas(int $id_usuario, int $torneo_id): array {
        $pdo = DB::pdo();
        
        // Partidas ganadas (solo mesas normales; BYE se cuenta aparte)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as ganados
            FROM partiresul pr1
            LEFT JOIN partiresul pr2 ON pr1.id_torneo = pr2.id_torneo 
                AND pr1.partida = pr2.partida 
                AND pr1.mesa = pr2.mesa
                AND pr2.id_usuario != pr1.id_usuario
                AND (
                    (pr1.secuencia IN (1, 2) AND pr2.secuencia IN (3, 4)) OR
                    (pr1.secuencia IN (3, 4) AND pr2.secuencia IN (1, 2))
                )
            WHERE pr1.id_usuario = ? 
              AND pr1.id_torneo = ?
              AND pr1.mesa > 0
              AND pr1.registrado = 1
              AND pr1.ff = 0
              AND (
                  (pr1.sancion = 0 AND pr1.resultado1 > pr1.resultado2) OR
                  (pr1.sancion > 0 AND (pr1.resultado1 - pr1.sancion) > COALESCE(pr2.resultado1, pr1.resultado2))
              )
        ");
        $stmt->execute([$id_usuario, $torneo_id]);
        $ganados = (int)$stmt->fetchColumn();

        // Partidas ganadas por BYE (mesa=0: partida ganada, 100% puntos, 50% efectividad)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM partiresul
            WHERE id_usuario = ? AND id_torneo = ? AND registrado = 1 AND mesa = 0 AND resultado1 > resultado2
        ");
        $stmt->execute([$id_usuario, $torneo_id]);
        $ganados += (int)$stmt->fetchColumn();
        
        // Partidas perdidas (solo mesas normales; BYE no cuenta como perdida)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as perdidos
            FROM partiresul pr1
            LEFT JOIN partiresul pr2 ON pr1.id_torneo = pr2.id_torneo 
                AND pr1.partida = pr2.partida 
                AND pr1.mesa = pr2.mesa
                AND pr2.id_usuario != pr1.id_usuario
                AND (
                    (pr1.secuencia IN (1, 2) AND pr2.secuencia IN (3, 4)) OR
                    (pr1.secuencia IN (3, 4) AND pr2.secuencia IN (1, 2))
                )
            WHERE pr1.id_usuario = ? 
              AND pr1.id_torneo = ?
              AND pr1.mesa > 0
              AND pr1.registrado = 1
              AND pr1.ff = 0
              AND (
                  (pr1.sancion = 0 AND pr1.resultado1 < pr1.resultado2) OR
                  (pr1.sancion > 0 AND (pr1.resultado1 - pr1.sancion) <= COALESCE(pr2.resultado1, pr1.resultado2))
              )
        ");
        $stmt->execute([$id_usuario, $torneo_id]);
        $perdidos = (int)$stmt->fetchColumn();
        
        // Efectividad total
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(efectividad), 0) as efectividad
            FROM partiresul
            WHERE id_usuario = ? 
              AND id_torneo = ?
              AND registrado = 1
        ");
        $stmt->execute([$id_usuario, $torneo_id]);
        $efectividad = (int)$stmt->fetchColumn();

        // Puntos acumulados (suma de resultado1 de todas las partidas registradas, incluye BYE)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(resultado1), 0) as puntos
            FROM partiresul
            WHERE id_usuario = ? 
              AND id_torneo = ?
              AND registrado = 1
        ");
        $stmt->execute([$id_usuario, $torneo_id]);
        $puntos = (int)$stmt->fetchColumn();
        
        // Sanciones
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(sancion), 0) as sancion,
                COALESCE(SUM(chancleta), 0) as chancletas,
                COALESCE(SUM(zapato), 0) as zapatos,
                COALESCE(SUM(tarjeta), 0) as tarjeta
            FROM partiresul
            WHERE id_usuario = ? 
              AND id_torneo = ?
              AND registrado = 1
        ");
        $stmt->execute([$id_usuario, $torneo_id]);
        $sanciones = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'ganados' => $ganados,
            'perdidos' => $perdidos,
            'efectividad' => $efectividad,
            'puntos' => $puntos,
            'sancion' => (int)($sanciones['sancion'] ?? 0),
            'chancletas' => (int)($sanciones['chancletas'] ?? 0),
            'zapatos' => (int)($sanciones['zapatos'] ?? 0),
            'tarjeta' => (int)($sanciones['tarjeta'] ?? 0),
            'total_partidas' => $ganados + $perdidos
        ];
    }
    
    /**
     * Actualiza las estadísticas de un inscrito desde partiresul
     * 
     * @param int $id_usuario ID del usuario
     * @param int $torneo_id ID del torneo
     * @return bool True si se actualizó, False si no existe el inscrito
     */
    public static function actualizarEstadisticas(int $id_usuario, int $torneo_id): bool {
        $inscrito = self::verificarInscrito($id_usuario, $torneo_id);
        
        if (!$inscrito) {
            return false;
        }
        
        $estadisticas = self::obtenerEstadisticas($id_usuario, $torneo_id);
        
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("
            UPDATE inscritos
            SET ganados = ?,
                perdidos = ?,
                efectividad = ?,
                puntos = ?,
                sancion = ?,
                chancletas = ?,
                zapatos = ?,
                tarjeta = ?
            WHERE id_usuario = ? AND torneo_id = ?
        ");
        
        return $stmt->execute([
            $estadisticas['ganados'],
            $estadisticas['perdidos'],
            $estadisticas['efectividad'],
            $estadisticas['puntos'],
            $estadisticas['sancion'],
            $estadisticas['chancletas'],
            $estadisticas['zapatos'],
            $estadisticas['tarjeta'],
            $id_usuario,
            $torneo_id
        ]);
    }
    
    /**
     * Valida que se puede insertar una partida en partiresul
     * 
     * @param int $id_usuario ID del usuario
     * @param int $torneo_id ID del torneo
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarInsercionPartida(int $id_usuario, int $torneo_id): array {
        $inscrito = self::verificarInscrito($id_usuario, $torneo_id);
        
        if (!$inscrito) {
            return [
                'valid' => false,
                'message' => 'El usuario no está inscrito en este torneo'
            ];
        }
        
        if (!self::puedeJugar($id_usuario, $torneo_id)) {
            $estatus_texto = InscritosHelper::getEstatusTexto((int)$inscrito['estatus']);
            return [
                'valid' => false,
                'message' => "El inscrito tiene estatus '{$estatus_texto}' y no puede jugar partidas"
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Validación exitosa',
            'inscrito' => $inscrito
        ];
    }
    
    /**
     * Obtiene inscrito con estadísticas desde partiresul
     * 
     * @param int $id_usuario ID del usuario
     * @param int $torneo_id ID del torneo
     * @return array|null Datos del inscrito con estadísticas o null
     */
    public static function obtenerInscritoConEstadisticas(int $id_usuario, int $torneo_id): ?array {
        $inscrito = self::verificarInscrito($id_usuario, $torneo_id);
        
        if (!$inscrito) {
            return null;
        }
        
        $estadisticas = self::obtenerEstadisticas($id_usuario, $torneo_id);
        
        return array_merge($inscrito, $estadisticas);
    }
    
    /**
     * Obtiene clasificación de un torneo
     * 
     * @param int $torneo_id ID del torneo
     * @param int $limit Límite de resultados
     * @return array Lista de inscritos ordenados por ranking
     */
    public static function obtenerClasificacion(int $torneo_id, int $limit = 0): array {
        $pdo = DB::pdo();
        
        $sql = "
            SELECT 
                i.*,
                COUNT(DISTINCT p.id) as total_partidas,
                COUNT(DISTINCT CASE WHEN p.ff = 1 THEN p.id END) as total_forfaits
            FROM inscritos i
            LEFT JOIN partiresul p ON i.id_usuario = p.id_usuario AND i.torneo_id = p.id_torneo
            WHERE i.torneo_id = ?
              AND i.estatus = 'confirmado'
            GROUP BY i.id
            ORDER BY i.ptosrnk DESC, i.efectividad DESC, i.ganados DESC
        ";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$torneo_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

