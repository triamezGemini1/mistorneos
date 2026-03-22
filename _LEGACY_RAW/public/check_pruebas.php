<?php
/**
 * Diagnóstico para /pruebas/ (página en blanco).
 * Acceso: https://laestaciondeldominohoy.com/pruebas/check_pruebas.php
 * Eliminar o restringir en producción cuando todo funcione.
 */
header('Content-Type: text/html; charset=utf-8');
$publicDir = __DIR__;
$rootDir = dirname($publicDir);
$configDir = $rootDir . '/config';
$checks = [
    'PHP' => PHP_VERSION,
    'public (__DIR__)' => $publicDir,
    'raíz (dirname public)' => $rootDir,
    'config/ existe' => is_dir($configDir) ? 'sí' : 'NO',
    'config/bootstrap.php' => file_exists($configDir . '/bootstrap.php') ? 'sí' : 'NO',
    'config/db_config.php' => file_exists($configDir . '/db_config.php') ? 'sí' : 'NO',
    'includes/ existe' => is_dir($rootDir . '/includes') ? 'sí' : 'NO',
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? '-',
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '-',
];
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Diagnóstico /pruebas/</title>';
echo '<style>body{font-family:sans-serif;padding:2rem;max-width:640px;margin:0 auto;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ccc;padding:8px;text-align:left;} th{background:#1a365d;color:#fff;} .no{color:red;} .ok{color:green;}</style></head><body>';
echo '<h1>Diagnóstico mistorneos (pruebas)</h1><p>Si <code>config/</code> no existe, hay que desplegar el proyecto completo (no solo la carpeta <code>public/</code>).</p>';
echo '<table><tr><th>Comprobación</th><th>Valor</th></tr>';
foreach ($checks as $label => $value) {
    $cls = (is_string($value) && $value === 'NO') ? ' class="no"' : '';
    echo '<tr' . $cls . '><td>' . htmlspecialchars($label) . '</td><td>' . htmlspecialchars((string)$value) . '</td></tr>';
}
echo '</table><p><a href="index.php">Ir a index.php</a></p></body></html>';
exit;
