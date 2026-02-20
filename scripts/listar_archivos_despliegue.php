<?php
/**
 * Script para listar todos los archivos que deben subirse a producción
 * Útil para verificar que no falte ningún archivo
 */

$base_dir = __DIR__ . '/..';

echo "=== ARCHIVOS PARA DESPLIEGUE A PRODUCCIÓN ===\n\n";

$archivos_nuevos = [
    // Public - Nuevos
    'public/inscribir_evento_masivo.php',
    'public/reportar_pago_evento_masivo.php',
    'public/ver_recibo_pago.php',
    'public/api/search_persona.php',
    'public/api/search_user_persona.php',
    'public/api/verificar_inscripcion.php',
    
    // Modules - Nuevos
    'modules/cuentas_bancarias.php',
    'modules/reportes_pago_usuarios.php',
    'modules/tournament_admin/podios_equipos.php',
    'modules/tournament_admin/equipos_detalle.php',
    
    // Manuales
    'manuales_web/admin_club_resumido.html',
    'manuales_web/manual_usuario.php',
    
    // Lib
    'lib/BankValidator.php',
];

$archivos_modificados = [
    // Public - Modificados
    'public/landing.php',
    'public/includes/layout.php',
    'public/user_portal.php',
    
    // Modules - Modificados
    'modules/tournaments.php',
    'modules/tournaments/save.php',
    'modules/tournaments/update.php',
    'modules/affiliate_requests/list.php',
    'modules/affiliate_requests/send_whatsapp.php',
    'modules/torneo_gestion.php',
    'modules/gestion_torneos/panel.php',
    'modules/gestion_torneos/panel-moderno.php',
    'modules/gestion_torneos/panel_equipos.php',
];

echo "ARCHIVOS NUEVOS (" . count($archivos_nuevos) . "):\n";
echo str_repeat("-", 60) . "\n";
foreach ($archivos_nuevos as $archivo) {
    $ruta = $base_dir . '/' . $archivo;
    if (file_exists($ruta)) {
        $tamaño = filesize($ruta);
        echo "✓ $archivo (" . number_format($tamaño) . " bytes)\n";
    } else {
        echo "✗ $archivo (NO ENCONTRADO)\n";
    }
}

echo "\nARCHIVOS MODIFICADOS (" . count($archivos_modificados) . "):\n";
echo str_repeat("-", 60) . "\n";
foreach ($archivos_modificados as $archivo) {
    $ruta = $base_dir . '/' . $archivo;
    if (file_exists($ruta)) {
        $tamaño = filesize($ruta);
        $modificado = date('Y-m-d H:i:s', filemtime($ruta));
        echo "✓ $archivo (" . number_format($tamaño) . " bytes, modificado: $modificado)\n";
    } else {
        echo "✗ $archivo (NO ENCONTRADO)\n";
    }
}

// Verificar directorios de imágenes del manual
echo "\nDIRECTORIOS DE RECURSOS:\n";
echo str_repeat("-", 60) . "\n";
$directorios = [
    'manuales_web/assets/images/admin_club',
    'manuales_web/assets/images/admin_general',
    'manuales_web/assets/images/admin_torneo',
    'manuales_web/assets/images/usuario',
];

foreach ($directorios as $dir) {
    $ruta = $base_dir . '/' . $dir;
    if (is_dir($ruta)) {
        $archivos = glob($ruta . '/*');
        $cantidad = count($archivos);
        echo "✓ $dir ($cantidad archivos)\n";
    } else {
        echo "✗ $dir (NO EXISTE)\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "TOTAL: " . (count($archivos_nuevos) + count($archivos_modificados)) . " archivos\n";
echo str_repeat("=", 60) . "\n";

