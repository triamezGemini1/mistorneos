<?php
/**
 * Componente Contacto - Footer con información de contacto
 * Variables globales disponibles: $user, app_base_url()
 */
?>
    <!-- Footer / Contacto -->
    <footer id="contacto" class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="mb-4 md:mb-0">
                    <h5 class="text-xl font-bold mb-2 flex items-center">
                        <?php 
                        $logo_url = class_exists('AppHelpers') ? AppHelpers::getAppLogo() : (rtrim(app_base_url(), '/') . '/public/view_image.php?path=' . rawurlencode('lib/Assets/mislogos/logo4.png'));
                        ?>
                        <img src="<?= htmlspecialchars($logo_url) ?>" alt="La Estación del Dominó" class="h-6 mr-2">
                        La Estación del Dominó
                    </h5>
                    <p class="text-gray-400">Sistema integral para la gestión de torneos de dominó</p>
                </div>
                <div class="text-center md:text-right">
                    <p class="text-gray-400 mb-1 flex items-center justify-center md:justify-end">
                        <i class="fas fa-envelope mr-2"></i>info@laestaciondeldomino.com
                    </p>
                    <p class="text-gray-500 text-sm">
                        &copy; <?= date('Y') ?> La Estación del Dominó. Todos los derechos reservados.
                    </p>
                </div>
            </div>
        </div>
    </footer>
