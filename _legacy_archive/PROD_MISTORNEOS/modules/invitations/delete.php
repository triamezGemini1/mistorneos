<?php
/**
 * Eliminar Invitación
 */



require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireRole(['admin_general','admin_torneo']);

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

try {
    $pdo = DB::pdo();
    
    // Eliminar
    $stmt = $pdo->prepare("DELETE FROM invitations WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        header("Location: index.php?msg=" . urlencode("Invitación eliminada exitosamente"));
    } else {
        header("Location: index.php?error=" . urlencode("Error al eliminar invitación"));
    }
    
} catch (PDOException $e) {
    header("Location: index.php?error=" . urlencode($e->getMessage()));
}
exit;










