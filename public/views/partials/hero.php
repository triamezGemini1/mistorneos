<?php

declare(strict_types=1);

/** @var string $publicPrefix */

$apiBuscar = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') . 'api/buscar_atleta.php';

?>
<section class="mn-hero" id="buscar" aria-labelledby="mn-hero-title">
  <div class="mn-container">
    <h1 class="mn-hero__title" id="mn-hero-title">Gestión y consulta de torneos</h1>
    <p class="mn-hero__lead">
      Consulta sobre la tabla maestra <strong>usuarios</strong> de la plataforma (documento exacto o prefijo, nombre por prefijo).
      Máximo 10 resultados por petición. La base auxiliar de personas se reserva para validar altas en registro.
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
