<?php
/**
 * Script para regenerar la segunda ronda del torneo ID 4
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Core/TorneoMesaAsignacionResolver.php';

$torneo_id = 4;
$ronda = 2;

$pdo = DB::pdo();

$stmtT = $pdo->prepare('SELECT modalidad, rondas FROM tournaments WHERE id = ?');
$stmtT->execute([$torneo_id]);
$tInfo = $stmtT->fetch(PDO::FETCH_ASSOC);
if (!$tInfo) {
    fwrite(STDERR, "Torneo no encontrado.\n");
    exit(1);
}
$modalidad = (int)($tInfo['modalidad'] ?? 0);
$totalRondas = (int)($tInfo['rondas'] ?? 0);

echo "=== REGENERANDO RONDA $ronda DEL TORNEO ID $torneo_id (modalidad $modalidad) ===\n\n";

// Eliminar la ronda 2 actual
echo "1. Eliminando ronda $ronda existente...\n";
$stmt = $pdo->prepare("DELETE FROM partiresul WHERE id_torneo = ? AND partida = ?");
$stmt->execute([$torneo_id, $ronda]);
echo "   ✓ Ronda $ronda eliminada\n\n";

echo "2. Generando ronda $ronda (antes conviene actualizar estadísticas desde el panel si hay partidas previas)...\n";

$estrategia = $modalidad === TorneoMesaAsignacionResolver::MODALIDAD_EQUIPOS ? 'secuencial' : 'separar';
$resultado = $modalidad === TorneoMesaAsignacionResolver::MODALIDAD_EQUIPOS
    ? TorneoMesaAsignacionResolver::generarAsignacionRondaEquipos($torneo_id, $ronda, $totalRondas, $estrategia)
    : TorneoMesaAsignacionResolver::servicioPorModalidad($modalidad)->generarAsignacionRonda($torneo_id, $ronda, $totalRondas, $estrategia);

if ($resultado['success']) {
    echo "   ✓ Ronda $ronda generada exitosamente\n";
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
    echo "   ✗ Error al generar ronda $ronda: {$resultado['message']}\n";
    exit(1);
}

echo "\n=== FIN DE LA REGENERACIÓN ===\n";












