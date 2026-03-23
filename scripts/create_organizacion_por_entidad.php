<?php
/**
 * Crea una organización por cada entidad que aún no tenga al menos una.
 * Nombre: "ASOCIACION DE DOMINO DEL ESTADO " + nombre de la entidad.
 * Las organizaciones se crean INACTIVAS (estatus = 0) hasta que se asigne un usuario
 * válido y se active desde el panel (actualizando usuario y contraseña).
 *
 * Uso: php scripts/create_organizacion_por_entidad.php
 * Opcional: php scripts/create_organizacion_por_entidad.php --admin_user_id=2
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$admin_user_id = null;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--admin_user_id=(\d+)$/', $arg, $m)) {
        $admin_user_id = (int) $m[1];
        break;
    }
}

$pdo = DB::pdo();

// 1. Resolver admin_user_id placeholder (obligatorio en la tabla; se usa solo para orgs inactivas)
if ($admin_user_id === null) {
    $stmt = $pdo->query("SELECT id FROM usuarios WHERE role = 'admin_general' AND status = 0 ORDER BY id ASC LIMIT 1");
    $admin_user_id = $stmt->fetchColumn();
    if ($admin_user_id === false || $admin_user_id === null) {
        $stmt = $pdo->query("SELECT id FROM usuarios ORDER BY id ASC LIMIT 1");
        $admin_user_id = $stmt->fetchColumn();
    }
}
if (!$admin_user_id) {
    fwrite(STDERR, "Error: No hay ningún usuario en la base de datos. organizaciones.admin_user_id es obligatorio.\n");
    exit(1);
}
echo "Usando admin_user_id = {$admin_user_id} como placeholder (organizaciones inactivas).\n";

// 2. Detectar columnas de la tabla entidad
$cols = $pdo->query("SHOW COLUMNS FROM entidad")->fetchAll(PDO::FETCH_ASSOC);
$codeCol = $nameCol = null;
foreach ($cols as $c) {
    $f = strtolower($c['Field'] ?? $c['field'] ?? '');
    if (!$codeCol && in_array($f, ['codigo', 'cod_entidad', 'id', 'code'], true)) {
        $codeCol = $f;
    }
    if (!$nameCol && in_array($f, ['nombre', 'descripcion', 'entidad', 'nombre_entidad'], true)) {
        $nameCol = $f;
    }
}
if (!$codeCol || !$nameCol) {
    fwrite(STDERR, "Error: No se pudo detectar columna de código o nombre en la tabla entidad.\n");
    exit(1);
}
echo "Entidad: columna código = {$codeCol}, nombre = {$nameCol}\n";

// 3. Listar todas las entidades
$stmt = $pdo->query("SELECT {$codeCol} AS cod, {$nameCol} AS nombre FROM entidad ORDER BY {$nameCol} ASC");
$entidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($entidades)) {
    echo "No hay registros en la tabla entidad. Nada que hacer.\n";
    exit(0);
}
echo "Total entidades en tabla entidad: " . count($entidades) . "\n";

// 4. Por cada entidad, crear organización si no existe
$insert = $pdo->prepare("
    INSERT INTO organizaciones (nombre, entidad, admin_user_id, estatus)
    VALUES (?, ?, ?, 0)
");
$creadas = 0;
$ya_existian = 0;

foreach ($entidades as $e) {
    $cod = $e['cod'];
    $nombre_entidad = trim($e['nombre']);
    $nombre_org = 'ASOCIACION DE DOMINO DEL ESTADO ' . $nombre_entidad;
    $stmt = $pdo->prepare("SELECT id FROM organizaciones WHERE entidad = ? LIMIT 1");
    $stmt->execute([$cod]);
    if ($stmt->fetch()) {
        $ya_existian++;
        continue;
    }
    try {
        $insert->execute([$nombre_org, $cod, $admin_user_id]);
        $creadas++;
        echo "  Creada (inactiva): \"{$nombre_org}\" (entidad = {$cod})\n";
    } catch (Exception $ex) {
        fwrite(STDERR, "  Error al crear organización para entidad {$cod}: " . $ex->getMessage() . "\n");
    }
}

echo "\nResumen: {$creadas} organizaciones creadas (inactivas), {$ya_existian} entidades ya tenían al menos una organización.\n");
echo "Para activar cada organización: asignar usuario administrador y contraseña desde el panel (Reactivar).\n";
