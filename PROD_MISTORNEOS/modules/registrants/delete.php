<?php
/**
 * ARCHIVO DE REDIRECCIÓN
 * Este archivo redirige al sistema moderno integrado en el dashboard.
 * Mantiene compatibilidad con enlaces antiguos.
 */
$id = $_GET['id'] ?? 0;
header('Location: ../../public/index.php?page=registrants&action=delete&id=' . (int)$id);
exit;
