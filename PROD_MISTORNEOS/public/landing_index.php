<?php
/**
 * Landing Page Modular - Estructura con include_once
 * Requiere: config.php cargado, $user, $pdo, app_base_url() disponibles
 */
$META_TITLE = $META_TITLE ?? 'La Estación del Dominó';
$META_DESCRIPTION = $META_DESCRIPTION ?? 'Plataforma integral para gestión de torneos de dominó en Venezuela';
$META_KEYWORDS = $META_KEYWORDS ?? 'dominó, torneos, venezuela';
$SITE_URL = $SITE_URL ?? (rtrim(app_base_url(), '/') . '/public/landing.php');
$OG_IMAGE = $OG_IMAGE ?? (rtrim(app_base_url(), '/') . '/lib/Assets/mislogos/logo4.png');
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1a365d">
    <title><?= htmlspecialchars($META_TITLE) ?></title>
    <meta name="description" content="<?= htmlspecialchars($META_DESCRIPTION) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($META_KEYWORDS) ?>">
    <meta name="author" content="<?= htmlspecialchars($META_AUTHOR ?? 'La Estación del Dominó') ?>">
    <meta name="robots" content="index, follow">
    <meta name="language" content="es">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($SITE_URL) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($META_OG_TITLE ?? $META_TITLE) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($META_OG_DESCRIPTION ?? $META_DESCRIPTION) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($OG_IMAGE) ?>">
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= htmlspecialchars($SITE_URL) ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($META_OG_TITLE ?? $META_TITLE) ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($META_OG_DESCRIPTION ?? $META_DESCRIPTION) ?>">
    <meta property="twitter:image" content="<?= htmlspecialchars($OG_IMAGE) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($SITE_URL) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(rtrim(app_base_url(), '/') . '/public/assets/dist/output.css') ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',system-ui,sans-serif;}</style>
</head>
<body class="bg-gray-50 antialiased">
<?php
include_once __DIR__ . '/components/header.php';
include_once __DIR__ . '/components/hero.php';
include_once __DIR__ . '/components/services-grid.php';
include_once __DIR__ . '/components/trust-badges.php';
include_once __DIR__ . '/components/contact-form.php';
include_once __DIR__ . '/components/footer.php';
?>
    <script>
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            document.getElementById('mobile-menu')?.classList.toggle('hidden');
        });
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                document.getElementById('mobile-menu')?.classList.add('hidden');
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.star-rating').forEach(input => {
                input.addEventListener('change', function() {
                    const v = parseInt(this.value);
                    const container = this.closest('div');
                    container.querySelectorAll('i').forEach((star, i) => {
                        star.classList.toggle('fas', i < v);
                        star.classList.toggle('far', i >= v);
                        star.classList.toggle('text-yellow-500', i < v);
                    });
                });
            });
        });
    </script>
</body>
</html>
