<?php
$torneo_id = isset($_GET['torneo_id']) ? (int)$_GET['torneo_id'] : 0;
$ronda = isset($_GET['ronda']) ? (int)$_GET['ronda'] : 0;
header('Location: cuadricula.php?torneo_id=' . $torneo_id . '&ronda=' . $ronda);
exit;
