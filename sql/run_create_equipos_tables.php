<?php
/**
 * Script para ejecutar la migración de tablas de equipos
 * Versión simplificada con ejecución directa
 */

// Evitar ejecución desde navegador en producción
if (php_sapi_name() !== 'cli' && !isset($_GET['confirm'])) {
    echo "<html><head><title>Migración Equipos</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "</head><body class='container mt-5'>";
    echo "<div class='alert alert-warning'>";
    echo "<h4>⚠️ Migración de Base de Datos - Tablas de Equipos</h4>";
    echo "<p>Este script creará las tablas <strong>equipos</strong> y <strong>equipo_jugadores</strong>.</p>";
    echo "<a href='?confirm=1' class='btn btn-primary'>Ejecutar Migración</a> ";
    echo "<a href='javascript:history.back()' class='btn btn-secondary'>Cancelar</a>";
    echo "</div></body></html>";
    exit;
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$isWeb = php_sapi_name() !== 'cli';

function output($message, $type = 'info', $isWeb = false) {
    if ($isWeb) {
        $colors = ['success' => 'text-success', 'error' => 'text-danger', 'warning' => 'text-warning', 'info' => 'text-primary'];
        echo "<p class='{$colors[$type]}'>{$message}</p>";
    } else {
        $prefixes = ['success' => '✅', 'error' => '❌', 'warning' => '⚠️', 'info' => 'ℹ️'];
        echo "{$prefixes[$type]} {$message}\n";
    }
}

if ($isWeb) {
    echo "<html><head><title>Migración Equipos</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "</head><body class='container mt-5'><h2>🏆 Migración de Tablas de Equipos</h2><hr>";
}

try {
    $pdo = DB::pdo();
    
    output("Iniciando migración...", "info", $isWeb);
    
    // 1. Eliminar tablas si existen (en orden correcto por FK)
    output("Eliminando tablas existentes...", "info", $isWeb);
    
    try { $pdo->exec("DROP TABLE IF EXISTS `equipo_jugadores`"); output("DROP equipo_jugadores: OK", "success", $isWeb); } 
    catch (Exception $e) { output("DROP equipo_jugadores: " . $e->getMessage(), "warning", $isWeb); }
    
    try { $pdo->exec("DROP TABLE IF EXISTS `equipos`"); output("DROP equipos: OK", "success", $isWeb); } 
    catch (Exception $e) { output("DROP equipos: " . $e->getMessage(), "warning", $isWeb); }
    
    // 2. Crear tabla equipos
    output("Creando tabla equipos...", "info", $isWeb);
    
    $sqlEquipos = "
    CREATE TABLE `equipos` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `id_torneo` INT NOT NULL COMMENT 'Referencia al torneo',
      `id_club` INT NOT NULL COMMENT 'Club al que pertenece el equipo',
      `nombre_equipo` VARCHAR(100) NOT NULL COMMENT 'Nombre del equipo',
      `codigo_equipo` VARCHAR(10) NOT NULL COMMENT 'Código único: LPAD(club,2,0) + LPAD(consecutivo,3,0)',
      `consecutivo_club` INT NOT NULL DEFAULT 1 COMMENT 'Consecutivo por club (1 a N)',
      `estatus` TINYINT NOT NULL DEFAULT 0 COMMENT '0=Activo, 1=Inactivo',
      `ganados` INT NOT NULL DEFAULT 0,
      `perdidos` INT NOT NULL DEFAULT 0,
      `efectividad` INT NOT NULL DEFAULT 0,
      `puntos` INT NOT NULL DEFAULT 0,
      `gff` INT NOT NULL DEFAULT 0,
      `posicion` INT NOT NULL DEFAULT 0,
      `sancion` INT NOT NULL DEFAULT 0,
      `creado_por` INT NULL,
      `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_equipo_torneo_club` (`id_torneo`, `id_club`, `nombre_equipo`),
      UNIQUE KEY `uk_codigo_torneo` (`id_torneo`, `codigo_equipo`),
      KEY `idx_torneo` (`id_torneo`),
      KEY `idx_club` (`id_club`),
      KEY `idx_estatus` (`estatus`),
      KEY `idx_posicion` (`posicion`),
      KEY `idx_puntos` (`puntos`),
      CONSTRAINT `fk_equipos_torneo` FOREIGN KEY (`id_torneo`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_equipos_club` FOREIGN KEY (`id_club`) REFERENCES `clubes` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_equipos_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sqlEquipos);
    output("CREATE TABLE equipos: OK", "success", $isWeb);
    
    // 3. Crear tabla equipo_jugadores
    output("Creando tabla equipo_jugadores...", "info", $isWeb);
    
    $sqlJugadores = "
    CREATE TABLE `equipo_jugadores` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `id_equipo` INT NOT NULL COMMENT 'Referencia al equipo',
      `id_inscrito` INT UNSIGNED NULL COMMENT 'Referencia a tabla inscritos',
      `id_inscripcion` INT NULL COMMENT 'Referencia a tabla inscripciones',
      `cedula` VARCHAR(20) NOT NULL,
      `nombre` VARCHAR(100) NOT NULL,
      `posicion_equipo` TINYINT NOT NULL DEFAULT 1,
      `es_capitan` TINYINT(1) NOT NULL DEFAULT 0,
      `puntos_aportados` INT NOT NULL DEFAULT 0,
      `partidas_jugadas` INT NOT NULL DEFAULT 0,
      `estatus` TINYINT NOT NULL DEFAULT 1,
      `fecha_asignacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_equipo_posicion` (`id_equipo`, `posicion_equipo`),
      KEY `idx_cedula` (`cedula`),
      KEY `idx_equipo` (`id_equipo`),
      KEY `idx_inscrito` (`id_inscrito`),
      KEY `idx_inscripcion` (`id_inscripcion`),
      CONSTRAINT `fk_ej_equipo` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_ej_inscrito` FOREIGN KEY (`id_inscrito`) REFERENCES `inscritos` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sqlJugadores);
    output("CREATE TABLE equipo_jugadores: OK", "success", $isWeb);
    
    // 4. Crear trigger para código automático
    output("Creando trigger para código automático...", "info", $isWeb);
    
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS `trg_equipos_before_insert`");
        
        $sqlTrigger = "
        CREATE TRIGGER `trg_equipos_before_insert` BEFORE INSERT ON `equipos`
        FOR EACH ROW
        BEGIN
            DECLARE v_consecutivo INT;
            
            SELECT IFNULL(MAX(consecutivo_club), 0) + 1 INTO v_consecutivo
            FROM equipos
            WHERE id_torneo = NEW.id_torneo AND id_club = NEW.id_club;
            
            SET NEW.consecutivo_club = v_consecutivo;
            SET NEW.codigo_equipo = CONCAT(
                LPAD(NEW.id_club, 2, '0'),
                LPAD(v_consecutivo, 3, '0')
            );
        END";
        
        $pdo->exec($sqlTrigger);
        output("CREATE TRIGGER trg_equipos_before_insert: OK", "success", $isWeb);
    } catch (Exception $e) {
        output("TRIGGER: " . $e->getMessage(), "warning", $isWeb);
    }
    
    // 5. Crear procedimientos almacenados
    output("Creando procedimientos almacenados...", "info", $isWeb);
    
    try {
        $pdo->exec("DROP PROCEDURE IF EXISTS `sp_crear_equipo`");
        
        $sqlProc1 = "
        CREATE PROCEDURE `sp_crear_equipo`(
            IN p_id_torneo INT,
            IN p_id_club INT,
            IN p_nombre_equipo VARCHAR(100),
            IN p_creado_por INT,
            OUT p_id_equipo INT,
            OUT p_codigo_equipo VARCHAR(10),
            OUT p_mensaje VARCHAR(255)
        )
        BEGIN
            DECLARE v_existe INT DEFAULT 0;
            DECLARE v_modalidad INT DEFAULT 0;
            
            SELECT modalidad INTO v_modalidad FROM tournaments WHERE id = p_id_torneo;
            
            IF v_modalidad != 3 THEN
                SET p_id_equipo = 0;
                SET p_codigo_equipo = '';
                SET p_mensaje = 'Error: El torneo no es modalidad equipos';
            ELSE
                SELECT COUNT(*) INTO v_existe 
                FROM equipos 
                WHERE id_torneo = p_id_torneo 
                  AND id_club = p_id_club 
                  AND UPPER(nombre_equipo) = UPPER(p_nombre_equipo);
                
                IF v_existe > 0 THEN
                    SET p_id_equipo = 0;
                    SET p_codigo_equipo = '';
                    SET p_mensaje = 'Error: Ya existe un equipo con ese nombre en este club';
                ELSE
                    INSERT INTO equipos (id_torneo, id_club, nombre_equipo, creado_por)
                    VALUES (p_id_torneo, p_id_club, UPPER(p_nombre_equipo), p_creado_por);
                    
                    SET p_id_equipo = LAST_INSERT_ID();
                    
                    SELECT codigo_equipo INTO p_codigo_equipo 
                    FROM equipos WHERE id = p_id_equipo;
                    
                    SET p_mensaje = 'Equipo creado exitosamente';
                END IF;
            END IF;
        END";
        
        $pdo->exec($sqlProc1);
        output("CREATE PROCEDURE sp_crear_equipo: OK", "success", $isWeb);
    } catch (Exception $e) {
        output("PROCEDURE sp_crear_equipo: " . $e->getMessage(), "warning", $isWeb);
    }
    
    // 6. Crear vistas
    output("Creando vistas...", "info", $isWeb);
    
    try {
        $sqlView = "
        CREATE OR REPLACE VIEW `v_equipos_resumen` AS
        SELECT 
            e.id,
            e.id_torneo,
            e.id_club,
            e.nombre_equipo,
            e.codigo_equipo,
            e.estatus,
            e.ganados,
            e.perdidos,
            e.efectividad,
            e.puntos,
            e.posicion,
            t.nombre AS nombre_torneo,
            c.nombre AS nombre_club,
            (SELECT COUNT(*) FROM equipo_jugadores ej WHERE ej.id_equipo = e.id AND ej.estatus = 1) AS total_jugadores
        FROM equipos e
        LEFT JOIN tournaments t ON e.id_torneo = t.id
        LEFT JOIN clubes c ON e.id_club = c.id";
        
        $pdo->exec($sqlView);
        output("CREATE VIEW v_equipos_resumen: OK", "success", $isWeb);
    } catch (Exception $e) {
        output("VIEW: " . $e->getMessage(), "warning", $isWeb);
    }
    
    // 7. Crear índices adicionales
    output("Creando índices adicionales...", "info", $isWeb);
    
    try {
        $pdo->exec("CREATE INDEX `idx_ej_cedula_estatus` ON `equipo_jugadores` (`cedula`, `estatus`)");
        output("CREATE INDEX idx_ej_cedula_estatus: OK", "success", $isWeb);
    } catch (Exception $e) {
        output("INDEX: " . $e->getMessage(), "warning", $isWeb);
    }
    
    try {
        $pdo->exec("CREATE INDEX `idx_equipos_ranking` ON `equipos` (`id_torneo`, `posicion`, `puntos` DESC, `efectividad` DESC)");
        output("CREATE INDEX idx_equipos_ranking: OK", "success", $isWeb);
    } catch (Exception $e) {
        output("INDEX: " . $e->getMessage(), "warning", $isWeb);
    }
    
    // Verificación final
    output("", "info", $isWeb);
    output("=== VERIFICACIÓN FINAL ===", "info", $isWeb);
    
    $tables = ['equipos', 'equipo_jugadores'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            output("Tabla '$table': ✓ EXISTE", "success", $isWeb);
        } else {
            output("Tabla '$table': ✗ NO EXISTE", "error", $isWeb);
        }
    }
    
    output("", "info", $isWeb);
    output("🎉 MIGRACIÓN COMPLETADA EXITOSAMENTE", "success", $isWeb);
    
} catch (Exception $e) {
    output("ERROR FATAL: " . $e->getMessage(), "error", $isWeb);
}

if ($isWeb) {
    echo "<hr><a href='javascript:history.back()' class='btn btn-secondary'>Volver</a>";
    echo " <a href='../modules/equipos/' class='btn btn-primary'>Ir al Módulo de Equipos</a>";
    echo "</body></html>";
}
