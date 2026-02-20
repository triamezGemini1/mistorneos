<?php
/**
 * Elimina un jugador (usuario con role = 'usuario') de la base SQLite. Requiere confirmación en el cliente.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

session_start();
if (empty($_SESSION['desktop_user'])) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

require_once __DIR__ . '/db_local.php';
try {
    $pdo = DB_Local::pdo();
    // Solo permitir borrar jugadores (role = 'usuario'), no administradores
    $stmt = $pdo->prepare("SELECT id, role FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }
    $role = $row['role'] ?? '';
    $esJugador = ($role === 'usuario' || $role === '' || $role === null);
    if (!$esJugador) {
        echo json_encode(['ok' => false, 'error' => 'Solo se pueden eliminar jugadores registrados']);
        exit;
    }
    $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
