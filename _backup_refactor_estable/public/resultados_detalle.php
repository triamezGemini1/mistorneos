<?php

declare(strict_types=1);

$id = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$q = $id > 0 ? 'torneo_id=' . $id : '';
header('Location: resultado_torneo.php' . ($q !== '' ? '?' . $q : ''), true, 301);
exit;
