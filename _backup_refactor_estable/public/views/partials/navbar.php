<?php

declare(strict_types=1);

/** @var string $publicPrefix */

$base = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8');
$adminTorneosUrl = $base . 'admin_torneo.php';
$dashboardUrl = $base . 'dashboard.php';
$logoutUrl = $base . 'logout.php';
$homeUrl = $base . 'index.php';
$resUrl = $base . 'resultados.php';
$calUrl = $base . 'calendario.php';
$afilUrl = $base . 'afiliacion.php';
$isAdmin = function_exists('mn_admin_session') && mn_admin_session() !== null;
$isAtleta = AuthHelper::isLoggedIn();

?>
<header class="mn-nav" role="banner">
  <div class="mn-container mn-nav__inner">
    <a class="mn-logo" href="<?= $homeUrl ?>" aria-label="mistorneos — inicio">
      <span class="mn-logo__mark" aria-hidden="true">M</span>
      <span>mistorneos</span>
    </a>
    <nav aria-label="Navegación principal">
      <ul class="mn-nav__links">
        <li><a href="<?= $homeUrl ?>#destacados">Torneos</a></li>
        <li><a href="<?= $resUrl ?>">Resultados</a></li>
        <li><a href="<?= $calUrl ?>">Calendario</a></li>
        <li><a href="<?= $afilUrl ?>">Afiliación</a></li>
        <li><a href="<?= $homeUrl ?>#buscar">Buscar atleta</a></li>
      </ul>
    </nav>
    <div class="mn-nav__ctas" role="navigation" aria-label="Acceso por perfil">
      <?php if ($isAdmin) : ?>
        <a class="mn-btn mn-btn--ghost" href="<?= $adminTorneosUrl ?>">Panel de torneos</a>
        <a class="mn-btn mn-btn--ghost" href="<?= $logoutUrl ?>">Salir</a>
      <?php elseif ($isAtleta) : ?>
        <a class="mn-btn mn-btn--ghost" href="<?= $dashboardUrl ?>">Mi cuenta</a>
        <a class="mn-btn mn-btn--ghost" href="<?= $logoutUrl ?>">Salir</a>
      <?php else : ?>
        <a class="mn-btn mn-btn--ghost" href="<?= $dashboardUrl ?>">Jugadores</a>
        <button type="button" class="mn-btn mn-btn--ghost" id="mn-open-admin">Organización / staff</button>
      <?php endif; ?>
    </div>
  </div>
</header>
