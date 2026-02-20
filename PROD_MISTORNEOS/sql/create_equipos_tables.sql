-- =====================================================
-- MIGRACIÓN: Creación de tablas para EQUIPOS de 4 Jugadores
-- Fecha: 2025-01-08
-- Descripción: Sistema de equipos para torneos modalidad equipos (4 jugadores)
-- =====================================================
-- 
-- ESTRUCTURA PROPUESTA:
-- 1. equipos: Tabla principal con información del equipo
-- 2. equipo_jugadores: Relación de jugadores con el equipo
--
-- LÓGICA DEL CÓDIGO DE EQUIPO:
-- codigo_equipo = LPAD(id_club, 2, '0') + LPAD(consecutivo_club, 3, '0')
-- Ejemplo: Club 5, consecutivo 12 = "05012"
-- =====================================================

-- =====================================================
-- TABLA: equipos
-- Almacena la información principal de cada equipo
-- =====================================================
DROP TABLE IF EXISTS `equipo_jugadores`;
DROP TABLE IF EXISTS `equipos`;

CREATE TABLE IF NOT EXISTS `equipos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  
  -- Relaciones principales (INT para coincidir con tournaments.id y clubes.id)
  `id_torneo` INT NOT NULL COMMENT 'Referencia al torneo',
  `id_club` INT NOT NULL COMMENT 'Club al que pertenece el equipo',
  
  -- Identificación del equipo
  `nombre_equipo` VARCHAR(100) NOT NULL COMMENT 'Nombre del equipo',
  `codigo_equipo` VARCHAR(10) NOT NULL COMMENT 'Código único: LPAD(club,2,0) + LPAD(consecutivo,3,0). Ej: 05012',
  `consecutivo_club` INT NOT NULL DEFAULT 1 COMMENT 'Consecutivo por club (1 a N)',
  
  -- Estado del equipo
  `estatus` TINYINT NOT NULL DEFAULT 0 COMMENT '0=Activo, 1=Inactivo',
  
  -- Estadísticas del torneo
  `ganados` INT NOT NULL DEFAULT 0 COMMENT 'Partidas ganadas',
  `perdidos` INT NOT NULL DEFAULT 0 COMMENT 'Partidas perdidas',
  `efectividad` INT NOT NULL DEFAULT 0 COMMENT 'Diferencial de efectividad',
  `puntos` INT NOT NULL DEFAULT 0 COMMENT 'Puntos acumulados',
  `gff` INT NOT NULL DEFAULT 0 COMMENT 'Ganados por forfait',
  `posicion` INT NOT NULL DEFAULT 0 COMMENT 'Posición en la clasificación',
  `sancion` INT NOT NULL DEFAULT 0 COMMENT 'Puntos de sanción',
  
  -- Auditoría
  `creado_por` INT NULL COMMENT 'Usuario que creó el equipo',
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- Un equipo con el mismo nombre no puede repetirse en el mismo torneo/club
  UNIQUE KEY `uk_equipo_torneo_club` (`id_torneo`, `id_club`, `nombre_equipo`),
  
  -- El código debe ser único por torneo
  UNIQUE KEY `uk_codigo_torneo` (`id_torneo`, `codigo_equipo`),
  
  -- Índices para búsquedas frecuentes
  KEY `idx_torneo` (`id_torneo`),
  KEY `idx_club` (`id_club`),
  KEY `idx_estatus` (`estatus`),
  KEY `idx_posicion` (`posicion`),
  KEY `idx_puntos` (`puntos`),
  
  -- Foreign keys (usando INT para coincidir con las tablas existentes)
  CONSTRAINT `fk_equipos_torneo` FOREIGN KEY (`id_torneo`) 
    REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_equipos_club` FOREIGN KEY (`id_club`) 
    REFERENCES `clubes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_equipos_creador` FOREIGN KEY (`creado_por`) 
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
  COMMENT='Equipos de jugadores para torneos modalidad equipos';


-- =====================================================
-- TABLA: equipo_jugadores
-- Relaciona los jugadores con su equipo (máximo 4 por equipo)
-- Soporta jugadores de ambos sistemas: inscritos e inscripciones
-- =====================================================

CREATE TABLE IF NOT EXISTS `equipo_jugadores` (
  `id` INT NOT NULL AUTO_INCREMENT,
  
  -- Relación con el equipo
  `id_equipo` INT NOT NULL COMMENT 'Referencia al equipo',
  
  -- Relación con jugador (uno de los dos debe estar presente)
  `id_inscrito` INT UNSIGNED NULL COMMENT 'Referencia a tabla inscritos (usuarios registrados)',
  `id_inscripcion` INT NULL COMMENT 'Referencia a tabla inscripciones (jugadores por invitación)',
  
  -- Datos del jugador para referencia rápida (desnormalización controlada)
  `cedula` VARCHAR(20) NOT NULL COMMENT 'Cédula del jugador',
  `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre completo del jugador',
  
  -- Posición dentro del equipo (1-4)
  `posicion_equipo` TINYINT NOT NULL DEFAULT 1 COMMENT 'Posición del jugador en el equipo (1-4)',
  
  -- Es el capitán/líder del equipo
  `es_capitan` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=Es capitán del equipo',
  
  -- Estadísticas individuales dentro del equipo (opcional)
  `puntos_aportados` INT NOT NULL DEFAULT 0 COMMENT 'Puntos que ha aportado al equipo',
  `partidas_jugadas` INT NOT NULL DEFAULT 0 COMMENT 'Partidas jugadas con el equipo',
  
  -- Estado
  `estatus` TINYINT NOT NULL DEFAULT 1 COMMENT '0=Inactivo, 1=Activo, 2=Suspendido',
  
  -- Auditoría
  `fecha_asignacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  
  -- Un jugador solo puede estar en un equipo por torneo (se valida por cédula)
  -- Esto se controlará a nivel de aplicación ya que necesitamos validar el torneo
  UNIQUE KEY `uk_equipo_posicion` (`id_equipo`, `posicion_equipo`),
  
  -- Un jugador (por cédula) solo puede estar en un equipo del mismo torneo
  -- Esto se manejará a nivel de aplicación
  KEY `idx_cedula` (`cedula`),
  KEY `idx_equipo` (`id_equipo`),
  KEY `idx_inscrito` (`id_inscrito`),
  KEY `idx_inscripcion` (`id_inscripcion`),
  
  -- Foreign keys (id_equipo usa INT para coincidir con equipos.id)
  CONSTRAINT `fk_ej_equipo` FOREIGN KEY (`id_equipo`) 
    REFERENCES `equipos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ej_inscrito` FOREIGN KEY (`id_inscrito`) 
    REFERENCES `inscritos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
  -- Nota: No agregamos FK a inscripciones porque la tabla puede no existir
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
  COMMENT='Relación de jugadores con equipos (máximo 4 por equipo)';


-- =====================================================
-- TRIGGERS para automatización
-- =====================================================

-- Trigger para generar código de equipo automáticamente al insertar
DELIMITER //

CREATE TRIGGER `trg_equipos_before_insert` BEFORE INSERT ON `equipos`
FOR EACH ROW
BEGIN
    DECLARE v_consecutivo INT;
    
    -- Obtener el siguiente consecutivo para este club en este torneo
    SELECT IFNULL(MAX(consecutivo_club), 0) + 1 INTO v_consecutivo
    FROM equipos
    WHERE id_torneo = NEW.id_torneo AND id_club = NEW.id_club;
    
    -- Asignar consecutivo
    SET NEW.consecutivo_club = v_consecutivo;
    
    -- Generar código: LPAD(id_club, 2, '0') + LPAD(consecutivo, 3, '0')
    -- Ejemplo: Club 5, consecutivo 12 = "05012"
    SET NEW.codigo_equipo = CONCAT(
        LPAD(NEW.id_club, 2, '0'),
        LPAD(v_consecutivo, 3, '0')
    );
END//

DELIMITER ;


-- =====================================================
-- VISTAS ÚTILES
-- =====================================================

-- Vista: Equipos con conteo de jugadores
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
    (SELECT COUNT(*) FROM equipo_jugadores ej WHERE ej.id_equipo = e.id AND ej.estatus = 1) AS total_jugadores,
    (SELECT nombre FROM equipo_jugadores ej WHERE ej.id_equipo = e.id AND ej.es_capitan = 1 LIMIT 1) AS capitan
FROM equipos e
LEFT JOIN tournaments t ON e.id_torneo = t.id
LEFT JOIN clubes c ON e.id_club = c.id;


-- Vista: Jugadores de equipo con información completa
CREATE OR REPLACE VIEW `v_equipo_jugadores_detalle` AS
SELECT 
    ej.id,
    ej.id_equipo,
    ej.cedula,
    ej.nombre,
    ej.posicion_equipo,
    ej.es_capitan,
    ej.puntos_aportados,
    ej.partidas_jugadas,
    ej.estatus,
    e.nombre_equipo,
    e.codigo_equipo,
    e.id_torneo,
    e.id_club,
    CASE 
        WHEN ej.id_inscrito IS NOT NULL THEN 'inscritos'
        WHEN ej.id_inscripcion IS NOT NULL THEN 'inscripciones'
        ELSE 'manual'
    END AS origen_jugador
FROM equipo_jugadores ej
INNER JOIN equipos e ON ej.id_equipo = e.id;


-- =====================================================
-- PROCEDIMIENTOS ALMACENADOS
-- =====================================================

DELIMITER //

-- Procedimiento: Crear equipo con validaciones
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
    
    -- Verificar que el torneo sea modalidad equipos (3)
    SELECT modalidad INTO v_modalidad FROM tournaments WHERE id = p_id_torneo;
    
    IF v_modalidad != 3 THEN
        SET p_id_equipo = 0;
        SET p_codigo_equipo = '';
        SET p_mensaje = 'Error: El torneo no es modalidad equipos';
    ELSE
        -- Verificar que no exista un equipo con el mismo nombre en el club/torneo
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
            -- Insertar el equipo
            INSERT INTO equipos (id_torneo, id_club, nombre_equipo, creado_por)
            VALUES (p_id_torneo, p_id_club, UPPER(p_nombre_equipo), p_creado_por);
            
            SET p_id_equipo = LAST_INSERT_ID();
            
            -- Obtener el código generado
            SELECT codigo_equipo INTO p_codigo_equipo 
            FROM equipos WHERE id = p_id_equipo;
            
            SET p_mensaje = 'Equipo creado exitosamente';
        END IF;
    END IF;
END//


-- Procedimiento: Agregar jugador a equipo
CREATE PROCEDURE `sp_agregar_jugador_equipo`(
    IN p_id_equipo INT,
    IN p_cedula VARCHAR(20),
    IN p_nombre VARCHAR(100),
    IN p_posicion_equipo TINYINT,
    IN p_es_capitan TINYINT,
    IN p_id_inscrito INT,
    IN p_id_inscripcion INT,
    OUT p_id_jugador INT,
    OUT p_mensaje VARCHAR(255)
)
BEGIN
    DECLARE v_total_jugadores INT DEFAULT 0;
    DECLARE v_existe_cedula INT DEFAULT 0;
    DECLARE v_existe_posicion INT DEFAULT 0;
    DECLARE v_id_torneo INT;
    
    -- Obtener el torneo del equipo
    SELECT id_torneo INTO v_id_torneo FROM equipos WHERE id = p_id_equipo;
    
    -- Verificar que no haya más de 4 jugadores
    SELECT COUNT(*) INTO v_total_jugadores 
    FROM equipo_jugadores 
    WHERE id_equipo = p_id_equipo AND estatus = 1;
    
    IF v_total_jugadores >= 4 THEN
        SET p_id_jugador = 0;
        SET p_mensaje = 'Error: El equipo ya tiene 4 jugadores';
    ELSE
        -- Verificar que la posición no esté ocupada
        SELECT COUNT(*) INTO v_existe_posicion 
        FROM equipo_jugadores 
        WHERE id_equipo = p_id_equipo 
          AND posicion_equipo = p_posicion_equipo 
          AND estatus = 1;
        
        IF v_existe_posicion > 0 THEN
            SET p_id_jugador = 0;
            SET p_mensaje = CONCAT('Error: La posición ', p_posicion_equipo, ' ya está ocupada');
        ELSE
            -- Verificar que el jugador no esté en otro equipo del mismo torneo
            SELECT COUNT(*) INTO v_existe_cedula 
            FROM equipo_jugadores ej
            INNER JOIN equipos e ON ej.id_equipo = e.id
            WHERE ej.cedula = p_cedula 
              AND e.id_torneo = v_id_torneo 
              AND ej.estatus = 1;
            
            IF v_existe_cedula > 0 THEN
                SET p_id_jugador = 0;
                SET p_mensaje = 'Error: El jugador ya está inscrito en otro equipo de este torneo';
            ELSE
                -- Insertar el jugador
                INSERT INTO equipo_jugadores (
                    id_equipo, id_inscrito, id_inscripcion, 
                    cedula, nombre, posicion_equipo, es_capitan
                ) VALUES (
                    p_id_equipo, 
                    IF(p_id_inscrito = 0, NULL, p_id_inscrito),
                    IF(p_id_inscripcion = 0, NULL, p_id_inscripcion),
                    p_cedula, 
                    UPPER(p_nombre), 
                    p_posicion_equipo, 
                    p_es_capitan
                );
                
                SET p_id_jugador = LAST_INSERT_ID();
                SET p_mensaje = 'Jugador agregado exitosamente';
            END IF;
        END IF;
    END IF;
END//


-- Procedimiento: Obtener siguiente consecutivo de equipo para un club
CREATE PROCEDURE `sp_get_siguiente_consecutivo`(
    IN p_id_torneo INT,
    IN p_id_club INT,
    OUT p_consecutivo INT,
    OUT p_codigo_preview VARCHAR(10)
)
BEGIN
    SELECT IFNULL(MAX(consecutivo_club), 0) + 1 INTO p_consecutivo
    FROM equipos
    WHERE id_torneo = p_id_torneo AND id_club = p_id_club;
    
    SET p_codigo_preview = CONCAT(
        LPAD(p_id_club, 2, '0'),
        LPAD(p_consecutivo, 3, '0')
    );
END//

DELIMITER ;


-- =====================================================
-- ÍNDICES ADICIONALES PARA RENDIMIENTO
-- =====================================================

-- Índice para búsqueda de jugador por cédula en torneo específico
CREATE INDEX `idx_ej_cedula_estatus` ON `equipo_jugadores` (`cedula`, `estatus`);

-- Índice para ranking de equipos
CREATE INDEX `idx_equipos_ranking` ON `equipos` (`id_torneo`, `posicion`, `puntos` DESC, `efectividad` DESC);


-- =====================================================
-- DATOS DE EJEMPLO (COMENTADO - Descomentar para pruebas)
-- =====================================================
/*
-- Ejemplo de uso:
-- Crear un equipo
CALL sp_crear_equipo(1, 5, 'Los Invencibles', 1, @id_equipo, @codigo, @msg);
SELECT @id_equipo, @codigo, @msg;

-- Agregar jugadores
CALL sp_agregar_jugador_equipo(@id_equipo, '12345678', 'JUAN PEREZ', 1, 1, NULL, NULL, @id_jug, @msg);
CALL sp_agregar_jugador_equipo(@id_equipo, '23456789', 'MARIA GARCIA', 2, 0, NULL, NULL, @id_jug, @msg);
CALL sp_agregar_jugador_equipo(@id_equipo, '34567890', 'PEDRO LOPEZ', 3, 0, NULL, NULL, @id_jug, @msg);
CALL sp_agregar_jugador_equipo(@id_equipo, '45678901', 'ANA MARTINEZ', 4, 0, NULL, NULL, @id_jug, @msg);

-- Ver equipos
SELECT * FROM v_equipos_resumen;

-- Ver jugadores de un equipo
SELECT * FROM v_equipo_jugadores_detalle WHERE id_equipo = @id_equipo;
*/


-- =====================================================
-- NOTAS DE IMPLEMENTACIÓN
-- =====================================================
/*
INTEGRACIÓN CON FLUJOS EXISTENTES:

1. SISTEMA DE INVITACIONES (modules/invitations/inscripciones/):
   - Después de inscribir jugadores en tabla 'inscripciones'
   - Permitir crear equipo y asignar jugadores inscritos
   - Usar id_inscripcion en equipo_jugadores

2. SISTEMA PRINCIPAL (inscritos):
   - Para usuarios registrados en el sistema
   - Usar id_inscrito en equipo_jugadores

3. MODO MIXTO:
   - Un equipo puede tener jugadores de ambos sistemas
   - La cédula sirve como identificador unificador

4. FLUJO RECOMENDADO PARA INSCRIPCIÓN DE EQUIPOS:
   a) Delegado ingresa al módulo de inscripción
   b) Opción "Crear Equipo" muestra formulario:
      - Nombre del equipo
      - 4 campos para buscar/agregar jugadores por cédula
   c) Al guardar:
      - Se crea registro en 'equipos' (trigger genera código)
      - Se crean 4 registros en 'equipo_jugadores'
      - Cada jugador se inscribe también en 'inscripciones' si no existe

5. CÓDIGO DE EQUIPO:
   - Formato: XXYYY donde XX=club (2 dígitos), YYY=consecutivo (3 dígitos)
   - Ejemplos:
     * Club 1, Equipo 1: 01001
     * Club 1, Equipo 2: 01002
     * Club 15, Equipo 3: 15003
   - Se genera automáticamente por trigger

6. ESTADÍSTICAS:
   - A nivel equipo: ganados, perdidos, efectividad, puntos, posicion
   - A nivel jugador: puntos_aportados, partidas_jugadas
   - Actualizar con procedimientos tras cada partida
*/

