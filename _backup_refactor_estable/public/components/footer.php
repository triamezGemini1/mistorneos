<?php
/**
 * Componente Footer - Pie de p├ígina con contacto
 * Variables globales: $SITE_NAME, $SITE_TAGLINE, $SITE_EMAIL (desde config.php), app_base_url()
 */
$site_name = $SITE_NAME ?? 'mistorneos';
$site_tagline = $SITE_TAGLINE ?? 'Torneos de dominó';
$site_email = $SITE_EMAIL !== '' ? $SITE_EMAIL : '—';
?>
    <footer id="contacto" class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="mb-4 md:mb-0">
                    <h5 class="text-xl font-bold mb-2 flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 border border-white/20 text-sm font-extrabold">M</span>
                        <?= htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') ?>
                    </h5>
                    <p class="text-gray-400"><?= htmlspecialchars($site_tagline) ?></p>
                </div>
                <div class="text-center md:text-right">
                    <p class="text-gray-400 mb-1 flex items-center justify-center md:justify-end">
                        <i class="fas fa-envelope mr-2"></i><?= htmlspecialchars($site_email) ?>
                    </p>
                    <p class="text-gray-500 text-sm">&copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </footer>
