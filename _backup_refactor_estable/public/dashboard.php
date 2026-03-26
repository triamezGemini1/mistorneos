<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
require $root . '/config/internal_to_landing.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/InstitucionalContextService.php';

AuthHelper::requireUser();

$u = AuthHelper::currentUser();
if ($u === null) {
    exit;
}

$mn_institucional = [
    'entidad' => ['id' => 0, 'nombre' => '', 'logo' => null],
    'organizacion' => ['id' => 0, 'nombre' => '', 'logo' => null],
    'club' => null,
    'torneo' => null,
];
try {
    $mn_institucional = InstitucionalContextService::forAtleta(Connection::get(), $u);
} catch (Throwable $e) {
    // Sin BD no se resuelve club → cintillo omitido
}

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$publicPrefix = str_contains($script, '/public/') ? '' : 'public/';

$nombre = trim((string) ($u['nombre'] ?? ''));
$cedula = trim((string) ($u['cedula'] ?? ''));
$email = trim((string) ($u['email'] ?? ''));
$nac = trim((string) ($u['nacionalidad'] ?? ''));

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mi panel — mistorneos</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/css/mistorneos-core.css" />
</head>
<body class="mn-dash-body">
  <?php require $root . '/public/views/partials/header_institucional.php'; ?>
  <div class="mn-dash-shell">
    <aside class="mn-dash-sidebar" aria-label="Menú principal">
      <div class="mn-dash-sidebar__brand">
        <span class="mn-dash-sidebar__logo" aria-hidden="true">M</span>
        <span class="mn-dash-sidebar__title">mistorneos</span>
      </div>
      <nav class="mn-dash-nav">
        <a class="mn-dash-nav__link mn-dash-nav__link--active" href="dashboard.php">Inicio</a>
        <a class="mn-dash-nav__link" href="#">Mis torneos</a>
        <a class="mn-dash-nav__link" href="#">Ranking</a>
        <a class="mn-dash-nav__link" href="#">Configuración</a>
      </nav>
      <div class="mn-dash-sidebar__foot">
        <a class="mn-dash-nav__link mn-dash-nav__link--muted" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>index.php">Volver al sitio</a>
        <a class="mn-dash-nav__link mn-dash-nav__link--muted" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>logout.php">Cerrar sesión</a>
      </div>
    </aside>

    <div class="mn-dash-main">
      <header class="mn-dash-topbar">
        <p class="mn-dash-topbar__tag">Perfil de atleta</p>
        <h1 class="mn-dash-topbar__h1">Bienvenido a mistorneos</h1>
      </header>

      <div class="mn-container mn-dash-content">
        <section class="mn-dash-profile" aria-labelledby="dash-perfil-title">
          <h2 class="mn-dash-profile__title" id="dash-perfil-title">Estado de tu perfil</h2>
          <p class="mn-dash-profile__welcome">
            Hola, <strong><?= htmlspecialchars($nombre !== '' ? $nombre : ($u['username'] ?? 'atleta'), ENT_QUOTES, 'UTF-8') ?></strong>.
            Tu cuenta está activa; aquí verás torneos y ranking cuando conectemos esas secciones.
          </p>
          <dl class="mn-dash-dl">
            <div class="mn-dash-dl__row">
              <dt>Nombre completo</dt>
              <dd><?= htmlspecialchars($nombre !== '' ? $nombre : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div class="mn-dash-dl__row">
              <dt>Cédula / documento</dt>
              <dd><?= htmlspecialchars($cedula !== '' ? $cedula : '—', ENT_QUOTES, 'UTF-8') ?>
                <?= $nac !== '' ? ' <span class="mn-dash-dl__muted">(' . htmlspecialchars($nac, ENT_QUOTES, 'UTF-8') . ')</span>' : '' ?>
              </dd>
            </div>
            <div class="mn-dash-dl__row">
              <dt>Correo</dt>
              <dd><?= htmlspecialchars($email !== '' ? $email : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div class="mn-dash-dl__row">
              <dt>Usuario</dt>
              <dd><?= htmlspecialchars((string) ($u['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
          </dl>
          <p class="mn-hint" style="margin-top:1.25rem;">
            Datos de identidad provienen del padrón; puede completar o corregir información de contacto en Configuración cuando esté disponible.
          </p>
        </section>
      </div>
    </div>
  </div>
</body>
</html>
