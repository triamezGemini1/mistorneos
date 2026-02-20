<?php
/**
 * Reportar Pago de Inscripción
 * Permite a usuarios reportar el pago de su inscripción en un torneo
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$payment_id = (int)($_GET['payment_id'] ?? 0);

if ($payment_id <= 0) {
    die('ID de pago no válido');
}

$pdo = DB::pdo();

// Obtener información del pago
$stmt = $pdo->prepare("
    SELECT p.*, t.nombre as torneo_nombre, t.fechator, c.nombre as club_nombre
    FROM payments p
    LEFT JOIN tournaments t ON p.torneo_id = t.id
    LEFT JOIN clubes c ON p.club_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die('Pago no encontrado');
}

// Verificar si el pago ya fue confirmado
$ya_confirmado = $payment['status'] === 'completed' || $payment['status'] === 'confirmado';

// Procesar reporte de pago
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reportar_pago') {
    try {
        if ($ya_confirmado) {
            throw new Exception('Este pago ya fue confirmado');
        }
        
        $tipo_pago = trim($_POST['tipo_pago'] ?? '');
        $referencia = trim($_POST['referencia'] ?? '');
        $banco = trim($_POST['banco'] ?? '');
        $fecha_pago = trim($_POST['fecha_pago'] ?? date('Y-m-d'));
        $observaciones = trim($_POST['observaciones'] ?? '');
        
        if (empty($tipo_pago)) {
            throw new Exception('Debes seleccionar el tipo de pago');
        }
        
        if (in_array($tipo_pago, ['transferencia', 'deposito']) && empty($referencia)) {
            throw new Exception('Debes proporcionar el número de referencia');
        }
        
        $pdo->beginTransaction();
        
        // Actualizar pago
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET method = ?, reference = ?, status = 'pendiente', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $tipo_pago,
            $referencia ?: null,
            $payment_id
        ]);
        
        $pdo->commit();
        $success_message = 'Pago reportado exitosamente. El administrador revisará tu pago y lo confirmará.';
        
        // Recargar información del pago
        $stmt = $pdo->prepare("
            SELECT p.*, t.nombre as torneo_nombre, t.fechator, c.nombre as club_nombre
            FROM payments p
            LEFT JOIN tournaments t ON p.torneo_id = t.id
            LEFT JOIN clubes c ON p.club_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        $ya_confirmado = $payment['status'] === 'confirmado';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Error al reportar pago: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportar Pago - <?= htmlspecialchars($payment['torneo_nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .container-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 2rem;
            max-width: 700px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="container-card">
            <div class="text-center mb-4">
                <i class="fas fa-money-bill-wave fa-3x text-success mb-3"></i>
                <h2>Reportar Pago</h2>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <!-- Información del Pago -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Información del Pago
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Torneo:</strong><br>
                            <?= htmlspecialchars($payment['torneo_nombre']) ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Fecha del Torneo:</strong><br>
                            <?= date('d/m/Y', strtotime($payment['fechator'])) ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Monto:</strong><br>
                            <span class="text-success fw-bold">$<?= number_format($payment['amount'], 2) ?></span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Estado:</strong><br>
                            <?php
                            $estados = [
                                'pending' => ['label' => 'Pendiente', 'class' => 'warning'],
                                'completed' => ['label' => 'Confirmado', 'class' => 'success'],
                                'confirmado' => ['label' => 'Confirmado', 'class' => 'success'],
                                'rechazado' => ['label' => 'Rechazado', 'class' => 'danger']
                            ];
                            $estado = $estados[$payment['status']] ?? ['label' => $payment['status'], 'class' => 'secondary'];
                            ?>
                            <span class="badge bg-<?= $estado['class'] ?>"><?= $estado['label'] ?></span>
                        </div>
                        <?php if ($payment['club_nombre']): ?>
                        <div class="col-md-6 mb-3">
                            <strong>Club:</strong><br>
                            <?= htmlspecialchars($payment['club_nombre']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Formulario de Reporte -->
            <?php if (!$ya_confirmado): ?>
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Reportar Información de Pago
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="reportar_pago">
                        
                        <div class="mb-3">
                            <label for="tipo_pago" class="form-label">Tipo de Pago <span class="text-danger">*</span></label>
                            <select class="form-select" id="tipo_pago" name="tipo_pago" required>
                                <option value="">Seleccionar...</option>
                                <option value="efectivo" <?= ($payment['method'] ?? '') === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                <option value="transferencia" <?= ($payment['method'] ?? '') === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                                <option value="deposito" <?= ($payment['method'] ?? '') === 'deposito' ? 'selected' : '' ?>>Depósito</option>
                                <option value="zelle" <?= ($payment['method'] ?? '') === 'zelle' ? 'selected' : '' ?>>Zelle</option>
                                <option value="paypal" <?= ($payment['method'] ?? '') === 'paypal' ? 'selected' : '' ?>>PayPal</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="referencia_group" style="display: none;">
                            <label for="referencia" class="form-label">Número de Referencia <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="referencia" 
                                   name="referencia" 
                                   value="<?= htmlspecialchars($payment['reference'] ?? '') ?>"
                                   placeholder="Número de referencia, transacción, etc.">
                        </div>
                        
                        
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-check me-2"></i>Reportar Pago
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Este pago ya fue confirmado.</strong>
            </div>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <a href="tournament_register.php?torneo_id=<?= $payment['torneo_id'] ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </a>
            </div>
        </div>
    </div>
    
    <script>
    document.getElementById('tipo_pago').addEventListener('change', function() {
        const tipo = this.value;
        const referenciaGroup = document.getElementById('referencia_group');
        const referencia = document.getElementById('referencia');
        
        if (tipo === 'transferencia' || tipo === 'deposito' || tipo === 'zelle' || tipo === 'paypal') {
            referenciaGroup.style.display = 'block';
            referencia.required = true;
        } else {
            referenciaGroup.style.display = 'none';
            referencia.required = false;
        }
    });
    
    // Trigger on load
    document.getElementById('tipo_pago').dispatchEvent(new Event('change'));
    </script>
</body>
</html>

