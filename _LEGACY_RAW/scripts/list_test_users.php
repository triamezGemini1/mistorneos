<?php
/**
 * Script para listar usuarios de prueba del club 5
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$club_id = 5;

$stmt = DB::pdo()->prepare("
    SELECT id, nombre, username, email, cedula, sexo, status, club_id
    FROM usuarios 
    WHERE club_id = ?
    ORDER BY id DESC
    LIMIT 50
");

$stmt->execute([$club_id]);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "═══════════════════════════════════════════════════════════\n";
echo "📋 USUARIOS DEL CLUB $club_id\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Total encontrados: " . count($usuarios) . "\n\n";

foreach ($usuarios as $index => $usuario) {
    $num = $index + 1;
    echo sprintf(
        "%2d. %-40s | %-20s | %-25s | %s\n",
        $num,
        substr($usuario['nombre'], 0, 40),
        $usuario['username'],
        $usuario['email'],
        $usuario['status']
    );
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "🔑 Credenciales de acceso:\n";
echo "   Username: [ver arriba]\n";
echo "   Password: test123\n";
echo "═══════════════════════════════════════════════════════════\n";












