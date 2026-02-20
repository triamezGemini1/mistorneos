<?php
/**
 * Router: Admin de Organización (admin_club)
 * Punto de entrada para el Hub y acciones del administrador de organización.
 * Mantiene compatibilidad con rutas legacy.
 */

$action = $_GET['action'] ?? 'hub';

if ($action === 'hub') {
    include __DIR__ . '/admin_org/organizacion/actions/hub.php';
    return;
}

// Fallback: mostrar hub
include __DIR__ . '/admin_org/organizacion/actions/hub.php';
