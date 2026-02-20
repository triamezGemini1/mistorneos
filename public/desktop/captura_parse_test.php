<?php
$torneo_id = 1;
$partida_filtro = 0;
$mesa_filtro = 0;
$error_message = '';
$tabla_partiresul_existe = true;
$torneos = [['id'=>1,'nombre'=>'T']];
$rondas_disponibles = [1];
$mesas_disponibles = [];
$partidas_mesa = [];
?>
<?php if ($error_message): ?>
x
<?php endif; ?>
<?php if ($torneo_id > 0): ?>
  <?php if (empty($partidas_mesa)): ?>
  y
  <?php else: ?>
  z
  <?php endif; ?>
<?php endif; ?>
