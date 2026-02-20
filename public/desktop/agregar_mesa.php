<?php
/**
 * Agregar mesa (Desktop). Redirige a gestión de rondas / cuadrícula.
 */
declare(strict_types=1);
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$ronda = isset($_GET['ronda']) ? (int)$_GET['ronda'] : 0;
header('Location: torneo_control.php?torneo_id=' . $torneo_id);
exit;
