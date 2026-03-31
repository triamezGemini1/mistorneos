<?php
/**
 * Ver Recibo/Estado del Pago
 * Permite a usuarios ver el estado de sus pagos reportados
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/app_helpers.php';

$pdo = DB::pdo();
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$cedula = trim($_GET['cedula'] ?? '');

$error = '';
$torneo = null;
$usuario = null;
$inscripciones = [];
$reportes_pago = [];

// Obtener información del torneo
if ($torneo_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, c.nombre as club_nombre
            FROM tournaments t
            LEFT JOIN clubes c ON t.club_responsable = c.id
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
            
            // Obtener reportes de pago
            $stmt = $pdo->prepare("
                SELECT * FROM reportes_pago_usuarios
                WHERE id_usuario = ? AND torneo_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$usuario['id'], $torneo_id]);
            $reportes_pago = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = 'Usuario no encontrado con la cédula proporcionada';
        }
    } catch (Exception $e) {
        error_log("Error obteniendo usuario: " . $e->getMessage());
        $error = 'Error al buscar usuario';
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Ver Recibo - <?= htmlspecialchars($torneo['nombre'] ?? 'Evento Masivo') ?></title>
    
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
                <i class="fas fa-receipt mr-3 text-yellow-400"></i>Recibo de Pago
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
                    <p class="text-sm text-gray-600">Inscripciones</p>
                    <p class="text-lg font-semibold text-purple-600"><?= count($inscripciones) ?> inscripción(es)</p>
                </div>
            </div>
        </div>
        
        <!-- Información del Evento -->
        <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-calendar-alt mr-2 text-purple-600"></i>Información del Evento
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Evento</p>
                    <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($torneo['nombre']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Fecha</p>
                    <p class="text-lg font-semibold text-gray-900"><?= date('d/m/Y', strtotime($torneo['fechator'])) ?></p>
                </div>
                <?php if ($torneo['lugar']): ?>
                <div>
                    <p class="text-sm text-gray-600">Lugar</p>
                    <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($torneo['lugar']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($torneo['costo'] > 0): ?>
                <div>
                    <p class="text-sm text-gray-600">Costo por Persona</p>
                    <p class="text-lg font-semibold text-green-600">$<?= number_format($torneo['costo'], 2) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Reportes de Pago -->
        <?php if (!empty($reportes_pago)): ?>
        <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">
                <i class="fas fa-money-bill-wave mr-2 text-purple-600"></i>Historial de Pagos Reportados
            </h2>
            
            <div class="space-y-6">
                <?php foreach ($reportes_pago as $reporte): ?>
                <div class="border-2 border-gray-200 rounded-lg p-6 hover:shadow-lg transition-shadow">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <p class="text-2xl font-bold text-gray-900">
                                Reporte #<?= $reporte['id'] ?>
                            </p>
                            <p class="text-sm text-gray-600 mt-1">
                                Reportado el <?= date('d/m/Y H:i', strtotime($reporte['created_at'])) ?>
                            </p>
                        </div>
                        <span class="px-4 py-2 rounded-full text-sm font-bold
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
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Tipo de Pago</p>
                            <p class="text-lg font-semibold text-gray-900"><?= ucfirst($reporte['tipo_pago']) ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Monto</p>
                            <p class="text-2xl font-bold text-green-600">$<?= number_format($reporte['monto'], 2) ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Fecha del Pago</p>
                            <p class="text-lg font-semibold text-gray-900"><?= date('d/m/Y', strtotime($reporte['fecha'])) ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Hora del Pago</p>
                            <p class="text-lg font-semibold text-gray-900"><?= $reporte['hora'] ?></p>
                        </div>
                        <?php if ($reporte['banco']): ?>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Banco</p>
                            <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($reporte['banco']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($reporte['referencia']): ?>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Referencia</p>
                            <p class="text-lg font-semibold text-gray-900 font-mono"><?= htmlspecialchars($reporte['referencia']) ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Cantidad de Inscritos</p>
                            <p class="text-lg font-semibold text-gray-900"><?= $reporte['cantidad_inscritos'] ?> persona(s)</p>
                        </div>
                    </div>
                    
                    <?php if ($reporte['comentarios']): ?>
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                        <p class="text-sm text-gray-600 mb-1">Comentarios</p>
                        <p class="text-gray-900"><?= nl2br(htmlspecialchars($reporte['comentarios'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8 text-center">
            <i class="fas fa-inbox text-5xl text-gray-400 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-900 mb-2">No hay reportes de pago</h3>
            <p class="text-gray-600 mb-6">Aún no has reportado ningún pago para este evento.</p>
            <a href="reportar_pago_evento_masivo.php?torneo_id=<?= $torneo_id ?>&cedula=<?= urlencode($cedula) ?>" 
               class="inline-block bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition-all">
                <i class="fas fa-money-bill-wave mr-2"></i>Reportar Pago
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Acciones -->
        <div class="flex flex-col sm:flex-row gap-3 justify-center mt-6">
            <a href="reportar_pago_evento_masivo.php?torneo_id=<?= $torneo_id ?>&cedula=<?= urlencode($cedula) ?>" 
               class="inline-flex items-center justify-center bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition-all">
                <i class="fas fa-money-bill-wave mr-2"></i>Reportar Nuevo Pago
            </a>
            <a href="landing.php#eventos-masivos" 
               class="inline-flex items-center justify-center bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-all">
                <i class="fas fa-arrow-left mr-2"></i>Volver a Eventos
            </a>
        </div>
        
        <?php elseif ($torneo && empty($cedula)): ?>
        <!-- Formulario de Búsqueda por Cédula -->
        <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-search mr-2 text-purple-600"></i>Buscar Recibo
            </h2>
            <p class="text-gray-600 mb-6">Ingresa tu cédula para ver tus recibos de pago</p>
            
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
</body>
</html>

