<?php
/**
 * Generar Rondas del Torneo
 */

// Verificar que la tabla partiresul existe
if (!$tabla_partiresul_existe) {
    echo '<div class="alert alert-danger">';
    echo '<h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Tabla partiresul no encontrada</h6>';
    echo '<p class="mb-2">La tabla <code>partiresul</code> no existe. Para generar rondas, debe crear esta tabla primero.</p>';
    echo '<p class="mb-0">Ejecute: <code>php scripts/migrate_partiresul_table.php</code></p>';
    echo '</div>';
    return;
}

// Verificar si el torneo está finalizado (admin_general puede generar para correcciones)
$torneo_finalizado = isset($torneo['finalizado']) && $torneo['finalizado'] == 1;
$admin_general_puede_corregir = Auth::isAdminGeneral();

// Procesar generación de ronda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_ronda'])) {
    if ($torneo_finalizado && !$admin_general_puede_corregir) {
        $error_message = 'No se pueden generar rondas en un torneo finalizado';
    } else {
        require_once __DIR__ . '/../../config/csrf.php';
        CSRF::validate();
        
        $numero_ronda = (int)($_POST['numero_ronda'] ?? 0);
        $mesas = (int)($_POST['mesas'] ?? 0);
        
        if ($numero_ronda <= 0 || $mesas <= 0) {
            $error_message = 'Debe especificar el número de ronda y cantidad de mesas';
        } else {
        try {
            // Obtener inscritos confirmados (pago verificado o inscripción en sitio)
            $stmt = $pdo->prepare("
                SELECT id_usuario, id_club, posicion
                FROM inscritos
                WHERE torneo_id = ? 
                  AND estatus = 'confirmado'
                ORDER BY posicion ASC, id ASC
            ");
            $stmt->execute([$torneo_id]);
            $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($inscritos)) {
                $error_message = 'No hay inscritos activos para generar rondas';
            } else {
                // Algoritmo simple de asignación (puede mejorarse)
                $inscritos_por_mesa = ceil(count($inscritos) / $mesas);
                $mesa_actual = 1;
                $secuencia = 1;
                
                $pdo->beginTransaction();
                
                foreach ($inscritos as $index => $inscrito) {
                    if ($index > 0 && $index % $inscritos_por_mesa == 0 && $mesa_actual < $mesas) {
                        $mesa_actual++;
                        $secuencia = 1;
                    }
                    
                    // Insertar en partiresul (sin resultados aún)
                    $stmt = $pdo->prepare("
                        INSERT INTO partiresul (
                            id_torneo, partida, mesa, secuencia, id_usuario,
                            resultado1, resultado2, efectividad, ff,
                            registrado_por, registrado
                        ) VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, ?, 0)
                    ");
                    $stmt->execute([
                        $torneo_id,
                        $numero_ronda,
                        $mesa_actual,
                        $secuencia,
                        $inscrito['id_usuario'],
                        Auth::user()['id']
                    ]);
                    
                    $secuencia++;
                }
                
                $pdo->commit();
                
                header('Location: index.php?page=tournament_admin&torneo_id=' . $torneo_id . '&action=generar_rondas&success=' . urlencode("Ronda #{$numero_ronda} generada exitosamente con {$mesas} mesas"));
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'Error al generar ronda: ' . $e->getMessage();
        }
    }
    }
}

// Obtener última ronda generada
$stmt = $pdo->prepare("SELECT MAX(partida) as ultima_ronda FROM partiresul WHERE id_torneo = ?");
$stmt->execute([$torneo_id]);
$ultima_ronda = (int)$stmt->fetchColumn() ?: 0;
$siguiente_ronda = $ultima_ronda + 1;

// Obtener total de inscritos activos
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM inscritos
    WHERE torneo_id = ? AND estatus = 'confirmado'
");
$stmt->execute([$torneo_id]);
$total_inscritos = (int)$stmt->fetchColumn();
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-shuffle me-2"></i>Generar Rondas
        </h5>
    </div>
    <div class="card-body">
        <?php if ($torneo_finalizado && !$admin_general_puede_corregir): ?>
            <div class="alert alert-danger">
                <i class="fas fa-lock me-2"></i>
                <strong>Torneo Finalizado:</strong> No se pueden generar rondas en un torneo finalizado.
            </div>
        <?php elseif ($torneo_finalizado && $admin_general_puede_corregir): ?>
            <div class="alert alert-warning mb-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Modo corrección (Admin General):</strong> Este torneo está finalizado. Puede generar rondas adicionales para atender solicitudes de corrección.
            </div>
        <?php endif; ?>
        <?php if (!$torneo_finalizado || $admin_general_puede_corregir): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <h6 class="alert-heading">
                        <i class="fas fa-info-circle me-2"></i>Información
                    </h6>
                    <ul class="mb-0">
                        <li>Total de inscritos activos: <strong><?= $total_inscritos ?></strong></li>
                        <li>Última ronda generada: <strong><?= $ultima_ronda > 0 ? "Ronda #{$ultima_ronda}" : "Ninguna" ?></strong></li>
                        <li>Próxima ronda: <strong>Ronda #<?= $siguiente_ronda ?></strong></li>
                    </ul>
                </div>
            </div>
        </div>

        <form method="POST" action="">
            <?= CSRF::input(); ?>
            <input type="hidden" name="generar_ronda" value="1">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Número de Ronda <span class="text-danger">*</span></label>
                        <input type="number" name="numero_ronda" class="form-control" 
                               value="<?= $siguiente_ronda ?>" min="1" required>
                        <small class="text-muted">Ronda a generar</small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cantidad de Mesas <span class="text-danger">*</span></label>
                        <input type="number" name="mesas" class="form-control" 
                               value="<?= max(1, ceil($total_inscritos / 4)) ?>" min="1" required>
                        <small class="text-muted">Número de mesas para esta ronda</small>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Advertencia:</strong> Al generar una nueva ronda, se asignarán automáticamente los inscritos activos a las mesas.
                El sistema distribuirá los jugadores de manera equitativa.
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="admin_torneo.php?action=panel&torneo_id=<?= $torneo_id ?>" 
                   class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-magic me-2"></i>Generar Ronda
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

