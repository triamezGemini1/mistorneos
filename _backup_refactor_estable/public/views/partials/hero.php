<?php

declare(strict_types=1);

/** @var string $publicPrefix */

$apiBuscar = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') . 'api/buscar_atleta.php';
$resUrl = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') . 'resultados.php';
$calUrl = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') . 'calendario.php';
$afilUrl = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') . 'afiliacion.php';

?>
<section class="mn-hero" id="buscar" aria-labelledby="mn-hero-title">
  <div class="mn-container">
    <h1 class="mn-hero__title" id="mn-hero-title">Torneos, resultados y calendario</h1>
    <p class="mn-hero__lead">
      Explorá el calendario y los resultados <strong>sin iniciar sesión</strong>. Podés buscar jugadores en la tabla maestra de la plataforma
      o registrarte más abajo para participar como atleta. Organizadores y staff usan <em>Organización / staff</em> en la barra superior.
    </p>
    <p class="mn-hero__links" style="display:flex;flex-wrap:wrap;gap:0.75rem;margin:1rem 0 1.25rem;">
      <a class="mn-btn mn-btn--success" href="<?= $resUrl ?>">Resultados públicos</a>
      <a class="mn-btn mn-btn--ghost" href="<?= $calUrl ?>">Calendario</a>
      <a class="mn-btn mn-btn--ghost" href="<?= $afilUrl ?>">Solicitar afiliación</a>
    </p>
    <form
      class="mn-search"
      id="mn-athlete-search"
      action="<?= $apiBuscar ?>"
      method="get"
      role="search"
      aria-label="Buscar atleta"
      data-endpoint="<?= $apiBuscar ?>"
    >
      <div class="mn-search__field">
        <label class="mn-label" for="mn-athlete-q">Buscar atleta</label>
        <input
          class="mn-input"
          type="search"
          id="mn-athlete-q"
          name="q"
          placeholder="Cédula/DNI o inicio del nombre"
          autocomplete="off"
          maxlength="120"
        />
      </div>
      <div class="mn-search__field mn-search__actions">
        <button type="submit" class="mn-btn mn-btn--success mn-btn--block">Buscar</button>
      </div>
    </form>
    <div
      id="mn-athlete-results"
      class="mn-results"
      role="region"
      aria-live="polite"
      aria-label="Resultados de búsqueda"
      hidden
    ></div>
    <p class="mn-hint" id="mn-athlete-hint">Escriba al menos 2 letras para nombre o 3 dígitos para documento.</p>
  </div>
</section>
