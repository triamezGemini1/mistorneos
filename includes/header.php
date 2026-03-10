<?php
/**
 * Cabecera común: estructura HTML superior, metadatos y assets (mistorneos).
 * SOLUCIÓN DE RENDIMIENTO: favicon en ruta absoluta para evitar 404 y retraso ~190ms en tests de red.
 * <title> y recursos visuales reflejan la identidad de mistorneos (La Estación del Dominó).
 * Uso: definir $header_title opcional; luego include_once __DIR__ . '/../includes/header.php';
 * No cierra </head> para que la página pueda añadir estilos o meta adicionales.
 */
$header_title = $header_title ?? 'La Estación del Dominó';
?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="theme-color" content="#1a365d">
  <link rel="icon" type="image/x-icon" href="/mistorneos_beta/favicon.ico">
  <title><?= htmlspecialchars($header_title) ?></title>
  <meta name="description" content="mistorneos - La Estación del Dominó. Gestión de torneos, inscripciones y resultados.">
