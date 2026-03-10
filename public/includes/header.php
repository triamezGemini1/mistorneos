<?php
/**
 * Cabecera común: <head> con favicon en ruta absoluta (evita 404 y mejora Pingdom).
 * Favicon: /mistorneos_beta/favicon.ico
 * Uso: definir $header_title opcional; luego include_once __DIR__ . '/includes/header.php';
 */
$header_title = $header_title ?? 'La Estación del Dominó';
?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="theme-color" content="#1a365d">
  <link rel="icon" type="image/x-icon" href="/mistorneos_beta/favicon.ico">
  <title><?= htmlspecialchars($header_title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
