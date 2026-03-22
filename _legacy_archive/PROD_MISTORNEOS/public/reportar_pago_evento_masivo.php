<?php
/**
 * Reportar Pago de Inscripción en Evento Masivo
 * Permite a usuarios reportar el pago de su inscripción
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../lib/app_helpers.php';

$pdo = DB::pdo();
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$cedula = trim($_GET['cedula'] ?? '');

$error = '';
$success = '';
$torneo = null;
$inscripciones = [];
$usuario = null;

// Obtener información del torneo
if ($torneo_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                t.*, 
                c.nombre as club_nombre, 
                t.cuenta_id,
                cb.banco as cuenta_banco,
                cb.numero_cuenta as cuenta_numero,
                cb.tipo_cuenta as cuenta_tipo,
                cb.telefono_afiliado as cuenta_telefono,
                cb.nombre_propietario as cuenta_propietario,
                cb.cedula_propietario as cuenta_cedula
            FROM tournaments t
            LEFT JOIN clubes c ON t.club_responsable = c.id
            LEFT JOIN cuentas_bancarias cb ON t.cuenta_id = cb.id
            WHERE t.id = ? AND t.es_evento_masivo = 1
        ");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$torneo) {
            $error = 'Evento no encontrado';
        }
    } catch (Exception $e) {
        error_log("Error obteniendo torneo: " . $e->getMessage());
        $error = 'Error al cargar la información del evento';
    }
} else {
    $error = 'Debe especificar un evento';
}

// Si se proporciona cédula, buscar usuario e inscripciones
if ($torneo && !empty($cedula)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE cedula = ?");
        $stmt->execute([$cedula]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            // Obtener inscripciones del usuario en este torneo
            $stmt = $pdo->prepare("
                SELECT i.*, u.nombre as usuario_nombre, u.cedula as usuario_cedula
                FROM inscritos i
                INNER JOIN usuarios u ON i.id_usuario = u.id
                WHERE i.torneo_id = ? AND i.id_usuario = ?
            ");
            $stmt->execute([$torneo_id, $usuario['id']]);
            $inscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error obteniendo usuario: " . $e->getMessage());
    }
}

// Procesar reporte de pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $torneo && $usuario) {
    CSRF::validate();
    
    $inscrito_id = (int)($_POST['inscrito_id'] ?? 0);
    $cantidad_inscritos = (int)($_POST['cantidad_inscritos'] ?? 1);
    $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
    $hora = trim($_POST['hora'] ?? date('H:i'));
    $tipo_pago = trim($_POST['tipo_pago'] ?? '');
    $banco = trim($_POST['banco'] ?? '');
    $monto = (float)($_POST['monto'] ?? 0);
    $referencia = trim($_POST['referencia'] ?? '');
    $comentarios = trim($_POST['comentarios'] ?? '');
    
    // Validaciones
    if (empty($tipo_pago) || !in_array($tipo_pago, ['transferencia', 'pagomovil', 'efectivo'])) {
        $error = 'Debe seleccionar un tipo de pago válido';
    } elseif ($monto <= 0) {
        $error = 'El monto debe ser mayor a cero';
    } elseif (empty($fecha)) {
        $error = 'Debe proporcionar la fecha del pago';
    } elseif (empty($hora)) {
        $error = 'Debe proporcionar la hora del pago';
    } elseif (in_array($tipo_pago, ['transferencia', 'pagomovil']) && empty($banco)) {
        $error = 'Debe proporcionar el banco para este tipo de pago';
    } elseif (in_array($tipo_pago, ['transferencia', 'pagomovil']) && empty($referencia)) {
        $error = 'Debe proporcionar el número de referencia';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Obtener cuenta_id del torneo
            $cuenta_id = !empty($torneo['cuenta_id']) ? (int)$torneo['cuenta_id'] : null;
            
            // Insertar reporte de pago
            $stmt = $pdo->prepare("
                INSERT INTO reportes_pago_usuarios (
                    id_usuario, torneo_id, cuenta_id, inscrito_id, cantidad_inscritos,
                    fecha, hora, tipo_pago, banco, monto, referencia, comentarios, estatus
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
            ");
            $stmt->execute([
                $usuario['id'],
                $torneo_id,
                $cuenta_id,
                $inscrito_id > 0 ? $inscrito_id : null,
                $cantidad_inscritos,
                $fecha,
                $hora,
                $tipo_pago,
                $banco ?: null,
                $monto,
                $referencia ?: null,
                $comentarios ?: null
            ]);
            
            $pdo->commit();
            $success = '¡Reporte de pago enviado exitosamente! El administrador revisará tu pago y lo confirmará.';
            
            // Limpiar formulario
            $_POST = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error al reportar pago: ' . $e->getMessage();
        }
    }
}

// Obtener reportes de pago existentes del usuario
$reportes_pago = [];
if ($usuario && $torneo) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM reportes_pago_usuarios
            WHERE id_usuario = ? AND torneo_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$usuario['id'], $torneo_id]);
        $reportes_pago = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo reportes de pago: " . $e->getMessage());
    }
}

$csrf_token = CSRF::token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Reportar Pago - <?= htmlspecialchars($torneo['nombre'] ?? 'Evento Masivo') ?></title>
    
    <!-- Tailwind CSS (compilado localmente para mejor rendimiento) -->
    <link rel="stylesheet" href="assets/dist/output.css">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-600 via-purple-700 to-indigo-800 min-h-screen">
    
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="landing.php#eventos-masivos" class="inline-flex items-center text-white/90 hover:text-white mb-4">
                <i class="fas fa-arrow-left mr-2"></i> Volver a Eventos
            </a>
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
                <i class="fas fa-money-bill-wave mr-3 text-yellow-400"></i>Reportar Pago
            </h1>
            <?php if ($torneo): ?>
            <p class="text-white/80 text-lg"><?= htmlspecialchars($torneo['nombre']) ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Mensajes -->
        <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <p><?= htmlspecialchars($success) ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($torneo && $usuario): ?>
        <!-- Información del Usuario -->
        <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-user mr-2 text-purple-600"></i>Información del Usuario
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">ID Usuario</p>
                    <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($usuario['id']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Nombre</p>
                    <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($usuario['nombre']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Cédula</p>
                    <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($usuario['cedula']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Inscripciones en este evento</p>
                    <p class="text-lg font-semibold text-purple-600"><?= count($inscripciones) ?></p>
                </div>
            </div>
        </div>
        
        <!-- Formulario de Reporte de Pago -->
        <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">
                <i class="fas fa-edit mr-2 text-purple-600"></i>Nuevo Reporte de Pago
            </h2>
            
            <form method="POST" action="" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <!-- Cantidad de Inscritos -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        ¿A cuántas personas estás inscribiendo con este pago? <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="cantidad_inscritos" min="1" max="10" value="<?= htmlspecialchars($_POST['cantidad_inscritos'] ?? count($inscripciones) ?: 1) ?>" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <small class="text-gray-500">Si inscribes a más de 1 persona, indica la cantidad</small>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Fecha -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Fecha del Pago <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="fecha" value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <!-- Hora -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Hora del Pago <span class="text-red-500">*</span>
                        </label>
                        <input type="time" name="hora" value="<?= htmlspecialchars($_POST['hora'] ?? date('H:i')) ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>
                
                <!-- Tipo de Pago -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Tipo de Pago <span class="text-red-500">*</span>
                    </label>
                    <select name="tipo_pago" id="tipo_pago" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="">Seleccione...</option>
                        <option value="transferencia" <?= ($_POST['tipo_pago'] ?? '') === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                        <option value="pagomovil" <?= ($_POST['tipo_pago'] ?? '') === 'pagomovil' ? 'selected' : '' ?>>Pago Móvil</option>
                        <option value="efectivo" <?= ($_POST['tipo_pago'] ?? '') === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                    </select>
                </div>
                
                <!-- Banco (solo para transferencia y pagomovil) -->
                <div id="banco_group" style="display: none;">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Banco <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="banco" id="banco" value="<?= htmlspecialchars($_POST['banco'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           placeholder="Ej: Banco de Venezuela, Banesco, etc.">
                </div>
                
                <!-- Monto -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Monto Pagado ($) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="monto" step="0.01" min="0.01" value="<?= htmlspecialchars($_POST['monto'] ?? '') ?>" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           placeholder="0.00">
                </div>
                
                <!-- Referencia (solo para transferencia y pagomovil) -->
                <div id="referencia_group" style="display: none;">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Número de Referencia <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="referencia" id="referencia" value="<?= htmlspecialchars($_POST['referencia'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           placeholder="Número de referencia de la transacción">
                </div>
                
                <!-- Comentarios -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Comentarios (Opcional)
                    </label>
                    <textarea name="comentarios" rows="3"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                              placeholder="Información adicional sobre el pago..."><?= htmlspecialchars($_POST['comentarios'] ?? '') ?></textarea>
                </div>
                
                <!-- Botón para mostrar información de pago -->
                <?php if (!empty($torneo['cuenta_id']) && !empty($torneo['cuenta_banco'])): ?>
                <div class="pt-4">
                    <button type="button" onclick="mostrarInfoPago()" id="btnPagar" class="w-full bg-gradient-to-r from-green-600 to-green-700 text-white py-4 rounded-lg font-bold text-lg hover:from-green-700 hover:to-green-800 transition-all shadow-lg hover:shadow-xl transform hover:scale-105">
                        <i class="fas fa-credit-card mr-2"></i>Ver Datos de Pago
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Botón de Envío -->
                <div class="pt-4">
                    <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-4 rounded-lg font-bold text-lg hover:from-purple-700 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl transform hover:scale-105">
                        <i class="fas fa-paper-plane mr-2"></i>Enviar Reporte de Pago
                    </button>
                </div>
            </form>
            
            <!-- Botón de Retorno al Landing -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <a href="landing.php#eventos-masivos" class="w-full inline-flex items-center justify-center bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-all">
                    <i class="fas fa-home mr-2"></i>Volver al Inicio
                </a>
            </div>
        </div>
        
        <!-- Historial de Reportes de Pago -->
        <?php if (!empty($reportes_pago)): ?>
        <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8">
            <h2 class="text-xl font-bold text-gray-900 mb-6">
                <i class="fas fa-history mr-2 text-purple-600"></i>Historial de Reportes de Pago
            </h2>
            
            <div class="space-y-4">
                <?php foreach ($reportes_pago as $reporte): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="font-semibold text-gray-900">
                                Reporte #<?= $reporte['id'] ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                <?= date('d/m/Y H:i', strtotime($reporte['created_at'])) ?>
                            </p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-sm font-semibold
                            <?php
                            switch($reporte['estatus']) {
                                case 'confirmado':
                                    echo 'bg-green-100 text-green-800';
                                    break;
                                case 'rechazado':
                                    echo 'bg-red-100 text-red-800';
                                    break;
                                default:
                                    echo 'bg-yellow-100 text-yellow-800';
                            }
                            ?>">
                            <?= ucfirst($reporte['estatus']) ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-sm">
                        <div>
                            <p class="text-gray-600">Tipo</p>
                            <p class="font-semibold"><?= ucfirst($reporte['tipo_pago']) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Monto</p>
                            <p class="font-semibold text-green-600">$<?= number_format($reporte['monto'], 2) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Fecha/Hora</p>
                            <p class="font-semibold"><?= date('d/m/Y', strtotime($reporte['fecha'])) ?> <?= $reporte['hora'] ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Cantidad</p>
                            <p class="font-semibold"><?= $reporte['cantidad_inscritos'] ?> persona(s)</p>
                        </div>
                    </div>
                    <?php if ($reporte['referencia']): ?>
                    <div class="mt-2 text-sm">
                        <p class="text-gray-600">Referencia: <span class="font-semibold"><?= htmlspecialchars($reporte['referencia']) ?></span></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Botón de Retorno al Landing -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <a href="landing.php#eventos-masivos" class="w-full inline-flex items-center justify-center bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-all">
                    <i class="fas fa-home mr-2"></i>Volver al Inicio
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php elseif ($torneo && empty($cedula)): ?>
        <!-- Formulario de Búsqueda por Cédula -->
        <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-search mr-2 text-purple-600"></i>Buscar Inscripción
            </h2>
            <p class="text-gray-600 mb-6">Ingresa tu cédula para reportar tu pago</p>
            
            <form method="GET" action="" class="space-y-4">
                <input type="hidden" name="torneo_id" value="<?= $torneo_id ?>">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Cédula <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="cedula" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           placeholder="Ej: V12345678">
                </div>
                
                <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 rounded-lg font-bold hover:from-purple-700 hover:to-indigo-700 transition-all">
                    <i class="fas fa-search mr-2"></i>Buscar
                </button>
            </form>
        </div>
        
        <?php elseif (!$torneo): ?>
        <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
            <i class="fas fa-exclamation-triangle text-5xl text-red-500 mb-4"></i>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Evento no disponible</h2>
            <p class="text-gray-600 mb-6"><?= htmlspecialchars($error ?: 'El evento seleccionado no está disponible') ?></p>
            <a href="landing.php#eventos-masivos" class="inline-block bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition-all">
                <i class="fas fa-arrow-left mr-2"></i>Volver a Eventos
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Formulario Flotante de Información de Pago -->
    <?php if ($torneo && $usuario && !empty($torneo['cuenta_id']) && !empty($torneo['cuenta_banco'])): ?>
    <div id="infoPagoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8 w-11/12 max-w-md relative">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-2xl font-bold text-green-800">
                    <i class="fas fa-credit-card mr-2"></i>Datos de Pago
                </h3>
                <button onclick="ocultarInfoPago()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <?php if ($torneo['costo'] > 0): ?>
            <div class="mb-4 p-3 bg-green-50 rounded-lg">
                <p class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-dollar-sign mr-2 text-green-600"></i>
                    Costo: <span class="text-green-700">$<?= number_format($torneo['costo'], 2) ?></span>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="space-y-3">
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Banco</p>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($torneo['cuenta_banco']) ?></p>
                </div>
                
                <?php if (!empty($torneo['cuenta_numero'])): ?>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Número de Cuenta</p>
                    <p class="font-mono font-semibold text-gray-800"><?= htmlspecialchars($torneo['cuenta_numero']) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($torneo['cuenta_tipo'])): ?>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Tipo de Cuenta</p>
                    <p class="font-semibold text-gray-800"><?= ucfirst($torneo['cuenta_tipo']) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($torneo['cuenta_telefono'])): ?>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Teléfono Pago Móvil</p>
                    <p class="font-mono font-semibold text-gray-800"><?= htmlspecialchars($torneo['cuenta_telefono']) ?></p>
                </div>
                <?php endif; ?>
                
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-1">Propietario</p>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($torneo['cuenta_propietario']) ?></p>
                    <p class="text-xs text-gray-500">C.I. <?= htmlspecialchars($torneo['cuenta_cedula']) ?></p>
                </div>
            </div>
            
            <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-1"></i>
                    Después de realizar el pago, completa el formulario y envía el reporte.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
    // Mostrar información de pago
    function mostrarInfoPago() {
        const modal = document.getElementById('infoPagoModal');
        const btnPagar = document.getElementById('btnPagar');
        if (modal) {
            modal.classList.remove('hidden');
            if (btnPagar) {
                btnPagar.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Información de Pago Visible';
                btnPagar.classList.remove('from-green-600', 'to-green-700', 'hover:from-green-700', 'hover:to-green-800');
                btnPagar.classList.add('from-blue-600', 'to-blue-700', 'hover:from-blue-700', 'hover:to-blue-800');
            }
        }
    }
    
    // Ocultar información de pago
    function ocultarInfoPago() {
        const modal = document.getElementById('infoPagoModal');
        const btnPagar = document.getElementById('btnPagar');
        if (modal) {
            modal.classList.add('hidden');
            if (btnPagar) {
                btnPagar.innerHTML = '<i class="fas fa-credit-card mr-2"></i>Ver Datos de Pago';
                btnPagar.classList.remove('from-blue-600', 'to-blue-700', 'hover:from-blue-700', 'hover:to-blue-800');
                btnPagar.classList.add('from-green-600', 'to-green-700', 'hover:from-green-700', 'hover:to-green-800');
            }
        }
    }
    
    // Cerrar modal al hacer clic fuera de él
    document.getElementById('infoPagoModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            ocultarInfoPago();
        }
    });
    
    // Mostrar/ocultar campos según tipo de pago
    document.getElementById('tipo_pago')?.addEventListener('change', function() {
        const tipo = this.value;
        const bancoGroup = document.getElementById('banco_group');
        const referenciaGroup = document.getElementById('referencia_group');
        const banco = document.getElementById('banco');
        const referencia = document.getElementById('referencia');
        
        if (tipo === 'transferencia' || tipo === 'pagomovil') {
            bancoGroup.style.display = 'block';
            referenciaGroup.style.display = 'block';
            banco.required = true;
            referencia.required = true;
        } else {
            bancoGroup.style.display = 'none';
            referenciaGroup.style.display = 'none';
            banco.required = false;
            referencia.required = false;
        }
    });
    
    // Trigger on load
    document.getElementById('tipo_pago')?.dispatchEvent(new Event('change'));
    </script>
</body>
</html>

