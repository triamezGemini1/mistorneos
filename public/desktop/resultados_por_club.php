<?php
/**
 * Resultados por club (Desktop). Redirige a posiciones; en una versión futura podría filtrar por club.
 */
declare(strict_types=1);
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
header('Location: posiciones.php?torneo_id=' . $torneo_id);
exit;
