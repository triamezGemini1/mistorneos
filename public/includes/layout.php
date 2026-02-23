<?php
// layout.php
// La autenticación ya se verificó en index.php
$user = $_SESSION['user'];
$current_page = $_GET['page'] ?? 'home';

// Base URL para CSS/JS (carpeta public/) — evita doble public/public
$layout_asset_base = AppHelpers::getPublicUrl();

// Logo y nombre para el identificador del dashboard (organización cuando no es admin_general)
$dashboard_org = Auth::getDashboardOrganizacion();

// Contar solicitudes pendientes (solo para admin_general)
$solicitudes_pendientes = 0;
if ($user['role'] === 'admin_general') {
    try {
        $solicitudes_pendientes = DB::pdo()->query("SELECT COUNT(*) FROM solicitudes_afiliacion WHERE estatus = 'pendiente'")->fetchColumn();
    } catch (Exception $e) {
        $solicitudes_pendientes = 0;
    }
}

// Contar actas pendientes de verificación (admin_club, admin_general y admin_torneo)
$actas_pendientes_count = 0;
if (in_array($user['role'], ['admin_club', 'admin_general', 'admin_torneo'], true)) {
    try {
        require_once __DIR__ . '/../../lib/ActasPendientesHelper.php';
        $actas_pendientes_count = ActasPendientesHelper::contar();
    } catch (Exception $e) {
        $actas_pendientes_count = 0;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <base href="<?= htmlspecialchars($layout_asset_base) ?>/">
  <title><?= $dashboard_org ? 'Dashboard - ' . htmlspecialchars($dashboard_org['nombre']) : 'Dashboard - La Estación del Dominó' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="theme-color" content="#1a365d">
  
  <!-- SEO Meta Tags -->
  <meta name="description" content="Panel de administración de La Estación del Dominó - Gestión de torneos, inscripciones y resultados">
  <meta name="robots" content="noindex, nofollow">
  <meta name="language" content="es">
  
  <!-- Preconnect: conexiones tempranas a CDNs -->
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Estilos custom -->
  <link rel="stylesheet" href="<?= htmlspecialchars($layout_asset_base) ?>/assets/dashboard.css">
  <!-- Google Fonts: carga diferida -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"></noscript>
  <!-- Iconos: carga diferida -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
</head>
<body class="bg-light">
  <!-- Contenedor para notificaciones toast (Push + tarjeta visual) -->
  <div id="notification-container" aria-live="polite"></div>

  <!-- Mensajes flash (éxito/error) superpuestos, no desplazan el contenido -->
  <div id="app-flash-messages" class="app-flash-messages" aria-live="polite">
    <?php
    $flash_success = $_SESSION['success'] ?? $_SESSION['success_message'] ?? null;
    $flash_error   = $_SESSION['error'] ?? $_SESSION['error_message'] ?? null;
    $flash_warning = $_SESSION['warning'] ?? $_SESSION['warning_message'] ?? null;
    $flash_info    = $_SESSION['info'] ?? $_SESSION['info_message'] ?? null;
    if ($flash_success) { unset($_SESSION['success'], $_SESSION['success_message']); ?>
    <div class="alert alert-success alert-dismissible fade show app-flash-item" role="alert">
      <?= htmlspecialchars($flash_success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php }
    if ($flash_error) { unset($_SESSION['error'], $_SESSION['error_message']); ?>
    <div class="alert alert-danger alert-dismissible fade show app-flash-item" role="alert">
      <?= htmlspecialchars($flash_error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php }
    if ($flash_warning) { unset($_SESSION['warning'], $_SESSION['warning_message']); ?>
    <div class="alert alert-warning alert-dismissible fade show app-flash-item" role="alert">
      <?= htmlspecialchars($flash_warning) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php }
    if ($flash_info) { unset($_SESSION['info'], $_SESSION['info_message']); ?>
    <div class="alert alert-info alert-dismissible fade show app-flash-item" role="alert">
      <?= htmlspecialchars($flash_info) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php } ?>
  </div>

  <div class="d-flex" id="wrapper">
    
    <!-- Sidebar -->
    <nav id="sidebar" class="bg-dark text-white border-end shadow d-flex flex-column" style="height: 100vh;">
      <div class="sidebar-header p-4 border-bottom">
        <h4 class="mb-0 text-center d-flex align-items-center justify-content-center flex-nowrap">
          <?= AppHelpers::appLogo('me-2', 'La Estación del Dominó', 35, true) ?>
          <span class="sidebar-brand text-truncate" title="La Estación del Dominó">La Estación del Dominó</span>
        </h4>
      </div>
      
      <ul class="list-unstyled px-3 py-3 flex-grow-1" style="overflow-y: auto;">
        <?php if ($user['role'] !== 'admin_general'): ?>
        <!-- Inicio y Calendario: links directos para admin_club y admin_torneo -->
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard()) ?>" class="nav-link <?= $current_page === 'home' ? 'active' : '' ?>">
            <i class="fas fa-home me-3"></i>
            <span class="nav-text">Inicio</span>
          </a>
        </li>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('calendario')) ?>" class="nav-link <?= $current_page === 'calendario' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt me-3"></i>
            <span class="nav-text">Calendario</span>
          </a>
        </li>
        <?php endif; ?>
        
        <?php if ($user['role'] === 'admin_club'): ?>
        <?php
        // Detectar si estamos en una página de gestión de torneos
        $is_torneo_gestion = ($current_page === 'torneo_gestion');
        
        // Obtener torneo_id desde diferentes fuentes
        $torneo_id_selected = (int)($_GET['torneo_id'] ?? $_REQUEST['torneo_id'] ?? $_POST['torneo_id'] ?? 0);
        if ($torneo_id_selected === 0 && isset($_SESSION['current_torneo_id'])) {
          $torneo_id_selected = (int)$_SESSION['current_torneo_id'];
        }
        
        $torneo_action = $_GET['action'] ?? $_REQUEST['action'] ?? '';
        $is_torneo_menu_active = $is_torneo_gestion || in_array($torneo_action, ['index', 'panel', 'panel_equipos', 'mesas', 'rondas', 'posiciones', 'galeria_fotos', 'inscripciones', 'notificaciones', 'inscribir_sitio', 'inscribir_equipo_sitio', 'gestionar_inscripciones_equipos', 'cuadricula', 'hojas_anotacion', 'registrar_resultados', 'registrar_resultados_v2', 'agregar_mesa', 'reasignar_mesa', 'podio', 'podios', 'podios_equipos', 'resultados_por_club', 'resumen_individual', 'equipos', 'verificar_actas', 'verificar_acta', 'verificar_actas_index', 'verificar_resultados']) || in_array($current_page, ['invitations', 'notificaciones_masivas']);
        $is_torneo_submenu_open = $torneo_id_selected > 0 || $is_torneo_menu_active;
        
        if ($torneo_id_selected > 0) {
          if ($current_page === 'registrants') { $is_torneo_menu_active = true; $is_torneo_submenu_open = true; $torneo_action = 'inscripciones'; }
          elseif ($current_page === 'player_invitations' || $current_page === 'tournaments/invitation_link') { $is_torneo_menu_active = true; $is_torneo_submenu_open = true; }
        }
        
        $filtro_actual_ac = $_GET['filtro'] ?? '';
        $admin_club_org_id = Auth::getUserOrganizacionId();
        ?>
        
        <!-- Mi Organización: acceso directo a vista de estructura (organizaciones&id=X) -->
        <?php if ($admin_club_org_id): ?>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('organizaciones', ['id' => $admin_club_org_id])) ?>" class="nav-link <?= ($current_page === 'organizaciones' && (int)($_GET['id'] ?? 0) === $admin_club_org_id) ? 'active' : '' ?>">
            <i class="fas fa-building me-3"></i>
            <span class="nav-text">Mi Organización</span>
          </a>
        </li>
        <?php endif; ?>
        <!-- Menú al mismo nivel (sin agrupación Organizaciones) -->
        <li class="mb-2">
          <a href="index.php?page=torneo_gestion&action=index" class="nav-link <?= ($current_page === 'torneo_gestion' && ($_GET['action'] ?? '') === 'index') ? 'active' : '' ?>">
            <i class="fas fa-trophy me-3"></i>
            <span class="nav-text">Torneos</span>
          </a>
        </li>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('clubes_asociados')) ?>" class="nav-link <?= $current_page === 'clubes_asociados' ? 'active' : '' ?>">
            <i class="fas fa-sitemap me-3"></i>
            <span class="nav-text">Clubes de la organización</span>
          </a>
        </li>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('admin_torneo_operadores')) ?>" class="nav-link <?= $current_page === 'admin_torneo_operadores' ? 'active' : '' ?>">
            <i class="fas fa-user-cog me-3"></i>
            <span class="nav-text">Admin Torneo y Operadores</span>
          </a>
        </li>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('cuentas_bancarias')) ?>" class="nav-link <?= $current_page === 'cuentas_bancarias' ? 'active' : '' ?>">
            <i class="fas fa-university me-3"></i>
            <span class="nav-text">Cuentas Bancarias</span>
          </a>
        </li>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('reportes_pago_usuarios')) ?>" class="nav-link <?= $current_page === 'reportes_pago_usuarios' ? 'active' : '' ?>">
            <i class="fas fa-money-bill-wave me-3"></i>
            <span class="nav-text">Reportes de Pago</span>
            <?php
            try {
                $org_id_menu = class_exists('Auth') ? Auth::getUserOrganizacionId() : null;
                if ($org_id_menu) {
                    $stmt_p = DB::pdo()->prepare("SELECT COUNT(*) FROM reportes_pago_usuarios rpu INNER JOIN tournaments t ON rpu.torneo_id = t.id WHERE rpu.estatus = 'pendiente' AND t.club_responsable = ?");
                    $stmt_p->execute([$org_id_menu]);
                    $pendientes_pagos = $stmt_p->fetchColumn();
                } else {
                    $pendientes_pagos = 0;
                }
                if ($pendientes_pagos > 0):
            ?>
              <span class="badge bg-warning rounded-pill ms-2"><?= $pendientes_pagos ?></span>
            <?php
                endif;
            } catch (Exception $e) {}
            ?>
          </a>
        </li>
        
        <!-- Comentarios -->
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('comments_public')) ?>" class="nav-link <?= $current_page === 'comments_public' ? 'active' : '' ?>">
            <i class="fas fa-comment-dots me-3"></i>
            <span class="nav-text">Comentarios</span>
          </a>
        </li>
        <!-- 1. Portal Público -->
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::url('landing-spa.php')) ?>" class="nav-link">
            <i class="fas fa-id-card me-3"></i>
            <span class="nav-text">Portal Público</span>
            <i class="fas fa-external-link-alt ms-auto" style="font-size: 0.75rem;"></i>
          </a>
        </li>
        <!-- 1. Manual de Usuario -->
        <li class="mb-2">
          <a href="<?= htmlspecialchars(rtrim(AppHelpers::getBaseUrl(), '/') . '/manuales_web/manual_usuario.php') ?>" class="nav-link">
            <i class="fas fa-book me-3"></i>
            <span class="nav-text">Manual de Usuario</span>
            <i class="fas fa-external-link-alt ms-auto" style="font-size: 0.75rem;"></i>
          </a>
        </li>
        <?php endif; ?>
        
        <?php if ($user['role'] === 'admin_general'): ?>
        <?php
        $is_inicio_open = in_array($current_page, ['home', 'calendario']);
        $is_estructura_open = in_array($current_page, ['entidades', 'organizaciones', 'clubs', 'directorio_clubes']);
        $is_afiliaciones_open = in_array($current_page, ['admin_clubs', 'affiliate_requests']);
        $is_comunicacion_open = in_array($current_page, ['notificaciones_masivas', 'whatsapp_config', 'comments']);
        ?>
        <!-- 1. Inicio (acordeón: Dashboard, Calendario) -->
        <li class="mb-2">
          <a href="#" class="nav-link <?= $is_inicio_open ? 'active' : '' ?>"
             onclick="event.preventDefault(); toggleSubmenu('inicio-submenu', this);"
             style="cursor: pointer;">
            <i class="fas fa-home me-3"></i>
            <span class="nav-text">Inicio</span>
            <i class="fas fa-chevron-<?= $is_inicio_open ? 'up' : 'down' ?> ms-auto submenu-icon"></i>
          </a>
          <ul class="list-unstyled ps-4 mt-1 collapse-submenu <?= $is_inicio_open ? 'show' : '' ?>" id="inicio-submenu">
            <li class="mb-1">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard()) ?>" class="nav-link nav-sub-sub-link <?= $current_page === 'home' ? 'active' : '' ?>">
                <i class="fas fa-chart-line me-2"></i>
                <span>Dashboard</span>
              </a>
            </li>
            <li class="mb-1">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard('calendario')) ?>" class="nav-link nav-sub-sub-link <?= $current_page === 'calendario' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt me-2"></i>
                <span>Calendario</span>
              </a>
            </li>
          </ul>
        </li>
        <!-- 2. Estructura (acordeón: Entidades, Organizaciones, Clubes) -->
        <li class="mb-2">
          <a href="#" class="nav-link <?= $is_estructura_open ? 'active' : '' ?>"
             onclick="event.preventDefault(); toggleSubmenu('estructura-submenu', this);"
             style="cursor: pointer;">
            <i class="fas fa-sitemap me-3"></i>
            <span class="nav-text">Estructura</span>
            <i class="fas fa-chevron-<?= $is_estructura_open ? 'up' : 'down' ?> ms-auto submenu-icon"></i>
          </a>
          <ul class="list-unstyled ps-4 mt-1 collapse-submenu <?= $is_estructura_open ? 'show' : '' ?>" id="estructura-submenu">
            <li class="mb-1">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard('entidades')) ?>" class="nav-link nav-sub-sub-link <?= $current_page === 'entidades' ? 'active' : '' ?>">
                <i class="fas fa-map-marked-alt me-2"></i>
                <span>Entidades</span>
              </a>
            </li>
            <li class="mb-1">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard('organizaciones')) ?>" class="nav-link nav-sub-sub-link <?= $current_page === 'organizaciones' ? 'active' : '' ?>">
                <i class="fas fa-building me-2"></i>
                <span>Organizaciones</span>
              </a>
            </li>
            <li class="mb-1">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard('clubs')) ?>" class="nav-link nav-sub-sub-link <?= $current_page === 'clubs' ? 'active' : '' ?>">
                <i class="fas fa-building me-2"></i>
                <span>Clubes</span>
              </a>
            </li>
            <li class="nav-item mb-1">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard('directorio_clubes')) ?>" class="nav-link nav-sub-sub-link <?= $current_page === 'directorio_clubes' ? 'active' : '' ?>">
                <i class="fas fa-address-book me-2"></i>
                <span>Directorio de Clubes</span>
              </a>
            </li>
          </ul>
        </li>
        <!-- 3. Afiliaciones (acordeón: Invitar, Solicitudes) -->
        <li class="mb-2">
          <a href="#" class="nav-link <?= $is_afiliaciones_open ? 'active' : '' ?>"
             onclick="event.preventDefault(); toggleSubmenu('afiliaciones-submenu', this);"
             style="cursor: pointer;">
            <i class="fas fa-user-plus me-3"></i>
            <span class="nav-text">Afiliaciones</span>
            <i class="fas fa-chevron-<?= $is_afiliaciones_open ? 'up' : 'down' ?> ms-auto submenu-icon"></i>
          </a>
          <ul class="list-unstyled ps-4 mt-1 collapse-submenu <?= $is_afiliaciones_open ? 'show' : '' ?>" id="afiliaciones-submenu">
            <li class="mb-1">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard('admin_clubs', ['action' => 'invitar'])) ?>" class="nav-link nav-sub-sub-link <?= ($current_page === 'admin_clubs' && ($_GET['action'] ?? '') === 'invitar') ? 'active' : '' ?>">
                <i class="fas fa-user-plus me-2"></i>
                <span>Invitar Afiliados</span>
              </a>
            </li>
            <li class="mb-1">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard('affiliate_requests')) ?>" class="nav-link nav-sub-sub-link <?= $current_page === 'affiliate_requests' ? 'active' : '' ?>">
                <i class="fas fa-user-clock me-2"></i>
                <span>Solicitudes de Afiliación</span>
                <?php if ($solicitudes_pendientes > 0): ?>
                  <span class="badge bg-danger rounded-pill ms-2"><?= $solicitudes_pendientes ?></span>
                <?php endif; ?>
              </a>
            </li>
          </ul>
        </li>
        <!-- Torneos -->
        <li class="mb-2">
          <a href="index.php?page=torneo_gestion&action=index" class="nav-link <?= ($current_page === 'torneo_gestion' && ($_GET['action'] ?? '') === 'index') ? 'active' : '' ?>">
            <i class="fas fa-trophy me-3"></i>
            <span class="nav-text">Torneos</span>
          </a>
        </li>
        <!-- Usuarios -->
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('users')) ?>" class="nav-link <?= $current_page === 'users' ? 'active' : '' ?>">
            <i class="fas fa-user-cog me-3"></i>
            <span class="nav-text">Gestión de Usuarios y Roles</span>
          </a>
        </li>
        <?php if (($user['role'] ?? '') === 'admin_general'): ?>
        <!-- Reporte de actividad (Auditoría) - Solo Super Admin -->
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('auditoria')) ?>" class="nav-link <?= $current_page === 'auditoria' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list me-3"></i>
            <span class="nav-text">Reporte de actividad</span>
          </a>
        </li>
        <?php endif; ?>
        <!-- 4. Comunicación (acordeón) -->
        <li class="mb-2">
          <a href="#" class="nav-link <?= $is_comunicacion_open ? 'active' : '' ?>"
             onclick="event.preventDefault(); toggleSubmenu('comunicacion-submenu', this);"
             style="cursor: pointer;">
            <i class="fas fa-bullhorn me-3"></i>
            <span class="nav-text">Comunicación</span>
            <i class="fas fa-chevron-<?= $is_comunicacion_open ? 'up' : 'down' ?> ms-auto submenu-icon"></i>
          </a>
          <ul class="list-unstyled ps-4 mt-1 collapse-submenu <?= $is_comunicacion_open ? 'show' : '' ?>" id="comunicacion-submenu">
            <li class="mb-1">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard('notificaciones_masivas')) ?>" class="nav-link nav-sub-sub-link <?= $current_page === 'notificaciones_masivas' ? 'active' : '' ?>">
                <i class="fas fa-bell me-2"></i>
                <span>Notificaciones Masivas</span>
              </a>
            </li>
            <li class="mb-1">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard('whatsapp_config')) ?>" class="nav-link nav-sub-sub-link <?= $current_page === 'whatsapp_config' ? 'active' : '' ?>">
                <i class="fab fa-whatsapp me-2"></i>
                <span>Mensajes WhatsApp</span>
              </a>
            </li>
            <li class="mb-1">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard('comments')) ?>" class="nav-link nav-sub-sub-link <?= $current_page === 'comments' ? 'active' : '' ?>">
                <i class="fas fa-comments me-2"></i>
                <span>Comentarios (Aprobación)</span>
                <?php
                try {
                    $pendientes = DB::pdo()->query("SELECT COUNT(*) FROM comentariossugerencias WHERE estatus = 'pendiente'")->fetchColumn();
                    if ($pendientes > 0):
                ?>
                  <span class="badge bg-danger rounded-pill ms-2"><?= $pendientes ?></span>
                <?php endif;
                } catch (Exception $e) {}
                ?>
              </a>
            </li>
          </ul>
        </li>
        <!-- Herramientas -->
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('control_admin')) ?>" class="nav-link <?= $current_page === 'control_admin' ? 'active' : '' ?>">
            <i class="fas fa-tools me-3"></i>
            <span class="nav-text">Control Especial</span>
            <span class="badge bg-danger ms-2" style="font-size: 0.65rem;">Admin</span>
          </a>
        </li>
        <!-- Enlaces -->
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::url('landing-spa.php')) ?>" class="nav-link">
            <i class="fas fa-id-card me-3"></i>
            <span class="nav-text">Portal Público</span>
            <i class="fas fa-external-link-alt ms-auto" style="font-size: 0.75rem;"></i>
          </a>
        </li>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(rtrim(AppHelpers::getBaseUrl(), '/') . '/manuales_web/manual_usuario.php') ?>" class="nav-link">
            <i class="fas fa-book me-3"></i>
            <span class="nav-text">Manual de Usuario</span>
            <i class="fas fa-external-link-alt ms-auto" style="font-size: 0.75rem;"></i>
          </a>
        </li>
        <?php endif; ?>
        
        <?php if ($user['role'] === 'admin_torneo'): ?>
        <li class="mb-2">
          <a href="index.php?page=torneo_gestion&action=index" class="nav-link <?= ($current_page === 'torneo_gestion' && ($_GET['action'] ?? '') === 'index') ? 'active' : '' ?>">
            <i class="fas fa-trophy me-3"></i>
            <span class="nav-text">Torneos</span>
          </a>
        </li>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('cuentas_bancarias')) ?>" class="nav-link <?= $current_page === 'cuentas_bancarias' ? 'active' : '' ?>">
            <i class="fas fa-university me-3"></i>
            <span class="nav-text">Cuentas Bancarias</span>
          </a>
        </li>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('notificaciones_masivas')) ?>" class="nav-link <?= $current_page === 'notificaciones_masivas' ? 'active' : '' ?>">
            <i class="fas fa-bell me-3"></i>
            <span class="nav-text">Notificaciones</span>
          </a>
        </li>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::dashboard('reportes_pago_usuarios')) ?>" class="nav-link <?= $current_page === 'reportes_pago_usuarios' ? 'active' : '' ?>">
            <i class="fas fa-money-bill-wave me-3"></i>
            <span class="nav-text">Reportes de Pago</span>
            <?php
            try {
                $pendientes_pagos = DB::pdo()->query("SELECT COUNT(*) FROM reportes_pago_usuarios WHERE estatus = 'pendiente'")->fetchColumn();
                if ($pendientes_pagos > 0):
            ?>
              <span class="badge bg-warning rounded-pill ms-2"><?= $pendientes_pagos ?></span>
            <?php
                endif;
            } catch (Exception $e) {
                // Ignorar error si la tabla no existe aún
            }
            ?>
          </a>
        </li>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(AppHelpers::url('landing-spa.php')) ?>" class="nav-link">
            <i class="fas fa-id-card me-3"></i>
            <span class="nav-text">Portal Público</span>
            <i class="fas fa-external-link-alt ms-auto" style="font-size: 0.75rem;"></i>
          </a>
        </li>
        <li class="mb-2">
          <a href="<?= htmlspecialchars(rtrim(AppHelpers::getBaseUrl(), '/') . '/manuales_web/manual_usuario.php') ?>" class="nav-link">
            <i class="fas fa-book me-3"></i>
            <span class="nav-text">Manual de Usuario</span>
            <i class="fas fa-external-link-alt ms-auto" style="font-size: 0.75rem;"></i>
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </nav>

    <!-- Contenido principal -->
    <div id="page-content-wrapper" class="flex-grow-1">
      
      <!-- Topbar -->
      <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
        <div class="container-fluid">
          <button class="btn btn-outline-secondary me-3" id="menu-toggle">
            <i class="fas fa-bars"></i>
          </button>
          
          <div class="navbar-nav me-auto d-flex align-items-center">
            <?php
            $topbar_org = $dashboard_org;
            if (!$topbar_org) {
              $topbar_org = ['nombre' => 'La Estación del Dominó', 'logo' => null];
            }
            $topbar_logo_src = !empty($topbar_org['logo'])
              ? $layout_asset_base . '/view_image.php?path=' . rawurlencode($topbar_org['logo'])
              : $layout_asset_base . '/view_image.php?path=' . rawurlencode('lib/Assets/mislogos/logo4.png');
            $topbar_nombre = htmlspecialchars($topbar_org['nombre']);
            ?>
            <img src="<?= htmlspecialchars($topbar_logo_src) ?>" alt="<?= $topbar_nombre ?>" height="32" class="me-2 d-none d-md-inline-block" style="object-fit: contain;">
            <h5 class="mb-0 text-muted d-none d-md-block"><?= $topbar_nombre ?></h5>
            <h6 class="mb-0 text-muted d-md-none"><?= strlen($topbar_nombre) > 20 ? 'Dashboard' : $topbar_nombre ?></h6>
          </div>
          
          <div class="d-flex align-items-center">
            <?php if ($user['role'] === 'admin_general' && $solicitudes_pendientes > 0): ?>
            <!-- Indicador de Solicitudes Pendientes -->
            <div class="me-3">
              <a href="<?= htmlspecialchars(AppHelpers::dashboard('affiliate_requests')) ?>" class="btn btn-warning position-relative" title="Solicitudes de Afiliación Pendientes">
                <i class="fas fa-user-clock"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                  <?= $solicitudes_pendientes ?>
                  <span class="visually-hidden">solicitudes pendientes</span>
                </span>
              </a>
            </div>
            <?php endif; ?>
            
            <!-- Barra de búsqueda -->
            <div class="search-box me-3 d-none d-lg-block">
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                  <i class="fas fa-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0" placeholder="Buscar..." id="searchInput">
              </div>
            </div>
            
            <!-- Botón búsqueda móvil -->
            <button class="btn btn-outline-secondary d-lg-none me-2" onclick="toggleMobileSearch()">
              <i class="fas fa-search"></i>
            </button>

            <!-- Campanita: notificaciones web pendientes -->
            <a href="<?= htmlspecialchars(AppHelpers::dashboard('user_notificaciones')) ?>" class="btn btn-outline-secondary position-relative me-2" id="campana-link" title="Notificaciones">
              <i class="fas fa-bell"></i>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="campana-badge" style="display: none;">0</span>
            </a>
            
            <?php if ($user['role'] === 'admin_club'): ?>
            <!-- Perfil de la organización (solo admin_club) -->
            <a href="<?= htmlspecialchars(AppHelpers::dashboard('mi_organizacion')) ?>" class="btn btn-outline-primary me-2" title="Editar datos de la organización">
              <i class="fas fa-building me-1"></i>
              <span class="d-none d-md-inline">Perfil de la organización</span>
            </a>
            <?php endif; ?>
            
            <!-- Usuario -->
            <div class="dropdown">
              <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-user me-2"></i>
                <?= htmlspecialchars($user['username']) ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-item-text text-muted">Rol: <?= htmlspecialchars($user['role']) ?></span></li>
                <?php if ($user['role'] === 'admin_club'): ?>
                <li><a class="dropdown-item" href="<?= htmlspecialchars(AppHelpers::dashboard('mi_organizacion')) ?>"><i class="fas fa-building me-2"></i>Perfil de la organización</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="<?= htmlspecialchars(AppHelpers::url('profile.php')) ?>"><i class="fas fa-id-card me-2"></i>Mi Perfil</a></li>
                <li><a class="dropdown-item" href="<?= htmlspecialchars(AppHelpers::url('modules/users/change_password.php')) ?>"><i class="fas fa-key me-2"></i>Cambiar Contraseña</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= htmlspecialchars(AppHelpers::logout()) ?>"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <?php if ($actas_pendientes_count > 0 && in_array($user['role'], ['admin_club', 'admin_general', 'admin_torneo'], true)): ?>
      <!-- Banner de alerta: actas pendientes de validación -->
      <div class="alert alert-warning alert-dismissible fade show rounded-0 mb-0 border-0 border-bottom border-warning" role="alert">
        <div class="container-fluid d-flex align-items-center justify-content-between flex-wrap gap-2">
          <span><i class="fas fa-exclamation-triangle me-2"></i><strong>Atención:</strong> Tienes actas de mesa esperando validación visual.</span>
          <a href="index.php?page=torneo_gestion&action=verificar_actas_index" class="btn btn-warning btn-sm">
            <i class="fas fa-qrcode me-1"></i>Abrir Verificador
          </a>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
      </div>
      <?php endif; ?>

      <!-- Contenido din�mico -->
      <main class="container-fluid py-4">
        <?php 
        // Soportar sub-rutas con / (ej: invitations/enviar_masivo)
        $content = __DIR__ . "/../../modules/$current_page.php";
        if (file_exists($content)) {
          include $content;
        } else {
          include __DIR__ . "/../../modules/404.php";
        }
        ?>
      </main>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
  <script>window.APP_BASE_URL = '<?= htmlspecialchars(rtrim(AppHelpers::getBaseUrl(), "/")) ?>'; window.notifAjaxUrl = '<?= htmlspecialchars($layout_asset_base . "/notificaciones_ajax.php") ?>';</script>

  <?php
  $pages_needing_image_preview = ['mi_organizacion', 'admin_org', 'tournaments', 'tournament_admin', 'users', 'clubs', 'clubes_asociados', 'admin_clubs', 'directorio_clubes'];
  $action = $_GET['action'] ?? '';
  $needs_image_preview = in_array($current_page, $pages_needing_image_preview)
    || ($current_page === 'torneo_gestion' && in_array($action, ['galeria_fotos', 'index']));
  if ($needs_image_preview): ?>
  <script src="<?= htmlspecialchars($layout_asset_base) ?>/assets/image-preview.js" defer></script>
  <?php endif; ?>
  <script src="<?= htmlspecialchars($layout_asset_base) ?>/assets/notifications-toast.js" defer></script>
  <script src="<?= htmlspecialchars($layout_asset_base) ?>/assets/breadcrumb-back.js" defer></script>
  
  <script src="<?= htmlspecialchars($layout_asset_base) ?>/assets/dashboard-init.js" defer></script>
</body>
</html>
