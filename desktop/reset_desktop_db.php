<?php
/**
 * Borra la base de datos SQLite del desktop para empezar desde cero.
 * Elimina: desktop/data/mistorneos_local.db (y equivalente según DESKTOP_DB_BASE)
 * (y sus archivos .db-wal, .db-shm si existen).
 *
 * Uso: php desktop/reset_desktop_db.php
 */
declare(strict_types=1);

$base = dirname(__DIR__);
// Ruta desktop: desktop/data/ (public no es parte de la ruta de desktop)
$paths = [
    $base . DIRECTORY_SEPARATOR . 'desktop' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mistorneos_local.db',
];

$deleted = 0;
foreach ($paths as $path) {
    foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
        if (is_file($file)) {
            if (@unlink($file)) {
                echo "Eliminado: " . $file . PHP_EOL;
                $deleted++;
            } else {
                echo "No se pudo eliminar: " . $file . PHP_EOL;
            }
        }
    }
}

if ($deleted === 0) {
    echo "No había archivos de base de datos que borrar." . PHP_EOL;
} else {
    echo "Listo. La próxima vez que uses la app desktop o import_from_web.php se creará una base nueva." . PHP_EOL;
}
