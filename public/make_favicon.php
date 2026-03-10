<?php
/**
 * Genera un favicon ligero (<10 KB) para mistorneos.
 * Ejecutar una vez: php make_favicon.php (o desde navegador en desarrollo).
 *
 * Origen (en este orden):
 * - favicon_io.zip: se extrae favicon.ico del ZIP (pack de favicon.io) y se usa.
 * - favicon.ico: se redimensiona a 32x32 y se guarda public/favicon.png.
 * - Si no hay ninguno: se crea favicon.png 32x32 con color #1a365d.
 */
$publicDir = __DIR__;
$icoPath = $publicDir . '/favicon.ico';
$zipPath = $publicDir . '/favicon_io.zip';
$pngPath = $publicDir . '/favicon.png';
$targetSize = 32;

if (!extension_loaded('gd')) {
    echo "GD no está cargado. Instale php-gd o habilite extension=gd en php.ini.\n";
    exit(1);
}

// Si existe favicon_io.zip, extraer favicon.ico (y opcionalmente apple-touch-icon.png)
if (file_exists($zipPath) && class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) === true) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $base = strtolower(basename($name));
            if ($base === 'favicon.ico') {
                file_put_contents($icoPath, $zip->getFromIndex($i));
                echo "Extraído favicon.ico desde favicon_io.zip.\n";
                break;
            }
        }
        $zip->close();
    }
}

$im = null;

// Intentar cargar favicon.ico existente y redimensionar
if (file_exists($icoPath)) {
    $data = @file_get_contents($icoPath);
    if ($data !== false && strlen($data) > 0) {
        $im = @imagecreatefromstring($data);
    }
}

// Si no hay imagen, crear una 32x32 con color identidad mistorneos (#1a365d)
if (!$im) {
    $im = imagecreatetruecolor($targetSize, $targetSize);
    if (!$im) {
        echo "No se pudo crear la imagen.\n";
        exit(1);
    }
    $dark = imagecolorallocate($im, 0x1a, 0x36, 0x5d);
    imagefill($im, 0, 0, $dark);
} else {
    $new = imagecreatetruecolor($targetSize, $targetSize);
    if ($new) {
        imagecopyresampled($new, $im, 0, 0, 0, 0, $targetSize, $targetSize, imagesx($im), imagesy($im));
        imagedestroy($im);
        $im = $new;
    }
}

imagepng($im, $pngPath, 9);
imagedestroy($im);

$size = file_exists($pngPath) ? filesize($pngPath) : 0;
echo "Creado: " . $pngPath . " (" . number_format($size) . " bytes). Objetivo: <10 KB.\n";
if ($size > 10 * 1024) {
    echo "Aviso: el PNG supera 10 KB. Considere usar 16x16 o comprimir más.\n";
}
