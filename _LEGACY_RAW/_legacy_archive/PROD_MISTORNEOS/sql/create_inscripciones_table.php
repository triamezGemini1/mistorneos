<?php
/**
 * Crear tabla inscripciones si no existe
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = DB::pdo();
    
    // Verificar si la tabla existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'inscripciones'");
    
    if ($stmt->fetch()) {
        echo "✅ Tabla 'inscripciones' ya existe\n";
    } else {
        echo "⚠️ Tabla 'inscripciones' no existe. Creando...\n";
        
        $sql = "
        CREATE TABLE `inscripciones` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `cedula` VARCHAR(20) NOT NULL,
          `nombre` VARCHAR(100) NOT NULL,
          `sexo` TINYINT DEFAULT 1 COMMENT '1=Masculino, 2=Femenino',
          `fechnac` DATE NULL,
          `celular` VARCHAR(20) NULL,
          `club_id` INT NOT NULL,
          `torneo_id` INT NOT NULL,
          `identificador` INT DEFAULT 0,
          `estatus` INT DEFAULT 1,
          `categ` INT DEFAULT 0,
          `posicion` INT DEFAULT 0,
          `ganados` INT DEFAULT 0,
          `perdidos` INT DEFAULT 0,
          `efectividad` INT DEFAULT 0,
          `puntos` INT DEFAULT 0,
          `ptosrnk` INT DEFAULT 0,
          `sancion` INT DEFAULT 0,
          `chancletas` INT DEFAULT 0,
          `zapatos` INT DEFAULT 0,
          `tarjeta` INT DEFAULT 0,
          `notas` TEXT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_cedula` (`cedula`),
          KEY `idx_torneo` (`torneo_id`),
          KEY `idx_club` (`club_id`),
          KEY `idx_cedula_torneo` (`cedula`, `torneo_id`),
          CONSTRAINT `fk_inscripciones_torneo` FOREIGN KEY (`torneo_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_inscripciones_club` FOREIGN KEY (`club_id`) REFERENCES `clubes` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ Tabla 'inscripciones' creada exitosamente\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}









