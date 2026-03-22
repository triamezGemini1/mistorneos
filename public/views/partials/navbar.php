<?php

declare(strict_types=1);

/** @var string $publicPrefix */

?>
<header class="mn-nav" role="banner">
  <div class="mn-container mn-nav__inner">
    <a class="mn-logo" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>index.php" aria-label="mistorneos — inicio">
      <span class="mn-logo__mark" aria-hidden="true">M</span>
      <span>mistorneos</span>
    </a>
    <nav aria-label="Navegación principal">
      <ul class="mn-nav__links">
        <li><a href="#torneos">Torneos</a></li>
        <li><a href="#resultados">Resultados</a></li>
        <li><a href="#ranking">Ranking</a></li>
        <li><a href="#buscar">Buscar atleta</a></li>
      </ul>
    </nav>
    <button type="button" class="mn-btn mn-btn--ghost" id="mn-open-admin">Acceso administrador</button>
  </div>
</header>
