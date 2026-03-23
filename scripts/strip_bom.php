<?php
/** Quita BOM UTF-8 del inicio de un archivo. Uso: php strip_bom.php <ruta> */
$path = $argv[1] ?? __DIR__ . '/../public/notificaciones_ajax.php';
$path = realpath($path) ?: $path;
if (!is_file($path)) {
    fwrite(STDERR, "No existe: $path\n");
    exit(1);
}
$raw = file_get_contents($path);
$bom = "\xEF\xBB\xBF";
if (strpos($raw, $bom) === 0) {
    file_put_contents($path, substr($raw, 3));
    echo "BOM eliminado en $path\n";
} else {
    echo "Sin BOM en $path\n";
}
