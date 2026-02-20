<?php
/**
 * Layout desktop: sidebar + área principal. Definir $pageTitle antes de incluir.
 * Mobile-First: sidebar hamburger en móvil, fija en desktop.
 */
$desktopActive = $desktopActive ?? 'registro';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle ?? 'Desktop') ?> — Mis Torneos</title>
    <!-- Recursos locales (relativos). CDN en HTTPS. -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <link href="desktop.css" rel="stylesheet">
</head>
<body>
    <div class="desktop-wrap">
        <div class="desktop-sidebar-overlay" id="desktopSidebarOverlay" aria-hidden="true"></div>
        <nav class="desktop-sidebar py-3" id="desktopSidebar" aria-label="Menú principal">
            <div class="px-3 mb-3">
                <a href="dashboard.php" class="text-white text-decoration-none fw-bold"><i class="fas fa-desktop me-2"></i>Desktop</a>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link <?= $desktopActive === 'dashboard' ? 'active' : '' ?>" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?= $desktopActive === 'torneos' ? 'active' : '' ?>" href="torneos.php"><i class="fas fa-trophy me-2"></i>Torneos</a></li>
                <li class="nav-item"><a class="nav-link <?= $desktopActive === 'registro' ? 'active' : '' ?>" href="registro_jugadores.php"><i class="fas fa-user-plus me-2"></i>Registro de jugador</a></li>
            </ul>
            <hr class="border-secondary my-3">
            <ul class="nav flex-column px-3">
                <li class="nav-item"><a class="nav-link text-white-50 small" href="logout_local.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
            </ul>
        </nav>
        <button type="button" class="desktop-nav-toggle" id="desktopNavToggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="desktopSidebar">
            <i class="fas fa-bars"></i>
            <i class="fas fa-times"></i>
        </button>
        <main class="desktop-main flex-grow-1">
        <div id="desktop-connection-banner" class="desktop-connection-banner" role="status" aria-live="polite">
            <span id="desktop-connection-text">En línea</span>
        </div>
        <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                var sidebar = document.getElementById('desktopSidebar');
                var toggle = document.getElementById('desktopNavToggle');
                var overlay = document.getElementById('desktopSidebarOverlay');
                if (!sidebar || !toggle) return;
                function openMenu() {
                    sidebar.classList.add('is-open');
                    if (overlay) overlay.classList.add('is-visible');
                    toggle.setAttribute('aria-label', 'Cerrar menú');
                    toggle.setAttribute('aria-expanded', 'true');
                }
                function closeMenu() {
                    sidebar.classList.remove('is-open');
                    if (overlay) overlay.classList.remove('is-visible');
                    toggle.setAttribute('aria-label', 'Abrir menú');
                    toggle.setAttribute('aria-expanded', 'false');
                }
                toggle.addEventListener('click', function() {
                    if (sidebar.classList.contains('is-open')) closeMenu(); else openMenu();
                });
                if (overlay) overlay.addEventListener('click', closeMenu);
                var connBanner = document.getElementById('desktop-connection-banner');
                var connText = document.getElementById('desktop-connection-text');
                function updateConnectionBanner() {
                    if (!connBanner || !connText) return;
                    var onLine = navigator.onLine;
                    connBanner.className = 'desktop-connection-banner ' + (onLine ? 'desktop-connection-online' : 'desktop-connection-offline');
                    connText.textContent = onLine ? 'En línea' : 'Trabajando sin conexión';
                }
                updateConnectionBanner();
                window.addEventListener('online', updateConnectionBanner);
                window.addEventListener('offline', updateConnectionBanner);
            });
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })();
        </script>
