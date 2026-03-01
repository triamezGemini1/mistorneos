<?php
/**
 * Landing Page Pública - La Estación del Dominó
 * Página pública con información de eventos, resultados y calendario
 * Acceso: http://localhost/mistorneos/public/landing.php (sin restricciones)
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../lib/app_helpers.php';
require_once __DIR__ . '/../lib/UrlHelper.php';
require_once __DIR__ . '/../lib/LandingDataService.php';
require_once __DIR__ . '/config.php';

// Manejo de errores para evitar que la página crashee
try {
    $pdo = DB::pdo();
} catch (PDOException $e) {
    http_response_code(503);
    include __DIR__ . '/error_service_unavailable.php';
    exit;
}

$user = Auth::user();
$landingService = new LandingDataService($pdo);

// Eventos realizados (LandingDataService) - límite amplio para mostrar torneos antiguos (ej. torneo id=1)
$eventos_realizados = $landingService->getEventosRealizados(50);

// Eventos futuros (LandingDataService)
$eventos_todos_futuros = $landingService->getProximosEventos(500);
$eventos_futuros = array_values(array_filter($eventos_todos_futuros, function($e) {
    $em = $e['es_evento_masivo'] ?? null;
    return $em === null || $em === '' || in_array((int)$em, [0, 4]);
}));
$eventos_inscripcion_linea = array_values(array_filter($eventos_todos_futuros, function($e) {
    return in_array((int)($e['es_evento_masivo'] ?? 0), [1, 2, 3]);
}));
$eventos_privados = array_values(array_filter($eventos_todos_futuros, function($e) {
    return (int)($e['es_evento_masivo'] ?? 0) === 4;
}));

// Calendario (LandingDataService)
$eventos_calendario = $landingService->getEventosCalendario();

// Logos de clientes: desde la carpeta de logos de clubes (tabla clubes, columna logo = upload/logos/...)
$logos_clientes_clubes = [];
try {
    $stmt = $pdo->prepare("SELECT id, nombre, logo FROM clubes WHERE logo IS NOT NULL AND logo != '' AND (estatus = 1 OR estatus = '1') ORDER BY nombre ASC");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $path = trim((string)($row['logo'] ?? ''));
        if ($path !== '') {
            $logos_clientes_clubes[] = ['nombre' => $row['nombre'] ?? 'Club', 'path' => $path];
        }
    }
} catch (Exception $e) {
    // ignorar
}
// Fallback: si no hay clubes con logo, usar imágenes de la carpeta upload/logos/
if (empty($logos_clientes_clubes)) {
    $upload_logos_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'logos';
    $extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
    if (is_dir($upload_logos_dir)) {
        foreach (new DirectoryIterator($upload_logos_dir) as $f) {
            if ($f->isDot() || !$f->isFile()) continue;
            $ext = strtolower($f->getExtension());
            if (in_array($ext, $extensions, true)) {
                $path = 'upload/logos/' . $f->getFilename();
                $logos_clientes_clubes[] = ['nombre' => pathinfo($f->getFilename(), PATHINFO_FILENAME), 'path' => $path];
            }
        }
    }
}
// Añadir logos subidos por admin en upload/logos_clientes/ (FVD, etc.)
$logos_clientes_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'logos_clientes';
if (is_dir($logos_clientes_dir)) {
    foreach (new DirectoryIterator($logos_clientes_dir) as $f) {
        if ($f->isDot() || !$f->isFile()) continue;
        $ext = strtolower($f->getExtension());
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
            $logos_clientes_clubes[] = ['nombre' => pathinfo($f->getFilename(), PATHINFO_FILENAME), 'path' => 'upload/logos_clientes/' . $f->getFilename()];
        }
    }
}
$mitad = (int) ceil(count($logos_clientes_clubes) / 2);
$logos_fila1 = array_slice($logos_clientes_clubes, 0, $mitad);
$logos_fila2 = array_slice($logos_clientes_clubes, $mitad);

// Documentos oficiales de dominó (upload/documentos_oficiales/)
$documentos_oficiales = [];
$doc_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'documentos_oficiales';
$doc_extensions = ['pdf', 'doc', 'docx'];
if (is_dir($doc_dir)) {
    foreach (new DirectoryIterator($doc_dir) as $f) {
        if ($f->isDot() || !$f->isFile()) continue;
        $ext = strtolower($f->getExtension());
        if (in_array($ext, $doc_extensions, true)) {
            $nombre = pathinfo($f->getFilename(), PATHINFO_FILENAME);
            $path_rel = 'upload/documentos_oficiales/' . $f->getFilename();
            $documentos_oficiales[] = ['titulo' => $nombre, 'path' => $path_rel, 'archivo' => $f->getFilename()];
        }
    }
}

$invitaciones_fvd = [];
$inv_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'invitaciones_fvd';
if (is_dir($inv_dir)) {
    foreach (new DirectoryIterator($inv_dir) as $f) {
        if ($f->isDot() || !$f->isFile()) continue;
        $ext = strtolower($f->getExtension());
        if (in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'], true)) {
            $invitaciones_fvd[] = ['titulo' => pathinfo($f->getFilename(), PATHINFO_FILENAME), 'path' => 'upload/invitaciones_fvd/' . $f->getFilename()];
        }
    }
}

// Función helper para obtener URL de afiche (compatible con view_tournament_file.php)
function getAficheUrl($torneo) {
    if (!empty($torneo['afiche'])) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $base_path = dirname($_SERVER['PHP_SELF']);
        $file = str_replace('upload/tournaments/', '', $torneo['afiche']);
        return $protocol . '://' . $host . $base_path . '/view_tournament_file.php?file=' . urlencode($file);
    }
    return null;
}

// Función helper para obtener URL del logo de la organización
function getLogoOrganizacionUrl($evento) {
    if (!empty($evento['organizacion_logo'])) {
        return 'view_image.php?path=' . rawurlencode($evento['organizacion_logo']);
    }
    return null;
}

// Función helper para formatear modalidad y clase
$modalidades = [1 => 'Individual', 2 => 'Parejas', 3 => 'Equipos'];
$clases = [1 => 'Torneo', 2 => 'Campeonato'];

// Función para limpiar el nombre del torneo (eliminar "masivos" y "Masivos")
function limpiarNombreTorneo($nombre) {
    if (empty($nombre)) return $nombre;
    // Eliminar "masivos", "Masivos", "MASIVOS" y todas las variaciones (case-insensitive)
    $nombre = preg_replace('/\bmasivos?\b/i', '', $nombre);
    // También eliminar "Masivos" específicamente si aparece al inicio o después de espacios
    $nombre = preg_replace('/\s+Masivos\s*/i', ' ', $nombre);
    $nombre = preg_replace('/^Masivos\s+/i', '', $nombre);
    $nombre = preg_replace('/\s+Masivos$/i', '', $nombre);
    $nombre = preg_replace('/\s+/', ' ', $nombre); // Limpiar espacios múltiples
    return trim($nombre);
}

// Índice de eventos por fecha (Y-m-d) para el calendario interactivo (con URLs para tarjetas)
$eventos_por_fecha = [];
foreach ($eventos_calendario as $ev) {
    $fecha_key = date('Y-m-d', strtotime($ev['fechator']));
    if (!isset($eventos_por_fecha[$fecha_key])) {
        $eventos_por_fecha[$fecha_key] = [];
    }
    $ev['afiche_url'] = getAficheUrl($ev);
    $ev['logo_url'] = getLogoOrganizacionUrl($ev);
    $ev['nombre_limpio'] = limpiarNombreTorneo($ev['nombre'] ?? '');
    $eventos_por_fecha[$fecha_key][] = $ev;
}
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1a365d">
    
    <!-- SEO Meta Tags - IMPORTANTE: No usar noindex para permitir indexación -->
    <title><?= htmlspecialchars($META_TITLE) ?></title>
    <meta name="description" content="<?= htmlspecialchars($META_DESCRIPTION) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($META_KEYWORDS) ?>">
    <meta name="author" content="<?= htmlspecialchars($META_AUTHOR) ?>">
    <meta name="robots" content="index, follow">
    <meta name="language" content="es">
    <meta name="revisit-after" content="7 days">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($SITE_URL) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($META_OG_TITLE) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($META_OG_DESCRIPTION) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($OG_IMAGE) ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= htmlspecialchars($SITE_URL) ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($META_OG_TITLE) ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($META_OG_DESCRIPTION) ?>">
    <meta property="twitter:image" content="<?= htmlspecialchars($OG_IMAGE) ?>">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?= htmlspecialchars($SITE_URL) ?>">
    
    <!-- Tailwind CSS (compilado localmente con colores primary/accent) -->
    <link rel="stylesheet" href="<?= htmlspecialchars(rtrim(app_base_url(), '/') . '/public/assets/dist/output.css') ?>">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
        }
        /* Logo de organización en publicaciones: 60% del contenedor, sin distorsión en todos los dispositivos */
        .landing-logo-org {
            max-height: 60%;
            max-width: 60%;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        /* Calendario anual - año completo en un bloque sin scroll */
        #calendario .cal-contenedor-anual {
            height: calc(100vh - 160px);
            min-height: 380px;
            max-height: 80vh;
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
        }
        #grid-anual {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: repeat(3, 1fr);
            gap: 6px;
            height: 100%;
            overflow: hidden;
        }
        .cal-mini {
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .cal-mini .cal-grid-unico {
            flex: 1;
            min-height: 0;
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-auto-rows: minmax(0, 1fr);
            gap: 1px;
            padding: 2px;
        }
        .cal-mini .cal-dia-celda {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: clamp(6px, 1.2vw, 10px);
            border-radius: 2px;
            cursor: pointer;
            position: relative;
        }
        /* Indicadores de actividad: 1 color = 1 torneo, 2+ colores = 2+ torneos */
        .cal-indicadores-multiples {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 2px;
            margin-top: 2px;
        }
        .cal-mini .cal-indicadores-multiples { gap: 1px; margin-top: 1px; }
        .cal-dot-actividad {
            border-radius: 50%;
            flex-shrink: 0;
        }
        .cal-mini .cal-dot-actividad {
            width: 4px;
            height: 4px;
        }
        .cal-mes-ampliado .cal-dot-actividad {
            width: 8px;
            height: 8px;
        }
        /* Colores por estado de fecha: rojo=pasados, verde=próximas 24h, azul=próximas */
        .cal-fondo-rojo { background-color: #dc3545 !important; color: white !important; }
        .cal-fondo-verde { background-color: #198754 !important; color: white !important; }
        .cal-fondo-azul { background-color: #0d6efd !important; color: white !important; }
        /* Mes ampliado: nombres de días en una fila, fechas alineadas debajo de cada columna */
        #cal-mes-header,
        #grid-mes-ampliado {
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }
        @media (max-width: 640px) {
            #grid-anual { grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(4, 1fr); }
            #calendario .cal-contenedor-anual { height: calc(100vh - 120px); }
        }
        /* Cintillo de logos de clientes: dos filas, desplazamiento lento */
        .logos-clientes-wrap { overflow: hidden; width: 100%; min-height: 120px; background: linear-gradient(to bottom, #f8fafc, #e2e8f0); padding: 1.5rem 0; }
        .logos-clientes-row { display: flex; width: max-content; animation: marquee 45s linear infinite; }
        .logos-clientes-row:hover { animation-play-state: paused; }
        .logos-clientes-row .logo-item { flex-shrink: 0; display: flex; align-items: center; justify-content: center; width: 360px; height: 180px; margin: 0 2rem; padding: 1rem; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .logos-clientes-row .logo-item img { max-width: 100%; max-height: 100%; object-fit: contain; }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
    </style>
</head>
<body class="bg-gray-50 antialiased">
<?php include_once __DIR__ . '/components/header.php'; ?>
<?php include_once __DIR__ . '/components/hero.php'; ?>

    <!-- Documentos oficiales de dominó -->
    <section id="documentos" class="py-16 md:py-24 bg-gradient-to-br from-slate-50 to-blue-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-primary-700 mb-4">
                    <i class="fas fa-file-alt mr-3 text-accent"></i>Documentos oficiales de dominó
                </h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Consulte en línea, lea o descargue reglamentos, normas y documentos oficiales del dominó.</p>
            </div>
            <?php if (!empty($documentos_oficiales)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8 max-w-5xl mx-auto">
                <?php foreach ($documentos_oficiales as $doc): ?>
                <?php
                    $url_base = 'view_documento.php?path=' . rawurlencode($doc['path']);
                    $url_ver = $url_base;
                    $url_descarga = $url_base . '&download=1';
                ?>
                <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all border border-gray-100 overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-center justify-center w-14 h-14 bg-primary-100 rounded-xl mb-4">
                            <i class="fas fa-file-pdf text-2xl text-primary-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3"><?= htmlspecialchars($doc['titulo']) ?></h3>
                        <div class="flex flex-wrap gap-2">
                            <a href="<?= htmlspecialchars($url_ver) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-primary-500 text-white font-semibold rounded-lg hover:bg-primary-600 transition-all text-sm">
                                <i class="fas fa-external-link-alt mr-2"></i>Ver en línea
                            </a>
                            <a href="<?= htmlspecialchars($url_descarga) ?>" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 transition-all text-sm" download>
                                <i class="fas fa-download mr-2"></i>Descargar
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-12 bg-white/60 rounded-2xl max-w-xl mx-auto">
                <i class="fas fa-folder-open text-5xl text-gray-300 mb-4"></i>
                <p class="text-gray-600">Próximamente se publicarán aquí los documentos oficiales. Los archivos se colocan en <code class="text-sm bg-gray-100 px-2 py-1 rounded">upload/documentos_oficiales/</code>.</p>
            </div>
            <?php endif; ?>

            <?php if (!empty($invitaciones_fvd)): ?>
            <div class="mt-16 pt-12 border-t border-gray-200">
                <h3 class="text-2xl font-bold text-primary-700 mb-6 text-center"><i class="fas fa-envelope-open-text mr-2 text-accent"></i>Invitaciones FVD</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8 max-w-5xl mx-auto">
                    <?php foreach ($invitaciones_fvd as $doc): ?>
                    <?php $url_ver = 'view_documento.php?path=' . rawurlencode($doc['path']); $url_descarga = $url_ver . '&download=1'; ?>
                    <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all border border-gray-100 overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-center justify-center w-14 h-14 bg-green-100 rounded-xl mb-4"><i class="fas fa-file-pdf text-2xl text-green-600"></i></div>
                            <h4 class="text-lg font-bold text-gray-900 mb-3"><?= htmlspecialchars($doc['titulo']) ?></h4>
                            <div class="flex flex-wrap gap-2">
                                <a href="<?= htmlspecialchars($url_ver) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-primary-500 text-white font-semibold rounded-lg hover:bg-primary-600 transition-all text-sm"><i class="fas fa-external-link-alt mr-2"></i>Ver en línea</a>
                                <a href="<?= htmlspecialchars($url_descarga) ?>" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 transition-all text-sm" download><i class="fas fa-download mr-2"></i>Descargar</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Sección de Registro (solo afiliación, centrada) -->
    <section id="registro" class="py-16 md:py-24 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 flex flex-col items-center">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-primary-700 mb-4">Solicitud de Afiliación</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Para clubes y organizadores que desean ser parte del proyecto y administrar eventos</p>
            </div>
            
            <div class="w-full flex justify-center">
                <div class="grid grid-cols-1 gap-6 lg:gap-8 max-w-md mx-auto w-full justify-items-center">
                <!-- Solicitud de Afiliación -->
                <div class="group relative">
                    <div class="absolute inset-0 bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl blur opacity-75 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl p-8 text-white shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 h-full">
                        <div class="text-center mb-6">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-xl mb-4">
                                <i class="fas fa-building text-3xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold mb-3">Solicitud de Afiliación</h3>
                            <p class="text-white/90 mb-6">
                                Para clubes y organizadores que desean ser parte del proyecto y administrar eventos.
                            </p>
                        </div>
                        
                        <ul class="space-y-3 mb-6 text-left">
                            <li class="flex items-center">
                                <i class="fas fa-check-circle mr-3 text-white/90"></i>
                                <span>Administra tu propio club</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check-circle mr-3 text-white/90"></i>
                                <span>Crea y gestiona torneos</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check-circle mr-3 text-white/90"></i>
                                <span>Invita jugadores a eventos</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check-circle mr-3 text-white/90"></i>
                                <span>Reportes y estadísticas</span>
                            </li>
                        </ul>
                        
                        <a href="affiliate_request.php" class="block w-full px-6 py-3 bg-white text-rose-600 font-semibold rounded-xl hover:bg-gray-100 transition-all duration-200 text-center shadow-lg">
                            <i class="fas fa-paper-plane mr-2"></i>Solicitar Afiliación
                        </a>
                    </div>
                </div>
                </div>
            </div>
            
            <div class="text-center mt-10">
                <p class="text-gray-600 flex items-center justify-center">
                    <i class="fas fa-info-circle mr-2 text-primary-500"></i>
                    Las solicitudes de afiliación serán revisadas por el administrador del sistema.
                </p>
            </div>
        </div>
    </section>

    <!-- Logos de clientes atendidos (desde carpeta de logos de clubes: upload/logos) -->
    <?php if (!empty($logos_fila1) || !empty($logos_fila2)): ?>
    <section id="logos-clientes" class="logos-clientes-wrap" aria-label="Clientes y entidades que nos respaldan">
        <div class="logos-clientes-row mb-4">
            <?php for ($r = 0; $r < 2; $r++): foreach ($logos_fila1 as $logo): ?>
                <div class="logo-item">
                    <img src="view_image.php?path=<?= rawurlencode($logo['path']) ?>" alt="<?= htmlspecialchars($logo['nombre']) ?>" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling&&this.nextElementSibling.classList.remove('hidden');">
                    <span class="hidden text-xl font-bold text-primary-600"><?= htmlspecialchars($logo['nombre']) ?></span>
                </div>
            <?php endforeach; endfor; ?>
        </div>
        <div class="logos-clientes-row">
            <?php for ($r = 0; $r < 2; $r++): foreach ($logos_fila2 as $logo): ?>
                <div class="logo-item">
                    <img src="view_image.php?path=<?= rawurlencode($logo['path']) ?>" alt="<?= htmlspecialchars($logo['nombre']) ?>" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling&&this.nextElementSibling.classList.remove('hidden');">
                    <span class="hidden text-xl font-bold text-primary-600"><?= htmlspecialchars($logo['nombre']) ?></span>
                </div>
            <?php endforeach; endfor; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Eventos Masivos Section -->
    <?php if (!empty($eventos_masivos)): ?>
    <section id="eventos-masivos" class="py-16 md:py-24 bg-gradient-to-br from-purple-600 via-purple-700 to-indigo-800 text-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4">
                    <i class="fas fa-users-cog mr-3 text-yellow-400"></i>Eventos Nacionales
                </h2>
                <p class="text-lg md:text-xl text-white/90 max-w-3xl mx-auto">
                    Inscríbete desde tu dispositivo móvil en estos eventos. Abierto a jugadores de todas las entidades.
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                <?php foreach ($eventos_masivos as $evento): ?>
                    <?php 
                    $modalidad = is_numeric($evento['modalidad']) ? (int)$evento['modalidad'] : 1;
                    $clase = is_numeric($evento['clase']) ? (int)$evento['clase'] : 1;
                    ?>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl shadow-2xl hover:shadow-3xl transition-all duration-300 overflow-hidden border-2 border-white/20 hover:border-yellow-400 transform hover:-translate-y-2 text-center">
                        <div class="w-full h-48 bg-white/20 flex flex-col items-center justify-center p-4">
                            <?php $logo_org_url = getLogoOrganizacionUrl($evento); if ($logo_org_url): ?>
                                <img src="<?= htmlspecialchars($logo_org_url) ?>" alt="" class="landing-logo-org object-contain mb-2" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <span class="text-white text-xl font-bold"><?= htmlspecialchars($evento['organizacion_nombre'] ?? 'Organizador') ?></span>
                        </div>
                        <div class="p-6 text-center flex flex-col items-center">
                            <div class="inline-flex items-center px-3 py-1 bg-yellow-400 text-purple-900 rounded-full text-sm font-bold mb-4">
                                <i class="fas fa-calendar mr-2"></i><?= date('d/m/Y', strtotime($evento['fechator'])) ?>
                            </div>
                            <h5 class="text-xl font-bold text-white mb-2 w-full"><?= htmlspecialchars(limpiarNombreTorneo($evento['nombre'])) ?></h5>
                            <p class="text-white/80 text-sm mb-4 flex items-center justify-center">
                                <i class="fas fa-map-marker-alt mr-2 text-yellow-400"></i>
                                <?= htmlspecialchars($evento['lugar'] ?? 'No especificado') ?>
                            </p>
                            <div class="flex flex-wrap gap-2 mb-4 justify-center">
                                <span class="px-3 py-1 bg-blue-500/80 text-white rounded-full text-xs font-semibold"><?= $clases[$clase] ?? 'Torneo' ?></span>
                                <span class="px-3 py-1 bg-cyan-500/80 text-white rounded-full text-xs font-semibold"><?= $modalidades[$modalidad] ?? 'Individual' ?></span>
                                <?php if ($evento['costo'] > 0): ?>
                                    <span class="px-3 py-1 bg-green-500/80 text-white rounded-full text-xs font-semibold">$<?= number_format($evento['costo'], 2) ?></span>
                                <?php endif; ?>
                                <span class="px-3 py-1 bg-yellow-400 text-purple-900 rounded-full text-xs font-bold">
                                    <i class="fas fa-users mr-1"></i><?= number_format($evento['total_inscritos'] ?? 0) ?> inscritos
                                </span>
                            </div>
                            <a href="torneo_detalle.php?torneo_id=<?= (int)$evento['id'] ?>" 
                               class="block w-full px-4 py-2 mb-2 bg-white/20 hover:bg-white/30 text-white font-semibold rounded-lg border border-white/40 transition-all text-center">
                                <i class="fas fa-info-circle mr-2"></i>Ver información del torneo
                            </a>
                            <?php 
                            $permite_online = (int)($evento['permite_inscripcion_linea'] ?? 1) === 1; 
                            $tel_contacto = $evento['admin_celular'] ?? $evento['club_telefono'] ?? '';
                            $es_hoy_masivo = $evento['fechator'] && (date('Y-m-d', strtotime($evento['fechator'])) === date('Y-m-d'));
                            ?>
                            <?php if ($permite_online && !$es_hoy_masivo): ?>
                            <a href="inscribir_evento_masivo.php?torneo_id=<?= $evento['id'] ?>" 
                               class="block w-full px-4 py-3 bg-gradient-to-r from-yellow-400 to-orange-500 text-purple-900 font-bold rounded-lg hover:from-yellow-500 hover:to-orange-600 transition-all text-center shadow-lg hover:shadow-xl transform hover:scale-105">
                                <i class="fas fa-mobile-alt mr-2"></i>Inscribirme Ahora
                            </a>
                            <?php elseif ($permite_online && $es_hoy_masivo): ?>
                            <div class="bg-blue-400/20 rounded-lg p-3 border border-white/40">
                                <p class="text-xs text-purple-900 text-center mb-0">Inscripción deshabilitada el día del torneo. Presentarse al evento.</p>
                            </div>
                            <?php else: ?>
                            <div class="bg-yellow-400/20 rounded-lg p-3 mb-3 border border-yellow-400/50">
                                <p class="text-xs text-purple-900 text-center mb-2">
                                    <i class="fas fa-info-circle mr-1"></i>Inscripción en sitio. Contacta al organizador.
                                </p>
                                <?php if ($tel_contacto): ?>
                                <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $tel_contacto)) ?>" 
                                   class="block w-full px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all text-center shadow-lg">
                                    <i class="fas fa-phone mr-2"></i>Contactar administración
                                </a>
                                <?php else: ?>
                                <p class="text-xs text-center text-purple-800">Consulta con el organizador para inscribirte</p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Eventos con Inscripción en Línea (Código 2) -->
    <?php if (!empty($eventos_inscripcion_linea)): ?>
    <section id="eventos-inscripcion-linea" class="py-16 md:py-24 bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 text-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4">
                    <i class="fas fa-globe mr-3 text-yellow-400"></i>Inscripción en Línea
                </h2>
                <p class="text-lg md:text-xl text-white/90 max-w-3xl mx-auto">
                    Inscríbete directamente en estos eventos. 
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                <?php foreach ($eventos_inscripcion_linea as $evento): ?>
                    <?php 
                    $modalidad = is_numeric($evento['modalidad']) ? (int)$evento['modalidad'] : 1;
                    $clase = is_numeric($evento['clase']) ? (int)$evento['clase'] : 1;
                    ?>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl shadow-2xl hover:shadow-3xl transition-all duration-300 overflow-hidden border-2 border-white/20 hover:border-yellow-400 transform hover:-translate-y-2 text-center">
                        <div class="w-full h-48 bg-white/20 flex flex-col items-center justify-center p-4">
                            <?php $logo_org_url = getLogoOrganizacionUrl($evento); if ($logo_org_url): ?>
                                <img src="<?= htmlspecialchars($logo_org_url) ?>" alt="" class="landing-logo-org object-contain mb-2" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <span class="text-white text-xl font-bold"><?= htmlspecialchars($evento['organizacion_nombre'] ?? 'Organizador') ?></span>
                        </div>
                        <div class="p-6 text-center">
                            <div class="inline-flex items-center px-3 py-1 bg-yellow-400 text-blue-900 rounded-full text-sm font-bold mb-4">
                                <i class="fas fa-calendar mr-2"></i><?= date('d/m/Y', strtotime($evento['fechator'])) ?>
                            </div>
                            <h5 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars(limpiarNombreTorneo($evento['nombre'])) ?></h5>
                            <p class="text-white/80 text-sm mb-4 flex items-center justify-center">
                                <i class="fas fa-map-marker-alt mr-2 text-yellow-400"></i>
                                <?= htmlspecialchars($evento['lugar'] ?? 'No especificado') ?>
                            </p>
                            <div class="flex flex-wrap gap-2 mb-4 justify-center">
                                <span class="px-3 py-1 bg-blue-500/80 text-white rounded-full text-xs font-semibold"><?= $clases[$clase] ?? 'Torneo' ?></span>
                                <span class="px-3 py-1 bg-cyan-500/80 text-white rounded-full text-xs font-semibold"><?= $modalidades[$modalidad] ?? 'Individual' ?></span>
                                <?php if ($evento['costo'] > 0): ?>
                                    <span class="px-3 py-1 bg-green-500/80 text-white rounded-full text-xs font-semibold">$<?= number_format($evento['costo'], 2) ?></span>
                                <?php endif; ?>
                                <span class="px-3 py-1 bg-yellow-400 text-blue-900 rounded-full text-xs font-bold">
                                    <i class="fas fa-users mr-1"></i><?= number_format($evento['total_inscritos'] ?? 0) ?> inscritos
                                </span>
                            </div>
                            <?php if ($evento['costo'] > 0): ?>
                                <div class="bg-blue-400/20 rounded-lg p-3 mb-4 border border-blue-400/50">
                                    <p class="text-xs text-blue-200 text-center">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <strong>Costo:</strong> $<?= number_format($evento['costo'], 2) ?> - Puedes pagar después de inscribirte
                                    </p>
                                </div>
                            <?php endif; ?>
                            <a href="torneo_detalle.php?torneo_id=<?= (int)$evento['id'] ?>" 
                               class="block w-full px-4 py-2 mb-2 bg-white/20 hover:bg-white/30 text-white font-semibold rounded-lg border border-white/40 transition-all text-center">
                                <i class="fas fa-info-circle mr-2"></i>Ver información del torneo
                            </a>
                            <?php 
                            $permite_online = (int)($evento['permite_inscripcion_linea'] ?? 1) === 1; 
                            $tel_contacto = $evento['admin_celular'] ?? $evento['club_telefono'] ?? '';
                            $es_hoy = $evento['fechator'] && (date('Y-m-d', strtotime($evento['fechator'])) === date('Y-m-d'));
                            ?>
                            <?php if ($permite_online && !$es_hoy): ?>
                            <a href="inscribir_evento_masivo.php?torneo_id=<?= $evento['id'] ?>" 
                               class="block w-full px-4 py-3 bg-gradient-to-r from-yellow-400 to-orange-500 text-blue-900 font-bold rounded-lg hover:from-yellow-500 hover:to-orange-600 transition-all text-center shadow-lg hover:shadow-xl transform hover:scale-105">
                                <i class="fas fa-sign-in-alt mr-2"></i>Inscríbete Ahora
                            </a>
                            <?php elseif ($permite_online && $es_hoy): ?>
                            <div class="bg-blue-400/30 rounded-lg p-3 border border-white/40">
                                <p class="text-xs text-white text-center mb-0">
                                    <i class="fas fa-calendar-day mr-1"></i>Inscripción en línea deshabilitada el día del torneo. Presentarse al evento para participar.
                                </p>
                            </div>
                            <?php else: ?>
                            <div class="bg-blue-400/20 rounded-lg p-3 border border-blue-400/50">
                                <p class="text-xs text-blue-900 text-center mb-2">
                                    <i class="fas fa-info-circle mr-1"></i>Inscripción en sitio. Contacta al organizador.
                                </p>
                                <?php if ($tel_contacto): ?>
                                <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $tel_contacto)) ?>" 
                                   class="block w-full px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all text-center shadow-lg">
                                    <i class="fas fa-phone mr-2"></i>Contactar administración
                                </a>
                                <?php else: ?>
                                <p class="text-xs text-center text-blue-900">Consulta con el organizador para inscribirte</p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Eventos Privados Section (Código 4 - Solo visualización) -->
    <?php if (!empty($eventos_privados)): ?>
    <section id="eventos-privados" class="py-16 md:py-24 bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900 text-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4">
                    <i class="fas fa-lock mr-3 text-yellow-400"></i>Eventos Privados
                </h2>
                <p class="text-lg md:text-xl text-white/90 max-w-3xl mx-auto">
                    Estos eventos son privados. Para inscribirte, contacta directamente con el organizador.
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                <?php foreach ($eventos_privados as $evento): ?>
                    <?php 
                    $modalidad = is_numeric($evento['modalidad']) ? (int)$evento['modalidad'] : 1;
                    $clase = is_numeric($evento['clase']) ? (int)$evento['clase'] : 1;
                    ?>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl shadow-2xl hover:shadow-3xl transition-all duration-300 overflow-hidden border-2 border-white/20 hover:border-yellow-400 transform hover:-translate-y-2 text-center">
                        <div class="w-full h-48 bg-white/20 flex flex-col items-center justify-center p-4">
                            <?php $logo_org_url = getLogoOrganizacionUrl($evento); if ($logo_org_url): ?>
                                <img src="<?= htmlspecialchars($logo_org_url) ?>" alt="" class="landing-logo-org object-contain mb-2" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <span class="text-white text-xl font-bold"><?= htmlspecialchars($evento['organizacion_nombre'] ?? 'Organizador') ?></span>
                        </div>
                        <div class="p-6 text-center">
                            <div class="inline-flex items-center px-3 py-1 bg-yellow-400 text-gray-900 rounded-full text-sm font-bold mb-4">
                                <i class="fas fa-calendar mr-2"></i><?= date('d/m/Y', strtotime($evento['fechator'])) ?>
                            </div>
                            <h5 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars($evento['nombre']) ?></h5>
                            <p class="text-white/80 text-sm mb-4 flex items-center justify-center">
                                <i class="fas fa-map-marker-alt mr-2 text-yellow-400"></i>
                                <?= htmlspecialchars($evento['lugar'] ?? 'No especificado') ?>
                            </p>
                            <div class="flex flex-wrap gap-2 mb-4 justify-center">
                                <span class="px-3 py-1 bg-blue-500/80 text-white rounded-full text-xs font-semibold"><?= $clases[$clase] ?? 'Torneo' ?></span>
                                <span class="px-3 py-1 bg-cyan-500/80 text-white rounded-full text-xs font-semibold"><?= $modalidades[$modalidad] ?? 'Individual' ?></span>
                                <?php if ($evento['costo'] > 0): ?>
                                    <span class="px-3 py-1 bg-green-500/80 text-white rounded-full text-xs font-semibold">$<?= number_format($evento['costo'], 2) ?></span>
                                <?php endif; ?>
                                <span class="px-3 py-1 bg-yellow-400 text-gray-900 rounded-full text-xs font-bold">
                                    <i class="fas fa-users mr-1"></i><?= number_format($evento['total_inscritos'] ?? 0) ?> inscritos
                                </span>
                            </div>
                            <a href="torneo_detalle.php?torneo_id=<?= (int)$evento['id'] ?>" 
                               class="block w-full px-4 py-2 mb-2 bg-white/20 hover:bg-white/30 text-white font-semibold rounded-lg border border-white/40 transition-all text-center">
                                <i class="fas fa-info-circle mr-2"></i>Ver información del torneo
                            </a>
                            <div class="bg-yellow-400/20 rounded-lg p-3 border border-yellow-400/50">
                                <p class="text-xs text-yellow-200 text-center">
                                    <i class="fas fa-lock mr-1"></i>
                                    <strong>Evento Privado:</strong> Contacta al organizador para inscribirte
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Eventos Section -->
    <section id="eventos" class="py-16 md:py-24 bg-gray-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Eventos por Realizar -->
            <?php if (!empty($eventos_futuros)): ?>
            <div class="mb-16">
                <div class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-bold text-primary-700 mb-4">
                        <i class="fas fa-calendar-check mr-3 text-accent"></i>Próximos Eventos
                    </h2>
                    <p class="text-lg text-gray-600">Eventos programados que puedes esperar</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                    <?php foreach ($eventos_futuros as $evento): ?>
                        <?php 
                        $modalidad = is_numeric($evento['modalidad']) ? (int)$evento['modalidad'] : 1;
                        $clase = is_numeric($evento['clase']) ? (int)$evento['clase'] : 1;
                        ?>
                        <div class="bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden border border-gray-200 hover:border-primary-500 transform hover:-translate-y-2 text-center">
                            <div class="w-full h-48 bg-gray-100 flex flex-col items-center justify-center p-4">
                                <?php $logo_org_url = getLogoOrganizacionUrl($evento); if ($logo_org_url): ?>
                                    <img src="<?= htmlspecialchars($logo_org_url) ?>" alt="" class="landing-logo-org object-contain mb-2" loading="lazy" decoding="async">
                                <?php endif; ?>
                                <span class="text-gray-900 text-xl font-bold"><?= htmlspecialchars($evento['organizacion_nombre'] ?? 'Organizador') ?></span>
                            </div>
                            <div class="p-6 text-center">
                                <div class="inline-flex items-center px-3 py-1 bg-primary-500 text-white rounded-full text-sm font-semibold mb-4">
                                    <i class="fas fa-calendar mr-2"></i><?= date('d/m/Y', strtotime($evento['fechator'])) ?>
                                </div>
                                <h5 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($evento['nombre']) ?></h5>
                                <p class="text-gray-600 text-sm mb-4 flex items-center justify-center">
                                    <i class="fas fa-map-marker-alt mr-2 text-primary-500"></i>
                                    <?= htmlspecialchars($evento['lugar'] ?? 'No especificado') ?>
                                </p>
                                <div class="flex flex-wrap gap-2 mb-4 justify-center">
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold"><?= $clases[$clase] ?? 'Torneo' ?></span>
                                    <span class="px-3 py-1 bg-cyan-100 text-cyan-700 rounded-full text-xs font-semibold"><?= $modalidades[$modalidad] ?? 'Individual' ?></span>
                                    <?php if ($evento['costo'] > 0): ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">$<?= number_format($evento['costo'], 2) ?></span>
                                    <?php endif; ?>
                                </div>
                                <a href="torneo_detalle.php?torneo_id=<?= (int)$evento['id'] ?>" class="block w-full px-4 py-2 bg-primary-500 text-white font-semibold rounded-lg hover:bg-primary-600 transition-all text-center mb-2">
                                    <i class="fas fa-info-circle mr-2"></i>Ver información del torneo
                                </a>
                                <?php 
                                $permite_online = (int)($evento['permite_inscripcion_linea'] ?? 1) === 1; 
                                $tel_contacto = $evento['admin_celular'] ?? $evento['club_telefono'] ?? '';
                                $es_hoy_fut = $evento['fechator'] && (date('Y-m-d', strtotime($evento['fechator'])) === date('Y-m-d'));
                                ?>
                                <?php if ($permite_online && !$es_hoy_fut): ?>
                                <a href="inscribir_evento_masivo.php?torneo_id=<?= $evento['id'] ?>" class="block w-full px-4 py-2 bg-green-500 text-white font-semibold rounded-lg hover:bg-green-600 transition-all text-center mb-2">
                                    <i class="fas fa-sign-in-alt mr-2"></i>Inscribirme
                                </a>
                                <?php elseif ($permite_online && $es_hoy_fut): ?>
                                <p class="text-xs text-gray-500 text-center mb-2">Inscripción deshabilitada el día del torneo.</p>
                                <?php elseif ($tel_contacto): ?>
                                <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $tel_contacto)) ?>" class="block w-full px-4 py-2 bg-green-500 text-white font-semibold rounded-lg hover:bg-green-600 transition-all text-center mb-2">
                                    <i class="fas fa-phone mr-2"></i>Contactar administración
                                </a>
                                <?php endif; ?>
                                <a href="consulta_credencial.php" class="block w-full px-4 py-2 bg-gray-500 text-white font-semibold rounded-lg hover:bg-gray-600 transition-all text-center">
                                    <i class="fas fa-id-card mr-2"></i>Consulta credencial
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Eventos Realizados -->
            <?php if (!empty($eventos_realizados)): ?>
            <div>
                <div class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-bold text-primary-700 mb-4">
                        <i class="fas fa-history mr-3 text-accent"></i>Eventos Realizados
                    </h2>
                    <p class="text-lg text-gray-600">Revisa los resultados y fotografías de eventos pasados</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                    <?php foreach ($eventos_realizados as $evento): ?>
                        <?php 
                        // Asegurar que total_fotos esté definido antes de usarlo
                        if (!isset($evento['total_fotos'])) {
                            $evento['total_fotos'] = 0;
                        }
                        $evento['total_fotos'] = (int)$evento['total_fotos'];
                        
                        $afiche_url = getAficheUrl($evento);
                        $modalidad = is_numeric($evento['modalidad']) ? (int)$evento['modalidad'] : 1;
                        $clase = is_numeric($evento['clase']) ? (int)$evento['clase'] : 1;
                        ?>
                        <div class="bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden border border-gray-200 hover:border-primary-500 transform hover:-translate-y-2 text-center">
                            <div class="w-full h-48 bg-gray-100 flex flex-col items-center justify-center p-4">
                                <?php $logo_org_url = getLogoOrganizacionUrl($evento); if ($logo_org_url): ?>
                                    <img src="<?= htmlspecialchars($logo_org_url) ?>" alt="" class="landing-logo-org object-contain mb-2" loading="lazy" decoding="async">
                                <?php endif; ?>
                                <span class="text-gray-900 text-xl font-bold"><?= htmlspecialchars($evento['organizacion_nombre'] ?? 'Organizador') ?></span>
                            </div>
                            <div class="p-6 text-center">
                                <div class="inline-flex items-center px-3 py-1 bg-gray-600 text-white rounded-full text-sm font-semibold mb-4">
                                    <i class="fas fa-calendar mr-2"></i><?= date('d/m/Y', strtotime($evento['fechator'])) ?>
                                </div>
                                <h5 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($evento['nombre']) ?></h5>
                                <p class="text-gray-600 text-sm mb-4 flex items-center justify-center">
                                    <i class="fas fa-users mr-2 text-primary-500"></i>
                                    <?= number_format($evento['total_inscritos'] ?? 0) ?> participantes
                                </p>
                                <div class="flex flex-wrap gap-2 mb-4 justify-center">
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold"><?= $clases[$clase] ?? 'Torneo' ?></span>
                                    <span class="px-3 py-1 bg-cyan-100 text-cyan-700 rounded-full text-xs font-semibold"><?= $modalidades[$modalidad] ?? 'Individual' ?></span>
                                </div>
                                <div class="space-y-2">
                                    <a href="torneo_detalle.php?torneo_id=<?= (int)$evento['id'] ?>" 
                                       class="block w-full px-4 py-2 bg-primary-500 text-white font-semibold rounded-lg hover:bg-primary-600 transition-all text-center">
                                        <i class="fas fa-info-circle mr-2"></i>Ver información del torneo
                                    </a>
                                    <?php $resultados_url = UrlHelper::resultadosUrl($evento['id'], $evento['nombre']); ?>
                                    <a href="<?= htmlspecialchars($resultados_url) ?>" 
                                       class="block w-full px-4 py-2 bg-green-500 text-white font-semibold rounded-lg hover:bg-green-600 transition-all text-center">
                                        <i class="fas fa-chart-bar mr-2"></i>Ver Resultados
                                    </a>
                                    <?php if (isset($evento['total_fotos']) && (int)$evento['total_fotos'] > 0): ?>
                                        <button type="button" 
                                                onclick="viewEventPhotos(<?= $evento['id'] ?>, '<?= htmlspecialchars($evento['nombre'], ENT_QUOTES) ?>', <?= $evento['total_fotos'] ?>)"
                                                class="w-full px-4 py-2 bg-primary-500 text-white font-semibold rounded-lg hover:bg-primary-600 transition-all">
                                            <i class="fas fa-images mr-2"></i>Ver Fotos (<?= $evento['total_fotos'] ?>)
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-8">
                    <a href="torneos_historico.php" class="inline-flex items-center px-6 py-3 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-700 transition-all shadow-lg hover:shadow-xl">
                        <i class="fas fa-history mr-2"></i>Ver Histórico Completo
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Calendario Section - Año completo en un bloque, sin scroll -->
    <section id="calendario" class="py-3 min-h-[85vh]">
        <div class="container mx-auto px-3 sm:px-4">
            <div class="rounded-2xl p-4 sm:p-6" style="background-color: #83e3f7;">
            <div class="text-center mb-3">
                <h2 class="text-xl md:text-2xl font-bold text-slate-800 mb-1">
                    <i class="fas fa-calendar-alt mr-2 text-teal-600"></i>Calendario de Torneos
                </h2>
                <p class="text-sm text-slate-600">Haz clic en un mes para ampliarlo. Selecciona una fecha con eventos.</p>
            </div>

            <!-- VISTA 1: Año completo en un bloque - Enero, Febrero, Marzo... sin scroll -->
            <div id="vista-anual" class="cal-vista">
                <div class="flex justify-center gap-2 mb-2">
                    <button type="button" id="cal-year-prev" class="px-2 py-1 rounded bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-medium transition-colors">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h3 id="cal-year-display" class="text-lg font-bold text-slate-800 px-3 self-center"></h3>
                    <button type="button" id="cal-year-next" class="px-2 py-1 rounded bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-medium transition-colors">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="cal-contenedor-anual">
                    <div id="grid-anual">
                        <!-- 12 meses: Enero, Febrero, Marzo... con días y fechas -->
                    </div>
                </div>
            </div>

            <!-- VISTA 2: Mes ampliado (oculto por defecto) -->
            <div id="vista-mes" class="cal-vista hidden">
                <div id="contenedor-grid-mes">
                <a href="#" id="btn-volver-anual" class="inline-flex items-center gap-1 mb-3 px-3 py-1.5 text-sm bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium rounded transition-colors">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
                <div class="bg-white rounded-lg border border-slate-200 shadow overflow-hidden max-w-5xl mx-auto cal-mes-ampliado">
                    <div class="px-6 py-3 bg-slate-100 border-b border-slate-200">
                        <h3 id="mes-ampliado-titulo" class="text-xl font-bold text-slate-800"></h3>
                    </div>
                    <div class="p-4">
                        <!-- Fila única de nombres de días: Do, Lu, Ma, Mi, Ju, Vi, Sa -->
                        <div id="cal-mes-header" class="grid grid-cols-7 gap-2 mb-2">
                            <!-- Se rellena por JS -->
                        </div>
                        <!-- Grid de fechas alineadas debajo de cada columna de día -->
                        <div id="grid-mes-ampliado" class="grid grid-cols-7 gap-2 min-h-[300px] max-h-[75vh]">
                            <!-- Celdas de fechas -->
                        </div>
                    </div>
                </div>
                </div>
                <!-- Sección torneos del día: oculta cuadrícula, muestra tarjetas (mismo formato que publicaciones en landing) -->
                <div id="seccion-eventos-dia" class="hidden rounded-2xl overflow-hidden bg-gradient-to-br from-purple-600 via-purple-700 to-indigo-800 text-white p-6 shadow-xl">
                    <div class="flex items-center justify-between mb-6">
                        <h4 id="eventos-dia-titulo" class="text-xl font-bold text-white">Torneos del día</h4>
                        <a href="#" id="btn-volver-mes" class="inline-flex items-center gap-1 px-4 py-2 bg-white/20 hover:bg-white/30 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-arrow-left mr-1"></i>Volver al calendario
                        </a>
                    </div>
                    <div id="lista-eventos-dia" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                        <!-- Tarjetas de torneos (mismo formato que eventos-masivos) -->
                    </div>
                </div>
            </div>
            </div>
        </div>
    </section>

    <script>
    (function() {
        const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        const diasSemana = ['Do','Lu','Ma','Mi','Ju','Vi','Sa'];
        const eventosPorFecha = <?= json_encode($eventos_por_fecha) ?>;
        const baseUrl = '';

        let calAnio = new Date().getFullYear();
        let calMes = new Date().getMonth();
        let fechaSeleccionada = null;

        const vistaAnual = document.getElementById('vista-anual');
        const vistaMes = document.getElementById('vista-mes');
        const gridAnual = document.getElementById('grid-anual');
        const calMesHeader = document.getElementById('cal-mes-header');
        const gridMesAmpliado = document.getElementById('grid-mes-ampliado');
        const contenedorGridMes = document.getElementById('contenedor-grid-mes');
        const seccionEventosDia = document.getElementById('seccion-eventos-dia');
        const listaEventosDia = document.getElementById('lista-eventos-dia');

        /** Domingo = 0, Lunes = 1, ... Sábado = 6 (para calendario Do, Lu, Ma, Mi, Ju, Vi, Sa) */
        function diaSemanaDomPrimero(date) {
            return date.getDay();
        }

        /** Colores para indicar cantidad de torneos: 1=teal, 2=teal+amber, 3=teal+amber+blue, etc. */
        const coloresActividad = ['#0d9488', '#d97706', '#2563eb', '#7c3aed', '#059669', '#dc2626', '#0891b2'];
        function renderIndicadoresActividad(cantidad) {
            if (cantidad <= 0) return '';
            const maxDots = 6;
            const n = Math.min(cantidad, maxDots);
            let html = '<span class="cal-indicadores-multiples" title="' + cantidad + ' torneo(s) programado(s)">';
            for (let i = 0; i < n; i++) {
                const color = coloresActividad[i % coloresActividad.length];
                html += '<span class="cal-dot-actividad" style="background:' + color + '"></span>';
            }
            html += '</span>';
            return html;
        }

        function claseFondoPorFecha(fechaStr, tieneEventos) {
            const hoy = new Date();
            const hoyStr = hoy.getFullYear() + '-' + String(hoy.getMonth()+1).padStart(2,'0') + '-' + String(hoy.getDate()).padStart(2,'0');
            const manana = new Date(hoy);
            manana.setDate(manana.getDate() + 1);
            const mananaStr = manana.getFullYear() + '-' + String(manana.getMonth()+1).padStart(2,'0') + '-' + String(manana.getDate()).padStart(2,'0');
            if (!tieneEventos) return fechaStr === hoyStr ? 'bg-amber-50 text-amber-900 font-semibold' : 'bg-white text-slate-600 hover:bg-slate-50';
            if (fechaStr < hoyStr) return 'cal-fondo-rojo font-semibold';
            if (fechaStr === hoyStr || fechaStr === mananaStr) return 'cal-fondo-verde font-semibold';
            return 'cal-fondo-azul font-semibold';
        }

        function renderMiniCalendario(anio, mes) {
            const primerDia = new Date(anio, mes, 1);
            const ultimoDia = new Date(anio, mes + 1, 0).getDate();
            const inicioOffset = diaSemanaDomPrimero(primerDia);
            const hoy = new Date();
            const hoyStr = hoy.getFullYear() + '-' + String(hoy.getMonth()+1).padStart(2,'0') + '-' + String(hoy.getDate()).padStart(2,'0');

            let html = '<div class="bg-white border border-slate-200 rounded overflow-hidden shadow-sm cal-mini">';
            html += '<a href="#" class="cal-link-mes block px-1 py-0.5 bg-slate-100 hover:bg-teal-100 border-b border-slate-200 text-center text-[10px] font-bold text-slate-800 hover:text-teal-700 transition-colors shrink-0" data-mes="' + mes + '" data-anio="' + anio + '">' + meses[mes] + '</a>';
            html += '<div class="cal-grid-unico">';
            diasSemana.forEach(d => { html += '<div class="cal-dia-celda bg-slate-100 text-slate-600 font-semibold text-[7px]">' + d + '</div>'; });
            for (let i = 0; i < inicioOffset; i++) html += '<div class="cal-dia-celda bg-slate-50"></div>';
            for (let d = 1; d <= ultimoDia; d++) {
                const fechaStr = anio + '-' + String(mes+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
                const eventos = eventosPorFecha[fechaStr] || [];
                const tieneEventos = eventos.length > 0;
                const esHoy = fechaStr === hoyStr;
                let cls = 'cal-dia-celda cal-dia-mini ';
                cls += claseFondoPorFecha(fechaStr, tieneEventos);
                if (tieneEventos) cls += ' hover:opacity-90';
                else if (esHoy) cls += ' border border-amber-300';
                html += '<div class="' + cls + '" data-fecha="' + fechaStr + '" data-mes="' + mes + '" data-anio="' + anio + '">' + d + (tieneEventos ? renderIndicadoresActividad(eventos.length) : '') + '</div>';
            }
            html += '</div></div>';
            return html;
        }

        function renderVistaAnual() {
            document.getElementById('cal-year-display').textContent = calAnio;
            let html = '';
            for (let m = 0; m < 12; m++) {
                html += renderMiniCalendario(calAnio, m);
            }
            gridAnual.innerHTML = html;
            gridAnual.querySelectorAll('.cal-link-mes').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    calMes = parseInt(this.getAttribute('data-mes'));
                    calAnio = parseInt(this.getAttribute('data-anio'));
                    mostrarVistaMes(null);
                });
            });
            gridAnual.querySelectorAll('.cal-dia-mini').forEach(celda => {
                celda.addEventListener('click', function(e) {
                    e.preventDefault();
                    const fecha = this.getAttribute('data-fecha');
                    calMes = parseInt(this.getAttribute('data-mes'));
                    calAnio = parseInt(this.getAttribute('data-anio'));
                    const eventos = eventosPorFecha[fecha] || [];
                    mostrarVistaMes(eventos.length > 0 ? fecha : null);
                });
            });
        }

        function mostrarVistaMes(fechaConEventos) {
            vistaAnual.classList.add('hidden');
            vistaMes.classList.remove('hidden');
            contenedorGridMes.classList.remove('hidden');
            seccionEventosDia.classList.add('hidden');
            document.getElementById('mes-ampliado-titulo').textContent = meses[calMes] + ' ' + calAnio;

            const primerDia = new Date(calAnio, calMes, 1);
            const ultimoDia = new Date(calAnio, calMes + 1, 0).getDate();
            const inicioOffset = diaSemanaDomPrimero(primerDia);
            const hoy = new Date();
            const hoyStr = hoy.getFullYear() + '-' + String(hoy.getMonth()+1).padStart(2,'0') + '-' + String(hoy.getDate()).padStart(2,'0');

            // Fila única: Do, Lu, Ma, Mi, Ju, Vi, Sa
            let headerHtml = '';
            diasSemana.forEach(d => { headerHtml += '<div class="py-2 text-center text-sm font-bold text-slate-600 bg-slate-100 rounded">' + d + '</div>'; });
            calMesHeader.innerHTML = headerHtml;

            // Fechas alineadas debajo de cada columna de día
            let html = '';
            for (let i = 0; i < inicioOffset; i++) {
                html += '<div class="min-h-[54px] p-1 rounded bg-slate-50"></div>';
            }
            for (let d = 1; d <= ultimoDia; d++) {
                const fechaStr = calAnio + '-' + String(calMes+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
                const eventos = eventosPorFecha[fechaStr] || [];
                const tieneEventos = eventos.length > 0;
                const esHoy = fechaStr === hoyStr;
                let cls = 'min-h-[54px] p-1 rounded flex flex-col items-center justify-center text-base cursor-pointer transition-all ';
                cls += tieneEventos ? claseFondoPorFecha(fechaStr, true) : (esHoy ? 'bg-amber-50 text-amber-900 font-semibold' : 'bg-slate-50 hover:bg-slate-100 text-slate-600');
                if (esHoy && !tieneEventos) cls += ' ring-2 ring-amber-400 ring-offset-1';
                html += '<div class="cal-dia-ampliado ' + cls + '" data-fecha="' + fechaStr + '">' + d;
                if (tieneEventos) html += renderIndicadoresActividad(eventos.length);
                html += '</div>';
            }
            gridMesAmpliado.innerHTML = html;

            gridMesAmpliado.querySelectorAll('.cal-dia-ampliado').forEach(celda => {
                celda.addEventListener('click', function() {
                    const fecha = this.getAttribute('data-fecha');
                    const eventos = eventosPorFecha[fecha] || [];
                    mostrarEventosEnPagina(fecha, eventos);
                });
            });
            if (fechaConEventos) {
                const eventos = eventosPorFecha[fechaConEventos] || [];
                mostrarEventosEnPagina(fechaConEventos, eventos);
            }
        }

        const modalidades = {1:'Individual',2:'Parejas',3:'Equipos'};
        const clases = {1:'Torneo',2:'Campeonato'};

        /** Renderiza tarjeta de torneo - mismo formato que publicaciones (eventos-masivos) en landing */
        function renderTarjetaEvento(ev) {
            const esPasado = new Date(ev.fechator) < new Date();
            const permiteOnline = parseInt(ev.permite_inscripcion_linea || 1) === 1;
            const esMasivo = [1,2,3].includes(parseInt(ev.es_evento_masivo || 0));
            const telContacto = ev.admin_celular || ev.club_telefono || '';
            const modalidad = modalidades[parseInt(ev.modalidad)||1] || 'Individual';
            const clase = clases[parseInt(ev.clase)||1] || 'Torneo';
            const fechaDmY = new Date(ev.fechator).toLocaleDateString('es-VE', { day:'2-digit', month:'2-digit', year:'numeric' });
            const nombreTorneo = escapeHtml(ev.nombre_limpio || ev.nombre || '');

            let html = '<div class="bg-white/10 backdrop-blur-md rounded-2xl shadow-2xl hover:shadow-3xl transition-all duration-300 overflow-hidden border-2 border-white/20 hover:border-yellow-400 transform hover:-translate-y-2 text-center">';
            html += '<div class="w-full h-48 bg-white/20 flex flex-col items-center justify-center p-4">';
            if (ev.logo_url) html += '<img src="' + escapeHtml(ev.logo_url) + '" alt="" class="landing-logo-org object-contain mb-2" loading="lazy">';
            html += '<span class="text-white text-xl font-bold">' + escapeHtml(ev.organizacion_nombre || 'Organizador') + '</span></div>';
            html += '<div class="p-6 text-center">';
            html += '<div class="inline-flex items-center px-3 py-1 bg-yellow-400 text-purple-900 rounded-full text-sm font-bold mb-4"><i class="fas fa-calendar mr-2"></i>' + fechaDmY + '</div>';
            html += '<h5 class="text-xl font-bold text-white mb-2">' + nombreTorneo + '</h5>';
            html += '<p class="text-white/80 text-sm mb-4 flex items-center justify-center"><i class="fas fa-map-marker-alt mr-2 text-yellow-400"></i>' + escapeHtml(ev.lugar || 'No especificado') + '</p>';
            html += '<div class="flex flex-wrap gap-2 mb-4 justify-center">';
            html += '<span class="px-3 py-1 bg-blue-500/80 text-white rounded-full text-xs font-semibold">' + clase + '</span>';
            html += '<span class="px-3 py-1 bg-cyan-500/80 text-white rounded-full text-xs font-semibold">' + modalidad + '</span>';
            if (ev.costo > 0) html += '<span class="px-3 py-1 bg-green-500/80 text-white rounded-full text-xs font-semibold">$' + parseFloat(ev.costo).toFixed(2) + '</span>';
            html += '<span class="px-3 py-1 bg-yellow-400 text-purple-900 rounded-full text-xs font-bold"><i class="fas fa-users mr-1"></i>' + (ev.total_inscritos||0) + ' inscritos</span>';
            html += '</div>';
            html += '<a href="' + baseUrl + 'torneo_detalle.php?torneo_id=' + ev.id + '" class="block w-full px-4 py-2 mb-2 bg-white/20 hover:bg-white/30 text-white font-semibold rounded-lg border border-white/40 transition-all text-center"><i class="fas fa-info-circle mr-2"></i>Ver información del torneo</a>';
            if (esPasado) {
                html += '<a href="' + baseUrl + 'resultados_detalle.php?torneo_id=' + ev.id + '" class="block w-full px-4 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-bold rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all text-center shadow-lg"><i class="fas fa-trophy mr-2"></i>Ver Resultados</a>';
            } else if (permiteOnline) {
                const urlInsc = esMasivo ? (baseUrl + 'inscribir_evento_masivo.php?torneo_id=' + ev.id) : (baseUrl + 'tournament_register.php?torneo_id=' + ev.id);
                html += '<a href="' + urlInsc + '" class="block w-full px-4 py-3 bg-gradient-to-r from-yellow-400 to-orange-500 text-purple-900 font-bold rounded-lg hover:from-yellow-500 hover:to-orange-600 transition-all text-center shadow-lg hover:shadow-xl transform hover:scale-105"><i class="fas fa-mobile-alt mr-2"></i>Inscribirme Ahora</a>';
            } else {
                html += '<div class="bg-yellow-400/20 rounded-lg p-3 mb-3 border border-yellow-400/50"><p class="text-xs text-purple-900 text-center mb-2"><i class="fas fa-info-circle mr-1"></i>Inscripción en sitio. Contacta al organizador.</p>';
                if (telContacto) html += '<a href="tel:' + telContacto.replace(/\D/g,'') + '" class="block w-full px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all text-center shadow-lg"><i class="fas fa-phone mr-2"></i>Contactar administración</a>';
                else html += '<p class="text-xs text-center text-purple-800">Consulta con el organizador para inscribirte</p>';
                html += '</div>';
            }
            html += '</div></div>';
            return html;
        }

        function mostrarEventosEnPagina(fechaStr, eventos) {
            fechaSeleccionada = fechaStr;
            const [y, m, d] = fechaStr.split('-');
            document.getElementById('eventos-dia-titulo').textContent = 'Torneos del ' + d + '/' + m + '/' + y;
            contenedorGridMes.classList.add('hidden');
            seccionEventosDia.classList.remove('hidden');
            seccionEventosDia.scrollIntoView({ behavior: 'smooth' });

            if (eventos.length === 0) {
                listaEventosDia.innerHTML = '<p class="col-span-full text-white/90 py-8 text-center">No hay torneos programados para esta fecha.</p>';
            } else {
                listaEventosDia.innerHTML = eventos.map(ev => renderTarjetaEvento(ev)).join('');
            }
        }

        function escapeHtml(s) {
            if (!s) return '';
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        document.getElementById('btn-volver-anual').addEventListener('click', function(e) {
            e.preventDefault();
            vistaMes.classList.add('hidden');
            vistaAnual.classList.remove('hidden');
            renderVistaAnual();
        });

        document.getElementById('btn-volver-mes').addEventListener('click', function(e) {
            e.preventDefault();
            seccionEventosDia.classList.add('hidden');
            contenedorGridMes.classList.remove('hidden');
            contenedorGridMes.scrollIntoView({ behavior: 'smooth' });
        });

        document.getElementById('cal-year-prev').addEventListener('click', function() {
            calAnio--;
            renderVistaAnual();
        });
        document.getElementById('cal-year-next').addEventListener('click', function() {
            calAnio++;
            renderVistaAnual();
        });

        renderVistaAnual();
    })();
    </script>

    <?php include_once __DIR__ . '/components/services-grid.php'; ?>

    <?php include_once __DIR__ . '/components/trust-badges.php'; ?>

    <?php include_once __DIR__ . '/components/logos-wall.php'; ?>

    <?php include_once __DIR__ . '/components/precios.php'; ?>

    <!-- Galería de Imágenes -->
    <section id="galeria" class="py-16 md:py-24 bg-gray-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-primary-700 mb-4">
                    <i class="fas fa-images mr-3 text-accent"></i>Galería de Torneos
                </h2>
                <p class="text-lg text-gray-600">Momentos destacados de nuestros eventos</p>
            </div>

            <?php
            // Obtener 4 fotos del último torneo
            $fotos_ultimo_torneo = [];
            $ultimo_torneo_info = null;
            try {
                $club_photos_exists = false;
                try {
                    $stmt_check = $pdo->query("SHOW TABLES LIKE 'club_photos'");
                    $club_photos_exists = $stmt_check->rowCount() > 0;
                } catch (Exception $e) {
                    $club_photos_exists = false;
                }

                if ($club_photos_exists) {
                    // Obtener el último torneo con fotos
                    $stmt = $pdo->query("
                        SELECT 
                            t.id as torneo_id,
                            t.nombre as torneo_nombre,
                            t.fechator,
                            o.nombre as organizacion_nombre,
                            t.club_responsable as club_id
                        FROM tournaments t
                        LEFT JOIN organizaciones o ON t.club_responsable = o.id
                        WHERE t.estatus = 1 
                        AND EXISTS (
                            SELECT 1 FROM club_photos tp WHERE tp.torneo_id = t.id
                        )
                        ORDER BY t.fechator DESC, t.created_at DESC
                        LIMIT 1
                    ");
                    $ultimo_torneo_info = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($ultimo_torneo_info) {
                        // Obtener 4 fotos del último torneo
                        $stmt = $pdo->prepare("
                            SELECT 
                                tp.*,
                                t.nombre as torneo_nombre,
                                t.fechator,
                                o.nombre as organizacion_nombre
                            FROM club_photos tp
                            JOIN tournaments t ON tp.torneo_id = t.id
                            LEFT JOIN organizaciones o ON t.club_responsable = o.id
                            WHERE tp.torneo_id = ?
                            ORDER BY tp.orden ASC, tp.fecha_subida DESC
                            LIMIT 4
                        ");
                        $stmt->execute([$ultimo_torneo_info['torneo_id']]);
                        $fotos_ultimo_torneo = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                }
            } catch (Exception $e) {
                error_log("Error obteniendo fotos del último torneo: " . $e->getMessage());
            }
            ?>

            <?php if (!empty($fotos_ultimo_torneo) && $ultimo_torneo_info): ?>
                <div class="mb-6">
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">
                        <i class="fas fa-trophy mr-2 text-primary-500"></i>
                        <?= htmlspecialchars($ultimo_torneo_info['torneo_nombre']) ?>
                    </h3>
                    <?php if ($ultimo_torneo_info['organizacion_nombre']): ?>
                        <p class="text-gray-600 mb-4">
                            <i class="fas fa-building mr-1"></i>
                            <?= htmlspecialchars($ultimo_torneo_info['organizacion_nombre']) ?>
                            <?php if ($ultimo_torneo_info['fechator']): ?>
                                <span class="ml-3">
                                    <i class="fas fa-calendar mr-1"></i>
                                    <?= date('d/m/Y', strtotime($ultimo_torneo_info['fechator'])) ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <?php foreach ($fotos_ultimo_torneo as $foto): ?>
                        <div class="group relative aspect-square rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 cursor-pointer" 
                             onclick="viewFullImage('<?= htmlspecialchars($foto['ruta_imagen'], ENT_QUOTES) ?>', '<?= htmlspecialchars($foto['torneo_nombre'], ENT_QUOTES) ?>')">
                            <?php
                            // Construir URL correcta usando app_base_url
                            $foto_url = app_base_url() . '/' . ltrim($foto['ruta_imagen'], '/');
                            ?>
                            <img src="<?= htmlspecialchars($foto_url) ?>" 
                                 alt="<?= htmlspecialchars($foto['torneo_nombre']) ?>"
                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                 loading="lazy" 
                                 decoding="async">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                                <div class="absolute bottom-0 left-0 right-0 p-4 text-white">
                                    <h4 class="font-bold text-sm mb-1"><?= htmlspecialchars($foto['torneo_nombre']) ?></h4>
                                    <p class="text-xs opacity-90"><?= htmlspecialchars($foto['organizacion_nombre'] ?? '') ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center">
                    <?php
                    $galeria_url = app_base_url() . '/public/galeria_fotos.php';
                    if ($ultimo_torneo_info['torneo_id']) {
                        $galeria_url .= '?torneo_id=' . $ultimo_torneo_info['torneo_id'];
                    }
                    ?>
                    <a href="<?= htmlspecialchars($galeria_url) ?>" class="inline-block bg-primary-500 text-white px-8 py-3 rounded-lg font-semibold hover:bg-primary-600 transition-all shadow-lg hover:shadow-xl">
                        <i class="fas fa-images mr-2"></i>Ver Galería Completa
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center py-12 bg-white rounded-2xl shadow-lg">
                    <i class="fas fa-images text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600 text-lg mb-4">Próximamente: Galería de fotos de torneos</p>
                    <a href="<?= htmlspecialchars(app_base_url() . '/public/galeria_fotos.php') ?>" class="inline-block bg-primary-500 text-white px-6 py-2 rounded-lg font-semibold hover:bg-primary-600 transition-all">
                        <i class="fas fa-images mr-2"></i>Ver Galería
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include_once __DIR__ . '/components/preguntas.php'; ?>

    <?php include_once __DIR__ . '/components/contact-form.php'; ?>

    <?php include_once __DIR__ . '/components/footer.php'; ?>

    <!-- Modal Container (para fotos) -->
    <div id="modal-container"></div>

    <script>
        // Mobile Menu Toggle
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });

        // Smooth scroll para enlaces internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    // Cerrar menú móvil si está abierto
                    document.getElementById('mobile-menu')?.classList.add('hidden');
                }
            });
        });

        // Función para ver fotos del evento
        async function viewEventPhotos(torneoId, torneoNombre, totalFotos = 0) {
            const modalContainer = document.getElementById('modal-container');
            
            // Crear modal
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm';
            modal.innerHTML = `
                <div class="bg-white rounded-2xl max-w-6xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <h5 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-images mr-3 text-primary-500"></i>Fotografías - ${torneoNombre}
                        </h5>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-100px)]">
                        <div id="galeria-loading" class="text-center py-12">
                            <i class="fas fa-spinner fa-spin text-4xl text-primary-500 mb-4"></i>
                            <p class="text-gray-600">Cargando fotografías...</p>
                        </div>
                        <div id="galeria-content" class="hidden">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="galeria-fotos-container"></div>
                        </div>
                    </div>
                </div>
            `;
            
            modalContainer.appendChild(modal);
            
            // Cerrar al hacer clic fuera
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                }
            });
            
            // Cargar fotos
            try {
                const baseUrl = '<?= htmlspecialchars(app_base_url()) ?>';
                const response = await fetch(`${baseUrl}/public/api/tournament_photos_get.php?torneo_id=${torneoId}`);
                const data = await response.json();
                
                const loadingDiv = modal.querySelector('#galeria-loading');
                const contentDiv = modal.querySelector('#galeria-content');
                const container = modal.querySelector('#galeria-fotos-container');
                
                if (data.success && data.fotos && data.fotos.length > 0) {
                    loadingDiv.classList.add('hidden');
                    contentDiv.classList.remove('hidden');
                    
                    data.fotos.forEach(foto => {
                        const baseUrl = '<?= htmlspecialchars(app_base_url()) ?>';
                        let fotoUrl = foto.ruta_imagen;
                        if (fotoUrl && !fotoUrl.startsWith('http')) {
                            fotoUrl = baseUrl + '/' + (fotoUrl.startsWith('upload/') ? fotoUrl : 'upload/' + fotoUrl);
                        }
                        
                        const col = document.createElement('div');
                        col.className = 'relative group cursor-pointer';
                        col.innerHTML = `
                            <div class="aspect-square rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 transform group-hover:scale-105">
                                <img src="${fotoUrl}" 
                                     alt="Foto del torneo" 
                                     class="w-full h-full object-cover"
                                     loading="lazy"
                                     onclick="viewFullImage('${fotoUrl}')">
                            </div>
                        `;
                        container.appendChild(col);
                    });
                } else {
                    loadingDiv.innerHTML = `
                        <i class="fas fa-images text-5xl text-gray-300 mb-4"></i>
                        <p class="text-gray-600">No hay fotografías disponibles para este evento.</p>
                    `;
                }
            } catch (error) {
                console.error('Error cargando fotos:', error);
                const loadingDiv = modal.querySelector('#galeria-loading');
                loadingDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle text-5xl text-yellow-500 mb-4"></i>
                    <p class="text-gray-600">Error al cargar las fotografías.</p>
                `;
            }
        }
        
        // Función para ver imagen en tamaño completo
        function viewFullImage(imageUrl) {
            const modalContainer = document.getElementById('modal-container');
            const fullModal = document.createElement('div');
            fullModal.className = 'fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm';
            fullModal.innerHTML = `
                <div class="relative max-w-7xl max-h-[90vh]">
                    <button onclick="this.closest('.fixed').remove()" class="absolute top-4 right-4 text-white hover:text-gray-300 z-10 bg-black/50 rounded-full p-2 backdrop-blur-sm">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                    <img src="${imageUrl}" class="max-w-full max-h-[90vh] rounded-xl shadow-2xl object-contain" alt="Foto completa">
                </div>
            `;
            modalContainer.appendChild(fullModal);
            
            fullModal.addEventListener('click', function(e) {
                if (e.target === fullModal) {
                    fullModal.remove();
                }
            });
        }
        
        // Estrellas interactivas para calificación
        document.addEventListener('DOMContentLoaded', function() {
            const starInputs = document.querySelectorAll('.star-rating');
            starInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const value = parseInt(this.value);
                    const container = this.closest('div');
                    const stars = container.querySelectorAll('i');
                    stars.forEach((star, index) => {
                        if (index < value) {
                            star.classList.remove('far');
                            star.classList.add('fas');
                            star.classList.add('text-yellow-500');
                        } else {
                            star.classList.remove('fas');
                            star.classList.add('far');
                            star.classList.remove('text-yellow-500');
                        }
                    });
                });
            });
            
            // Scroll a sección si hay hash
            const hash = window.location.hash;
            if (hash) {
                setTimeout(() => {
                    const elemento = document.querySelector(hash);
                    if (elemento) {
                        elemento.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 100);
            }
        });
        
        // Función para ver imagen completa de la galería
        function viewFullImage(imageUrl, torneoNombre) {
            const modalContainer = document.getElementById('modal-container');
            const fullModal = document.createElement('div');
            fullModal.className = 'fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm';
            fullModal.innerHTML = `
                <div class="relative max-w-7xl max-h-[90vh]">
                    <button onclick="this.closest('.fixed').remove()" class="absolute top-4 right-4 text-white hover:text-gray-300 z-10 bg-black/50 rounded-full p-2 backdrop-blur-sm">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                    <div class="bg-white rounded-xl p-4 mb-2">
                        <h4 class="text-lg font-bold text-gray-900 text-center">${torneoNombre}</h4>
                    </div>
                    <img src="${imageUrl}" class="max-w-full max-h-[85vh] rounded-xl shadow-2xl object-contain" alt="${torneoNombre}">
                </div>
            `;
            modalContainer.appendChild(fullModal);
            
            fullModal.addEventListener('click', function(e) {
                if (e.target === fullModal || e.target.closest('button')) {
                    fullModal.remove();
                }
            });
        }
        
        // Función para mostrar/ocultar información del administrador y clubes afiliados
        function toggleAdminInfo(eventoId) {
            const infoDiv = document.getElementById('admin-info-' + eventoId);
            const icon = document.getElementById('icon-' + eventoId);
            
            if (infoDiv) {
                const isHidden = infoDiv.classList.contains('hidden');
                if (isHidden) {
                    infoDiv.classList.remove('hidden');
                    if (icon) icon.classList.add('rotate-180');
                } else {
                    infoDiv.classList.add('hidden');
                    if (icon) icon.classList.remove('rotate-180');
                }
            }
        }
    </script>
</body>
</html>
