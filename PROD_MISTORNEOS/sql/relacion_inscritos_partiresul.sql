-- =====================================================
-- PROCEDIMIENTOS Y VISTAS: Relación inscritos <-> partiresul
-- Fecha: 2025-01-XX
-- Descripción: Procedimientos para mantener consistencia entre inscritos y partiresul
-- =====================================================

-- =====================================================
-- VISTA: Estadísticas de inscritos con datos de partiresul
-- =====================================================
CREATE OR REPLACE VIEW `v_inscritos_estadisticas` AS
SELECT 
    i.id,
    i.id_usuario,
    i.torneo_id,
    i.id_club,
    i.posicion,
    i.estatus,
    i.ganados,
    i.perdidos,
    i.efectividad,
    i.puntos,
    i.ptosrnk,
    i.sancion,
    i.chancletas,
    i.zapatos,
    i.tarjeta,
    -- Estadísticas calculadas desde partiresul
    COUNT(DISTINCT p.id) as total_partidas,
    COUNT(DISTINCT CASE WHEN p.ff = 1 THEN p.id END) as total_forfaits,
    SUM(CASE WHEN p.registrado = 1 THEN p.efectividad ELSE 0 END) as efectividad_calculada,
    SUM(CASE WHEN p.registrado = 1 THEN p.sancion ELSE 0 END) as sancion_calculada,
    SUM(CASE WHEN p.registrado = 1 THEN p.chancleta ELSE 0 END) as chancletas_calculada,
    SUM(CASE WHEN p.registrado = 1 THEN p.zapato ELSE 0 END) as zapatos_calculada,
    SUM(CASE WHEN p.registrado = 1 THEN p.tarjeta ELSE 0 END) as tarjeta_calculada
FROM inscritos i
LEFT JOIN partiresul p ON i.id_usuario = p.id_usuario AND i.torneo_id = p.id_torneo
GROUP BY i.id;

-- =====================================================
-- PROCEDIMIENTO: Actualizar estadísticas de inscrito desde partiresul
-- =====================================================
DELIMITER //

CREATE PROCEDURE `sp_actualizar_estadisticas_inscrito`(
    IN p_id_usuario INT UNSIGNED,
    IN p_torneo_id INT UNSIGNED
)
BEGIN
    DECLARE v_ganados INT DEFAULT 0;
    DECLARE v_perdidos INT DEFAULT 0;
    DECLARE v_efectividad INT DEFAULT 0;
    DECLARE v_sancion INT DEFAULT 0;
    DECLARE v_chancletas INT DEFAULT 0;
    DECLARE v_zapatos INT DEFAULT 0;
    DECLARE v_tarjeta INT DEFAULT 0;
    
    -- Calcular partidas ganadas (donde resultado1 > resultado2 o resultado2 > resultado1)
    SELECT COUNT(*) INTO v_ganados
    FROM partiresul
    WHERE id_usuario = p_id_usuario 
      AND id_torneo = p_torneo_id
      AND registrado = 1
      AND ff = 0
      AND ((resultado1 > resultado2) OR (resultado2 > resultado1));
    
    -- Calcular partidas perdidas
    SELECT COUNT(*) INTO v_perdidos
    FROM partiresul
    WHERE id_usuario = p_id_usuario 
      AND id_torneo = p_torneo_id
      AND registrado = 1
      AND ff = 0
      AND ((resultado1 < resultado2) OR (resultado2 < resultado1));
    
    -- Sumar efectividad
    SELECT COALESCE(SUM(efectividad), 0) INTO v_efectividad
    FROM partiresul
    WHERE id_usuario = p_id_usuario 
      AND id_torneo = p_torneo_id
      AND registrado = 1;
    
    -- Sumar sanciones
    SELECT COALESCE(SUM(sancion), 0) INTO v_sancion
    FROM partiresul
    WHERE id_usuario = p_id_usuario 
      AND id_torneo = p_torneo_id
      AND registrado = 1;
    
    SELECT COALESCE(SUM(chancleta), 0) INTO v_chancletas
    FROM partiresul
    WHERE id_usuario = p_id_usuario 
      AND id_torneo = p_torneo_id
      AND registrado = 1;
    
    SELECT COALESCE(SUM(zapato), 0) INTO v_zapatos
    FROM partiresul
    WHERE id_usuario = p_id_usuario 
      AND id_torneo = p_torneo_id
      AND registrado = 1;
    
    SELECT COALESCE(SUM(tarjeta), 0) INTO v_tarjeta
    FROM partiresul
    WHERE id_usuario = p_id_usuario 
      AND id_torneo = p_torneo_id
      AND registrado = 1;
    
    -- Actualizar inscritos
    UPDATE inscritos
    SET ganados = v_ganados,
        perdidos = v_perdidos,
        efectividad = v_efectividad,
        sancion = v_sancion,
        chancletas = v_chancletas,
        zapatos = v_zapatos,
        tarjeta = v_tarjeta
    WHERE id_usuario = p_id_usuario 
      AND torneo_id = p_torneo_id;
END //

DELIMITER ;

-- =====================================================
-- PROCEDIMIENTO: Actualizar todas las estadísticas de un torneo
-- =====================================================
DELIMITER //

CREATE PROCEDURE `sp_actualizar_estadisticas_torneo`(
    IN p_torneo_id INT UNSIGNED
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_id_usuario INT UNSIGNED;
    
    DECLARE cur_inscritos CURSOR FOR
        SELECT DISTINCT id_usuario
        FROM inscritos
        WHERE torneo_id = p_torneo_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur_inscritos;
    
    read_loop: LOOP
        FETCH cur_inscritos INTO v_id_usuario;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        CALL sp_actualizar_estadisticas_inscrito(v_id_usuario, p_torneo_id);
    END LOOP;
    
    CLOSE cur_inscritos;
END //

DELIMITER ;

-- =====================================================
-- TRIGGER: Actualizar estadísticas cuando se registra una partida
-- =====================================================
DELIMITER //

CREATE TRIGGER `tr_partiresul_after_update`
AFTER UPDATE ON `partiresul`
FOR EACH ROW
BEGIN
    -- Si el campo registrado cambió a 1, actualizar estadísticas
    IF NEW.registrado = 1 AND (OLD.registrado = 0 OR OLD.registrado IS NULL) THEN
        CALL sp_actualizar_estadisticas_inscrito(NEW.id_usuario, NEW.id_torneo);
    END IF;
END //

DELIMITER ;

-- =====================================================
-- TRIGGER: Actualizar estadísticas cuando se inserta una partida registrada
-- =====================================================
DELIMITER //

CREATE TRIGGER `tr_partiresul_after_insert`
AFTER INSERT ON `partiresul`
FOR EACH ROW
BEGIN
    -- Si se inserta con registrado = 1, actualizar estadísticas
    IF NEW.registrado = 1 THEN
        CALL sp_actualizar_estadisticas_inscrito(NEW.id_usuario, NEW.id_torneo);
    END IF;
END //

DELIMITER ;

-- =====================================================
-- NOTAS IMPORTANTES:
-- =====================================================
-- 1. Los triggers actualizan automáticamente las estadísticas en inscritos
-- 2. Los procedimientos permiten actualización manual cuando sea necesario
-- 3. La vista v_inscritos_estadisticas muestra datos calculados en tiempo real
-- 4. Siempre verificar que existe inscrito antes de insertar en partiresul
-- =====================================================

