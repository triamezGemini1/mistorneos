<?php

declare(strict_types=1);

/** @var string $csrfToken */
/** @var string $publicPrefix */

?>
<div class="mn-modal-backdrop" id="mn-auth-backdrop" aria-hidden="true"></div>
<aside
  class="mn-modal"
  id="mn-auth-panel"
  role="dialog"
  aria-modal="true"
  aria-labelledby="mn-auth-title"
  aria-hidden="true"
>
  <div class="mn-modal__head">
    <h2 id="mn-auth-title">Acceso administrador</h2>
    <button type="button" class="mn-modal__close" id="mn-auth-close" aria-label="Cerrar">&times;</button>
  </div>
  <div class="mn-modal__body">
    <form id="mn-auth-form" method="post" action="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>auth_process.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
      <div class="mn-form-group">
        <label class="mn-label" for="mn-auth-user">Usuario o correo</label>
        <input
          class="mn-input"
          type="text"
          id="mn-auth-user"
          name="usuario"
          autocomplete="username"
          maxlength="128"
          required
        />
        <div class="mn-form-status" id="mn-auth-user-status" aria-live="polite"></div>
      </div>
      <div class="mn-form-group">
        <label class="mn-label" for="mn-auth-pass">Contraseña</label>
        <input
          class="mn-input"
          type="password"
          id="mn-auth-pass"
          name="password"
          autocomplete="current-password"
          minlength="8"
          required
        />
        <div class="mn-form-status" id="mn-auth-pass-status" aria-live="polite"></div>
      </div>
      <button type="submit" class="mn-btn mn-btn--success mn-btn--block">Entrar</button>
    </form>
  </div>
</aside>
