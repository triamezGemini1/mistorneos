<?php
/**
 * Guard para proteger páginas de equipos
 * Requiere sesión activa con token válido del sistema de invitaciones
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión de invitación
if (!isset($_SESSION['invitacion_id']) || !isset($_SESSION['torneo_id']) || !isset($_SESSION['club_id'])) {
    header("Location: ../invitations/inscripciones/login.php?error=" . urlencode("Debe iniciar sesión con su token"));
    exit;
}

// Verificar que el torneo sea modalidad equipos
require_once __DIR__ . '/../../config/db.php';

$pdo = DB::pdo();
$stmt = $pdo->prepare("SELECT modalidad FROM tournaments WHERE id = ?");
$stmt->execute([$_SESSION['torneo_id']]);
$torneo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$torneo || (int)$torneo['modalidad'] !== 3) {
    // No es modalidad equipos, redirigir al módulo de inscripción individual
    header("Location: ../invitations/inscripciones/index.php?error=" . urlencode("Este torneo no es modalidad equipos"));
    exit;
}









