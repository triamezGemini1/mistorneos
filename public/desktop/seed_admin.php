<?php
/**
 * Script único: inserta o actualiza el usuario administrador en la SQLite local.
 * Usa la MISMA base de datos que el login (desktop/data/mistorneos_local.db).
 * Ejecutar una vez: php seed_admin.php (desde public/desktop) o desde raíz.
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db_local.php';

$pdo = DB_Local::pdo();

$username = 'Trinoamez';
$password_hash = password_hash('npi$2025', PASSWORD_BCRYPT);
$role = 'admin_general'; // compatible con login_local: admin_general, admin_torneo, admin_club, operador

$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
$stmt->execute([$username]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ?, role = ?, is_active = 1, last_updated = datetime('now') WHERE id = ?");
    $stmt->execute([$password_hash, $role, (int)$existing['id']]);
    echo "OK: Usuario '$username' actualizado (password y rol). Ya puedes intentar el login.\n";
} else {
    $stmt = $pdo->prepare("INSERT INTO usuarios (username, password_hash, role, nombre, is_active, last_updated) VALUES (?, ?, ?, ?, 1, datetime('now'))");
    $stmt->execute([$username, $password_hash, $role, $username]);
    echo "OK: Usuario '$username' insertado. Ya puedes intentar el login.\n";
}
