<?php
/**
 * Script para regenerar la segunda ronda del torneo ID 4
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/MesaAsignacionService.php';

$torneo_id = 4;
$ronda = 2;

$pdo = DB::pdo();

echo "=== REGENERANDO RONDA 2 DEL TORNEO ID $torneo_id ===\n\n";

// Eliminar la ronda 2 actual
echo "1. Eliminando ronda 2 existente...\n";
$stmt = $pdo->prepare("DELETE FROM partiresul WHERE id_torneo = ? AND partida = ?");
$stmt->execute([$torneo_id, $ronda]);
echo "   ✓ Ronda 2 eliminada\n\n";

// Generar nueva ronda 2
echo "2. Generando nueva ronda 2...\n";
$mesaService = new MesaAsignacionService();
$resultado = $mesaService->generarAsignacionRonda($torneo_id, $ronda, 3);

if ($resultado['success']) {
    echo "   ✓ Ronda 2 generada exitosamente\n";
    echo "   - Total de mesas: {$resultado['total_mesas']}\n";
    echo "   - Jugadores con BYE: {$resultado['jugadores_bye']}\n\n";
    
    // Verificar asignación
    echo "3. Verificando asignación...\n";
    $stmt = $pdo->prepare("
        SELECT pr.mesa, COUNT(*) as jugadores
        FROM partiresul pr
        WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
        GROUP BY pr.mesa
        ORDER BY pr.mesa
    ");
    $stmt->execute([$torneo_id, $ronda]);
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $mesas_incompletas = 0;
    foreach ($mesas as $mesa) {
        if ($mesa['jugadores'] < 4) {
            $mesas_incompletas++;
            echo "   ⚠️ Mesa {$mesa['mesa']}: {$mesa['jugadores']} jugadores (incompleta)\n";
        } else {
            echo "   ✓ Mesa {$mesa['mesa']}: {$mesa['jugadores']} jugadores\n";
        }
    }
    
    if ($mesas_incompletas > 0) {
        echo "\n   ⚠️ ADVERTENCIA: Hay $mesas_incompletas mesa(s) incompleta(s)\n";
    } else {
        echo "\n   ✓ Todas las mesas están completas (4 jugadores cada una)\n";
    }
    
    // Verificar jugadores no asignados
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM inscritos i
        WHERE i.torneo_id = ? AND i.estatus = 'confirmado'
        AND i.id_usuario NOT IN (
            SELECT DISTINCT pr.id_usuario
            FROM partiresul pr
            WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
        )
    ");
    $stmt->execute([$torneo_id, $torneo_id, $ronda]);
    $no_asignados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($no_asignados['total'] > 0) {
        echo "\n   ⚠️ ADVERTENCIA: Hay {$no_asignados['total']} jugador(es) no asignado(s)\n";
        
        // Listar jugadores no asignados
        $stmt = $pdo->prepare("
            SELECT i.id_usuario, u.nombre, u.cedula
            FROM inscritos i
            INNER JOIN usuarios u ON i.id_usuario = u.id
            WHERE i.torneo_id = ? AND i.estatus = 'confirmado'
            AND i.id_usuario NOT IN (
                SELECT DISTINCT pr.id_usuario
                FROM partiresul pr
                WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa > 0
            )
        ");
        $stmt->execute([$torneo_id, $torneo_id, $ronda]);
        $jugadores_no_asignados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($jugadores_no_asignados as $j) {
            echo "      - {$j['nombre']} (ID: {$j['id_usuario']}, Cédula: {$j['cedula']})\n";
        }
    } else {
        echo "\n   ✓ Todos los jugadores están asignados\n";
    }
    
} else {
    echo "   ✗ Error al generar ronda 2: {$resultado['message']}\n";
    exit(1);
}

echo "\n=== FIN DE LA REGENERACIÓN ===\n";












