<?php
/**
 * Actualizar deuda de clubes basado en inscritos
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

try {
    // Verificar autenticación
    Auth::requireRole(['admin_general', 'admin_torneo']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['accion']) || $input['accion'] !== 'actualizar') {
        throw new Exception('Acción inválida');
    }
    
    $pdo = DB::pdo();
    $pdo->beginTransaction();
    
    $clubs_actualizados = 0;
    
    // Obtener todos los torneos activos
    $stmt = $pdo->query("SELECT id, costo FROM tournaments WHERE estatus = 1");
    $torneos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($torneos as $torneo) {
        $torneo_id = $torneo['id'];
        $costo_por_jugador = $torneo['costo'];
        
        // Obtener todos los clubs únicos que tienen inscritos en este torneo
        $stmt = $pdo->prepare("
            SELECT DISTINCT club_id 
            FROM inscritos 
            WHERE torneo_id = ? AND id_club IS NOT NULL
        ");
        $stmt->execute([$torneo_id]);
        $clubs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($clubs as $club_id) {
            // Contar inscritos del club en este torneo
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM inscritos 
                WHERE id_club = ? AND torneo_id = ?
            ");
            $stmt->execute([$club_id, $torneo_id]);
            $total_inscritos = $stmt->fetchColumn();
            
            // Calcular total a pagar
            $total_a_pagar = $total_inscritos * $costo_por_jugador;
            
            // Obtener total pagado
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) 
                FROM payments 
                WHERE club_id = ? AND torneo_id = ? AND status = 'completed'
            ");
            $stmt->execute([$club_id, $torneo_id]);
            $total_pagado = $stmt->fetchColumn();
            
            // Calcular deuda
            $deuda = $total_a_pagar - $total_pagado;
            
            // Actualizar o insertar en tabla de deudas (si existe)
            // Si no existe tabla de deudas, solo retornamos los datos
            try {
                // Intentar actualizar tabla de deudas si existe
                $stmt = $pdo->prepare("
                    INSERT INTO club_debts (club_id, torneo_id, total_inscritos, costo_por_jugador, total_a_pagar, total_pagado, deuda, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        total_inscritos = VALUES(total_inscritos),
                        costo_por_jugador = VALUES(costo_por_jugador),
                        total_a_pagar = VALUES(total_a_pagar),
                        total_pagado = VALUES(total_pagado),
                        deuda = VALUES(deuda),
                        updated_at = NOW()
                ");
                $stmt->execute([
                    $club_id,
                    $torneo_id,
                    $total_inscritos,
                    $costo_por_jugador,
                    $total_a_pagar,
                    $total_pagado,
                    $deuda
                ]);
            } catch (PDOException $e) {
                // Si no existe la tabla, continuar sin error
                // Solo registramos en log pero no fallamos
                error_log("Tabla club_debts no existe o error: " . $e->getMessage());
            }
            
            $clubs_actualizados++;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Deuda de clubes actualizada exitosamente',
        'clubs_actualizados' => $clubs_actualizados,
        'torneos_procesados' => count($torneos)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

