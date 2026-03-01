<?php
/**
 * Landing Page SPA - La Estación del Dominó
 * Single Page Application con Vue 3 para mejor UX
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../lib/app_helpers.php';

$app_base = rtrim(app_base_url(), '/');
$base_url = $app_base . '/public/';
$api_url = $base_url . 'api/landing_data.php';
$logo_url = $app_base . '/lib/Assets/mislogos/logo4.png';
$entidad_param = isset($_GET['entidad']) ? (int)$_GET['entidad'] : 0;
if ($entidad_param > 0) {
    $api_url .= '?entidad=' . $entidad_param;
}
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1a365d">
    <title>La Estación del Dominó - Sistema de Gestión de Torneos de Dominó en Venezuela</title>
    <meta name="description" content="Plataforma integral para la gestión de torneos de dominó en Venezuela. Participa en eventos, consulta resultados, inscríbete en torneos y únete a nuestra comunidad de jugadores.">
    <meta name="keywords" content="dominó, torneos dominó, dominó venezuela, torneos, campeonatos, clubes dominó, resultados dominó, inscripciones torneos">
    <meta name="robots" content="index, follow">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($base_url . 'landing-spa.php') ?>">
    <meta property="og:title" content="La Estación del Dominó - Sistema de Gestión de Torneos">
    <meta property="og:description" content="Plataforma integral para la gestión de torneos de dominó en Venezuela.">
    <link rel="canonical" href="<?= htmlspecialchars($base_url . 'landing-spa.php') ?>">
    
    <link rel="stylesheet" href="<?= htmlspecialchars($base_url . 'assets/dist/output.css') ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .landing-logo-org { max-height: 60%; max-width: 60%; width: auto; height: auto; object-fit: contain; }
        #calendario .cal-contenedor-anual { height: calc(100vh - 160px); min-height: 380px; max-height: 80vh; overflow: hidden; max-width: 1200px; margin: 0 auto; }
        #grid-anual { display: grid; grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(3, 1fr); gap: 6px; height: 100%; overflow: hidden; }
        .cal-mini { min-height: 0; display: flex; flex-direction: column; overflow: hidden; }
        .cal-mini .cal-grid-unico { flex: 1; min-height: 0; display: grid; grid-template-columns: repeat(7, 1fr); grid-auto-rows: minmax(0, 1fr); gap: 1px; padding: 2px; }
        .cal-mini .cal-dia-celda { display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: clamp(6px, 1.2vw, 10px); border-radius: 2px; cursor: pointer; position: relative; }
        .cal-indicadores-multiples { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 2px; margin-top: 2px; }
        .cal-dot-actividad { border-radius: 50%; flex-shrink: 0; }
        .cal-mini .cal-dot-actividad { width: 4px; height: 4px; }
        .cal-mes-ampliado .cal-dot-actividad { width: 8px; height: 8px; }
        #cal-mes-header, #grid-mes-ampliado { grid-template-columns: repeat(7, minmax(0, 1fr)); }
        @media (max-width: 640px) { #grid-anual { grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(4, 1fr); } #calendario .cal-contenedor-anual { height: calc(100vh - 120px); } }
        .fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
        .fade-enter-from, .fade-leave-to { opacity: 0; }
        .slide-enter-active, .slide-leave-active { transition: transform 0.3s ease; }
        .slide-enter-from { transform: translateY(-10px); opacity: 0; }
        .slide-leave-to { transform: translateY(10px); opacity: 0; }
        .logos-clientes-wrap { overflow: hidden; width: 100%; background: linear-gradient(to bottom, #f8fafc, #e2e8f0); padding: 1.5rem 0; }
        .logos-clientes-row { display: flex; width: max-content; animation: marquee 45s linear infinite; }
        .logos-clientes-row:hover { animation-play-state: paused; }
        .logos-clientes-row .logo-item { flex-shrink: 0; display: flex; align-items: center; justify-content: center; width: 180px; height: 90px; margin: 0 2rem; padding: 0.75rem; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .logos-clientes-row .logo-item img { max-width: 100%; max-height: 100%; object-fit: contain; }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
    </style>
</head>
<body class="bg-gray-50 antialiased">
    <div id="app">
        <div class="min-h-screen flex flex-col items-center justify-center py-20" v-if="loading">
            <div class="flex flex-col items-center gap-4">
                <i class="fas fa-spinner fa-spin text-5xl text-primary-500"></i>
                <p class="text-gray-600 font-medium">Cargando...</p>
            </div>
        </div>
        <div v-else-if="error" class="min-h-screen flex flex-col items-center justify-center py-20 px-4">
            <div class="bg-red-50 border border-red-200 rounded-xl p-8 max-w-md text-center">
                <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
                <p class="text-red-700 font-medium mb-4">{{ error }}</p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <button @click="cargarDatos" class="px-6 py-2 bg-primary-500 text-white rounded-lg font-semibold hover:bg-primary-600">
                        Reintentar
                    </button>
                    <a :href="baseUrl + 'landing.php'" class="px-6 py-2 bg-gray-600 text-white rounded-lg font-semibold hover:bg-gray-700 text-center no-underline">
                        Usar versión clásica
                    </a>
                </div>
            </div>
        </div>
        <landing-content v-else :data="data" :base-url="baseUrl" :logo-url="logoUrl" @refresh-comentarios="cargarDatos"></landing-content>
    </div>

    <script type="text/x-template" id="landing-template">
        <div>
            <!-- Navbar -->
            <nav class="bg-gradient-to-b from-primary-700 to-primary-600 shadow-lg sticky top-0 z-50 backdrop-blur-sm">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16 md:h-20">
                        <a href="#" @click.prevent="scrollToSection('hero')" class="flex items-center space-x-2 text-white font-bold text-lg md:text-xl hover:opacity-90 transition-opacity">
                            <img :src="logoUrl" alt="La Estación del Dominó" class="h-8 md:h-10 w-auto">
                            <span>La Estación del Dominó</span>
                        </a>
                        <div class="hidden md:flex items-center space-x-1">
                            <a href="#eventos-masivos" @click.prevent="scrollToSection('eventos-masivos')" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all font-medium">Eventos Nacionales</a>
                            <a href="#logos-clientes" @click.prevent="scrollToSection('logos-clientes')" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all font-medium">Clientes</a>
                            <a href="#eventos" @click.prevent="scrollToSection('eventos')" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all font-medium">Eventos</a>
                            <a href="#calendario" @click.prevent="scrollToSection('calendario')" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all font-medium">Calendario</a>
                            <a href="#registro" @click.prevent="scrollToSection('registro')" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all font-medium">Registro</a>
                            <a href="#servicios" @click.prevent="scrollToSection('servicios')" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all font-medium">Servicios</a>
                            <a href="#galeria" @click.prevent="scrollToSection('galeria')" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all font-medium">Galería</a>
                            <a href="#faq" @click.prevent="scrollToSection('faq')" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all font-medium">FAQ</a>
                            <a href="#comentarios" @click.prevent="scrollToSection('comentarios')" class="px-4 py-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all font-medium">Comentarios</a>
                            <a :href="baseUrl + 'login.php'" class="ml-4 px-6 py-2 bg-accent text-primary-700 font-semibold rounded-lg hover:bg-accentDark hover:text-white transition-all shadow-md"><i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión</a>
                        </div>
                        <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden text-white p-2 rounded-lg hover:bg-white/10"><i class="fas fa-bars text-xl"></i></button>
                    </div>
                    <div v-show="mobileMenuOpen" class="md:hidden pb-4">
                        <div class="flex flex-col space-y-2">
                            <a href="#" @click.prevent="scrollToSection('eventos-masivos')" class="px-4 py-2 text-white/90 hover:bg-white/10 rounded-lg">Eventos Nacionales</a>
                            <a href="#" @click.prevent="scrollToSection('logos-clientes')" class="px-4 py-2 text-white/90 hover:bg-white/10 rounded-lg">Clientes</a>
                            <a href="#" @click.prevent="scrollToSection('eventos')" class="px-4 py-2 text-white/90 hover:bg-white/10 rounded-lg">Eventos</a>
                            <a href="#" @click.prevent="scrollToSection('calendario')" class="px-4 py-2 text-white/90 hover:bg-white/10 rounded-lg">Calendario</a>
                            <a href="#" @click.prevent="scrollToSection('registro')" class="px-4 py-2 text-white/90 hover:bg-white/10 rounded-lg">Registro</a>
                            <a href="#" @click.prevent="scrollToSection('servicios')" class="px-4 py-2 text-white/90 hover:bg-white/10 rounded-lg">Servicios</a>
                            <a href="#" @click.prevent="scrollToSection('galeria')" class="px-4 py-2 text-white/90 hover:bg-white/10 rounded-lg">Galería</a>
                            <a href="#" @click.prevent="scrollToSection('faq')" class="px-4 py-2 text-white/90 hover:bg-white/10 rounded-lg">FAQ</a>
                            <a href="#" @click.prevent="scrollToSection('comentarios')" class="px-4 py-2 text-white/90 hover:bg-white/10 rounded-lg">Comentarios</a>
                            <a :href="baseUrl + 'login.php'" class="mt-2 px-4 py-2 bg-accent text-primary-700 font-semibold rounded-lg text-center"><i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión</a>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Hero -->
            <section id="hero" class="relative bg-gradient-to-br from-primary-700 via-primary-600 to-primary-500 text-white overflow-hidden">
                <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 40px 40px;"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-accent/20 via-transparent to-blue-500/20"></div>
                <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28 lg:py-32 relative z-10">
                    <div class="max-w-4xl mx-auto text-center">
                        <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 leading-tight">Bienvenido a<br><span class="text-accent">La Estación del Dominó</span></h1>
                        <p class="text-lg md:text-xl lg:text-2xl mb-8 text-white/90 leading-relaxed">La plataforma integral para la gestión de torneos de dominó en Venezuela.<br class="hidden md:block">Participa en eventos cerca de ti o únete como organizador.</p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                            <a :href="baseUrl + '#registro'" @click.prevent="scrollToSection('registro')" class="w-full sm:w-auto px-8 py-4 bg-accent text-primary-700 font-semibold rounded-xl hover:bg-accentDark hover:text-white transition-all shadow-xl transform hover:-translate-y-1"><i class="fas fa-building mr-2"></i>Solicitar Afiliación</a>
                            <a :href="baseUrl + 'login.php'" class="w-full sm:w-auto px-8 py-4 bg-white/10 backdrop-blur-sm text-white font-semibold rounded-xl border-2 border-white/30 hover:bg-white hover:text-primary-700 transition-all shadow-lg"><i class="fas fa-sign-in-alt mr-2"></i>Ya tengo cuenta</a>
                        </div>
                    </div>
                </div>
                <div class="absolute bottom-0 left-0 right-0"><svg class="w-full h-12 md:h-20" viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M0,0 C150,80 350,80 600,40 C850,0 1050,0 1200,40 L1200,120 L0,120 Z" fill="#f9fafb"></path></svg></div>
            </section>

            <!-- Registro (solo afiliación, centrada) -->
            <section id="registro" class="py-16 md:py-24 bg-white">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8 flex flex-col items-center">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-primary-700 mb-4">Solicitud de Afiliación</h2>
                        <p class="text-lg text-gray-600 max-w-2xl mx-auto">Para clubes y organizadores que desean ser parte del proyecto y administrar eventos</p>
                    </div>
                    <div class="w-full flex justify-center">
                        <div class="grid grid-cols-1 gap-6 lg:gap-8 max-w-md mx-auto w-full justify-items-center">
                            <div class="group relative w-full">
                                <div class="absolute inset-0 bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl blur opacity-75 group-hover:opacity-100 transition-opacity"></div>
                                <div class="relative bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl p-8 text-white shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2 h-full">
                                    <div class="text-center mb-6">
                                        <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-xl mb-4"><i class="fas fa-building text-3xl"></i></div>
                                        <h3 class="text-2xl font-bold mb-3">Solicitud de Afiliación</h3>
                                        <p class="text-white/90 mb-6">Para clubes y organizadores que desean ser parte del proyecto y administrar eventos.</p>
                                    </div>
                                    <ul class="space-y-3 mb-6 text-left">
                                        <li class="flex items-center"><i class="fas fa-check-circle mr-3"></i><span>Administra tu propio club</span></li>
                                        <li class="flex items-center"><i class="fas fa-check-circle mr-3"></i><span>Crea y gestiona torneos</span></li>
                                        <li class="flex items-center"><i class="fas fa-check-circle mr-3"></i><span>Invita jugadores a eventos</span></li>
                                        <li class="flex items-center"><i class="fas fa-check-circle mr-3"></i><span>Reportes y estadísticas</span></li>
                                    </ul>
                                    <a :href="baseUrl + 'affiliate_request.php'" class="block w-full px-6 py-3 bg-white text-rose-600 font-semibold rounded-xl hover:bg-gray-100 transition-all text-center shadow-lg"><i class="fas fa-paper-plane mr-2"></i>Solicitar Afiliación</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-10">
                        <p class="text-gray-600 flex items-center justify-center"><i class="fas fa-info-circle mr-2 text-primary-500"></i>Las solicitudes de afiliación serán revisadas por el administrador del sistema.</p>
                    </div>
                </div>
            </section>

            <!-- Logos de clientes atendidos (cintillo, dos filas, desplazamiento lento) -->
            <section id="logos-clientes" class="logos-clientes-wrap" aria-label="Clientes y entidades que nos respaldan">
                <div class="logos-clientes-row mb-4">
                    <template v-for="r in 2" :key="'r1-'+r">
                        <div v-for="logo in logosFila1" :key="'1-'+r+'-'+logo.nombre" class="logo-item">
                            <img :src="baseUrl + 'view_image.php?path=' + encodeURIComponent(logo.path)" :alt="logo.nombre" loading="lazy" @error="$event.target.style.display='none'; $event.target.nextElementSibling&&$event.target.nextElementSibling.classList.remove('hidden')">
                            <span class="hidden text-xl font-bold text-primary-600">{{ logo.nombre }}</span>
                        </div>
                    </template>
                </div>
                <div class="logos-clientes-row">
                    <template v-for="r in 2" :key="'r2-'+r">
                        <div v-for="logo in logosFila2" :key="'2-'+r+'-'+logo.nombre" class="logo-item">
                            <img :src="baseUrl + 'view_image.php?path=' + encodeURIComponent(logo.path)" :alt="logo.nombre" loading="lazy" @error="$event.target.style.display='none'; $event.target.nextElementSibling&&$event.target.nextElementSibling.classList.remove('hidden')">
                            <span class="hidden text-xl font-bold text-primary-600">{{ logo.nombre }}</span>
                        </div>
                    </template>
                </div>
            </section>

            <!-- Eventos Nacionales (Masivos) -->
            <section v-if="data.eventos_masivos?.length" id="eventos-masivos" class="py-16 md:py-24 bg-gradient-to-br from-purple-600 via-purple-700 to-indigo-800 text-white">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4"><i class="fas fa-users-cog mr-3 text-yellow-400"></i>Eventos Nacionales</h2>
                        <p class="text-lg md:text-xl text-white/90 max-w-3xl mx-auto">Inscríbete desde tu dispositivo móvil en estos eventos. Abierto a jugadores de todas las entidades.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                        <div v-for="ev in data.eventos_masivos" :key="ev.id" class="bg-white/10 backdrop-blur-md rounded-2xl shadow-2xl hover:shadow-3xl transition-all duration-300 overflow-hidden border-2 border-white/20 hover:border-yellow-400 transform hover:-translate-y-2 text-center">
                            <div class="w-full h-48 bg-white/20 flex flex-col items-center justify-center p-4">
                                <img v-if="ev.logo_url" :src="ev.logo_url" alt="" class="landing-logo-org object-contain mb-2" loading="lazy">
                                <span class="text-white text-xl font-bold">{{ ev.organizacion_nombre || 'Organizador' }}</span>
                            </div>
                            <div class="p-6 text-center">
                                <div class="inline-flex items-center px-3 py-1 bg-yellow-400 text-purple-900 rounded-full text-sm font-bold mb-4"><i class="fas fa-calendar mr-2"></i>{{ formatFecha(ev.fechator) }}</div>
                                <h5 class="text-xl font-bold text-white mb-2">{{ ev.nombre_limpio || ev.nombre }}</h5>
                                <p class="text-white/80 text-sm mb-4 flex items-center justify-center"><i class="fas fa-map-marker-alt mr-2 text-yellow-400"></i>{{ ev.lugar || 'No especificado' }}</p>
                                <div class="flex flex-wrap gap-2 mb-4 justify-center">
                                    <span class="px-3 py-1 bg-blue-500/80 text-white rounded-full text-xs font-semibold">{{ CLASES[parseInt(ev.clase)||1] || 'Torneo' }}</span>
                                    <span class="px-3 py-1 bg-cyan-500/80 text-white rounded-full text-xs font-semibold">{{ MODALIDADES[parseInt(ev.modalidad)||1] || 'Individual' }}</span>
                                    <span v-if="ev.costo > 0" class="px-3 py-1 bg-green-500/80 text-white rounded-full text-xs font-semibold">${{ parseFloat(ev.costo).toFixed(2) }}</span>
                                    <span class="px-3 py-1 bg-yellow-400 text-purple-900 rounded-full text-xs font-bold"><i class="fas fa-users mr-1"></i>{{ ev.total_inscritos||0 }} inscritos</span>
                                </div>
                                <a v-if="parseInt(ev.permite_inscripcion_linea||1)===1 && !esHoy(ev.fechator)" :href="baseUrl + 'inscribir_evento_masivo.php?torneo_id=' + ev.id" class="block w-full px-4 py-3 bg-gradient-to-r from-yellow-400 to-orange-500 text-purple-900 font-bold rounded-lg hover:from-yellow-500 hover:to-orange-600 transition-all text-center shadow-lg"><i class="fas fa-mobile-alt mr-2"></i>Inscribirme Ahora</a>
                                <div v-else-if="parseInt(ev.permite_inscripcion_linea||1)===1 && esHoy(ev.fechator)" class="bg-yellow-400/20 rounded-lg p-3 border border-yellow-400/50"><p class="text-xs text-purple-900 text-center mb-0"><i class="fas fa-info-circle mr-1"></i>Inscripción deshabilitada el día del torneo.</p></div>
                                <div v-else class="bg-yellow-400/20 rounded-lg p-3 border border-yellow-400/50">
                                    <p class="text-xs text-purple-900 text-center mb-2"><i class="fas fa-info-circle mr-1"></i>Inscripción en sitio. Contacta al organizador.</p>
                                    <a v-if="ev.admin_celular || ev.club_telefono" :href="'tel:' + (ev.admin_celular || ev.club_telefono || '').replace(/\D/g,'')" class="block w-full px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold rounded-lg text-center shadow-lg"><i class="fas fa-phone mr-2"></i>Contactar administración</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Eventos (Futuros + Realizados) -->
            <section id="eventos" class="py-16 md:py-24 bg-gray-50">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                    <div v-if="data.eventos_futuros?.length" class="mb-16">
                        <div class="text-center mb-12">
                            <h2 class="text-3xl md:text-4xl font-bold text-primary-700 mb-4"><i class="fas fa-calendar-check mr-3 text-accent"></i>Próximos Eventos</h2>
                            <p class="text-lg text-gray-600">Eventos programados que puedes esperar</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                            <div v-for="ev in data.eventos_futuros" :key="ev.id" class="bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all overflow-hidden border border-gray-200 hover:border-primary-500 transform hover:-translate-y-2 text-center">
                                <div class="w-full h-48 bg-gray-100 flex flex-col items-center justify-center p-4">
                                    <img v-if="ev.logo_url" :src="ev.logo_url" alt="" class="landing-logo-org object-contain mb-2" loading="lazy">
                                    <span class="text-gray-900 text-xl font-bold">{{ ev.organizacion_nombre || 'Organizador' }}</span>
                                </div>
                                <div class="p-6 text-center">
                                    <div class="inline-flex items-center px-3 py-1 bg-primary-500 text-white rounded-full text-sm font-semibold mb-4"><i class="fas fa-calendar mr-2"></i>{{ formatFecha(ev.fechator) }}</div>
                                    <h5 class="text-xl font-bold text-gray-900 mb-2">{{ ev.nombre }}</h5>
                                    <p class="text-gray-600 text-sm mb-4"><i class="fas fa-map-marker-alt mr-2 text-primary-500"></i>{{ ev.lugar || 'No especificado' }}</p>
                                    <div class="flex flex-wrap gap-2 mb-4 justify-center">
                                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">{{ CLASES[parseInt(ev.clase)||1] || 'Torneo' }}</span>
                                        <span class="px-3 py-1 bg-cyan-100 text-cyan-700 rounded-full text-xs font-semibold">{{ MODALIDADES[parseInt(ev.modalidad)||1] || 'Individual' }}</span>
                                        <span v-if="ev.costo > 0" class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">${{ parseFloat(ev.costo).toFixed(2) }}</span>
                                    </div>
                                    <a v-if="parseInt(ev.permite_inscripcion_linea||1)===1 && !esHoy(ev.fechator)" :href="baseUrl + 'tournament_register.php?torneo_id=' + ev.id" class="block w-full px-4 py-2 bg-green-500 text-white font-semibold rounded-lg hover:bg-green-600 transition-all text-center mb-2"><i class="fas fa-sign-in-alt mr-2"></i>Inscribirme</a>
                                    <p v-else-if="parseInt(ev.permite_inscripcion_linea||1)===1 && esHoy(ev.fechator)" class="text-xs text-gray-500 text-center mb-2">Inscripción deshabilitada el día del torneo.</p>
                                    <a v-else-if="ev.admin_celular || ev.club_telefono" :href="'tel:' + (ev.admin_celular || ev.club_telefono || '').replace(/\D/g,'')" class="block w-full px-4 py-2 bg-green-500 text-white font-semibold rounded-lg text-center mb-2"><i class="fas fa-phone mr-2"></i>Contactar</a>
                                    <a :href="baseUrl + 'consulta_credencial.php'" class="block w-full px-4 py-2 bg-primary-500 text-white font-semibold rounded-lg hover:bg-primary-600 transition-all text-center"><i class="fas fa-info-circle mr-2"></i>Ver Información</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="data.eventos_realizados?.length">
                        <div class="text-center mb-12">
                            <h2 class="text-3xl md:text-4xl font-bold text-primary-700 mb-4"><i class="fas fa-history mr-3 text-accent"></i>Eventos Realizados</h2>
                            <p class="text-lg text-gray-600">Revisa los resultados y fotografías de eventos pasados</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                            <div v-for="ev in data.eventos_realizados" :key="ev.id" class="bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all overflow-hidden border border-gray-200 hover:border-primary-500 transform hover:-translate-y-2 text-center">
                                <div class="w-full h-48 bg-gray-100 flex flex-col items-center justify-center p-4">
                                    <img v-if="ev.logo_url" :src="ev.logo_url" alt="" class="landing-logo-org object-contain mb-2" loading="lazy">
                                    <span class="text-gray-900 text-xl font-bold">{{ ev.organizacion_nombre || 'Organizador' }}</span>
                                </div>
                                <div class="p-6 text-center">
                                    <div class="inline-flex items-center px-3 py-1 bg-gray-600 text-white rounded-full text-sm font-semibold mb-4"><i class="fas fa-calendar mr-2"></i>{{ formatFecha(ev.fechator) }}</div>
                                    <h5 class="text-xl font-bold text-gray-900 mb-2">{{ ev.nombre }}</h5>
                                    <p class="text-gray-600 text-sm mb-4"><i class="fas fa-users mr-2 text-primary-500"></i>{{ ev.total_inscritos||0 }} participantes</p>
                                    <div class="flex flex-wrap gap-2 mb-4 justify-center">
                                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">{{ CLASES[parseInt(ev.clase)||1] || 'Torneo' }}</span>
                                        <span class="px-3 py-1 bg-cyan-100 text-cyan-700 rounded-full text-xs font-semibold">{{ MODALIDADES[parseInt(ev.modalidad)||1] || 'Individual' }}</span>
                                    </div>
                                    <a :href="baseUrl + 'resultados_detalle.php?torneo_id=' + ev.id" class="block w-full px-4 py-2 bg-green-500 text-white font-semibold rounded-lg hover:bg-green-600 transition-all text-center mb-2"><i class="fas fa-chart-bar mr-2"></i>Ver Resultados</a>
                                    <button v-if="ev.total_fotos > 0" type="button" @click="viewEventPhotos(ev.id, ev.nombre)" class="w-full px-4 py-2 bg-primary-500 text-white font-semibold rounded-lg hover:bg-primary-600 transition-all"><i class="fas fa-images mr-2"></i>Ver Fotos ({{ ev.total_fotos }})</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Calendario simplificado (placeholder - se puede expandir) -->
            <section id="calendario" class="py-16 md:py-24" style="background-color: #83e3f7;">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-8">
                        <h2 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2"><i class="fas fa-calendar-alt mr-2 text-teal-600"></i>Calendario de Torneos</h2>
                        <p class="text-slate-600">Próximos eventos por fecha</p>
                    </div>
                    <div class="max-w-4xl mx-auto">
                        <div v-for="[fecha, eventos] in calendarioFuturo" :key="fecha" class="mb-4">
                            <div v-if="eventos.length > 0" class="bg-white rounded-xl p-4 shadow-md">
                                <h4 class="font-bold text-slate-800 mb-3">{{ fecha.split('-').reverse().join('/') }}</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <a v-for="ev in eventos" :key="ev.id" :href="baseUrl + 'resultados_detalle.php?torneo_id=' + ev.id" class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg hover:bg-teal-50 transition-colors">
                                        <span class="text-primary-600 font-semibold">{{ ev.nombre_limpio || ev.nombre }}</span>
                                        <span class="text-sm text-gray-600">{{ ev.organizacion_nombre }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Servicios -->
            <section id="servicios" class="py-16 md:py-24 bg-white">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-primary-700 mb-4">¿Qué Ofrecemos?</h2>
                        <p class="text-lg md:text-xl text-gray-600 max-w-2xl mx-auto">Todo lo que necesitas para disfrutar del dominó de manera profesional y organizada</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                        <div class="group bg-gradient-to-br from-blue-50 to-indigo-100 rounded-2xl p-8 hover:shadow-2xl transition-all transform hover:-translate-y-2 border border-blue-100">
                            <div class="bg-primary-500 w-16 h-16 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform shadow-lg"><i class="fas fa-trophy text-white text-3xl"></i></div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">Gestión de Torneos</h3>
                            <p class="text-gray-600 mb-4">Sistema completo para organizar, administrar y seguir torneos de dominó con clasificaciones en tiempo real</p>
                            <a href="#" @click.prevent="scrollToSection('eventos')" class="text-primary-600 font-semibold hover:text-primary-800 inline-flex items-center">Ver Torneos <i class="fas fa-arrow-right ml-2"></i></a>
                        </div>
                        <div class="group bg-gradient-to-br from-purple-50 to-pink-100 rounded-2xl p-8 hover:shadow-2xl transition-all transform hover:-translate-y-2 border border-purple-100">
                            <div class="bg-purple-600 w-16 h-16 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform shadow-lg"><i class="fas fa-users text-white text-3xl"></i></div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">Clubes Registrados</h3>
                            <p class="text-gray-600 mb-4">Conoce todos los clubes afiliados, sus directivos y cómo contactarlos para participar en sus actividades</p>
                            <a href="#" @click.prevent="scrollToSection('registro')" class="text-purple-600 font-semibold hover:text-purple-800 inline-flex items-center">Explorar Clubes <i class="fas fa-arrow-right ml-2"></i></a>
                        </div>
                        <div class="group bg-gradient-to-br from-green-50 to-emerald-100 rounded-2xl p-8 hover:shadow-2xl transition-all transform hover:-translate-y-2 border border-green-100">
                            <div class="bg-accent w-16 h-16 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform shadow-lg"><i class="fas fa-calendar-alt text-white text-3xl"></i></div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">Calendario Anual</h3>
                            <p class="text-gray-600 mb-4">Mantente informado de todos los eventos, torneos y actividades durante todo el año</p>
                            <a href="#" @click.prevent="scrollToSection('calendario')" class="text-accent font-semibold hover:text-accentDark inline-flex items-center">Ver Calendario <i class="fas fa-arrow-right ml-2"></i></a>
                        </div>
                        <div class="group bg-gradient-to-br from-yellow-50 to-orange-100 rounded-2xl p-8 hover:shadow-2xl transition-all transform hover:-translate-y-2 border border-yellow-100">
                            <div class="bg-yellow-500 w-16 h-16 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform shadow-lg"><i class="fas fa-chart-line text-white text-3xl"></i></div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">Resultados en Vivo</h3>
                            <p class="text-gray-600 mb-4">Consulta resultados de torneos realizados, estadísticas de jugadores y rankings actualizados</p>
                            <a :href="baseUrl + 'resultados.php'" class="text-yellow-600 font-semibold hover:text-yellow-800 inline-flex items-center">Ver Resultados <i class="fas fa-arrow-right ml-2"></i></a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Galería -->
            <section id="galeria" class="py-16 md:py-24 bg-gray-50">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl md:text-4xl font-bold text-primary-700 mb-4"><i class="fas fa-images mr-3 text-accent"></i>Galería de Torneos</h2>
                        <p class="text-lg text-gray-600">Momentos destacados de nuestros eventos</p>
                    </div>
                    <div class="text-center py-12 bg-white rounded-2xl shadow-lg">
                        <i class="fas fa-images text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-600 text-lg mb-4">Momentos de nuestros torneos</p>
                        <a :href="baseUrl + 'galeria_fotos.php'" class="inline-block bg-primary-500 text-white px-6 py-2 rounded-lg font-semibold hover:bg-primary-600 transition-all"><i class="fas fa-images mr-2"></i>Ver Galería</a>
                    </div>
                </div>
            </section>

            <!-- FAQ -->
            <section id="faq" class="py-16 md:py-24 bg-white">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-primary-700 mb-4">Preguntas Frecuentes</h2>
                        <p class="text-lg md:text-xl text-gray-600 max-w-2xl mx-auto">Todo lo que necesitas saber sobre La Estación del Dominó</p>
                    </div>
                    <div class="max-w-4xl mx-auto space-y-4">
                        <details class="group bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-all border border-gray-200">
                            <summary class="flex items-center justify-between cursor-pointer font-bold text-lg text-gray-900 list-none">
                                <span><i class="fas fa-question-circle text-primary-500 mr-3"></i>¿Cómo solicito la afiliación de mi club?</span>
                                <i class="fas fa-chevron-down text-primary-500 group-open:rotate-180 transition-transform"></i>
                            </summary>
                            <p class="mt-4 text-gray-600 pl-10 leading-relaxed">Haz clic en "Solicitar Afiliación", completa el formulario de solicitud de afiliación para tu club u organización y el equipo lo revisará.</p>
                        </details>
                        <details class="group bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-all border border-gray-200">
                            <summary class="flex items-center justify-between cursor-pointer font-bold text-lg text-gray-900 list-none">
                                <span><i class="fas fa-question-circle text-primary-500 mr-3"></i>¿Es gratuito participar en los torneos?</span>
                                <i class="fas fa-chevron-down text-primary-500 group-open:rotate-180 transition-transform"></i>
                            </summary>
                            <p class="mt-4 text-gray-600 pl-10 leading-relaxed">Depende del torneo. Toda la información sobre costos está disponible en la ficha de cada torneo.</p>
                        </details>
                        <details class="group bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-all border border-gray-200">
                            <summary class="flex items-center justify-between cursor-pointer font-bold text-lg text-gray-900 list-none">
                                <span><i class="fas fa-question-circle text-primary-500 mr-3"></i>¿Puedo ver los resultados de torneos anteriores?</span>
                                <i class="fas fa-chevron-down text-primary-500 group-open:rotate-180 transition-transform"></i>
                            </summary>
                            <p class="mt-4 text-gray-600 pl-10 leading-relaxed">¡Por supuesto! Todos los resultados están disponibles en la sección "Eventos Realizados".</p>
                        </details>
                    </div>
                    <div class="text-center mt-12">
                        <a href="#" @click.prevent="scrollToSection('comentarios')" class="inline-block bg-primary-500 text-white px-8 py-3 rounded-lg font-semibold hover:bg-primary-600 transition-all shadow-lg"><i class="fas fa-comments mr-2"></i>Envíanos tu Consulta</a>
                    </div>
                </div>
            </section>

            <!-- Comentarios -->
            <section id="comentarios" class="py-16 md:py-24 bg-white">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl md:text-4xl font-bold text-primary-700 mb-4"><i class="fas fa-comments mr-3 text-accent"></i>Comentarios y Testimonios</h2>
                        <p class="text-lg text-gray-600">La opinión de nuestra comunidad es muy importante</p>
                    </div>
                    <div v-if="commentSuccess" class="max-w-4xl mx-auto mb-6">
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md"><i class="fas fa-check-circle mr-3"></i>{{ commentSuccess }}</div>
                    </div>
                    <div v-if="commentErrors.length" class="max-w-4xl mx-auto mb-6">
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
                            <div v-for="err in commentErrors" :key="err">{{ err }}</div>
                        </div>
                    </div>
                    <div class="max-w-7xl mx-auto">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <div class="lg:col-span-1">
                                <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-200 sticky top-24">
                                    <h3 class="text-2xl font-bold text-gray-900 mb-6"><i class="fas fa-comment-dots text-primary-500 mr-2"></i>Envía tu Comentario</h3>
                                    <template v-if="data.user">
                                        <form @submit.prevent="enviarComentario" class="space-y-4">
                                            <div class="bg-primary-50 p-3 rounded-lg mb-4">
                                                <p class="text-sm text-primary-700"><i class="fas fa-user-check mr-2"></i>Comentando como: <strong>{{ data.user.nombre }}</strong></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo *</label>
                                                <select v-model="commentForm.tipo" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                                    <option value="comentario">Comentario</option>
                                                    <option value="sugerencia">Sugerencia</option>
                                                    <option value="testimonio">Testimonio</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-semibold text-gray-700 mb-2">Calificación (opcional)</label>
                                                <div class="flex space-x-2">
                                                    <label v-for="i in 5" :key="i" class="cursor-pointer">
                                                        <input type="radio" v-model="commentForm.calificacion" :value="i" class="hidden">
                                                        <i class="far fa-star text-2xl hover:text-yellow-500 transition-colors" :class="commentForm.calificacion >= i ? 'fas text-yellow-400' : 'text-yellow-400'"></i>
                                                    </label>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-semibold text-gray-700 mb-2">Mensaje *</label>
                                                <textarea v-model="commentForm.contenido" rows="5" required placeholder="Escribe tu comentario..." class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                                            </div>
                                            <button type="submit" :disabled="commentSending" class="w-full bg-gradient-to-r from-primary-500 to-primary-700 text-white py-3 rounded-lg font-bold hover:from-primary-600 hover:to-primary-800 transition-all shadow-lg disabled:opacity-50">
                                                <i v-if="commentSending" class="fas fa-spinner fa-spin mr-2"></i>
                                                <i v-else class="fas fa-paper-plane mr-2"></i>{{ commentSending ? 'Enviando...' : 'Enviar Comentario' }}
                                            </button>
                                            <p class="text-xs text-gray-500 text-center"><i class="fas fa-shield-alt mr-1"></i>Los comentarios son moderados antes de publicarse</p>
                                        </form>
                                    </template>
                                    <div v-else class="text-center py-8">
                                        <i class="fas fa-lock text-4xl text-gray-400 mb-4"></i>
                                        <p class="text-gray-600 mb-4">Debes iniciar sesión para publicar comentarios</p>
                                        <a :href="baseUrl + 'login.php?redirect=' + encodeURIComponent(baseUrl + 'landing-spa.php#comentarios')" class="inline-block bg-primary-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-600 transition-all"><i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión</a>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-2">
                                <div v-if="data.comentarios?.length" class="space-y-6">
                                    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 border border-gray-200">
                                        <div class="grid grid-cols-3 gap-4 text-center">
                                            <div><div class="text-3xl font-bold text-primary-500">{{ data.comentarios.filter(c => c.tipo === 'comentario').length }}</div><div class="text-sm text-gray-600">Comentarios</div></div>
                                            <div><div class="text-3xl font-bold text-purple-600">{{ data.comentarios.filter(c => c.tipo === 'sugerencia').length }}</div><div class="text-sm text-gray-600">Sugerencias</div></div>
                                            <div><div class="text-3xl font-bold text-yellow-600">{{ data.comentarios.filter(c => c.tipo === 'testimonio').length }}</div><div class="text-sm text-gray-600">Testimonios</div></div>
                                        </div>
                                    </div>
                                    <div v-for="c in data.comentarios" :key="c.id" class="bg-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition-shadow border border-gray-200">
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-full flex items-center justify-center text-white font-bold">{{ (c.nombre || 'U').charAt(0).toUpperCase() }}</div>
                                                <div>
                                                    <h4 class="font-bold text-gray-900">{{ c.nombre }} <span v-if="c.usuario_username" class="text-xs text-primary-500 ml-2"><i class="fas fa-user-check"></i> Usuario registrado</span></h4>
                                                    <span class="text-xs text-gray-500">{{ new Date(c.fecha_creacion).toLocaleString('es-VE') }}</span>
                                                </div>
                                            </div>
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="c.tipo === 'comentario' ? 'bg-blue-100 text-blue-800' : c.tipo === 'sugerencia' ? 'bg-purple-100 text-purple-800' : 'bg-yellow-100 text-yellow-800'">{{ (c.tipo || 'comentario').charAt(0).toUpperCase() + (c.tipo || 'comentario').slice(1) }}</span>
                                        </div>
                                        <div v-if="c.calificacion" class="mb-3">
                                            <i v-for="i in 5" :key="i" class="fas fa-star" :class="i <= c.calificacion ? 'text-yellow-400' : 'text-gray-300'"></i>
                                        </div>
                                        <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">{{ c.contenido }}</p>
                                    </div>
                                </div>
                                <div v-else class="bg-white rounded-2xl shadow-lg p-12 text-center border border-gray-200">
                                    <i class="fas fa-comment-slash text-gray-400 text-6xl mb-4"></i>
                                    <h3 class="text-2xl font-bold text-gray-900 mb-2">No hay comentarios aún</h3>
                                    <p class="text-gray-600 mb-6">Sé el primero en compartir tu opinión con la comunidad.</p>
                                    <a v-if="!data.user" :href="baseUrl + 'login.php?redirect=' + encodeURIComponent(baseUrl + 'landing-spa.php#comentarios')" class="inline-block bg-gradient-to-r from-primary-500 to-primary-700 text-white px-6 py-3 rounded-lg font-semibold hover:from-primary-600 hover:to-primary-800 transition-all"><i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión para Comentar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="bg-gray-900 text-white py-12">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col md:flex-row items-center justify-between">
                        <div class="mb-4 md:mb-0">
                            <h5 class="text-xl font-bold mb-2 flex items-center">
                                <img :src="logoUrl" alt="La Estación del Dominó" class="h-6 mr-2">
                                La Estación del Dominó
                            </h5>
                            <p class="text-gray-400">Sistema integral para la gestión de torneos de dominó</p>
                        </div>
                        <div class="text-center md:text-right">
                            <p class="text-gray-400 mb-1 flex items-center justify-center md:justify-end"><i class="fas fa-envelope mr-2"></i>info@laestaciondeldomino.com</p>
                            <p class="text-gray-500 text-sm">&copy; 2025 La Estación del Dominó. Todos los derechos reservados.</p>
                        </div>
                    </div>
                </div>
            </footer>

            <div id="modal-container"></div>
        </div>
    </script>
    <script>
        window.APP_CONFIG = {
            apiUrl: <?= json_encode($api_url) ?>,
            baseUrl: <?= json_encode($base_url) ?>,
            logoUrl: <?= json_encode($logo_url) ?>
        };
    </script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script src="<?= htmlspecialchars($base_url . 'assets/landing-spa.js') ?>"></script>
</body>
</html>
