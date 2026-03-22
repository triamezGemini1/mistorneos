<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config/bootstrap.php';
require_once $root . '/app/Database/ConnectionException.php';
require_once $root . '/app/Database/Connection.php';
require_once $root . '/app/Core/TournamentEngineService.php';
require_once $root . '/app/Core/OrganizacionService.php';
require_once $root . '/app/Core/InstitucionalContextService.php';
require_once $root . '/app/Helpers/AdminApi.php';

if (mn_admin_session() === null) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $to = str_contains($script, '/public/') ? 'index.php' : 'public/index.php';
    header('Location: ' . $to . '?acceso=admin', true, 303);
    exit;
}

$admin = mn_admin_session();
$torneoId = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$publicPrefix = str_contains($script, '/public/') ? '' : 'public/';
$csrfToken = csrf_token();

$error = null;
$torneo = null;

$scope = mn_admin_torneo_query_scope();
if ($scope === false) {
    $error = 'Sin organización en sesión. El check-in requiere un workspace de organizador.';
} elseif ($torneoId <= 0) {
    $error = 'Indique torneo_id en la URL (ej. checkin.php?torneo_id=1).';
} else {
    try {
        $pdo = Connection::get();
        $torneo = TournamentEngineService::getTorneo($pdo, $torneoId, $scope);
        if ($torneo === null) {
            $error = 'Torneo no encontrado o no pertenece a su organización.';
        } elseif (!OrganizacionService::adminPuedeGestionarTorneo($admin, $torneo)) {
            $error = 'No tiene permisos para check-in de este torneo (organización distinta).';
            $torneo = null;
        }
    } catch (Throwable $e) {
        $error = 'Error de conexión.';
    }
}

$mn_institucional = [];
try {
    $pdoHdr = Connection::get();
    $mn_institucional = InstitucionalContextService::forAdmin($pdoHdr, $admin, $torneo, null);
} catch (Throwable $e) {
    $mn_institucional = InstitucionalContextService::forAdmin(null, $admin, $torneo, null);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Check-in — mistorneos</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/css/mistorneos-core.css" />
</head>
<body class="mn-checkin-body">
  <?php require $root . '/public/views/partials/header_institucional.php'; ?>
  <div class="mn-container mn-checkin-wrap">
    <header class="mn-checkin-head">
      <h1 class="mn-checkin-title">Check-in en sitio</h1>
      <?php if ($torneo !== null) : ?>
        <p class="mn-checkin-sub"><?= htmlspecialchars((string) $torneo['nombre'], ENT_QUOTES, 'UTF-8') ?> · ID <?= (int) $torneo['id'] ?></p>
      <?php endif; ?>
      <a class="mn-checkin-back" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>dashboard_test.php">← Panel admin</a>
    </header>

    <?php if ($error !== null) : ?>
      <p class="mn-hint mn-hint--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else : ?>
      <section class="mn-checkin-search" aria-label="Búsqueda rápida">
        <div class="mn-search" style="max-width:36rem;">
          <div class="mn-search__field">
            <label class="mn-label" for="mn-checkin-q">Cédula o documento</label>
            <input class="mn-input" type="text" id="mn-checkin-q" maxlength="20" autocomplete="off" />
          </div>
          <div class="mn-search__field mn-search__actions">
            <button type="button" class="mn-btn mn-btn--success mn-btn--block" id="mn-checkin-buscar">Buscar</button>
          </div>
        </div>
        <div id="mn-checkin-result" class="mn-checkin-result" hidden></div>
      </section>

      <section class="mn-checkin-watcher" aria-live="polite">
        <div id="mn-checkin-watcher-box" class="mn-checkin-watcher__box">
          <span class="mn-checkin-watcher__label">Atletas ratificados</span>
          <strong id="mn-checkin-ratificados" class="mn-checkin-watcher__n">0</strong>
          <span class="mn-hint">Individual: mínimo 8 ratificados para Ronda 1.</span>
        </div>
        <button type="button" class="mn-btn mn-trigger-ronda1" id="mn-btn-ronda1" disabled>
          Generar Ronda 1
        </button>
      </section>

      <section class="mn-checkin-tabla-wrap" aria-label="Inscritos">
        <h2 class="mn-checkin-h2">Inscritos</h2>
        <div class="mn-checkin-table-scroll">
          <table class="mn-checkin-table" id="mn-checkin-tabla">
            <thead>
              <tr>
                <th>Atleta</th>
                <th>Cédula</th>
                <th>Ratif.</th>
                <th>Presente</th>
              </tr>
            </thead>
            <tbody id="mn-checkin-tbody"></tbody>
          </table>
        </div>
      </section>

      <script>
        window.MN_CHECKIN = {
          torneoId: <?= (int) $torneoId ?>,
          csrf: <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>,
          apiLista: <?= json_encode($publicPrefix . 'api/checkin_lista.php', JSON_HEX_TAG | JSON_HEX_APOS) ?>,
          apiBuscar: <?= json_encode($publicPrefix . 'api/checkin_buscar.php', JSON_HEX_TAG | JSON_HEX_APOS) ?>,
          apiInscribir: <?= json_encode($publicPrefix . 'api/checkin_inscribir.php', JSON_HEX_TAG | JSON_HEX_APOS) ?>,
          apiToggle: <?= json_encode($publicPrefix . 'api/checkin_toggle.php', JSON_HEX_TAG | JSON_HEX_APOS) ?>,
          apiWatcher: <?= json_encode($publicPrefix . 'api/torneo_watcher_ronda1.php', JSON_HEX_TAG | JSON_HEX_APOS) ?>,
          apiRonda1: <?= json_encode($publicPrefix . 'api/torneo_generar_ronda1.php', JSON_HEX_TAG | JSON_HEX_APOS) ?>
        };
      </script>
      <script src="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/js/checkin.js" defer></script>
    <?php endif; ?>
  </div>
</body>
</html>
