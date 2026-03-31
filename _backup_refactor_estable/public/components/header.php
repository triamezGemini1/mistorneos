<?php
/**
 * Componente Header - Navbar + Men├║ flotante lateral
 * Variables globales: $user, app_base_url(), $SITE_NAME (desde config.php)
 */
$pp = isset($publicPrefix) && is_string($publicPrefix) ? $publicPrefix : '';
?>
    <!-- Navbar -->
    <nav class="bg-gradient-to-b from-primary-700 to-primary-600 shadow-lg sticky top-0 z-50 backdrop-blur-sm">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 md:h-20">
                <a href="<?= htmlspecialchars($pp . 'index.php', ENT_QUOTES, 'UTF-8') ?>" class="flex items-center space-x-2 text-white font-bold text-lg md:text-xl hover:opacity-90 transition-opacity">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white/15 border border-white/30 text-sm font-extrabold">M</span>
                    <span><?= htmlspecialchars($SITE_NAME ?? 'mistorneos', ENT_QUOTES, 'UTF-8') ?></span>
                </a>
                
                <div class="hidden md:flex items-center space-x-1">
                    <a href="#eventos-masivos" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 font-medium">Eventos Nacionales</a>
                    <a href="#eventos-entidad" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 font-medium">Eventos por Entidad</a>
                    <a href="#eventos" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 font-medium">Eventos</a>
                    <a href="#calendario" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 font-medium">Calendario</a>
                    <a href="#registro" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 font-medium">Registro</a>
                    <a href="#servicios" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 font-medium">Servicios</a>
                    <a href="#precios" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 font-medium">Precios</a>
                    <a href="#galeria" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 font-medium">Galer├¡a</a>
                    <a href="#faq" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 font-medium">FAQ</a>
                    <a href="#comentarios" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 font-medium">Comentarios</a>
                    <a href="<?= htmlspecialchars($pp . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>" class="ml-4 px-4 py-2 bg-white/15 text-white font-semibold rounded-lg hover:bg-white/25 transition-all duration-200">
                        <i class="fas fa-user mr-2"></i>Jugadores
                    </a>
                    <button type="button" id="mn-open-admin" class="ml-2 px-6 py-2 bg-accent text-primary-700 font-semibold rounded-lg hover:bg-accentDark hover:text-white transition-all duration-200 shadow-md hover:shadow-lg">
                        <i class="fas fa-sign-in-alt mr-2"></i>Staff
                    </button>
                </div>
                
                <button id="mobile-menu-btn" class="md:hidden text-white p-2 rounded-lg hover:bg-white/10 transition-colors">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
            
            <div id="mobile-menu" class="hidden md:hidden pb-4">
                <div class="flex flex-col space-y-2">
                    <a href="#eventos-masivos" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">Eventos Nacionales</a>
                    <a href="#eventos-entidad" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">Eventos por Entidad</a>
                    <a href="#eventos" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">Eventos</a>
                    <a href="#calendario" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">Calendario</a>
                    <a href="#registro" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">Registro</a>
                    <a href="#servicios" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">Servicios</a>
                    <a href="#precios" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">Precios</a>
                    <a href="#galeria" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">Galer├¡a</a>
                    <a href="#faq" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">FAQ</a>
                    <a href="#comentarios" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">Comentarios</a>
                    <a href="<?= htmlspecialchars($pp . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>" class="mt-2 px-4 py-2 bg-white/15 text-white font-semibold rounded-lg text-center transition-all">Jugadores</a>
                    <button type="button" id="mn-open-admin-mobile" class="mt-2 px-4 py-2 bg-accent text-primary-700 font-semibold rounded-lg text-center">Staff</button>
                </div>
            </div>
        </div>
    </nav>

    <?php require __DIR__ . '/side_nav.php'; ?>
