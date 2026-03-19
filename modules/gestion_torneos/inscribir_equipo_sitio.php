<?php
/**
 * Vista: Inscribir Equipos en Sitio
 * Formulario simplificado que muestra solo jugadores NO inscritos
 */
// Buffer amplio: evita flush por trozos (p. ej. 4KB) que hace ver primero Disponibles y luego el resto.
if (ob_get_level() < 5) {
    ob_start(null, 2 * 1024 * 1024);
}
$torneo = $view_data['torneo'] ?? [];
$jugadores_disponibles = $view_data['jugadores_disponibles'] ?? [];
$clubes_disponibles = $view_data['clubes_disponibles'] ?? [];
$equipos_registrados = $view_data['equipos_registrados'] ?? [];
$jugadores_por_equipo = $view_data['jugadores_por_equipo'] ?? 4;
$es_parejas = !empty($view_data['es_parejas']);
$jugadores_lista_lazy = !empty($view_data['jugadores_lista_lazy']);
$etiqueta_equipo = $es_parejas ? 'Pareja' : 'Equipo';
$etiqueta_equipos = $es_parejas ? 'Parejas' : 'Equipos';

/** Base URL hacia public/api/ — obligatoria para buscar_jugador, obtener_equipo, eliminar_equipo */
$api_base_path = (function_exists('AppHelpers') ? AppHelpers::getPublicPath() : '/mistorneos/public/') . 'api/';

// Determinar si el torneo ya inició (tiene rondas)
$torneo_iniciado = false;
if (!empty($torneo['id'])) {
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT MAX(CAST(partida AS UNSIGNED)) AS ultima_ronda FROM partiresul WHERE id_torneo = ? AND mesa > 0");
        $stmt->execute([(int)$torneo['id']]);
        $ultima_ronda = (int)($stmt->fetchColumn() ?? 0);
        // Equipos: bloquear desde la primera ronda
        $torneo_iniciado = $ultima_ronda >= 1;
    } catch (Exception $e) {
        $torneo_iniciado = false;
    }
}

$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';

// Guardado vía index.php / admin_torneo (misma sesión; no depender de guardar_equipo.php en /api/)
$api_guardar_equipo = $base_url . ($use_standalone ? '?' : '&') . 'action=guardar_equipo_sitio&torneo_id=' . (int)($torneo['id'] ?? 0);
?>
<!-- inscribir_equipo_sitio: POST interno action=guardar_equipo_sitio (no public/api) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
    body {
        background-color: #f8f9fa;
    }
    /* Una sola pantalla: sin scroll en la página (solo columnas internas si hiciera falta) */
    .page-inscripcion-sitio {
        height: 100vh;
        max-height: 100vh;
        overflow: hidden;
        box-sizing: border-box;
        padding: 0.35rem 0.5rem !important;
        display: flex;
        flex-direction: column;
    }
    .page-inscripcion-sitio .breadcrumb { margin-bottom: 0.25rem !important; padding: 0.25rem 0; font-size: 0.8rem; }
    .page-inscripcion-sitio .card.mb-4:first-of-type { margin-bottom: 0.35rem !important; }
    .page-inscripcion-sitio .card.mb-4:first-of-type .card-body { padding: 0.5rem 0.75rem !important; }
    .page-inscripcion-sitio .card.mb-4:first-of-type h2 { font-size: 1rem !important; margin-bottom: 0 !important; }
    .page-inscripcion-sitio .row.g-2.g-lg-3 {
        flex: 1 1 0;
        min-height: 0;
        margin-left: 0;
        margin-right: 0;
        align-items: stretch;
    }
    .page-inscripcion-sitio .row.g-2.g-lg-3 > [class^="col-"] {
        display: flex;
        flex-direction: column;
        min-height: 0;
        max-height: 100%;
    }
    .page-inscripcion-sitio .col-disponibles > .card,
    .page-inscripcion-sitio .col-insc-equipos > .card {
        flex: 1 1 0;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .page-inscripcion-sitio .col-disponibles .card-body.p-0,
    .page-inscripcion-sitio .equipo-sidebar-card .card-body {
        flex: 1 1 0;
        min-height: 0;
        overflow-y: auto;
        max-height: none !important;
    }
    .jugador-item {
        padding: 8px 12px;
        border-bottom: 1px solid #e9ecef;
        transition: background-color 0.2s;
        cursor: pointer;
    }
    .jugador-item:hover {
        background-color: #e9ecef;
    }
    .jugador-item.selected {
        background-color: #cfe2ff;
        border-left: 3px solid #0d6efd;
    }
    .page-inscripcion-sitio .search-box {
        position: sticky;
        top: 0;
        background: white;
        padding: 0.35rem 0.5rem;
        border-bottom: 1px solid #e9ecef;
        z-index: 10;
        flex-shrink: 0;
    }
    .page-inscripcion-sitio .separador-jugador {
        border-top: 1px dashed #0d6efd;
        margin: 2px 0 !important;
        opacity: 0.45;
    }
    .equipo-registrado-item {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        background: white;
        transition: all 0.2s;
    }
    .equipo-registrado-item:hover {
        background-color: #f8f9fa;
        border-color: #0d6efd;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .equipo-registrado-item.selected {
        background-color: #e7f3ff;
        border-color: #0d6efd;
        border-width: 2px;
    }
    .equipo-registrado-item > div:first-child:hover {
        color: #0d6efd;
    }
    /* Layout: un poco más ancho formulario para fila ID|cédula|nombre en una línea */
    .col-disponibles {
        flex: 0 0 28%;
        max-width: 28%;
        background: linear-gradient(180deg, #e8f4fc 0%, #f0f7ff 100%);
        border-radius: 0.5rem;
        padding: 0.5rem;
    }
    .col-insc-form {
        flex: 0 0 41%;
        max-width: 41%;
        background: linear-gradient(180deg, #fff9e6 0%, #fffdf5 100%);
        border-radius: 0.5rem;
        padding: 0.5rem;
    }
    .page-inscripcion-sitio .col-insc-form {
        min-height: 0;
    }
    .page-inscripcion-sitio .col-insc-form > .card {
        flex: 1 1 0;
        min-height: 0;
        max-height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .page-inscripcion-sitio .col-insc-form > .card > .card-header {
        flex-shrink: 0;
        padding: 0.35rem 0.5rem !important;
    }
    .page-inscripcion-sitio .col-insc-form > .card > .card-header h6 { font-size: 0.8rem; margin: 0; }
    .page-inscripcion-sitio .col-insc-form > .card > .card-body {
        flex: 1 1 0;
        min-height: 0;
        overflow: hidden;
        padding: 0.4rem 0.5rem !important;
        display: flex;
        flex-direction: column;
    }
    .page-inscripcion-sitio #formEquipo {
        display: flex;
        flex-direction: column;
        min-height: 0;
        flex: 1 1 0;
        overflow: hidden;
    }
    .page-inscripcion-sitio .fila-club-nombre-equipo {
        flex-shrink: 0;
        margin-bottom: 0.35rem !important;
    }
    .page-inscripcion-sitio .col-insc-form #club_id.form-select,
    .page-inscripcion-sitio .col-insc-form #nombre_equipo.form-control {
        min-height: 1.65rem !important;
        height: 1.65rem !important;
        padding: 0.15rem 0.35rem !important;
        font-size: 0.72rem !important;
    }
    .page-inscripcion-sitio #jugadores-container {
        flex: 1 1 0;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
    }
    .col-insc-equipos {
        flex: 0 0 31%;
        max-width: 31%;
        background: linear-gradient(180deg, #e8f5e9 0%, #f1faf1 100%);
        border-radius: 0.5rem;
        padding: 0.5rem;
    }
    .col-disponibles .jugador-item,
    .col-disponibles .jugador-item .small,
    .col-disponibles .jugador-item span { font-weight: 700 !important; }
    .col-disponibles .search-box small { font-weight: 600; }
    @media (max-width: 991px) {
        .col-disponibles, .col-insc-form, .col-insc-equipos {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
    /* Club + nombre equipo: misma línea, mismo alto */
    .fila-club-nombre-equipo {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-end;
        gap: 0.5rem;
        width: 100%;
        margin-bottom: 0.75rem;
    }
    .fila-club-nombre-equipo .campo-club,
    .fila-club-nombre-equipo .campo-nombre-equipo {
        flex: 1 1 50%;
        min-width: 0;
    }
    .fila-club-nombre-equipo .form-label { margin-bottom: 0.15rem; }
    @media (max-width: 576px) {
        .fila-club-nombre-equipo { flex-wrap: wrap; }
        .fila-club-nombre-equipo .campo-club,
        .fila-club-nombre-equipo .campo-nombre-equipo { flex: 1 1 100%; }
    }
    .col-insc-form #club_id.form-select,
    .col-insc-form #nombre_equipo.form-control {
        min-height: 2.35rem !important;
        height: 2.35rem !important;
        padding: 0.4rem 0.5rem !important;
        font-size: 0.8rem !important;
        line-height: 1.3 !important;
        box-sizing: border-box !important;
    }
    .jugador-item { padding: 4px 8px !important; font-size: 0.8rem; line-height: 1.25; }
    .fila-jugador-compacta {
        margin-bottom: 0.35rem !important;
        align-items: center !important;
        min-height: 0;
    }
    /* Controles compactos (no club/nombre ni filas jugador: tienen reglas propias) */
    .col-insc-form .form-control-sm:not(.jugador-id-usuario):not(.jugador-cedula):not(.jugador-nombre),
    .col-insc-form .form-select-sm:not(#club_id) {
        padding: 0.08rem 0.28rem !important;
        font-size: 0.7rem !important;
        line-height: 1.05 !important;
        min-height: calc(0.72em + 0.16rem) !important;
    }
    /* Filas jugador compactas para caber en viewport sin scroll global */
    .page-inscripcion-sitio .fila-jugador-compacta {
        margin-bottom: 0.15rem !important;
    }
    .page-inscripcion-sitio .fila-jugador-compacta .jugador-id-usuario,
    .page-inscripcion-sitio .fila-jugador-compacta .jugador-cedula,
    .page-inscripcion-sitio .fila-jugador-compacta .jugador-nombre {
        padding: 0.1rem 0.25rem !important;
        font-size: 0.72rem !important;
        line-height: 1.15 !important;
        min-height: 1.5rem !important;
        box-sizing: border-box !important;
    }
    .fila-jugador-compacta .wrap-inputs-jugador {
        display: flex !important;
        flex: 0 0 80% !important;
        width: 80% !important;
        max-width: 80% !important;
        min-width: 0;
        align-items: center;
        gap: 0.12rem;
    }
    .fila-jugador-compacta.row {
        flex-wrap: nowrap;
    }
    @media (max-width: 576px) {
        .fila-jugador-compacta.row { flex-wrap: wrap; }
        .fila-jugador-compacta .wrap-inputs-jugador {
            flex: 0 0 100% !important;
            width: 100% !important;
            max-width: 100% !important;
        }
    }
    .fila-jugador-compacta .input-id-usuario {
        flex: 4.608 1 0;
        min-width: 0;
        max-width: none;
    }
    .fila-jugador-compacta .input-cedula {
        flex: 6.656 1 0;
        min-width: 0;
        max-width: none;
    }
    .fila-jugador-compacta .input-nombre-jug {
        flex: 15.616 1 0;
        min-width: 0;
        max-width: none;
    }
    .equipo-sidebar-item {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        margin-bottom: 0.5rem;
        background: #fff;
    }
    .equipo-sidebar-header {
        padding: 0.4rem 0.5rem;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 700;
    }
    .equipo-sidebar-header:hover { background: #f8f9fa; }
    .equipo-sidebar-header .btn { cursor: pointer; font-weight: 600; }
    .equipo-sidebar-integrantes {
        font-size: 0.78rem;
        font-weight: 700;
        padding: 0 0.5rem 0.4rem;
        border-top: 1px dashed #e9ecef;
    }
    .equipo-sidebar-integrantes li { padding: 0.15rem 0; font-weight: 700; }
    #wrap_codigo_equipo_barra { min-height: 1.5rem; }
    .btn-editar-equipo-form { font-size: 0.7rem; padding: 0.1rem 0.35rem; }
    /* Parejas: formulario arriba ancho completo; abajo dos columnas 50% */
    <?php if ($es_parejas): ?>
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top {
        border: 2px solid #0d6efd;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        background: #f4f8ff;
        color: #000;
    }
    .page-inscripcion-sitio.form-parejas-amigable #formEquipo {
        border: 2px dashed #9ec5fe;
        border-radius: 8px;
        padding: 0.4rem 0.5rem;
        background: #f9fcff;
    }
    /* Formulario: letras ampliadas 40% */
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top input.form-control,
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top select.form-select {
        font-size: 0.91rem !important;
        line-height: 1.25 !important;
        height: 1.75rem !important;
        min-height: 1.75rem !important;
        padding: 0.15rem 0.35rem !important;
        color: #000 !important;
    }
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .form-label {
        font-size: 0.84rem !important;
        color: #000 !important;
        margin-bottom: 0.08rem !important;
    }
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .btn {
        font-size: 0.84rem !important;
        padding: 0.25rem 0.5rem !important;
    }
    /* Fila superior: Club y Nombre equipo ancho +100%; Cédula a buscar ancho +50% */
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .fila-parejas-compacta .campo-club-parejas,
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .fila-parejas-compacta .campo-nombre-parejas {
        flex: 2 1 0;
        min-width: 0;
    }
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .fila-parejas-compacta .campo-cedula-buscar-parejas {
        flex: 1.5 1 0;
        min-width: 0;
    }
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .fila-parejas-compacta .campo-club-parejas input,
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .fila-parejas-compacta .campo-club-parejas select,
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .fila-parejas-compacta .campo-nombre-parejas input,
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .fila-parejas-compacta .campo-cedula-buscar-parejas input {
        width: 100%;
        max-width: 100%;
    }
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .fila-parejas-compacta {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        margin-bottom: 0.2rem;
    }
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .jugadores-y-botones {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .page-inscripcion-sitio.form-parejas-amigable .form-parejas-top .jugadores-y-botones .botones-parejas {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        flex-shrink: 0;
    }
    /* Modelo: filas alineadas — codigo | id | cedula | nombre | botón */
    .page-inscripcion-sitio.form-parejas-amigable .fila-parejas-modelo {
        display: flex;
        align-items: flex-end;
        gap: 0.35rem;
        margin-bottom: 0.25rem;
    }
    .page-inscripcion-sitio.form-parejas-amigable .celda-codigo-parejas {
        width: 4.5rem;
        min-width: 4.5rem;
        flex-shrink: 0;
    }
    .page-inscripcion-sitio.form-parejas-amigable .celda-id-parejas { width: 2.75rem; min-width: 2.75rem; flex-shrink: 0; }
    .page-inscripcion-sitio.form-parejas-amigable .celda-cedula-parejas { width: 4.5rem; min-width: 4.5rem; flex-shrink: 0; }
    .page-inscripcion-sitio.form-parejas-amigable .celda-nombre-parejas { flex: 1; min-width: 4rem; }
    .page-inscripcion-sitio.form-parejas-amigable .celda-codigo-parejas input,
    .page-inscripcion-sitio.form-parejas-amigable .celda-id-parejas input,
    .page-inscripcion-sitio.form-parejas-amigable .celda-cedula-parejas input,
    .page-inscripcion-sitio.form-parejas-amigable .celda-nombre-parejas input {
        width: 100%;
        max-width: 100%;
    }
    .page-inscripcion-sitio.form-parejas-amigable .btn-parejas-fila {
        flex-shrink: 0;
    }
    .page-inscripcion-sitio.form-parejas-amigable .col-disponibles-parejas,
    .page-inscripcion-sitio.form-parejas-amigable .col-insc-equipos-parejas {
        flex: 0 0 50%;
        max-width: 50%;
        border: 2px solid #0d6efd;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        background: #f4f8ff;
        color: #000;
    }
    .page-inscripcion-sitio.form-parejas-amigable .col-disponibles-parejas .jugador-item .small,
    .page-inscripcion-sitio.form-parejas-amigable .col-disponibles-parejas .jugador-item span,
    .page-inscripcion-sitio.form-parejas-amigable .col-insc-equipos-parejas .equipo-sidebar-header .badge,
    .page-inscripcion-sitio.form-parejas-amigable .col-insc-equipos-parejas .equipo-sidebar-header .text-primary,
    .page-inscripcion-sitio.form-parejas-amigable .col-insc-equipos-parejas .equipo-sidebar-header .text-muted {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.875rem;
        color: #000;
    }
    @media (max-width: 767px) {
        .page-inscripcion-sitio.form-parejas-amigable .col-disponibles-parejas,
        .page-inscripcion-sitio.form-parejas-amigable .col-insc-equipos-parejas {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
    <?php endif; ?>
</style>

<div class="container-fluid py-4 page-inscripcion-sitio<?php echo $es_parejas ? ' form-parejas-amigable' : ''; ?>">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>">Gestión de Torneos</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
            <li class="breadcrumb-item active">Inscribir en Sitio</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2 class="h4 mb-1">
                        <i class="fas fa-user-plus text-warning me-2"></i>Inscribir <?php echo $etiqueta_equipo; ?> en Sitio
                    </h2>
                    <p class="text-muted mb-0">
                        <i class="fas fa-trophy me-1"></i><?php echo htmlspecialchars($torneo['nombre']); ?>
                        <span class="badge bg-info ms-2"><?php echo $jugadores_por_equipo; ?> jugadores por <?php echo strtolower($etiqueta_equipo); ?></span>
                    </p>
                </div>
                <div class="mt-2 mt-md-0">
                    <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" 
                       class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Retornar al Panel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($es_parejas): ?>
    <!-- Parejas: formulario arriba ancho completo -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm form-parejas-top">
                <div class="card-header bg-warning text-dark py-2">
                    <h6 class="mb-0">
                        <i class="fas fa-edit me-1"></i>Inscripción por parejas
                        <span class="d-block mt-1 fw-normal small text-muted">Club, nombre (opcional) y cédula en la fila del jugador; al salir del campo se busca automáticamente.</span>
                    </h6>
                </div>
                <div class="card-body py-2">
                    <?php if ($torneo_iniciado): ?>
                        <div class="alert alert-warning mb-1 py-1"><i class="fas fa-exclamation-triangle me-1"></i>El torneo ya inició. No se permiten nuevas inscripciones.</div>
                    <?php endif; ?>
                    <form id="formEquipo">
                        <?php require_once __DIR__ . '/../../config/csrf.php'; ?>
                        <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                        <input type="hidden" id="equipo_id" name="equipo_id" value="">
                        <input type="hidden" id="torneo_id" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                        <input type="hidden" id="codigo_equipo" name="codigo_equipo" value="">
                        <!-- Fila 1: Club | Nombre pareja | Cédula a buscar (blur = búsqueda automática) -->
                        <div class="fila-parejas-compacta">
                            <div class="campo-club-parejas">
                                <label class="form-label small mb-0" for="club_id">Club *</label>
                                <select id="club_id" name="club_id" class="form-select form-select-sm w-100" required>
                                    <option value="">Club *</option>
                                    <?php if (!empty($clubes_disponibles)): foreach ($clubes_disponibles as $club): ?>
                                        <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['nombre']); ?></option>
                                    <?php endforeach; else: ?>
                                        <option value="" disabled>No hay clubes</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="campo-nombre-parejas">
                                <label class="form-label small mb-0" for="nombre_equipo">Nombre pareja (opc.)</label>
                                <input type="text" id="nombre_equipo" name="nombre_equipo" class="form-control form-control-sm w-100" placeholder="Opcional">
                            </div>
                            <div class="campo-cedula-buscar-parejas">
                                <label class="form-label small mb-0" for="cedula_buscar_parejas">Cédula a buscar</label>
                                <input type="text" id="cedula_buscar_parejas" class="form-control form-control-sm w-100" placeholder="Salir del campo para buscar" inputmode="numeric" autocomplete="off">
                            </div>
                        </div>
                        <!-- Fila jugador 1: Código (oculto) | id | cedula | nombre | Nueva -->
                        <div class="fila-parejas-compacta fila-parejas-modelo fila-jugador-compacta" data-posicion="1" data-jugador-asignado="">
                            <div id="wrap_codigo_equipo_barra" class="celda-codigo-parejas" style="visibility:hidden;" aria-hidden="true">
                                <span class="small text-muted fw-bold">Código</span>
                                <span id="codigo_equipo_visible" class="badge bg-secondary px-2 py-0"></span>
                            </div>
                            <div class="celda-id-parejas">
                                <label class="form-label small mb-0 d-block">id</label>
                                <input type="text" class="form-control form-control-sm jugador-id-usuario input-id-usuario" id="jugador_id_usuario_1" placeholder="ID" readonly style="background:#e9ecef;">
                                <input type="hidden" id="jugador_id_usuario_h_1" name="jugadores[1][id_usuario]">
                            </div>
                            <div class="celda-cedula-parejas">
                                <label class="form-label small mb-0 d-block">cedula</label>
                                <input type="text" class="form-control form-control-sm jugador-cedula input-cedula" id="jugador_cedula_1" name="jugadores[1][cedula]" placeholder="Céd." data-posicion="1" onblur="buscarJugadorPorCedula(this)" oninput="validarFormulario()">
                                <input type="hidden" class="jugador-id-inscrito" id="jugador_id_inscrito_1" name="jugadores[1][id_inscrito]">
                            </div>
                            <div class="celda-nombre-parejas">
                                <label class="form-label small mb-0 d-block">nombre</label>
                                <input type="text" class="form-control form-control-sm jugador-nombre input-nombre-jug" id="jugador_nombre_1" name="jugadores[1][nombre]" placeholder="Nombre" readonly style="background:#e9ecef;" oninput="validarFormulario()">
                            </div>
                            <input type="hidden" id="es_capitan_1" name="jugadores[1][es_capitan]" value="1">
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 align-self-end" onclick="limpiarJugadorYDevolver(1)" title="Quitar" id="btn_limpiar_1" style="display:none;"><i class="fas fa-times"></i></button>
                            <button type="button" class="btn btn-secondary btn-sm align-self-end btn-parejas-fila" onclick="limpiarFormulario()" <?= $torneo_iniciado ? 'disabled' : '' ?>><i class="fas fa-redo me-1"></i>Nueva</button>
                        </div>
                        <!-- Fila jugador 2: (mismo ancho código) | id | cedula | nombre | Guardar -->
                        <div class="fila-parejas-compacta fila-parejas-modelo fila-jugador-compacta" data-posicion="2" data-jugador-asignado="">
                            <div class="celda-codigo-parejas"></div>
                            <div class="celda-id-parejas">
                                <label class="form-label small mb-0 d-block">id</label>
                                <input type="text" class="form-control form-control-sm jugador-id-usuario input-id-usuario" id="jugador_id_usuario_2" placeholder="ID" readonly style="background:#e9ecef;">
                                <input type="hidden" id="jugador_id_usuario_h_2" name="jugadores[2][id_usuario]">
                            </div>
                            <div class="celda-cedula-parejas">
                                <label class="form-label small mb-0 d-block">cedula</label>
                                <input type="text" class="form-control form-control-sm jugador-cedula input-cedula" id="jugador_cedula_2" name="jugadores[2][cedula]" placeholder="Céd." data-posicion="2" onblur="buscarJugadorPorCedula(this)" oninput="validarFormulario()">
                                <input type="hidden" class="jugador-id-inscrito" id="jugador_id_inscrito_2" name="jugadores[2][id_inscrito]">
                            </div>
                            <div class="celda-nombre-parejas">
                                <label class="form-label small mb-0 d-block">nombre</label>
                                <input type="text" class="form-control form-control-sm jugador-nombre input-nombre-jug" id="jugador_nombre_2" name="jugadores[2][nombre]" placeholder="Nombre" readonly style="background:#e9ecef;" oninput="validarFormulario()">
                            </div>
                            <input type="hidden" id="es_capitan_2" name="jugadores[2][es_capitan]" value="0">
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 align-self-end" onclick="limpiarJugadorYDevolver(2)" title="Quitar" id="btn_limpiar_2" style="display:none;"><i class="fas fa-times"></i></button>
                            <button type="submit" class="btn btn-success btn-sm align-self-end btn-parejas-fila" id="btnGuardarEquipo" <?= $torneo_iniciado ? 'disabled' : '' ?>><i class="fas fa-save me-1"></i>Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Dos columnas 50%: Disponibles | Inscritos -->
    <div class="row g-2">
        <div class="col-12 col-md-6 col-disponibles-parejas">
            <div class="card border-0 shadow-sm h-100 d-flex flex-column overflow-hidden">
                <div class="card-header bg-primary text-white py-2">
                    <h6 class="mb-0"><i class="fas fa-user-friends me-1"></i>Atletas de su entidad</h6>
                </div>
                <div class="search-box">
                    <small class="text-muted d-block">Clic en un atleta para asignarlo a una posición.</small>
                    <input type="text" id="searchJugadores" class="d-none" disabled aria-hidden="true">
                    <input type="hidden" id="buscarCedulaLazy" aria-hidden="true">
                </div>
                <div class="card-body p-0" style="flex:1;min-height:0;overflow-y:auto;">
                    <?php if (empty($jugadores_disponibles)): ?>
                        <div class="text-center py-3 text-muted small">Sin disponibles</div>
                    <?php else: ?>
                        <div class="small text-muted px-2 py-1 border-bottom bg-light fw-bold">ID | Céd. | Nombre</div>
                        <div id="listaJugadores">
                            <?php foreach ($jugadores_disponibles as $jugador): ?>
                                <div class="jugador-item <?= $torneo_iniciado ? 'disabled' : '' ?>" data-nombre="<?php echo strtolower(htmlspecialchars($jugador['nombre'] ?? '')); ?>" data-cedula="<?php echo htmlspecialchars($jugador['cedula'] ?? ''); ?>" data-id-usuario="<?php echo $jugador['id_usuario'] ?? ''; ?>" data-id="<?php echo $jugador['id'] ?? ''; ?>" data-jugador='<?php echo htmlspecialchars(json_encode($jugador, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>' <?php if (!$torneo_iniciado): ?>onclick="seleccionarJugador(this)"<?php endif; ?> style="cursor:<?= $torneo_iniciado ? 'not-allowed' : 'pointer' ?>;">
                                    <div class="small"><span class="text-muted fw-bold"><?php echo htmlspecialchars($jugador['id_usuario'] ?? '-'); ?></span> | <span class="text-muted"><?php echo htmlspecialchars($jugador['cedula'] ?? ''); ?></span> | <span class="text-dark"><?php echo htmlspecialchars($jugador['nombre'] ?? ''); ?></span></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-insc-equipos-parejas">
            <div class="card border-0 shadow-sm equipo-sidebar-card h-100">
                <div class="card-header bg-success text-white py-2">
                    <h6 class="mb-0"><i class="fas fa-users me-1"></i>Parejas inscritas (<?php echo count($equipos_registrados); ?>)</h6>
                    <small class="opacity-75 d-block">Clic en la fila para ver integrantes · Editar para cargar en el formulario</small>
                </div>
                <div class="card-body p-2">
                    <?php if (empty($equipos_registrados)): ?>
                        <div class="text-center py-3 text-muted small">Aún no hay parejas</div>
                    <?php else: ?>
                        <div id="listaEquiposRegistrados">
                            <?php foreach ($equipos_registrados as $equipo): $eid = (int)$equipo['id']; $collapseId = 'int-equipo-' . $eid; ?>
                            <div class="equipo-sidebar-item equipo-registrado-item" data-equipo-id="<?php echo $eid; ?>">
                                <div class="equipo-sidebar-header d-flex align-items-center justify-content-between gap-1 flex-wrap" role="button" tabindex="0" data-collapse-target="<?php echo htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="flex-grow-1 min-w-0">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($equipo['codigo_equipo']); ?></span>
                                        <span class="fw-semibold text-primary"><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></span>
                                        <div class="text-muted small"><?php echo htmlspecialchars($equipo['nombre_club'] ?? ''); ?></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-editar-equipo-form" onclick="event.stopPropagation(); cargarEquipo(<?php echo $eid; ?>); document.getElementById('formEquipo').scrollIntoView({behavior:'smooth'});" title="Editar">Editar</button>
                                    <span class="btn btn-sm btn-outline-secondary py-0 px-1 integrantes-chevron"><i class="fas fa-chevron-down small"></i></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="event.stopPropagation(); eliminarEquipo(<?php echo $eid; ?>, '<?php echo htmlspecialchars($equipo['nombre_equipo'], ENT_QUOTES); ?>')" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                                </div>
                                <ul class="list-unstyled mb-0 equipo-sidebar-integrantes collapse integrantes-collapse" id="<?php echo $collapseId; ?>">
                                    <?php foreach ($equipo['jugadores'] ?? [] as $j): ?>
                                        <li><?php echo htmlspecialchars($j['cedula'] ?? ''); ?> — <?php echo htmlspecialchars($j['nombre'] ?? ''); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Tres columnas: disponibles | formulario | equipos inscritos -->
    <div class="row g-2 g-lg-3">
        <div class="col-12 col-disponibles">
            <div class="card border-0 shadow-sm h-100 d-flex flex-column overflow-hidden">
                <div class="card-header bg-primary text-white py-2">
                    <h6 class="mb-0 small">
                        <i class="fas fa-user-friends me-1"></i><?php echo $es_parejas ? 'Atletas de su entidad' : 'Disponibles'; ?>
                    </h6>
                </div>
                
                <!-- Buscador: parejas = solo lista + cédula en fila (blur); equipos = lista o lazy con botón -->
                <div class="search-box">
                    <?php if ($es_parejas): ?>
                        <small class="text-muted d-block">Atletas de su entidad. Seleccione club y escriba cédula en la fila del jugador; al salir del campo se busca automáticamente.</small>
                        <input type="text" id="searchJugadores" class="form-control form-control-sm mt-1 d-none" disabled aria-hidden="true">
                        <input type="hidden" id="buscarCedulaLazy" aria-hidden="true">
                    <?php elseif ($jugadores_lista_lazy): ?>
                        <label class="form-label small mb-1 fw-semibold" for="buscarCedulaLazy">Buscar por cédula (añadir a disponibles)</label>
                        <div class="input-group input-group-sm mb-1">
                            <input type="text"
                                   id="buscarCedulaLazy"
                                   class="form-control"
                                   placeholder="Cédula del jugador"
                                   inputmode="numeric"
                                   autocomplete="off"
                                   aria-describedby="hintLazyCedula">
                            <button type="button" class="btn btn-primary" id="btnBuscarCedulaLazy" title="Consultar y añadir a la lista">
                                <i class="fas fa-plus"></i> Añadir
                            </button>
                        </div>
                        <small id="hintLazyCedula" class="text-muted d-block">1) Club y nombre del equipo. 2) Busque por cédula; el jugador aparece abajo para asignar.</small>
                        <input type="text" id="searchJugadores" class="form-control form-control-sm mt-1 d-none" disabled aria-hidden="true">
                    <?php else: ?>
                    <input type="text"
                           id="searchJugadores"
                           class="form-control"
                           placeholder="Buscar por ID, cédula o nombre..."
                           disabled>
                    <small class="text-muted">Seleccione el Club y Nombre del <?php echo $etiqueta_equipo; ?> para habilitar</small>
                    <?php endif; ?>
                </div>

                <!-- Lista de Jugadores: parejas = siempre lista de entidad; equipos = lista o lazy -->
                <div class="card-body p-0" style="flex:1;min-height:0;overflow-y:auto;">
                    <?php if (!$es_parejas && $jugadores_lista_lazy): ?>
                        <div class="small text-muted px-2 py-1 border-bottom bg-white fw-bold" style="font-size:0.7rem;">Disponibles (búsqueda por cédula)</div>
                        <div id="listaJugadores"></div>
                    <?php elseif (empty($jugadores_disponibles)): ?>
                        <div class="text-center py-3 text-muted small">
                            <i class="fas fa-user-slash fa-2x mb-2 opacity-50"></i>
                            <p class="mb-0 small">Sin disponibles</p>
                        </div>
                    <?php else: ?>
                        <div class="small text-muted px-2 py-1 border-bottom bg-light fw-bold" style="font-size:0.7rem;">ID | Céd. | Nombre</div>
                        <div id="listaJugadores">
                            <?php foreach ($jugadores_disponibles as $jugador): ?>
                                <div class="jugador-item <?= $torneo_iniciado ? 'disabled' : '' ?>" 
                                     data-nombre="<?php echo strtolower(htmlspecialchars($jugador['nombre'] ?? '')); ?>"
                                     data-cedula="<?php echo htmlspecialchars($jugador['cedula'] ?? ''); ?>"
                                     data-id-usuario="<?php echo $jugador['id_usuario'] ?? ''; ?>"
                                     data-id="<?php echo $jugador['id'] ?? ''; ?>"
                                     data-jugador='<?php echo htmlspecialchars(json_encode($jugador, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'
                                     <?php if (!$torneo_iniciado): ?>
                                     onclick="seleccionarJugador(this)"
                                     <?php endif; ?>
                                     style="cursor: <?= $torneo_iniciado ? 'not-allowed' : 'pointer' ?>;">
                                    <div class="small">
                                        <span class="text-muted fw-bold"><?php echo htmlspecialchars($jugador['id_usuario'] ?? '-'); ?></span>
                                        <span class="mx-1">|</span>
                                        <span class="text-muted"><?php echo htmlspecialchars($jugador['cedula'] ?? 'Sin cédula'); ?></span>
                                        <span class="mx-1">|</span>
                                        <span class="text-dark"><?php echo htmlspecialchars($jugador['nombre'] ?? 'Sin nombre'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- COLUMNA 2: Formulario (~20% menos ancho que col-lg-5) -->
        <div class="col-12 col-lg-4 col-insc-form">
            <div class="card border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark py-2">
                    <h6 class="mb-0 small">
                        <i class="fas fa-edit me-1"></i>Formulario de <?php echo strtolower($etiqueta_equipo); ?>
                        <span class="d-block mt-1 fw-semibold">Inscripción <?php echo strtolower($etiqueta_equipos); ?> de <?php echo (int)$jugadores_por_equipo; ?> jugadores</span>
                        <span class="text-muted fw-normal" style="font-size:0.75rem;">Clic en «Editar» en <?php echo strtolower($etiqueta_equipos); ?> inscritos para cargar y editar</span>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($torneo_iniciado): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            El torneo ya inició (hay rondas generadas). No se permiten nuevas inscripciones de <?php echo strtolower($etiqueta_equipos); ?>. Solo información de control.
                        </div>
                    <?php endif; ?>
                    <form id="formEquipo">
                        <?php require_once __DIR__ . '/../../config/csrf.php'; ?>
                        <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                        <input type="hidden" id="equipo_id" name="equipo_id" value="">
                        <input type="hidden" id="torneo_id" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                        <input type="hidden" id="codigo_equipo" name="codigo_equipo" value="">
                        
                        <!-- Club y nombre equipo: misma línea, mismo alto -->
                        <div class="fila-club-nombre-equipo">
                            <div class="campo-club">
                                <label class="form-label small mb-0" for="club_id">Club *</label>
                                <select id="club_id" name="club_id" class="form-select form-select-sm w-100" required>
                                    <option value="">Club *</option>
                                    <?php if (!empty($clubes_disponibles)): ?>
                                        <?php foreach ($clubes_disponibles as $club): ?>
                                            <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay clubes disponibles</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="campo-nombre-equipo">
                                <label class="form-label small mb-0" for="nombre_equipo">Nombre de la <?php echo strtolower($etiqueta_equipo); ?><?php echo $es_parejas ? ' (opcional)' : ' *'; ?></label>
                                <input type="text"
                                       id="nombre_equipo"
                                       name="nombre_equipo"
                                       class="form-control form-control-sm w-100"
                                       <?php echo $es_parejas ? '' : 'required '; ?>
                                       placeholder="<?php echo $es_parejas ? 'Opcional (sin nombre)' : 'Nombre del ' . $etiqueta_equipo . ' *'; ?>">
                            </div>
                        </div>
                        <?php if (empty($clubes_disponibles) && !empty($is_admin_club ?? false)): ?>
                            <small class="text-muted d-block mb-2">
                                <a href="<?php echo (function_exists('AppHelpers') ? AppHelpers::dashboard('clubes_asociados') : 'index.php?page=clubes_asociados'); ?>">Crear club</a> en Clubes de la organización
                            </small>
                        <?php endif; ?>
                        
                        <hr class="my-1">
                        
                        <!-- Jugadores + barra: código (solo edición) | guardar -->
                        <div class="mb-1 flex-shrink-0">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-1">
                                <div id="wrap_codigo_equipo_barra" class="d-flex align-items-center gap-2" style="visibility:hidden;" aria-hidden="true">
                                    <span class="small text-muted fw-bold mb-0">Código</span>
                                    <span id="codigo_equipo_visible" class="badge bg-secondary fs-6 px-2 py-1"></span>
                                </div>
                                <div class="d-flex gap-2 ms-auto">
                                    <button type="submit" class="btn btn-success btn-sm py-1" id="btnGuardarEquipo" <?= $torneo_iniciado ? 'disabled' : '' ?>>
                                        <i class="fas fa-save me-1"></i>Guardar <?php echo $etiqueta_equipo; ?>
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm py-1" onclick="limpiarFormulario()" <?= $torneo_iniciado ? 'disabled' : '' ?>>
                                        <i class="fas fa-redo me-1"></i>Nueva <?php echo $etiqueta_equipo; ?>
                                    </button>
                                </div>
                            </div>
                            <div id="jugadores-container">
                                <?php for ($i = 1; $i <= $jugadores_por_equipo; $i++): ?>
                                    <div class="row g-1 align-items-center fila-jugador-compacta" data-posicion="<?php echo $i; ?>" data-jugador-asignado="">
                                        <div class="col-auto text-center pe-0" style="width:1.5rem;">
                                            <?php if ($i == 1): ?>
                                                <span class="badge bg-warning text-dark" style="font-size:0.65rem;">★</span>
                                            <?php else: ?>
                                                <span class="small"><?php echo $i; ?></span>
                                            <?php endif; ?>
                                            <input type="hidden" 
                                                   id="es_capitan_<?php echo $i; ?>" 
                                                   name="jugadores[<?php echo $i; ?>][es_capitan]" 
                                                   value="<?php echo $i == 1 ? '1' : '0'; ?>">
                                        </div>
                                        <div class="col px-1 min-w-0 wrap-inputs-jugador flex-shrink-0">
                                            <input type="text" 
                                                   class="form-control form-control-sm jugador-id-usuario input-id-usuario" 
                                                   id="jugador_id_usuario_<?php echo $i; ?>" 
                                                   placeholder="ID"
                                                   readonly
                                                   style="background-color: #e9ecef; font-weight: bold;">
                                            <input type="hidden" 
                                                   id="jugador_id_usuario_h_<?php echo $i; ?>" 
                                                   name="jugadores[<?php echo $i; ?>][id_usuario]">
                                            <input type="text" 
                                                   class="form-control form-control-sm jugador-cedula input-cedula" 
                                                   id="jugador_cedula_<?php echo $i; ?>" 
                                                   name="jugadores[<?php echo $i; ?>][cedula]" 
                                                   placeholder="<?php echo $es_parejas ? 'Cédula (salir del campo para buscar)' : 'Céd.'; ?>"
                                                   data-posicion="<?php echo $i; ?>"
                                                   onblur="buscarJugadorPorCedula(this)"
                                                   oninput="validarFormulario()"
                                                   <?php echo $es_parejas ? '' : 'readonly '; ?>
                                                   style="background-color: <?php echo $es_parejas ? '#fff' : '#f1f1f1'; ?>;">
                                            <input type="hidden" 
                                                   class="jugador-id-inscrito" 
                                                   id="jugador_id_inscrito_<?php echo $i; ?>" 
                                                   name="jugadores[<?php echo $i; ?>][id_inscrito]">
                                            <input type="text" 
                                                   class="form-control form-control-sm jugador-nombre input-nombre-jug" 
                                                   id="jugador_nombre_<?php echo $i; ?>" 
                                                   name="jugadores[<?php echo $i; ?>][nombre]" 
                                                   placeholder="Nombre"
                                                   readonly
                                                   style="background-color: #e9ecef;"
                                                   oninput="validarFormulario()">
                                        </div>
                                        <div class="col-auto ps-0">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger py-0 px-1" 
                                                    onclick="limpiarJugadorYDevolver(<?php echo $i; ?>)"
                                                    title="Quitar"
                                                    id="btn_limpiar_<?php echo $i; ?>"
                                                    style="display: none; font-size:0.7rem;"
                                                    disabled>
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php if ($i < $jugadores_por_equipo): ?>
                                        <div class="separador-jugador mb-1" style="margin-top:0;"></div>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- COLUMNA 3: Equipos inscritos -->
        <div class="col-12 col-lg-4 col-insc-equipos">
            <div class="card border-0 shadow-sm equipo-sidebar-card h-100">
                <div class="card-header bg-success text-white py-2">
                    <h6 class="mb-0">
                        <i class="fas fa-users me-1"></i><?php echo $etiqueta_equipos; ?> inscritos (<?php echo count($equipos_registrados); ?>)
                    </h6>
                    <small class="opacity-75 fw-bold">Clic en la fila: mostrar / ocultar integrantes · «Editar» carga el formulario</small>
                </div>
                <div class="card-body p-2">
                    <?php if (empty($equipos_registrados)): ?>
                        <div class="text-center py-3 text-muted small">
                            <i class="fas fa-users-slash fa-2x mb-2 opacity-50"></i>
                            <p class="mb-0">Aún no hay <?php echo strtolower($etiqueta_equipos); ?></p>
                        </div>
                    <?php else: ?>
                        <div id="listaEquiposRegistrados">
                            <?php foreach ($equipos_registrados as $equipo):
                                $eid = (int)$equipo['id'];
                                $jugEq = $equipo['jugadores'] ?? [];
                                $collapseId = 'int-equipo-' . $eid;
                            ?>
                            <div class="equipo-sidebar-item equipo-registrado-item" data-equipo-id="<?php echo $eid; ?>">
                                <div class="equipo-sidebar-header d-flex align-items-center justify-content-between gap-1 flex-wrap"
                                     role="button" tabindex="0"
                                     data-collapse-target="<?php echo htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>"
                                     aria-expanded="false" aria-controls="<?php echo $collapseId; ?>">
                                    <div class="flex-grow-1 min-w-0">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($equipo['codigo_equipo']); ?></span>
                                        <span class="fw-semibold text-primary"><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></span>
                                        <div class="text-muted" style="font-size:0.72rem;"><?php echo htmlspecialchars($equipo['nombre_club'] ?? ''); ?></div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-editar-equipo-form"
                                                onclick="event.stopPropagation(); cargarEquipo(<?php echo $eid; ?>); document.getElementById('formEquipo').scrollIntoView({behavior:'smooth'});"
                                                title="Cargar en formulario para editar">Editar</button>
                                        <span class="btn btn-sm btn-outline-secondary py-0 px-1 mb-0 integrantes-chevron" title="Mostrar/ocultar integrantes">
                                            <i class="fas fa-chevron-down small"></i>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                                                onclick="event.stopPropagation(); eliminarEquipo(<?php echo $eid; ?>, '<?php echo htmlspecialchars($equipo['nombre_equipo'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="collapse integrantes-collapse" id="<?php echo $collapseId; ?>">
                                    <ul class="list-unstyled mb-0 equipo-sidebar-integrantes">
                                        <?php if (empty($jugEq)): ?>
                                            <li class="text-muted">Sin jugadores en lista</li>
                                        <?php else: ?>
                                            <?php foreach ($jugEq as $j): ?>
                                                <li>
                                                    <span class="text-muted"><?php echo htmlspecialchars($j['cedula'] ?? ''); ?></span>
                                                    — <?php echo htmlspecialchars($j['nombre'] ?? ''); ?>
                                                    <span class="badge bg-light text-dark" style="font-size:0.65rem;">#<?php echo (int)($j['id_usuario'] ?? 0); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
<script>
const JUGADORES_POR_EQUIPO = <?php echo $jugadores_por_equipo; ?>;
const ES_PAREJAS = <?php echo $es_parejas ? 'true' : 'false'; ?>;
const TORNEO_ID = <?php echo $torneo['id']; ?>;
const JUGADORES_LISTA_LAZY = <?php echo $jugadores_lista_lazy ? 'true' : 'false'; ?>;
/** Datos para editar: todo viene del servidor al cargar la página — sin fetch a obtener_equipo */
const EQUIPOS_EDITAR = <?php
$map = [];
foreach ($equipos_registrados as $eq) {
    $id = (int)($eq['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $map[(string)$id] = [
        'id' => $id,
        'codigo_equipo' => $eq['codigo_equipo'] ?? '',
        'nombre_equipo' => $eq['nombre_equipo'] ?? '',
        'id_club' => (int)($eq['id_club'] ?? 0),
        'club_nombre' => $eq['nombre_club'] ?? 'Sin Club',
        'jugadores' => array_values(array_map(static function ($j) {
            return [
                'id_inscrito' => (int)($j['id_inscrito'] ?? 0),
                'id_usuario' => (int)($j['id_usuario'] ?? 0),
                'cedula' => (string)($j['cedula'] ?? ''),
                'nombre' => (string)($j['nombre'] ?? ''),
            ];
        }, $eq['jugadores'] ?? [])),
    ];
}
echo json_encode($map, JSON_UNESCAPED_UNICODE);
?>;

// Validar formulario al cargar
document.addEventListener('DOMContentLoaded', function() {
    validarFormulario();
    actualizarBloqueoSeleccionJugadores();
    var cedulaBuscarParejas = document.getElementById('cedula_buscar_parejas');
    if (cedulaBuscarParejas) {
        cedulaBuscarParejas.addEventListener('blur', buscarCedulaParejasGlobal);
    }
    /* Integrantes: despliegue/repliegue manual (evita fallos del toggle en cabecera con botones) */
    (function initToggleIntegrantes() {
        var lista = document.getElementById('listaEquiposRegistrados');
        if (!lista || typeof bootstrap === 'undefined') return;
        function chevron(header, abajo) {
            var i = header && header.querySelector('.integrantes-chevron i');
            if (!i) return;
            i.classList.remove('fa-chevron-down', 'fa-chevron-up');
            i.classList.add(abajo ? 'fa-chevron-down' : 'fa-chevron-up');
        }
        function syncAria(header, collapseEl) {
            var open = collapseEl.classList.contains('show');
            header.setAttribute('aria-expanded', open ? 'true' : 'false');
            chevron(header, !open);
        }
        lista.querySelectorAll('.integrantes-collapse').forEach(function (collapseEl) {
            var header = collapseEl.previousElementSibling;
            if (!header || !header.classList.contains('equipo-sidebar-header')) return;
            collapseEl.addEventListener('shown.bs.collapse', function () { syncAria(header, collapseEl); });
            collapseEl.addEventListener('hidden.bs.collapse', function () { syncAria(header, collapseEl); });
        });
        lista.addEventListener('click', function (e) {
            if (e.target.closest('.btn-editar-equipo-form')) return;
            if (e.target.closest('button.btn-outline-danger')) return;
            var header = e.target.closest('.equipo-sidebar-header');
            if (!header || !lista.contains(header)) return;
            var id = header.getAttribute('data-collapse-target');
            if (!id) return;
            var collapseEl = document.getElementById(id);
            if (!collapseEl) return;
            var inst = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
            inst.toggle();
        });
        lista.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var header = e.target.closest('.equipo-sidebar-header');
            if (!header || e.target.closest('button')) return;
            e.preventDefault();
            var id = header.getAttribute('data-collapse-target');
            var collapseEl = id && document.getElementById(id);
            if (collapseEl) bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).toggle();
        });
    })();
    document.getElementById('nombre_equipo').addEventListener('input', () => {
        validarFormulario();
        actualizarBloqueoSeleccionJugadores();
    });
    document.getElementById('club_id').addEventListener('change', () => {
        validarFormulario();
        actualizarBloqueoSeleccionJugadores();
    });
});

// Búsqueda en tiempo real
document.getElementById('searchJugadores')?.addEventListener('input', function(e) {
    if (!puedeSeleccionarJugadores()) {
        e.target.value = '';
        return;
    }
    const searchTerm = e.target.value.toLowerCase();
    const items = document.querySelectorAll('.jugador-item');
    
    items.forEach(item => {
        const nombre = item.getAttribute('data-nombre') || '';
        const cedula = item.getAttribute('data-cedula') || '';
        const idUsuario = (item.getAttribute('data-id-usuario') || '').toString();
        
        if (nombre.includes(searchTerm) || cedula.includes(searchTerm) || idUsuario.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});

// Seleccionar jugador desde la lista
function seleccionarJugador(element) {
    if (!puedeSeleccionarJugadores()) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: ES_PAREJAS ? 'Primero seleccione el Club.' : 'Primero seleccione el Club y el Nombre del Equipo.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    const jugadorData = JSON.parse(element.getAttribute('data-jugador'));
    
    // Verificar que no esté jugando (ya tiene codigo_equipo)
    if (jugadorData.codigo_equipo) {
        Swal.fire({
            icon: 'warning',
            title: 'Jugador no disponible',
            text: 'Este jugador ya está asignado a un ' + (ES_PAREJAS ? 'pareja' : 'equipo') + ' (código: ' + jugadorData.codigo_equipo + ')',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    // Buscar primera posición vacía
    for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
        const cedula = document.getElementById(`jugador_cedula_${i}`).value.trim();
        if (!cedula) {
            asignarJugadorAPosicion(i, jugadorData);
            element.remove();
            actualizarContadorDisponibles();
            return;
        }
    }
    
    Swal.fire({
        icon: 'info',
        title: 'Posiciones completas',
        text: 'Todas las posiciones están ocupadas. Use el botón X para quitar un jugador.',
        confirmButtonColor: '#3b82f6'
    });
}

// Asignar jugador a una posición
function asignarJugadorAPosicion(posicion, jugador) {
    const idInscritoEl = document.getElementById(`jugador_id_inscrito_${posicion}`);
    if (idInscritoEl) idInscritoEl.value = jugador.id_inscrito || jugador.id || '';
    
    const idUsuarioEl = document.getElementById(`jugador_id_usuario_${posicion}`);
    const idUsuarioHEl = document.getElementById(`jugador_id_usuario_h_${posicion}`);
    const idUsuario = jugador.id_usuario || '';
    if (idUsuarioEl) idUsuarioEl.value = idUsuario;
    if (idUsuarioHEl) idUsuarioHEl.value = idUsuario;
    
    document.getElementById(`jugador_cedula_${posicion}`).value = jugador.cedula || '';
    document.getElementById(`jugador_nombre_${posicion}`).value = jugador.nombre || '';
    
    const fila = document.querySelector(`[data-posicion="${posicion}"]`);
    if (fila) {
        fila.setAttribute('data-jugador-asignado', JSON.stringify(jugador));
        const btnLimpiar = document.getElementById(`btn_limpiar_${posicion}`);
        if (btnLimpiar) btnLimpiar.style.display = 'inline-block';
    }
    
    validarFormulario();
    actualizarBloqueoSeleccionJugadores();
}

// Limpiar jugador y devolverlo al listado
async function limpiarJugadorYDevolver(posicion) {
    const fila = document.querySelector(`[data-posicion="${posicion}"]`);
    const jugadorDataStr = fila ? fila.getAttribute('data-jugador-asignado') : null;
    
    // Obtener nombre del jugador para mostrar en la confirmación
    const nombreJugador = document.getElementById(`jugador_nombre_${posicion}`)?.value || '';
    const cedulaJugador = document.getElementById(`jugador_cedula_${posicion}`)?.value || '';
    const jugadorTexto = nombreJugador ? `"${nombreJugador}"` : (cedulaJugador ? `con cédula ${cedulaJugador}` : 'este jugador');
    
    // Confirmar antes de retirar
    const result = await Swal.fire({
        icon: 'question',
        title: '¿Retirar jugador?',
        html: `¿Está seguro de retirar ${jugadorTexto} del equipo?<br><br>El jugador quedará disponible para asignarlo a otra posición.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, retirar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    });
    
    // Si el usuario cancela, no hacer nada
    if (!result.isConfirmed) {
        return;
    }
    
    // Ejecutar la acción de retirar
    limpiarJugador(posicion);
    
    if (jugadorDataStr) {
        try {
            const jugador = JSON.parse(jugadorDataStr);
            devolverJugadorAListado(jugador);
            fila.setAttribute('data-jugador-asignado', '');
        } catch (e) {
            console.error('Error al parsear jugador:', e);
        }
    }
    
    const btnLimpiar = document.getElementById(`btn_limpiar_${posicion}`);
    if (btnLimpiar) btnLimpiar.style.display = 'none';
    
    // Mostrar mensaje de confirmación de éxito
    Swal.fire({
        icon: 'success',
        title: 'Jugador retirado',
        text: 'El jugador ha sido retirado del equipo y está disponible para asignación.',
        confirmButtonColor: '#10b981',
        timer: 2000,
        timerProgressBar: true
    });
}

// Devolver jugador al listado
function devolverJugadorAListado(jugador) {
    const listaJugadores = document.getElementById('listaJugadores');
    if (!listaJugadores) return;
    
    const ready = puedeSeleccionarJugadores();
    const jugadorHtml = `
        <div class="jugador-item" 
             data-nombre="${(jugador.nombre || '').toLowerCase()}"
             data-cedula="${jugador.cedula || ''}"
             data-id-usuario="${jugador.id_usuario || ''}"
             data-id="${jugador.id || ''}"
             data-jugador='${JSON.stringify(jugador).replace(/'/g, "&#39;")}'
             onclick="seleccionarJugador(this)"
             style="cursor: ${ready ? 'pointer' : 'not-allowed'}; pointer-events: ${ready ? 'auto' : 'none'}; opacity: ${ready ? '1' : '0.6'};">
            <div class="small">
                <span class="text-muted fw-bold">${jugador.id_usuario || '-'}</span>
                <span class="mx-1">|</span>
                <span class="text-muted">${jugador.cedula || 'Sin cédula'}</span>
                <span class="mx-1">|</span>
                <span class="text-dark">${jugador.nombre || 'Sin nombre'}</span>
            </div>
        </div>
    `;
    
    listaJugadores.insertAdjacentHTML('beforeend', jugadorHtml);
    actualizarContadorDisponibles();
    // Asegurar que el bloqueo se actualice después de agregar
    actualizarBloqueoSeleccionJugadores();
}

// Actualizar contador
function actualizarContadorDisponibles() {
    const numItems = document.querySelectorAll('#listaJugadores .jugador-item').length;
}

/** Admin general (lista lazy): API + añade fila en #listaJugadores (mismo flujo que admin club). */
async function buscarCedulaLazyAnadir() {
    if (!JUGADORES_LISTA_LAZY) return;
    if (!puedeSeleccionarJugadores()) {
        Swal.fire({ icon: 'warning', title: 'Atención', text: 'Indique Club y nombre del equipo primero.', confirmButtonColor: '#3b82f6' });
        return;
    }
    const input = document.getElementById('buscarCedulaLazy');
    if (!input) return;
    const cedula = (input.value || '').trim();
    if (!cedula) {
        Swal.fire({ icon: 'info', title: 'Cédula', text: 'Escriba la cédula del jugador.', confirmButtonColor: '#3b82f6' });
        return;
    }
    try {
        const response = await fetch(`<?php echo $api_base_path; ?>buscar_jugador_inscripcion.php?cedula=${encodeURIComponent(cedula)}&torneo_id=${TORNEO_ID}`);
        const data = await response.json();
        if (!data.success || !data.jugador) {
            Swal.fire({ icon: 'error', title: 'No encontrado', text: data.message || 'Jugador no disponible', confirmButtonColor: '#3b82f6' });
            return;
        }
        if (data.jugador.codigo_equipo) {
            Swal.fire({ icon: 'warning', title: 'No disponible', text: 'Ya está en un equipo: ' + data.jugador.codigo_equipo, confirmButtonColor: '#3b82f6' });
            return;
        }
        let dup = false;
        document.querySelectorAll('#listaJugadores .jugador-item').forEach(function (el) {
            if (el.getAttribute('data-cedula') === cedula) dup = true;
        });
        if (dup) {
            Swal.fire({ icon: 'info', title: 'Ya en lista', text: 'Ese jugador ya está en disponibles.', confirmButtonColor: '#3b82f6' });
            return;
        }
        data.jugador.id = data.jugador.id_inscrito ?? data.jugador.id ?? null;
        devolverJugadorAListado(data.jugador);
        input.value = '';
        actualizarContadorDisponibles();
    } catch (e) {
        console.error(e);
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo consultar la cédula.', confirmButtonColor: '#3b82f6' });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    if (!JUGADORES_LISTA_LAZY) return;
    const btn = document.getElementById('btnBuscarCedulaLazy');
    const inp = document.getElementById('buscarCedulaLazy');
    if (btn) btn.addEventListener('click', function () { buscarCedulaLazyAnadir(); });
    if (inp) inp.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') { ev.preventDefault(); buscarCedulaLazyAnadir(); }
    });
});

// Buscar jugador por cédula
async function buscarJugadorPorCedula(input) {
    const cedula = input.value.trim();
    const posicion = input.getAttribute('data-posicion');
    
    if (!puedeSeleccionarJugadores()) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: ES_PAREJAS ? 'Primero seleccione el Club.' : 'Primero seleccione el Club y el Nombre del Equipo.',
            confirmButtonColor: '#3b82f6'
        });
        input.value = '';
        return;
    }
    
    if (!cedula) {
        limpiarJugador(posicion);
        return;
    }
    
    try {
        const response = await fetch(`<?php echo $api_base_path; ?>buscar_jugador_inscripcion.php?cedula=${encodeURIComponent(cedula)}&torneo_id=${TORNEO_ID}`);
        const data = await response.json();
        
        if (data.success && data.jugador) {
            // Verificar que no esté jugando
            if (data.jugador.codigo_equipo) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Jugador no disponible',
                    text: 'Este jugador ya está asignado a un equipo (código: ' + data.jugador.codigo_equipo + ')',
                    confirmButtonColor: '#3b82f6'
                });
                limpiarJugador(posicion);
                return;
            }
            asignarJugadorAPosicion(posicion, data.jugador);
            // Quitar de la lista si está
            const items = document.querySelectorAll('.jugador-item');
            items.forEach(item => {
                const itemCedula = item.getAttribute('data-cedula');
                if (itemCedula === cedula) {
                    item.remove();
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Jugador no encontrado',
                text: data.message || 'Jugador no encontrado o ya está inscrito en un equipo',
                confirmButtonColor: '#3b82f6'
            });
            limpiarJugador(posicion);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al buscar jugador por cédula',
            confirmButtonColor: '#3b82f6'
        });
        limpiarJugador(posicion);
    }
}

// Cédula a buscar (campo global parejas): blur = buscar y asignar a primera posición vacía
async function buscarCedulaParejasGlobal() {
    const input = document.getElementById('cedula_buscar_parejas');
    if (!input) return;
    const cedula = input.value.trim();
    if (!cedula) return;
    if (!puedeSeleccionarJugadores()) {
        Swal.fire({ icon: 'warning', title: 'Atención', text: 'Primero seleccione el Club.', confirmButtonColor: '#3b82f6' });
        return;
    }
    try {
        const response = await fetch(`<?php echo $api_base_path; ?>buscar_jugador_inscripcion.php?cedula=${encodeURIComponent(cedula)}&torneo_id=${TORNEO_ID}`);
        const data = await response.json();
        if (data.success && data.jugador) {
            if (data.jugador.codigo_equipo) {
                Swal.fire({ icon: 'warning', title: 'No disponible', text: 'Ya está asignado (código: ' + data.jugador.codigo_equipo + ')', confirmButtonColor: '#3b82f6' });
                input.value = '';
                return;
            }
            for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
                const cedulaEl = document.getElementById('jugador_cedula_' + i);
                if (cedulaEl && !cedulaEl.value.trim()) {
                    asignarJugadorAPosicion(String(i), data.jugador);
                    input.value = '';
                    validarFormulario();
                    return;
                }
            }
            Swal.fire({ icon: 'info', title: 'Completo', text: 'Las dos posiciones ya tienen jugador.', confirmButtonColor: '#3b82f6' });
        } else {
            Swal.fire({ icon: 'error', title: 'No encontrado', text: data.message || 'Verifique la cédula.', confirmButtonColor: '#3b82f6' });
        }
        input.value = '';
    } catch (e) {
        console.error(e);
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo buscar.', confirmButtonColor: '#3b82f6' });
        input.value = '';
    }
}

// Limpiar jugador
function limpiarJugador(posicion) {
    const idInscritoEl = document.getElementById(`jugador_id_inscrito_${posicion}`);
    const idUsuarioEl = document.getElementById(`jugador_id_usuario_${posicion}`);
    const idUsuarioHEl = document.getElementById(`jugador_id_usuario_h_${posicion}`);
    const cedulaEl = document.getElementById(`jugador_cedula_${posicion}`);
    const nombreEl = document.getElementById(`jugador_nombre_${posicion}`);
    const btnLimpiar = document.getElementById(`btn_limpiar_${posicion}`);
    
    if (idInscritoEl) idInscritoEl.value = '';
    if (idUsuarioEl) idUsuarioEl.value = '';
    if (idUsuarioHEl) idUsuarioHEl.value = '';
    if (cedulaEl) cedulaEl.value = '';
    if (nombreEl) nombreEl.value = '';
    if (btnLimpiar) btnLimpiar.style.display = 'none';
    
    const fila = document.querySelector(`[data-posicion="${posicion}"]`);
    if (fila) fila.setAttribute('data-jugador-asignado', '');
    
    validarFormulario();
    actualizarBloqueoSeleccionJugadores();
}

// Validar formulario
function validarFormulario() {
    let jugadoresCompletos = 0;
    
    for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
        const cedula = document.getElementById(`jugador_cedula_${i}`).value.trim();
        const nombre = document.getElementById(`jugador_nombre_${i}`).value.trim();
        
        if (cedula && nombre) {
            jugadoresCompletos++;
        }
    }
    
    const nombreEquipo = document.getElementById('nombre_equipo').value.trim();
    const clubId = document.getElementById('club_id').value;
    const nombreOk = ES_PAREJAS ? true : nombreEquipo;
    
    const btnGuardar = document.getElementById('btnGuardarEquipo');
    
    if (jugadoresCompletos === JUGADORES_POR_EQUIPO && nombreOk && clubId) {
        btnGuardar.disabled = false;
        btnGuardar.classList.remove('btn-secondary');
        btnGuardar.classList.add('btn-success');
    } else {
        btnGuardar.disabled = true;
        btnGuardar.classList.remove('btn-success');
        btnGuardar.classList.add('btn-secondary');
    }
}

// Bloqueo/Desbloqueo
function puedeSeleccionarJugadores() {
    const nombreEquipo = document.getElementById('nombre_equipo').value.trim();
    const clubId = document.getElementById('club_id').value;
    return !!(clubId && (ES_PAREJAS || nombreEquipo));
}

function actualizarBloqueoSeleccionJugadores() {
    const ready = puedeSeleccionarJugadores();

    const searchInput = document.getElementById('searchJugadores');
    if (searchInput && !searchInput.classList.contains('d-none')) {
        searchInput.disabled = !ready;
        if (!ready) searchInput.value = '';
    }
    const lazyCed = document.getElementById('buscarCedulaLazy');
    const lazyBtn = document.getElementById('btnBuscarCedulaLazy');
    if (lazyCed) {
        lazyCed.disabled = !ready;
        if (!ready) lazyCed.value = '';
    }
    if (lazyBtn) lazyBtn.disabled = !ready;
    
    // Actualizar contenedor y cada item individual
    const lista = document.getElementById('listaJugadores');
    if (lista) {
        lista.style.pointerEvents = ready ? 'auto' : 'none';
        lista.style.opacity = ready ? '1' : '0.6';
    }
    
    // Actualizar cada item de jugador individual
    const items = document.querySelectorAll('.jugador-item');
    items.forEach(item => {
        if (ready) {
            item.style.pointerEvents = 'auto';
            item.style.opacity = '1';
            item.style.cursor = 'pointer';
        } else {
            item.style.pointerEvents = 'none';
            item.style.opacity = '0.6';
            item.style.cursor = 'not-allowed';
        }
    });
    
    const editandoEquipo = parseInt(document.getElementById('equipo_id').value || '0', 10) > 0;
    for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
        const cedulaEl = document.getElementById(`jugador_cedula_${i}`);
        const limpiarBtn = document.getElementById(`btn_limpiar_${i}`);
        const filaTieneJugador = cedulaEl && cedulaEl.value.trim() !== '';
        const puedeFila = ready || editandoEquipo;
        if (cedulaEl) {
            cedulaEl.readOnly = !puedeFila;
            cedulaEl.style.backgroundColor = puedeFila ? '' : '#f1f1f1';
        }
        if (limpiarBtn) {
            limpiarBtn.disabled = !filaTieneJugador;
        }
    }
}

// Limpiar formulario
function limpiarFormulario() {
    // Devolver todos los jugadores asignados a la lista
    for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
        const fila = document.querySelector(`[data-posicion="${i}"]`);
        const jugadorDataStr = fila ? fila.getAttribute('data-jugador-asignado') : null;
        if (jugadorDataStr) {
            try {
                const jugador = JSON.parse(jugadorDataStr);
                devolverJugadorAListado(jugador);
            } catch (e) {}
        }
        limpiarJugador(i);
    }
    
    document.getElementById('formEquipo').reset();
    document.getElementById('equipo_id').value = '';
    document.getElementById('codigo_equipo').value = '';
    var barraCod = document.getElementById('wrap_codigo_equipo_barra');
    var codVis = document.getElementById('codigo_equipo_visible');
    if (barraCod) { barraCod.style.visibility = 'hidden'; barraCod.setAttribute('aria-hidden', 'true'); }
    if (codVis) { codVis.textContent = ''; }
    
    // Limpiar selección visual de equipo
    document.querySelectorAll('.equipo-registrado-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    validarFormulario();
    actualizarBloqueoSeleccionJugadores();
}

// Guardar equipo
document.getElementById('formEquipo').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    console.log('=== INICIO GUARDAR EQUIPO (JavaScript) ===');
    
    if (!puedeSeleccionarJugadores()) {
        console.log('ERROR: Validación falló - falta Club' + (ES_PAREJAS ? '' : ' o Nombre del Equipo'));
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: ES_PAREJAS ? 'Primero seleccione el Club.' : 'Primero seleccione el Club y el Nombre del Equipo.',
            confirmButtonColor: '#3b82f6'
        });
        return;
    }
    
    const form = this;
    const formData = new FormData();
    
    const equipo_id = document.getElementById('equipo_id').value || '';
    const torneo_id = document.getElementById('torneo_id').value || '';
    const nombre_equipo = document.getElementById('nombre_equipo').value || '';
    const club_id = document.getElementById('club_id').value || '';
    
    console.log('Datos del equipo:', { equipo_id, torneo_id, nombre_equipo, club_id });
    
    formData.append('csrf_token', form.querySelector('input[name="csrf_token"]')?.value || '');
    formData.append('equipo_id', equipo_id);
    formData.append('torneo_id', torneo_id);
    formData.append('nombre_equipo', nombre_equipo);
    formData.append('club_id', club_id);
    
    let posicionJugador = 1;
    const jugadoresEnviados = [];
    for (let i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
        const cedula = document.getElementById(`jugador_cedula_${i}`).value.trim();
        const nombre = document.getElementById(`jugador_nombre_${i}`).value.trim();
        
        if (cedula && nombre) {
            const id_inscritoEl = document.getElementById(`jugador_id_inscrito_${i}`);
            const id_inscrito = id_inscritoEl ? id_inscritoEl.value : '';
            const id_usuario_hel = document.getElementById(`jugador_id_usuario_h_${i}`);
            const id_usuario = id_usuario_hel ? id_usuario_hel.value : '';
            const es_capitan = document.getElementById(`es_capitan_${i}`)?.value == '1' ? 1 : 0;
            
            const jugadorData = { cedula, nombre, id_inscrito, id_usuario, es_capitan, posicion: i };
            jugadoresEnviados.push(jugadorData);
            console.log(`Jugador ${posicionJugador} (posición ${i}):`, jugadorData);
            
            formData.append(`jugadores[${posicionJugador}][cedula]`, cedula);
            formData.append(`jugadores[${posicionJugador}][nombre]`, nombre);
            formData.append(`jugadores[${posicionJugador}][id_inscrito]`, id_inscrito || '');
            formData.append(`jugadores[${posicionJugador}][id_usuario]`, id_usuario || '');
            formData.append(`jugadores[${posicionJugador}][es_capitan]`, es_capitan);
            posicionJugador++;
        }
    }
    
    console.log('Total de jugadores a enviar:', jugadoresEnviados.length);
    var _urlGuardar = <?php echo json_encode($api_guardar_equipo); ?>;
    console.log('[Inscribir equipo] POST a:', _urlGuardar);
    if (_urlGuardar.indexOf('guardar_equipo_sitio') === -1) {
        console.error('ERROR: Debe usar action=guardar_equipo_sitio (index/admin). Sube inscribir_equipo_sitio.php y torneo_gestion.php.');
    }
    try {
        const response = await fetch(_urlGuardar, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        console.log('Respuesta recibida, status:', response.status);
        console.log('Content-Type:', response.headers.get('content-type'));
        
        // Obtener el texto de la respuesta primero
        const responseText = await response.text();
        console.log('Respuesta completa (primeros 500 caracteres):', responseText.substring(0, 500));
        
        // Intentar parsear como JSON
        let data;
        try {
            data = JSON.parse(responseText);
            console.log('Datos de respuesta (JSON parseado):', data);
        } catch (parseError) {
            console.error('=== ERROR: La respuesta no es JSON válido ===');
            console.error('Error de parseo:', parseError);
            console.error('Respuesta completa:', responseText);
            
            // Si la respuesta contiene HTML (página de error), intentar extraer el mensaje
            if (responseText.includes('<!DOCTYPE') || responseText.includes('<html')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error del servidor',
                    html: 'El servidor devolvió una página de error HTML. Revisa la consola para más detalles.<br><br>Verifica los logs de PHP en el servidor.',
                    confirmButtonColor: '#3b82f6'
                });
                console.error('HTML recibido en lugar de JSON - probablemente un error de PHP');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de respuesta',
                    html: 'Respuesta del servidor no válida. Revisa la consola para más detalles.<br><br>' + responseText.substring(0, 200),
                    confirmButtonColor: '#3b82f6'
                });
            }
            return;
        }
        
        if (data.success) {
            console.log('=== ÉXITO: Equipo guardado correctamente ===');
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: data.message || 'Equipo guardado exitosamente',
                confirmButtonColor: '#10b981',
                timer: 2000,
                timerProgressBar: true
            }).then(() => {
                location.reload();
            });
        } else {
            console.error('=== ERROR: ' + (data.message || 'Error al guardar el equipo') + ' ===');
            console.error('Detalles del error:', data);
            var isCsrf = (data.error_type === 'CSRF_INVALID');
            Swal.fire({
                icon: 'error',
                title: isCsrf ? 'Token de seguridad expirado' : 'Error al guardar',
                text: data.message || 'Error al guardar el equipo',
                confirmButtonColor: '#3b82f6',
                showCancelButton: isCsrf,
                cancelButtonText: 'Cerrar',
                confirmButtonText: isCsrf ? 'Recargar página' : 'Entendido'
            }).then(function(result) {
                if (isCsrf && result.isConfirmed) {
                    location.reload();
                }
            });
        }
    } catch (error) {
        console.error('=== ERROR en fetch: ===', error);
        console.error('Stack trace:', error.stack);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            html: 'Error al guardar el equipo: ' + error.message + '<br><br>Revisa la consola para más detalles.',
            confirmButtonColor: '#3b82f6'
        });
    }
});

// Editar equipo: solo lectura de EQUIPOS_EDITAR (misma carga de página — cero APIs)
function cargarEquipo(equipoId) {
    const equipo = EQUIPOS_EDITAR[String(equipoId)] || EQUIPOS_EDITAR[equipoId];
    if (!equipo) {
        Swal.fire({ icon: 'info', title: 'Recargar', text: 'No hay datos en memoria para este equipo. Recarga la página (F5).', confirmButtonColor: '#3b82f6' });
        return;
    }
    document.querySelectorAll('.equipo-registrado-item').forEach(function (item) { item.classList.remove('selected'); });
    var el = document.querySelector('[data-equipo-id="' + equipoId + '"]');
    if (el) {
        el.classList.add('selected');
    }
    document.getElementById('equipo_id').value = equipo.id;
    document.getElementById('codigo_equipo').value = equipo.codigo_equipo || '';
    var barraCod = document.getElementById('wrap_codigo_equipo_barra');
    var codVis = document.getElementById('codigo_equipo_visible');
    if (barraCod && codVis) {
        barraCod.style.visibility = 'visible';
        barraCod.setAttribute('aria-hidden', 'false');
        codVis.textContent = equipo.codigo_equipo || '—';
    }
    document.getElementById('nombre_equipo').value = equipo.nombre_equipo || '';
    var selClub = document.getElementById('club_id');
    var idClub = (equipo.id_club !== undefined && equipo.id_club !== null) ? String(equipo.id_club) : '';
    if (idClub && selClub) {
        var opt = selClub.querySelector('option[value="' + idClub.replace(/"/g, '\\"') + '"]');
        if (!opt) {
            opt = document.createElement('option');
            opt.value = idClub;
            opt.textContent = equipo.club_nombre || ('Club #' + idClub);
            selClub.appendChild(opt);
        }
        selClub.value = idClub;
    } else if (selClub) {
        selClub.value = '';
    }

    for (var i = 1; i <= JUGADORES_POR_EQUIPO; i++) {
        var fila = document.querySelector('[data-posicion="' + i + '"]');
        var jugadorDataStr = fila ? fila.getAttribute('data-jugador-asignado') : null;
        if (jugadorDataStr) {
            try {
                devolverJugadorAListado(JSON.parse(jugadorDataStr));
            } catch (e) {}
        }
        limpiarJugador(i);
    }

    (equipo.jugadores || []).forEach(function (jugador, index) {
        var posicion = index + 1;
        if (posicion > JUGADORES_POR_EQUIPO) {
            return;
        }
        asignarJugadorAPosicion(posicion, {
            id: jugador.id_inscrito,
            id_inscrito: jugador.id_inscrito,
            id_usuario: jugador.id_usuario,
            cedula: jugador.cedula || '',
            nombre: jugador.nombre || '',
            club_nombre: equipo.club_nombre || 'Sin Club'
        });
        document.querySelectorAll('.jugador-item').forEach(function (item) {
            if (item.getAttribute('data-id-usuario') == jugador.id_usuario) {
                item.remove();
            }
        });
    });

    actualizarBloqueoSeleccionJugadores();
    document.getElementById('formEquipo').scrollIntoView({ behavior: 'smooth', block: 'start' });
    validarFormulario();
}

// Eliminar equipo
async function eliminarEquipo(equipoId, nombreEquipo) {
    const result = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar equipo?',
        html: `¿Está seguro de eliminar el equipo <strong>"${nombreEquipo}"</strong>?<br><br>Los jugadores del equipo quedarán liberados y disponibles para otros equipos.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    });
    
    if (!result.isConfirmed) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('equipo_id', equipoId);
        formData.append('csrf_token', '<?php echo CSRF::token(); ?>');
        
        const response = await fetch('<?php echo $api_base_path; ?>eliminar_equipo.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Eliminado!',
                text: data.message || 'Equipo eliminado exitosamente',
                confirmButtonColor: '#10b981',
                timer: 2000,
                timerProgressBar: true
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Error al eliminar el equipo',
                confirmButtonColor: '#3b82f6'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al eliminar el equipo: ' + error.message,
            confirmButtonColor: '#3b82f6'
        });
    }
}
</script>

