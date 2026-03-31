<?php
/**
 * Helper para verificar tablas necesarias en administración de torneos
 */

class TournamentAdminHelper {
    
    /**
     * Verifica si una tabla existe en la base de datos
     * 
     * @param PDO $pdo Conexión PDO
     * @param string $table_name Nombre de la tabla
     * @return bool True si existe, False si no
     */
    public static function tablaExiste(PDO $pdo, string $table_name): bool {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table_name}'");
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verifica si las tablas necesarias existen
     * 
     * @param PDO $pdo Conexión PDO
     * @return array ['inscritos' => bool, 'partiresul' => bool]
     */
    public static function verificarTablas(PDO $pdo): array {
        return [
            'inscritos' => self::tablaExiste($pdo, 'inscritos'),
            'partiresul' => self::tablaExiste($pdo, 'partiresul')
        ];
    }
    
    /**
     * Muestra alerta si falta alguna tabla
     * 
     * @param array $tablas Array con estado de las tablas
     * @return string HTML de alerta o string vacío
     */
    public static function mostrarAlertaTablasFaltantes(array $tablas): string {
        $faltantes = [];
        
        if (!$tablas['inscritos']) {
            $faltantes[] = 'inscritos';
        }
        
        if (!$tablas['partiresul']) {
            $faltantes[] = 'partiresul';
        }
        
        if (empty($faltantes)) {
            return '';
        }
        
        $html = '<div class="alert alert-warning">';
        $html .= '<h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Tablas Faltantes</h6>';
        $html .= '<p class="mb-2">Las siguientes tablas no existen en la base de datos:</p>';
        $html .= '<ul class="mb-2">';
        
        foreach ($faltantes as $tabla) {
            $html .= '<li><code>' . htmlspecialchars($tabla) . '</code></li>';
        }
        
        $html .= '</ul>';
        $html .= '<p class="mb-0"><strong>Para crear las tablas, ejecute:</strong></p>';
        $html .= '<ul class="mb-0">';
        
        if (!$tablas['inscritos']) {
            $html .= '<li><code>php scripts/migrate_inscritos_table_final.php</code></li>';
        }
        
        if (!$tablas['partiresul']) {
            $html .= '<li><code>php scripts/migrate_partiresul_table.php</code></li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
}

