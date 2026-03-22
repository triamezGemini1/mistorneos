<?php

declare(strict_types=1);

/** @var string $publicPrefix */
/** @var string $csrfToken */

$apiPadron = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') . 'api/padron_consulta.php';
$apiRegistro = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') . 'api/registro_desde_padron.php';
$dashboardRel = htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') . 'dashboard.php';

?>
<section
  class="mn-registro mn-hero"
  id="mn-registro-padron"
  aria-labelledby="mn-registro-title"
  data-api-padron="<?= $apiPadron ?>"
  data-api-registro="<?= $apiRegistro ?>"
  data-dashboard-url="<?= $dashboardRel ?>"
  data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
  style="border-bottom:1px solid var(--mn-border);padding-top:0;"
>
  <div class="mn-container">
    <h2 class="mn-hero__title" id="mn-registro-title" style="font-size:1.35rem;">Registro rápido con padrón</h2>
    <p class="mn-hint" style="margin-top:0;">
      Paso A: ingrese su cédula. Si existe en el padrón nacional (BD auxiliar), podrá completar solo correo, teléfono y contraseña.
    </p>

    <div class="mn-registro__step" data-step="a">
      <div class="mn-search" style="max-width:28rem;">
        <div class="mn-search__field">
          <label class="mn-label" for="mn-reg-cedula">Cédula / documento</label>
          <input
            class="mn-input"
            type="text"
            id="mn-reg-cedula"
            name="cedula_padron"
            inputmode="numeric"
            autocomplete="off"
            maxlength="20"
            placeholder="Ej. V12345678"
          />
        </div>
        <div class="mn-search__field mn-search__actions">
          <button type="button" class="mn-btn mn-btn--success mn-btn--block" id="mn-reg-buscar-padron">
            Buscar en padrón
          </button>
        </div>
      </div>
      <p class="mn-hint mn-hint--error" id="mn-reg-msg-a" hidden></p>
    </div>

    <div class="mn-registro__step mn-registro__panel" data-step="b" hidden>
      <p class="mn-registro__celebrate" id="mn-reg-encontrado" aria-live="polite"></p>
      <button type="button" class="mn-btn mn-btn--success" id="mn-reg-continuar">Confirmar y unirse</button>
    </div>

    <div class="mn-registro__step mn-registro__panel" data-step="c" hidden>
      <p class="mn-hint">Complete los datos que aún no tenemos en mistorneos:</p>
      <form id="mn-reg-form-final" class="mn-registro__form">
        <input type="hidden" name="cedula" id="mn-reg-hidden-cedula" value="" />
        <div class="mn-form-group">
          <label class="mn-label" for="mn-reg-email">Correo electrónico</label>
          <input class="mn-input" type="email" id="mn-reg-email" name="email" required maxlength="100" autocomplete="email" />
        </div>
        <div class="mn-form-group">
          <label class="mn-label" for="mn-reg-tel">Teléfono <span class="mn-placeholder">(opcional si su BD no tiene columna)</span></label>
          <input class="mn-input" type="tel" id="mn-reg-tel" name="telefono" maxlength="50" autocomplete="tel" />
        </div>
        <div class="mn-form-group">
          <label class="mn-label" for="mn-reg-pass">Contraseña</label>
          <input class="mn-input" type="password" id="mn-reg-pass" name="password" required minlength="8" autocomplete="new-password" />
        </div>
        <button type="submit" class="mn-btn mn-btn--success mn-btn--block">Crear mi cuenta</button>
      </form>
      <p class="mn-hint" id="mn-reg-msg-c" role="status"></p>
    </div>
  </div>
</section>
