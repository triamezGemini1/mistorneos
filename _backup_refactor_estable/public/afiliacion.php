<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$publicPrefix = str_contains($script, '/public/') ? '' : 'public/';
$csrfToken = csrf_token();

$contactoEmail = trim((string) (getenv('AFILIACION_CONTACTO_EMAIL') ?: ''));
$contactoWhatsapp = trim((string) (getenv('AFILIACION_WHATSAPP') ?: ''));

$pageTitle = 'Solicitud de afiliación';

ob_start();
?>
  <div class="mn-container" style="padding:2rem 0;max-width:40rem;">
    <header class="mn-public-head">
      <h1 class="mn-hero__title" style="margin-bottom:0.5rem;">Afiliación de clubes y organizaciones</h1>
      <p class="mn-hint" style="margin-top:0;">
        Podés explorar torneos y resultados sin cuenta. Para gestionar eventos o afiliar una entidad, contactá al equipo.
      </p>
    </header>

    <div class="mn-card" style="margin-top:1.5rem;">
      <h2 class="mn-card__title" style="font-size:1.1rem;">Cómo solicitar</h2>
      <ol class="mn-list" style="padding-left:1.25rem;margin:0.75rem 0 0;">
        <li>Enviá los datos de la entidad o club (nombre, responsable, ciudad).</li>
        <li>Indicá si querés acceso como organizador de torneos o solo consulta.</li>
        <li>El equipo revisará la solicitud y responderá por el canal indicado abajo.</li>
      </ol>
    </div>

    <div class="mn-card" style="margin-top:1rem;">
      <h2 class="mn-card__title" style="font-size:1.1rem;">Contacto</h2>
      <?php if ($contactoEmail !== '') : ?>
        <p style="margin:0.5rem 0 0;">
          <a class="mn-btn mn-btn--success" href="mailto:<?= htmlspecialchars($contactoEmail, ENT_QUOTES, 'UTF-8') ?>?subject=<?= rawurlencode('Solicitud de afiliación — mistorneos') ?>">Escribir por correo</a>
        </p>
        <p class="mn-hint" style="margin-top:0.75rem;"><?= htmlspecialchars($contactoEmail, ENT_QUOTES, 'UTF-8') ?></p>
      <?php else : ?>
        <p class="mn-hint" style="margin-top:0.5rem;">
          Configurá la variable de entorno <code>AFILIACION_CONTACTO_EMAIL</code> en <code>.env</code> para mostrar el botón de correo.
        </p>
      <?php endif; ?>
      <?php if ($contactoWhatsapp !== '') : ?>
        <p style="margin-top:1rem;">
          <a class="mn-btn mn-btn--ghost" href="https://wa.me/<?= preg_replace('/\D+/', '', $contactoWhatsapp) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
          <span class="mn-hint" style="margin-left:0.5rem;"><?= htmlspecialchars($contactoWhatsapp, ENT_QUOTES, 'UTF-8') ?></span>
        </p>
      <?php endif; ?>
    </div>

    <p style="margin-top:1.5rem;">
      <a href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>index.php">← Volver al inicio</a>
    </p>
  </div>
<?php
$content = ob_get_clean();
require $root . '/public/views/layout_public.php';
