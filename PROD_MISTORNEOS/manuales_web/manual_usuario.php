<?php
/**
 * Wrapper para el manual de usuario - Solo usuarios autenticados
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/auth.php';

// Verificar que el usuario esté autenticado
if (!Auth::user()) {
    // Redirigir al login si no está autenticado
    header('Location: ' . AppHelpers::login());
    exit;
}

// Leer el contenido del manual HTML
$manual_path = __DIR__ . '/admin_club_resumido.html';

if (!file_exists($manual_path)) {
    die('Error: El manual no está disponible.');
}

// Leer el contenido del archivo HTML
$manual_content = file_get_contents($manual_path);

// Opcional: Agregar información del usuario en el manual si es necesario
// Por ahora, simplemente mostramos el contenido

// Establecer headers apropiados
header('Content-Type: text/html; charset=UTF-8');

// Mostrar el contenido
echo $manual_content;

