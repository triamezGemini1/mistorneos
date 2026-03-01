<?php
/**
 * Visor y descarga de documentos oficiales (PDF, etc.) desde upload/documentos_oficiales/
 * Uso: view_documento.php?path=nombre.pdf (consulta en línea) o ?path=nombre.pdf&download=1 (descarga)
 */

declare(strict_types=1);

$path = $_GET['path'] ?? '';
$download = isset($_GET['download']) && $_GET['download'] !== '0';

$path = str_replace(['../', '..\\'], '', $path);
$path = ltrim($path, '/\\');

$allowed_prefixes = ['upload/documentos_oficiales/', 'upload/invitaciones_fvd/'];
$allowed = false;
foreach ($allowed_prefixes as $prefix) {
    if (strpos($path, $prefix) === 0) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    if (strpos($path, 'upload/') !== 0) {
        $path = 'upload/documentos_oficiales/' . ltrim($path, '/');
    }
    $allowed = false;
    foreach ($allowed_prefixes as $prefix) {
        if (strpos($path, $prefix) === 0) {
            $allowed = true;
            break;
        }
    }
}
if (!$allowed || $path === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Documento no encontrado';
    exit;
}

$base_dir = dirname(__DIR__);
$full_path = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

$real_base = realpath($base_dir);
$real_path = $full_path && is_file($full_path) ? realpath($full_path) : false;

if ($real_path === false || $real_base === false || strpos($real_path, $real_base) !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Documento no encontrado';
    exit;
}

$ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
$mimes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mime = $mimes[$ext] ?? 'application/octet-stream';

$filename = basename($real_path);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real_path));
header('Cache-Control: public, max-age=3600');
if ($download) {
    header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $filename) . '"');
} else {
    header('Content-Disposition: inline; filename="' . str_replace('"', '\\"', $filename) . '"');
}
readfile($real_path);
exit;
