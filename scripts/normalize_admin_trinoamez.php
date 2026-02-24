<?php
/**
 * Script único: normaliza el acceso del usuario admin_general Trinoamez en la base de datos principal (web).
 * Ejecutar una vez desde la raíz del proyecto: php scripts/normalize_admin_trinoamez.php
 *
 * Acciones:
 * - Si el usuario existe: actualiza password_hash, role=admin_general, status=0 y is_active=1 (si existe la columna).
 * - Si no existe: crea el usuario con esas credenciales.
 *
 * Tras ejecutarlo, inicia sesión en la web con usuario "Trinoamez" y la contraseña configurada.
 * Recomendación: cambiar la contraseña desde el panel tras el primer acceso y eliminar o no reutilizar este script.
 */
declare(strict_types=1);

$run_from_cli = (php_sapi_name() === 'cli');
if (!$run_from_cli) {
    die('Este script debe ejecutarse por línea de comandos: php scripts/normalize_admin_trinoamez.php');
}

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/bootstrap.php';
require_once $baseDir . '/config/db.php';
require_once $baseDir . '/lib/security.php';

$username = 'Trinoamez';
$password_plain = 'npi$2025';

try {
    $pdo = DB::pdo();

    $stmt = $pdo->prepare("SELECT id, username, role, status FROM usuarios WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $password_hash = Security::hashPassword($password_plain);

    if ($user) {
        $id = (int) $user['id'];
        $updates = ["password_hash = ?", "role = ?", "status = 0"];
        $params = [$password_hash, 'admin_general'];

        try {
            $chk = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'is_active'")->fetch();
            if ($chk) {
                $updates[] = "is_active = 1";
            }
        } catch (Throwable $e) {
            // ignorar si la columna no existe
        }

        $sql = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo "OK: Usuario '{$username}' actualizado (contraseña, role=admin_general, status=0).\n";
        echo "   Ya puedes iniciar sesión en la web con usuario: {$username}\n";
    } else {
        $cols = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
        $hasUuid = in_array('uuid', $cols, true);
        $hasIsActive = in_array('is_active', $cols, true);

        $fields = ['username', 'password_hash', 'role', 'status', 'nombre', 'email', 'cedula', 'nacionalidad', 'sexo', 'entidad'];
        $placeholders = array_fill(0, count($fields), '?');
        $values = [$username, $password_hash, 'admin_general', 0, $username, $username . '@mistorneos.local', '00000000', 'V', 'M', 0];

        if ($hasUuid) {
            $fields[] = 'uuid';
            $placeholders[] = '?';
            $values[] = Security::uuidV4();
        }
        if ($hasIsActive) {
            $fields[] = 'is_active';
            $placeholders[] = '?';
            $values[] = 1;
        }

        $sql = "INSERT INTO usuarios (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        echo "OK: Usuario '{$username}' creado con role=admin_general.\n";
        echo "   Inicia sesión en la web con usuario: {$username} y la contraseña indicada.\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
