<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$sql = file_get_contents(__DIR__ . '/../sql/add_plantilla_invitacion_torneo_formal.sql');
$pdo = DB::pdo();
$pdo->exec($sql);
echo "Plantilla invitacion_torneo_formal agregada/actualizada correctamente.\n";
