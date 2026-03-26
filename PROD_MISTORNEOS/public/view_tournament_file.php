<?php
/**
 * Sirve archivos adjuntos del torneo (afiche, invitación, normas/condiciones)
 * de forma segura desde upload/tournaments/
 * Uso: view_tournament_file.php?file=posters/afiche_1_123.pdf
 */
$file_param = $_GET['file'] ?? '';

// Solo el nombre del archivo o subruta bajo upload/tournaments/, sin directory traversal
$file_param = str_replace(['../', '..\\', "\0"], '', $file_param);
$file_param = ltrim($file_param, '/\\');

if ($file_param === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Parámetro file requerido';
    exit;
}

$base_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'tournaments';
$full_path = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file_param);

$real_base = realpath($base_dir);
$real_path = realpath($full_path);

if ($real_base === false || !is_dir($real_base)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Directorio de torneos no encontrado';
    exit;
}

if ($real_path === false || !is_file($real_path) || strpos($real_path, $real_base) !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Archivo no encontrado';
    exit;
}

$ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
$mimes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mime = $mimes[$ext] ?? 'application/octet-stream';

// Para imágenes y PDF permitir vista en navegador (inline); el resto descarga
$disposition = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif']) ? 'inline' : 'attachment';
$filename = basename($real_path);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real_path));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '\\"', $filename) . '"');
header('Cache-Control: public, max-age=3600');
readfile($real_path);
exit;
