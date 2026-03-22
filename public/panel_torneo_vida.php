<?php

declare(strict_types=1);

/**
 * Panel de control — flujo de vida del torneo (3 columnas).
 * Acciones visibles y habilitadas según tournaments.tipo_torneo (individual | parejas | equipos).
 */

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
$scope = mn_admin_torneo_query_scope();

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$publicPrefix = str_contains($script, '/public/') ? '' : 'public/';

$torneoId = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$error = null;
$torneo = null;

if ($scope === false) {
    $error = 'Sin organización en sesión. Este panel requiere un workspace de organizador.';
} elseif ($torneoId <= 0) {
    $error = 'Indique un torneo: panel_torneo_vida.php?torneo_id=ID';
} else {
    try {
        $pdo = Connection::get();
        $torneo = TournamentEngineService::getTorneo($pdo, $torneoId, $scope);
        if ($torneo === null) {
            $error = 'Torneo no encontrado o fuera de su organización.';
        } elseif (!OrganizacionService::adminPuedeGestionarTorneo($admin, $torneo)) {
            $error = 'No tiene permisos para gestionar este torneo.';
            $torneo = null;
        }
    } catch (Throwable $e) {
        $error = 'Error de conexión.';
    }
}

$tipo = $torneo !== null ? strtolower(trim((string) ($torneo['tipo_torneo'] ?? 'individual'))) : '';
if ($tipo === '' || !in_array($tipo, ['individual', 'parejas', 'equipos'], true)) {
    $tipo = 'individual';
}

$esIndividual = $tipo === 'individual';
$esParejas = $tipo === 'parejas';
$esEquipos = $tipo === 'equipos';

$checkinUrl = $publicPrefix . 'checkin.php?torneo_id=' . $torneoId;
$adminTorneosUrl = $publicPrefix . 'admin_torneo.php';

$mn_institucional = [];
try {
    $pdoHdr = isset($pdo) ? $pdo : Connection::get();
    $mn_institucional = InstitucionalContextService::forAdmin($pdoHdr, $admin, $torneo, null);
} catch (Throwable $e) {
    $mn_institucional = InstitucionalContextService::forAdmin(null, $admin, $torneo, null);
}

/**
 * @param array{href:string,label:string,desc:string,enabled:bool,soon?:bool,extraClass?:string} $a
 */
function mn_panel_tile(array $a): void
{
    $href = (string) $a['href'];
    $label = (string) $a['label'];
    $desc = (string) $a['desc'];
    $enabled = (bool) $a['enabled'];
    $soon = (bool) ($a['soon'] ?? false);
    $extra = trim((string) ($a['extraClass'] ?? ''));
    $classes = 'mn-panel-vida__tile';
    if ($extra !== '') {
        $classes .= ' ' . $extra;
    }
    if (!$enabled) {
        $classes .= ' mn-panel-vida__tile--disabled';
    }
    if ($soon) {
        $classes .= ' mn-panel-vida__tile--soon';
    }
    if ($enabled && $href !== '' && $href !== '#') {
        echo '<a class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
    } else {
        echo '<span class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" role="group" aria-disabled="true">';
    }
    echo '<span class="mn-panel-vida__tile-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '<span class="mn-panel-vida__tile-desc">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</span>';
    if ($soon && !$enabled) {
        echo '<span class="mn-panel-vida__badge">Fase 4</span>';
    }
    if ($enabled && $href !== '' && $href !== '#') {
        echo '</a>';
    } else {
        echo '</span>';
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Panel del torneo — mistorneos</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($publicPrefix, ENT_QUOTES, 'UTF-8') ?>assets/css/mistorneos-core.css" />
</head>
<body class="mn-panel-vida-body">
  <?php require $root . '/public/views/partials/header_institucional.php'; ?>

  <div class="mn-container mn-panel-vida-wrap">
    <header class="mn-panel-vida-header">
      <div>
        <p class="mn-panel-vida-eyebrow">Flujo de vida del torneo</p>
        <?php if ($torneo !== null) : ?>
          <h1 class="mn-panel-vida-title"><?= htmlspecialchars((string) $torneo['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="mn-panel-vida-meta">
            ID <?= (int) $torneo['id'] ?> ·
            Tipo: <strong><?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></strong>
          </p>
        <?php else : ?>
          <h1 class="mn-panel-vida-title">Panel de control</h1>
        <?php endif; ?>
      </div>
      <div class="mn-panel-vida-header__actions">
        <a class="mn-panel-vida-link" href="<?= htmlspecialchars($adminTorneosUrl, ENT_QUOTES, 'UTF-8') ?>">← Torneos</a>
        <?php if ($torneo !== null) : ?>
          <a class="mn-btn mn-btn--ghost mn-panel-vida-btn-head" href="<?= htmlspecialchars($checkinUrl, ENT_QUOTES, 'UTF-8') ?>">Check-in rápido</a>
        <?php endif; ?>
      </div>
    </header>

    <?php if ($error !== null) : ?>
      <p class="mn-hint mn-hint--error mn-panel-vida-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else : ?>

      <div class="mn-panel-vida-legend" role="note">
        <span class="mn-panel-vida-legend__item"><span class="mn-panel-vida-dot mn-panel-vida-dot--pre"></span> Pre-torneo</span>
        <span class="mn-panel-vida-legend__item"><span class="mn-panel-vida-dot mn-panel-vida-dot--op"></span> Operaciones</span>
        <span class="mn-panel-vida-legend__item"><span class="mn-panel-vida-dot mn-panel-vida-dot--rep"></span> Reportes</span>
      </div>

      <div class="mn-panel-vida-grid">
        <!-- Columna 1: Pre-torneo -->
        <section class="mn-panel-vida-col mn-panel-vida-col--pre" aria-labelledby="mn-col-pre">
          <div class="mn-panel-vida-col-head">
            <h2 id="mn-col-pre" class="mn-panel-vida-col-title">Pre-torneo</h2>
            <p class="mn-panel-vida-col-sub">Preparación e identificación</p>
          </div>
          <div class="mn-panel-vida-tiles">
            <?php
            mn_panel_tile([
                'href' => '#',
                'label' => 'Carnets con QR',
                'desc' => 'Impresión: foto, nombre, cédula e ID (listo para credenciales).',
                'enabled' => false,
                'soon' => true,
            ]);
            mn_panel_tile([
                'href' => $checkinUrl,
                'label' => 'Check-in e inscritos',
                'desc' => 'Búsqueda por cédula, ratificación y listado en sitio.',
                'enabled' => true,
            ]);
            ?>
          </div>
        </section>

        <!-- Columna 2: Operaciones -->
        <section class="mn-panel-vida-col mn-panel-vida-col--op" aria-labelledby="mn-col-op">
          <div class="mn-panel-vida-col-head">
            <h2 id="mn-col-op" class="mn-panel-vida-col-title">Operaciones</h2>
            <p class="mn-panel-vida-col-sub">Corazón del evento en pista</p>
          </div>
          <?php if ($torneo !== null) : ?>
            <a class="mn-panel-vida-carga-mesa" href="<?= htmlspecialchars($publicPrefix . 'carga_resultados.php?torneo_id=' . $torneoId, ENT_QUOTES, 'UTF-8') ?>">
              CARGAR RESULTADOS DE MESA
            </a>
          <?php endif; ?>
          <div class="mn-panel-vida-tiles">
            <?php
            mn_panel_tile([
                'href' => '#',
                'label' => 'Asignación de mesas',
                'desc' => 'Cuadrícula técnica según el sistema de competencia (integración mesas).',
                'enabled' => false,
                'soon' => true,
            ]);
            mn_panel_tile([
                'href' => '#',
                'label' => 'Hojas de anotación',
                'desc' => 'PDF carta para árbitros, listo para imprimir.',
                'enabled' => false,
                'soon' => true,
            ]);
            mn_panel_tile([
                'href' => '#',
                'label' => 'Clasificación / siembra',
                'desc' => 'Ranking y orden de juego según reglamento técnico.',
                'enabled' => false,
                'soon' => true,
            ]);
            ?>
          </div>
        </section>

        <!-- Columna 3: Reportes -->
        <section class="mn-panel-vida-col mn-panel-vida-col--rep" aria-labelledby="mn-col-rep">
          <div class="mn-panel-vida-col-head">
            <h2 id="mn-col-rep" class="mn-panel-vida-col-title">Reportes</h2>
            <p class="mn-panel-vida-col-sub">Resultados y podios</p>
          </div>
          <div class="mn-panel-vida-tiles">
            <?php
            mn_panel_tile([
                'href' => '#',
                'label' => 'Resultados individuales',
                'desc' => 'Filtros por ronda, mesa y jugador.',
                'enabled' => false,
                'soon' => true,
                'extraClass' => 'mn-panel-vida__tile--individuales',
            ]);
            if ($esEquipos) {
                mn_panel_tile([
                    'href' => '#',
                    'label' => 'Equipos (resumen / detalle)',
                    'desc' => 'Tablas y desglose por equipo.',
                    'enabled' => false,
                    'soon' => true,
                    'extraClass' => 'mn-panel-vida__tile--equipos',
                ]);
            }
            if ($esParejas) {
                mn_panel_tile([
                    'href' => '#',
                    'label' => 'Parejas',
                    'desc' => 'Ranking y desempeño por pareja.',
                    'enabled' => false,
                    'soon' => true,
                    'extraClass' => 'mn-panel-vida__tile--parejas',
                ]);
            }
            mn_panel_tile([
                'href' => '#',
                'label' => 'Podios',
                'desc' => 'Top posiciones y ceremonia.',
                'enabled' => false,
                'soon' => true,
            ]);
            ?>
          </div>
          <p class="mn-panel-vida-hint" id="mn-tipo-hint">
            <?php if ($esIndividual) : ?>
              <strong>Individual:</strong> no se muestran accesos a informes de equipos ni de parejas.
            <?php elseif ($esParejas) : ?>
              <strong>Parejas:</strong> informes de equipos ocultos; use parejas, individuales y podios.
            <?php else : ?>
              <strong>Equipos:</strong> resumen y detalle por equipo disponibles junto a podios e informes individuales.
            <?php endif; ?>
          </p>
        </section>
      </div>

    <?php endif; ?>
  </div>
</body>
</html>
