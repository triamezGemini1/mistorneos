<?php
/**
 * Guard para proteger páginas de inscripciones
 * Requiere sesión activa con token válido
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['invitacion_id']) || !isset($_SESSION['torneo_id']) || !isset($_SESSION['club_id'])) {
    header("Location: login.php?error=" . urlencode("Debe iniciar sesión con su token"));
    exit;
}










