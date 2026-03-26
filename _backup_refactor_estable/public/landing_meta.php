<?php

declare(strict_types=1);

/**
 * Meta SEO y branding del landing (adaptado desde main:public/config.php).
 * Requiere app_base_url() / mn_app_base_url().
 */

$SITE_NAME = trim((string) (getenv('SITE_NAME') ?: 'mistorneos'));
$SITE_TAGLINE = 'Torneos de dominó: calendario, resultados e inscripciones';
$META_TITLE = $SITE_NAME . ' — Torneos, resultados y calendario';
$META_DESCRIPTION = 'Consultá eventos, resultados y calendario sin iniciar sesión. Inscripciones y gestión para organizadores.';
$META_KEYWORDS = 'dominó, torneos, resultados, calendario, clubes, inscripciones';
$META_AUTHOR = $SITE_NAME;
$META_OG_TITLE = $META_TITLE;
$META_OG_DESCRIPTION = $META_DESCRIPTION;
$SITE_EMAIL = trim((string) (getenv('SITE_EMAIL') ?: ''));
$base = function_exists('mn_app_base_url') ? mn_app_base_url() : '';
$SITE_URL = $base !== '' ? $base . '/public/index.php' : '';
$OG_IMAGE = $SITE_URL !== '' ? $SITE_URL : ($base !== '' ? $base . '/favicon.ico' : '');
