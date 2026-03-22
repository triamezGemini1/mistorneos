<?php

declare(strict_types=1);

?>
<section class="mn-hero" id="buscar" aria-labelledby="mn-hero-title">
  <div class="mn-container">
    <h1 class="mn-hero__title" id="mn-hero-title">Gestión y consulta de torneos</h1>
    <p class="mn-hero__lead">
      Buscador de atletas pensado para alto volumen: consultas por identificador o término acotado,
      con paginación y filtros en servidor (sin cargar millones de filas en el navegador).
    </p>
    <form class="mn-search" action="#" method="get" role="search" aria-label="Buscar atleta">
      <div class="mn-search__field">
        <label class="mn-label" for="mn-athlete-q">Buscar atleta</label>
        <input
          class="mn-input"
          type="search"
          id="mn-athlete-q"
          name="q"
          placeholder="Documento, nombre o ID federativo"
          autocomplete="off"
          maxlength="120"
        />
      </div>
      <div class="mn-search__field" style="display:flex;align-items:flex-end;">
        <button type="submit" class="mn-btn mn-btn--success mn-btn--block">Buscar</button>
      </div>
    </form>
    <p class="mn-hint">
      Próximo paso: endpoint dedicado con índices (p. ej. búsqueda por prefijo + límite) para escalar a decenas de millones de registros.
    </p>
  </div>
</section>
