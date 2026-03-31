<?php
/**
 * Enviar Notificación por WhatsApp a Administrador de organización
 * Abre WhatsApp Web automáticamente con el mensaje preformateado
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole(['admin_general']);

try {
    $admin_id = (int)($_GET['admin_id'] ?? 0);
    
    if ($admin_id <= 0) {
        throw new Exception('ID de administrador inválido');
    }
    
    $pdo = DB::pdo();
    
    // Obtener información del administrador
    $stmt = $pdo->prepare("
        SELECT 
            u.*, 
            c.nombre as club_principal_nombre,
            c.telefono as club_telefono
        FROM usuarios u
        LEFT JOIN clubes c ON u.club_id = c.id
        WHERE u.id = ? AND u.role = 'admin_club'
    ");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        throw new Exception('Administrador no encontrado');
    }
    
    // Obtener estadísticas del administrador
    require_once __DIR__ . '/../../lib/ClubHelper.php';
    $club_ids = ClubHelper::getClubesSupervised($admin['club_id']);
    
    $stats = [
        'total_clubes' => count($club_ids),
        'total_torneos' => 0,
        'total_inscritos' => 0,
        'total_usuarios' => 0
    ];
    
    if (!empty($club_ids)) {
        $placeholders = implode(',', array_fill(0, count($club_ids), '?'));
        
        // Total de torneos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tournaments WHERE club_responsable IN ($placeholders)");
        $stmt->execute($club_ids);
        $stats['total_torneos'] = (int)$stmt->fetchColumn();
        
        // Total de inscritos
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM inscritos i 
            JOIN tournaments t ON i.torneo_id = t.id 
            WHERE t.club_responsable IN ($placeholders)
        ");
        $stmt->execute($club_ids);
        $stats['total_inscritos'] = (int)$stmt->fetchColumn();
        
        // Total de usuarios
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE club_id IN ($placeholders) AND role = 'usuario' AND status = 0");
        $stmt->execute($club_ids);
        $stats['total_usuarios'] = (int)$stmt->fetchColumn();
    }
    
    // Usar teléfono del administrador o del club principal
    $telefono = $admin['celular'] ?? $admin['club_telefono'] ?? '';
    
    if (empty($telefono)) {
        throw new Exception('No hay teléfono configurado para este administrador');
    }
    
    // Formatear teléfono
    $telefono = preg_replace('/[^0-9]/', '', $telefono);
    if ($telefono && $telefono[0] == '0') {
        $telefono = substr($telefono, 1);
    }
    if ($telefono && strlen($telefono) == 10 && !str_starts_with($telefono, '58')) {
        $telefono = '58' . $telefono;
    }
    
    // Generar mensaje de notificación
    $mensaje = "📋 *NOTIFICACIÓN - LA ESTACIÓN DEL DOMINÓ*\n\n";
    $mensaje .= "Hola *" . $admin['nombre'] . "*\n\n";
    $mensaje .= "━━━━━━━━━━━━━━━━━━\n";
    $mensaje .= "📊 *RESUMEN DE TU GESTIÓN*\n";
    $mensaje .= "━━━━━━━━━━━━━━━━━━\n\n";
    $mensaje .= "🏢 *Club Principal:* " . ($admin['club_principal_nombre'] ?? 'No asignado') . "\n";
    $mensaje .= "📦 *Clubes Gestionados:* " . $stats['total_clubes'] . "\n";
    $mensaje .= "🏆 *Torneos Creados:* " . $stats['total_torneos'] . "\n";
    $mensaje .= "👥 *Usuarios Registrados:* " . $stats['total_usuarios'] . "\n";
    $mensaje .= "📝 *Total Inscritos:* " . $stats['total_inscritos'] . "\n\n";
    $mensaje .= "━━━━━━━━━━━━━━━━━━\n\n";
    $mensaje .= "🌐 *ACCESO AL SISTEMA*\n";
    $mensaje .= "━━━━━━━━━━━━━━━━━━\n\n";
    $mensaje .= "Usuario: *" . $admin['username'] . "*\n";
    $app_url = $_ENV['APP_URL'] ?? 'http://localhost/mistorneos';
    $mensaje .= "URL: " . $app_url . "/public/login.php\n\n";
    $mensaje .= "━━━━━━━━━━━━━━━━━━\n\n";
    $mensaje .= "💡 *RECORDATORIO*\n";
    $mensaje .= "━━━━━━━━━━━━━━━━━━\n\n";
    $mensaje .= "Recuerda mantener actualizada la información de tus clubes y torneos.\n";
    $mensaje .= "Si tienes alguna consulta, no dudes en contactarnos.\n\n";
    $mensaje .= "¡Gracias por ser parte de nuestro proyecto! 🎲\n\n";
    $mensaje .= "_La Estación del Dominó_";
    
    // Generar URL de WhatsApp
    $mensaje_encoded = urlencode($mensaje);
    $whatsapp_url = "https://wa.me/{$telefono}?text={$mensaje_encoded}";
    
    // Redirigir automáticamente a WhatsApp Web
    header("Location: " . $whatsapp_url);
    exit;
    
} catch (Exception $e) {
    // Si hay error, mostrar mensaje y redirigir de vuelta
    $_SESSION['error_message'] = 'Error al enviar notificación: ' . $e->getMessage();
    header("Location: ?page=admin_clubs");
    exit;
}
?>





