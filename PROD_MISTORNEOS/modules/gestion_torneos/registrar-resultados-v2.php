<?php
/**
 * Vista: Registrar Resultados V2 - Formulario mejorado con todas las funcionalidades
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url = $use_standalone ? $script_actual : 'index.php?page=torneo_gestion';
$action_param = $use_standalone ? '?' : '&';
?>

<style>
    html {
        font-size: 16px; /* Base para rem */
    }
    
    /* Contenedor: 90% del ancho de pantalla para reducir márgenes laterales */
    .registrar-resultados-wrap {
        width: 90%;
        max-width: 100%;
        margin-left: auto;
        margin-right: auto;
    }
    
    /* Sidebar sticky en desktop; formulario ampliado 15% */
    @media (min-width: 769px) {
        .registrar-resultados-wrap #sidebar-mesas {
            flex: 0 0 8.9%;
            max-width: 8.9%;
        }
        .registrar-resultados-wrap #sidebar-mesas .card {
            max-width: 100%;
        }
        .registrar-resultados-wrap .col-form-registro {
            flex: 0 0 91.1%;
            max-width: 91.1%;
        }
    }
    
    .mesa-item {
        transition: all 0.3s;
        padding: 0.5rem 0.75rem !important;
        cursor: pointer;
        font-size: clamp(0.75rem, 1.5vw, 0.875rem);
    }
    .mesa-item:hover {
        transform: translateX(0.3125rem);
    }
    .mesa-activa {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: bold;
    }
    .mesa-completada {
        background: #10b981;
        color: white;
    }
    .mesa-pendiente {
        background: #f59e0b;
        color: white;
    }
    
    /* Validación de input de mesa */
    #input_ir_mesa.is-invalid {
        border-color: #dc3545;
        background-color: #fff5f5;
    }
    
    #input_ir_mesa.is-invalid:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }
    
    #input_ir_mesa.is-valid {
        border-color: #28a745;
        background-color: #f0fff4;
    }
    
    #input_ir_mesa.is-valid:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }
    
    /* Selector de búsqueda de mesa: ancho +40%, tamaño un punto menos */
    #input_ir_mesa {
        font-size: clamp(2.3rem, 4.1vw, 2.7rem) !important;
        font-weight: bold !important;
        width: clamp(9.8rem, 19.6vw, 12.75rem) !important;
        min-width: 9.8rem !important;
        text-align: center;
    }
    
    /* Validación de input de puntos */
    #puntos_pareja_A.is-invalid,
    #puntos_pareja_B.is-invalid {
        border-color: #dc3545;
        background-color: #fff5f5;
    }
    
    #puntos_pareja_A.is-invalid:focus,
    #puntos_pareja_B.is-invalid:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }
    
    #puntos_pareja_A.is-valid,
    #puntos_pareja_B.is-valid {
        border-color: #28a745;
        background-color: #f0fff4;
    }
    
    #puntos_pareja_A.is-valid:focus,
    #puntos_pareja_B.is-valid:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }
    
    /* Jugador con tarjeta previa: resaltar en naranja para advertir al administrador */
    .jugador-tarjeta-previa {
        color: #e65100 !important;
        background: linear-gradient(90deg, rgba(230, 81, 0, 0.15), transparent);
        padding: 2px 6px;
        border-radius: 4px;
    }
    
    /* Tarjetas */
    .tarjeta-btn {
        width: clamp(2rem, 5vw, 2.5rem);
        height: clamp(2rem, 5vw, 2.5rem);
        min-width: 2rem;
        min-height: 2rem;
        border-radius: 0.5rem;
        border: 0.125rem solid #666;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: clamp(1rem, 2.5vw, 1.2rem);
        background-color: transparent !important;
        position: relative;
        touch-action: manipulation;
    }
    .tarjeta-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.2);
    }
    .tarjeta-btn:active {
        transform: scale(0.95);
    }
    .tarjeta-btn.activo {
        border: 0.25rem solid #000;
        box-shadow: 0 0 0.75rem rgba(0,0,0,0.6);
        background-color: transparent !important;
    }
    .tarjeta-btn.activo::after {
        content: '✓';
        position: absolute;
        top: -0.3125rem;
        right: -0.3125rem;
        background-color: #10b981;
        color: white;
        border-radius: 50%;
        width: clamp(1rem, 2.5vw, 1.25rem);
        height: clamp(1rem, 2.5vw, 1.25rem);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: clamp(0.625rem, 1.5vw, 0.75rem);
        font-weight: bold;
        border: 0.125rem solid white;
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.3);
        z-index: 10;
    }
    
    /* Columnas específicas */
    /* Columna Puntos: ampliada 20% */
    .columna-puntos {
        width: auto;
        min-width: 6rem;
        max-width: 9rem;
    }
    
    /* Columna ID Usuario: reducida 20% */
    .columna-id {
        width: 3.2rem;
        min-width: 2.8rem;
        max-width: 4rem;
    }
    
    /* Columna Nombre: ampliada 15% */
    .columna-nombre {
        width: auto;
        min-width: 9rem;
        max-width: 16rem;
    }
    
    /* Columna Sanción: ampliada 15% */
    .columna-sancion {
        width: auto;
        min-width: 5rem;
        max-width: 6.5rem;
    }
    
    /* Columna Tarjeta: ampliada 15% */
    .columna-tarjeta {
        width: auto;
        min-width: 10rem;
        max-width: 11.5rem;
    }
    
    /* Columna Forfait: ampliada 15% */
    .columna-forfait {
        width: 3.7rem;
        min-width: 3.2rem;
        max-width: 4rem;
    }
    
    /* Columna Estadísticas: reducida 15% */
    .columna-estadisticas {
        width: auto;
        min-width: 6.4rem;
        max-width: 10rem;
        white-space: nowrap;
    }
    
    .estadisticas-valores {
        font-size: clamp(0.75rem, 1.5vw, 0.875rem);
        font-weight: bold;
        color: #111827;
        white-space: nowrap;
        line-height: 1.5;
    }
    
    /* Filas de jugadores: altura reducida y bordes sólidos */
    #formResultados tbody tr {
        border: 2px solid #333 !important;
    }
    #formResultados tbody tr td {
        padding: 0.25rem 0.4rem !important;
        vertical-align: middle;
        border: 1px solid #666;
    }
    #formResultados tbody tr.table-info,
    #formResultados tbody tr.table-success {
        border-left: 4px solid;
    }
    #formResultados tbody tr.table-info {
        border-left-color: #17a2b8;
    }
    #formResultados tbody tr.table-success {
        border-left-color: #28a745;
    }
    
    /* Sidebar sticky */
    .sidebar-sticky {
        position: sticky;
        top: 1.25rem;
        max-height: calc(100vh - 2.5rem);
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Lista de mesas: barra de desplazamiento al superar 10 mesas */
    .lista-mesas-scroll {
        max-height: 28rem; /* ~10 mesas visibles (~44px c/u) */
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    .lista-mesas-scroll::-webkit-scrollbar {
        width: 6px;
    }
    .lista-mesas-scroll::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    .lista-mesas-scroll::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 3px;
    }
    
    /* Formulario fijo: evita que se mueva con el ingreso de datos */
    .formulario-resultados-sticky {
        position: sticky;
        top: 1rem;
        align-self: flex-start;
    }
    
    /* Ancla del formulario: al volver tras guardar, la vista se mantiene en el formulario */
    #formResultados {
        scroll-margin-top: 1rem;
    }
    
    /* Evitar salto de layout: reservar espacio estable para mensajes */
    .card.formulario-resultados-sticky .card-body > .alert {
        min-height: 2.75rem;
    }
    
    /* Mensaje de validación */
    #mensaje-validacion {
        display: none;
    }
    #mensaje-validacion.show {
        display: block;
    }
    
    /* Responsive para tablets */
    @media screen and (max-width: 1024px) and (min-width: 769px) {
        .columna-puntos {
            min-width: 6.6rem;
            max-width: 9.6rem;
        }
        .columna-forfait {
            min-width: 2.5rem;
            max-width: 2.8rem;
        }
        .columna-sancion {
            min-width: 4rem;
            max-width: 5rem;
        }
        .columna-tarjeta {
            min-width: 7.5rem;
            max-width: 9rem;
        }
        .columna-estadisticas {
            min-width: 5.5rem;
        }
    }
    
    /* Responsive para móviles */
    @media screen and (max-width: 768px) {
        .registrar-resultados-wrap {
            width: 95%;
        }
        .container-fluid {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        
        /* Sidebar se convierte en dropdown o se oculta */
        .col-md-2.col-lg-1 {
            position: fixed;
            top: 0;
            left: -100%;
            width: 70%;
            max-width: 18.75rem;
            height: 100vh;
            z-index: 1050;
            background: white;
            transition: left 0.3s ease;
            box-shadow: 0.125rem 0 0.5rem rgba(0,0,0,0.2);
            overflow-y: auto;
        }
        
        .col-md-2.col-lg-1.show {
            left: 0;
        }
        
        .col-md-10.col-lg-11 {
            width: 100%;
            max-width: 100%;
        }
        
        /* Botón para mostrar sidebar en móvil */
        .sidebar-toggle {
            display: block;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1051;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            font-size: 1.25rem;
            cursor: pointer;
            box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,0.3);
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1049;
        }
        
        .overlay.show {
            display: block;
        }
        
        /* Tabla responsive */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            font-size: clamp(0.7rem, 2vw, 0.875rem);
        }
        
        .table th,
        .table td {
            padding: 0.375rem 0.25rem;
            font-size: clamp(0.65rem, 1.8vw, 0.8rem);
        }
        
        .columna-puntos {
            min-width: 5.4rem;
            max-width: 7.2rem;
        }
        .columna-forfait {
            min-width: 2.2rem;
            max-width: 2.5rem;
        }
        
        .columna-sancion {
            min-width: 3.5rem;
            max-width: 4.5rem;
        }
        
        .columna-tarjeta {
            min-width: 6.5rem;
            max-width: 8rem;
        }
        
        .columna-estadisticas {
            min-width: 4.7rem;
            font-size: clamp(0.6rem, 1.5vw, 0.75rem);
        }
        
        /* Inputs más grandes para touch */
        .form-control {
            min-height: 2.5rem;
            font-size: clamp(0.875rem, 2.5vw, 1rem);
        }
        
        .form-control-sm {
            min-height: 2rem;
            font-size: clamp(0.75rem, 2vw, 0.875rem);
        }
        
        /* Botones más grandes */
        .btn {
            min-height: 2.75rem;
            padding: 0.5rem 1rem;
            font-size: clamp(0.875rem, 2vw, 1rem);
            touch-action: manipulation;
        }
        
        .btn-sm {
            min-height: 2.25rem;
            padding: 0.375rem 0.75rem;
            font-size: clamp(0.75rem, 1.8vw, 0.875rem);
        }
        
        /* Ajustes de espaciado */
        .card-body {
            padding: 0.75rem;
        }
        
        .mb-3, .mb-4 {
            margin-bottom: 1rem !important;
        }
        
        /* Input de puntos más grande */
        #puntos_pareja_A,
        #puntos_pareja_B {
            font-size: clamp(1rem, 3vw, 1.25rem) !important;
            min-height: 3rem;
        }
    }
    
    /* Responsive para móviles pequeños */
    @media screen and (max-width: 480px) {
        .table {
            font-size: clamp(0.65rem, 1.8vw, 0.75rem);
        }
        
        .table th,
        .table td {
            padding: 0.25rem 0.15rem;
        }
        
        .columna-puntos {
            min-width: 4.8rem;
            max-width: 6rem;
        }
        .columna-forfait {
            min-width: 2.3rem;
            max-width: 2.5rem;
        }
        
        .columna-sancion {
            min-width: 3rem;
            max-width: 4rem;
        }
        
        .columna-tarjeta {
            min-width: 5.5rem;
            max-width: 7rem;
        }
        
        .columna-estadisticas {
            min-width: 3.8rem;
        }
        
        .tarjeta-btn {
            width: 1.75rem;
            height: 1.75rem;
            min-width: 1.75rem;
            min-height: 1.75rem;
            font-size: 0.9rem;
        }
        
        .d-flex.gap-2 {
            gap: 0.5rem !important;
        }
        
        .d-flex.gap-3 {
            gap: 0.75rem !important;
        }
    }
    
    /* Orientación horizontal en móviles */
    @media screen and (max-width: 768px) and (orientation: landscape) {
        .sidebar-sticky {
            max-height: calc(100vh - 1.5rem);
        }
        
        .table {
            font-size: clamp(0.7rem, 1.5vw, 0.85rem);
        }
    }
</style>

<div class="container-fluid registrar-resultados-wrap">
    <?php if (!empty($es_operador_ambito) && !empty($mesas_ambito)): ?>
    <div class="alert alert-info py-2 mb-2 d-flex align-items-center">
        <i class="fas fa-user-cog me-2"></i>
        <span><strong>Su ámbito:</strong> solo puede ver y registrar resultados en las mesas <?php echo min($mesas_ambito); ?> a <?php echo max($mesas_ambito); ?> (<?php echo count($mesas_ambito); ?> mesas asignadas).</span>
    </div>
    <?php endif; ?>
    <!-- Botón para mostrar sidebar en móvil -->
    <button class="sidebar-toggle d-md-none" onclick="toggleSidebar()" aria-label="Mostrar menú">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Overlay para cerrar sidebar en móvil -->
    <div class="overlay" onclick="toggleSidebar()"></div>
    
    <div class="row align-items-start">
        <!-- Panel Lateral - Lista de Mesas (ancho reducido 50%) -->
        <div class="col-md-2 col-lg-1" id="sidebar-mesas">
            <div class="card sidebar-sticky">
                <div class="card-header" style="background-color: #e3f2fd; color: #1565c0;">
                    <h6 class="mb-0">
                        <i class="fas fa-clipboard-list mr-2"></i>Navegación de Partidas
                    </h6>
                </div>

                <!-- Selector de Ronda/Partida -->
                <div class="card-body p-3 border-bottom bg-light">
                    <select id="selector-ronda" 
                            onchange="cambiarRonda(<?php echo $torneo['id']; ?>, this.value)"
                            class="form-control form-control-sm">
                        <?php foreach ($todasLasRondas as $r): ?>
                            <option value="<?php echo $r['partida']; ?>" <?php echo $r['partida'] == $ronda ? 'selected' : ''; ?>>
                                Ronda <?php echo $r['partida']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Estadísticas de Mesas: total y faltantes en la misma fila -->
                <div class="card-body p-3 border-bottom bg-light">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 small">
                        <span class="text-muted">
                            <i class="fas fa-table mr-1"></i>Total: <strong><?php echo $totalMesas; ?></strong> mesas
                        </span>
                        <span class="badge bg-warning text-dark px-2 py-1">
                            Faltantes: <strong><?php echo $mesasPendientes; ?></strong>
                        </span>
                    </div>
                </div>

                <!-- Lista de Mesas (solo las pendientes) -->
                <?php 
                $mesasPendientesLista = array_filter($todasLasMesas ?? [], function($m) { return empty($m['tiene_resultados']); });
                $mesasPendientesLista = array_values($mesasPendientesLista);
                ?>
                <div class="card-body p-2">
                    <h6 class="small font-weight-bold mb-2">
                        <i class="fas fa-table mr-1"></i>Mesas pendientes (Ronda <?php echo $ronda; ?>)
                    </h6>
                    <div class="<?php echo count($mesasPendientesLista) > 10 ? 'lista-mesas-scroll' : ''; ?>">
                    <div class="list-group list-group-flush">
                        <?php if (empty($mesasPendientesLista)): ?>
                            <div class="list-group-item text-center text-success py-3 small">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <div>Todas las mesas completadas</div>
                            </div>
                        <?php else: ?>
                        <?php foreach ($mesasPendientesLista as $m): ?>
                            <?php $esActiva = $m['numero'] == $mesaActual; ?>
                            <a href="<?php echo $base_url . $action_param; ?>action=registrar_resultados&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ronda; ?>&mesa=<?php echo $m['numero']; ?>"
                               class="mesa-item list-group-item list-group-item-action <?php echo $esActiva ? 'mesa-activa' : 'mesa-pendiente'; ?> rounded mb-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><?php echo $m['numero']; ?></strong>
                                    <i class="far fa-circle"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Área Principal - Formulario (ampliada 20%) -->
        <div class="col-md-10 col-lg-11 col-form-registro">
            <div class="card formulario-resultados-sticky">
                <div class="card-header d-flex flex-row justify-content-between align-items-center flex-wrap gap-2" style="background-color: #e3f2fd; color: #1565c0;">
                    <div class="d-flex flex-column align-items-start">
                        <h4 class="mb-0">
                            <i class="fas fa-keyboard mr-2"></i>Registro de Resultados
                        </h4>
                        <?php if (!empty($mostrar_countdown_correcciones) && !empty($countdown_fin_timestamp)): ?>
                        <p class="mb-0 mt-1 font-weight-bold" style="font-size: 1.1rem;">
                            Correcciones se cierran en: <span id="countdown-correcciones" class="tabular-nums" data-fin="<?php echo (int)$countdown_fin_timestamp; ?>">--:--</span>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex align-items-center">
                        <?php if (!empty($puede_cerrar_torneo)): ?>
                        <form method="POST" action="<?php echo $use_standalone ? $base_url : 'index.php?page=torneo_gestion'; ?>" class="mb-0" onsubmit="return confirm('¿Finalizar el torneo? A partir de ese momento no se podrán modificar datos.');">
                            <input type="hidden" name="action" value="cerrar_torneo">
                            <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                            <input type="hidden" name="torneo_id" value="<?php echo (int)$torneo['id']; ?>">
                            <button type="submit" class="btn btn-dark btn-sm font-weight-bold">
                                <i class="fas fa-lock mr-1"></i>Finalizar torneo
                            </button>
                        </form>
                        <?php elseif (!empty($torneo['locked']) && (int)$torneo['locked'] === 1): ?>
                        <span class="badge bg-secondary">Torneo finalizado</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Mensajes -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['warning'])): ?>
                        <div class="alert alert-warning alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <?php echo htmlspecialchars($_SESSION['warning']); unset($_SESSION['warning']); ?>
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <!-- Botones de navegación y reasignar -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <div class="text-muted font-weight-bold" style="font-size: clamp(2.625rem, 4.5vw, 3rem); font-weight: bold;">
                                    Ronda <?php echo $ronda ?? 0; ?> - Mesa <?php echo $mesaActual ?? 0; ?>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-center">
                                <div class="input-group" style="width: auto; max-width: 100%;">
                                    <input type="number" 
                                           id="input_ir_mesa" 
                                           name="ir_mesa"
                                           value="<?php echo (int)($mesaActual ?? 0); ?>"
                                           min="1"
                                           max="<?php echo !empty($todasLasMesas) ? max(array_column($todasLasMesas, 'numero')) : 1; ?>"
                                           class="form-control"
                                           style="text-align: center;"
                                           onkeydown="manejarEnterIrAMesa(event);"
                                           oninput="validarNumeroMesa(this); actualizarEstadoPorMesa();"
                                           onblur="validarNumeroMesa(this); actualizarEstadoPorMesa();"
                                           onfocus="this.select();"
                                           placeholder=""
                                           title="Ingrese un número entre 1 y <?php echo !empty($todasLasMesas) ? max(array_column($todasLasMesas, 'numero')) : 1; ?>">
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if (isset($vieneDeResumen) && $vieneDeResumen && isset($inscritoId) && $inscritoId): ?>
                                    <?php 
                                    // Preservar el parámetro from original si existe
                                    $from_param = isset($_GET['from_original']) ? $_GET['from_original'] : (isset($_GET['from']) && $_GET['from'] !== 'resumen' ? $_GET['from'] : '');
                                    $from_url_param = !empty($from_param) ? '&from=' . urlencode($from_param) : '';
                                    ?>
                                    <a href="<?php echo $base_url . $action_param; ?>action=resumen_individual&torneo_id=<?php echo $torneo['id']; ?>&inscrito_id=<?php echo $inscritoId; ?><?php echo $from_url_param; ?>" 
                                       class="btn btn-info btn-sm">
                                        <i class="fas fa-arrow-left mr-2"></i>Volver al Resumen
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($jugadores) && count($jugadores) == 4): ?>
                                    <a href="<?php echo $base_url . $action_param; ?>action=reasignar_mesa&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ronda; ?>&mesa=<?php echo $mesaActual; ?>" 
                                       class="btn btn-teal btn-sm" style="background-color: #20c997; color: white;">
                                        <i class="fas fa-exchange-alt mr-2"></i>Reasignar Mesa
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo $base_url . $action_param; ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" 
                                   class="btn btn-secondary btn-sm">
                                    <i class="fas fa-arrow-left mr-2"></i>Volver al Panel
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Mensaje de Validación -->
                    <div id="mensaje-validacion" class="mb-3"></div>

                    <!-- Formulario -->
                    <?php 
                    $mesaValida = ((int)($mesaActual ?? 0) > 0 && !empty($jugadores) && count($jugadores) == 4);
                    $mesasNumeros = array_column($todasLasMesas ?? [], 'numero');
                    if ($mesaValida && !empty($mesasNumeros)) {
                        $mesaValida = in_array((int)$mesaActual, array_map('intval', $mesasNumeros));
                    }
                    ?>
                    <?php if (empty($jugadores) || count($jugadores) != 4): ?>
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <p class="mb-0">No se encontraron los 4 jugadores de esta mesa</p>
                        </div>
                    <?php elseif (!$mesaValida): ?>
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <p class="mb-0">No hay una mesa válida seleccionada. Seleccione una mesa de la lista para registrar resultados.</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" 
                              action="<?php echo $base_url; ?>" 
                              id="formResultados"
                              data-mesa-valida="1"
                              data-mesa="<?php echo (int)$mesaActual; ?>">
                            
                            <input type="hidden" name="action" value="guardar_resultados">
                            <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                            <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                            <input type="hidden" name="ronda" value="<?php echo $ronda; ?>">
                            <input type="hidden" name="mesa" value="<?php echo (int)$mesaActual; ?>">

                            <!-- Tabla de Jugadores -->
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered table-sm">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th rowspan="2" class="text-center align-middle columna-id">ID</th>
                                            <th rowspan="2" class="text-center align-middle columna-nombre">Nombre</th>
                                            <th rowspan="2" class="text-center align-middle columna-puntos">Puntos</th>
                                            <th rowspan="2" class="text-center align-middle columna-sancion">Sanción</th>
                                            <th rowspan="2" class="text-center align-middle columna-forfait">Forfait</th>
                                            <th rowspan="2" class="text-center align-middle columna-tarjeta">Tarjeta</th>
                                            <th rowspan="2" class="text-center align-middle">Zap/Chan</th>
                                            <th colspan="4" class="text-center columna-estadisticas">Estadísticas</th>
                                        </tr>
                                        <tr>
                                            <th class="text-center" style="font-size: 0.65rem; line-height: 1.2;">Pos | Gan | Per | Efect</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $parejaA = [];
                                            $parejaB = [];
                                            foreach ($jugadores as $jugador) {
                                                if ($jugador['secuencia'] <= 2) {
                                                    $parejaA[] = $jugador;
                                                } else {
                                                    $parejaB[] = $jugador;
                                                }
                                            }
                                            
                                            // Procesar Pareja A
                                            foreach ($parejaA as $index => $jugador): 
                                                $indiceArray = $index;
                                                $puntosParejaA = $jugador['resultado1'] ?? 0;
                                        ?>
                                            <tr class="table-info">
                                                <!-- ID Usuario -->
                                                <td class="text-center font-weight-bold bg-info columna-id">
                                                    <?php echo $jugador['id_usuario']; ?>
                                                </td>
                                                
                                                <!-- Nombre (naranja si tiene tarjeta previa en partidas anteriores: advierte al administrador) -->
                                                <?php 
                                                $tarjetaPrevia = (int)($jugador['inscrito']['tarjeta_previa'] ?? $jugador['inscrito']['tarjeta'] ?? 0);
                                                $tieneTarjetaPrevia = $tarjetaPrevia >= 1; 
                                                $tituloTarjeta = $tieneTarjetaPrevia ? '⚠️ Tiene tarjeta previa. Sanción 80 pts = siguiente tarjeta (Roja/Negra).' : '';
                                                ?>
                                                <td class="columna-nombre">
                                                    <span class="font-weight-semibold <?php echo $tieneTarjetaPrevia ? 'jugador-tarjeta-previa' : ''; ?>" style="font-size: 1rem;" <?php echo $tituloTarjeta ? 'title="' . htmlspecialchars($tituloTarjeta) . '"' : ''; ?>><?php echo htmlspecialchars($jugador['nombre_completo'] ?? $jugador['nombre'] ?? 'N/A'); ?></span>
                                                </td>
                                                
                                                <!-- Puntos -->
                                                <?php if ($index == 0): ?>
                                                <td rowspan="2" class="text-center align-middle columna-puntos">
                                <input type="number" 
                                       id="puntos_pareja_A"
                                       class="form-control text-center font-weight-bold"
                                       style="font-size: clamp(1rem, 3vw, 1.25rem);"
                                       value="<?php echo $puntosParejaA; ?>"
                                                           min="0"
                                                           max="999"
                                                           maxlength="3"
                                                           onfocus="this.select();"
                                                           onkeydown="manejarEnterPuntos(event, 'A', 'B');"
                                                           onchange="distribuirPuntos('A'); validarPuntosEnTiempoReal();"
                                                           onblur="validarPuntosInmediato(event);"
                                                           oninput="limitardigitos(this, 3); distribuirPuntos('A'); validarPuntosEnTiempoReal();"
                                                           required>
                                                </td>
                                                <?php endif; ?>
                                                
                                                <!-- Sanción: 40=amarilla (adv. adm., no resta pts); 80=sin prev→amarilla, con prev→siguiente (roja/negra) -->
                                                <td class="text-center columna-sancion" data-tarjeta-inscritos="<?php echo (int)($jugador['inscrito']['tarjeta_previa'] ?? $jugador['inscrito']['tarjeta'] ?? 0); ?>">
                                                    <input type="number" 
                                                           name="jugadores[<?php echo $indiceArray; ?>][sancion]"
                                                           class="form-control form-control-sm text-center"
                                                           value="<?php echo min((int)($jugador['sancion'] ?? 0), 80); ?>"
                                                           min="0" 
                                                           max="80"
                                                           placeholder="0"
                                                           oninput="validarSancionYTarjeta(<?php echo $indiceArray; ?>);"
                                                           onchange="validarSancionYTarjeta(<?php echo $indiceArray; ?>); validarPuntosEnTiempoReal();">
                                                    <small id="indicador_tarjeta_80_<?php echo $indiceArray; ?>" class="d-block text-muted mt-1" style="display:none !important;"></small>
                                                </td>
                                                
                                                <!-- Forfait (FF) -->
                                                <td class="text-center columna-forfait">
                                                    <input type="checkbox" 
                                                           name="jugadores[<?php echo $indiceArray; ?>][ff]"
                                                           id="ff_<?php echo $indiceArray; ?>"
                                                           class="form-check-input"
                                                           value="1"
                                                           <?php echo (isset($jugador['ff']) && $jugador['ff']) ? 'checked' : ''; ?>
                                                           onchange="validarPuntosEnTiempoReal();">
                                                </td>
                                                
                                                <!-- Tarjeta -->
                                                <td class="text-center columna-tarjeta">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button type="button" class="tarjeta-btn" 
                                                                data-tarjeta="1"
                                                                data-index="<?php echo $indiceArray; ?>"
                                                                onclick="seleccionarTarjeta(<?php echo $indiceArray; ?>, 1)"
                                                                title="Tarjeta Amarilla">🟨</button>
                                                        <button type="button" class="tarjeta-btn" 
                                                                data-tarjeta="3"
                                                                data-index="<?php echo $indiceArray; ?>"
                                                                onclick="seleccionarTarjeta(<?php echo $indiceArray; ?>, 3)"
                                                                title="Tarjeta Roja">🟥</button>
                                                        <button type="button" class="tarjeta-btn" 
                                                                data-tarjeta="4"
                                                                data-index="<?php echo $indiceArray; ?>"
                                                                onclick="seleccionarTarjeta(<?php echo $indiceArray; ?>, 4)"
                                                                title="Tarjeta Negra">⬛</button>
                                                        <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][tarjeta]" 
                                                               id="tarjeta_<?php echo $indiceArray; ?>" 
                                                               value="<?php echo $jugador['tarjeta'] ?? 0; ?>">
                                                    </div>
                                                </td>
                                                
                                                <!-- Zapato/Chancleta -->
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <label class="mb-0 cursor-pointer">
                                                            <input type="radio" 
                                                                   name="pena_<?php echo $indiceArray; ?>" 
                                                                   value="chancleta"
                                                                   class="form-check-input"
                                                                   <?php echo (isset($jugador['chancleta']) && $jugador['chancleta'] > 0) ? 'checked' : ''; ?>>
                                                            <span class="ml-1">🥿</span>
                                                        </label>
                                                        <label class="mb-0 cursor-pointer">
                                                            <input type="radio" 
                                                                   name="pena_<?php echo $indiceArray; ?>" 
                                                                   value="zapato"
                                                                   class="form-check-input"
                                                                   <?php echo (isset($jugador['zapato']) && $jugador['zapato'] > 0) ? 'checked' : ''; ?>>
                                                            <span class="ml-1">👞</span>
                                                        </label>
                                                    </div>
                                                    <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][chancleta]" 
                                                           id="chancleta_<?php echo $indiceArray; ?>" 
                                                           value="<?php echo $jugador['chancleta'] ?? 0; ?>">
                                                    <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][zapato]" 
                                                           id="zapato_<?php echo $indiceArray; ?>" 
                                                           value="<?php echo $jugador['zapato'] ?? 0; ?>">
                                                </td>
                                                
                                                <!-- Estadísticas -->
                                                <td class="text-center bg-light columna-estadisticas">
                                                    <div class="estadisticas-valores">
                                                        <?php echo (int)($jugador['inscrito']['posicion'] ?? 0); ?> | 
                                                        <?php echo (int)($jugador['inscrito']['ganados'] ?? 0); ?> | 
                                                        <?php echo (int)($jugador['inscrito']['perdidos'] ?? 0); ?> | 
                                                        <?php echo (int)($jugador['inscrito']['efectividad'] ?? 0); ?>
                                                    </div>
                                                </td>
                                                
                                                <!-- Campos Hidden -->
                                                <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][id]" 
                                                       value="<?php echo $jugador['id']; ?>">
                                                <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][id_usuario]" 
                                                       value="<?php echo $jugador['id_usuario']; ?>">
                                                <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][secuencia]" 
                                                       value="<?php echo $jugador['secuencia']; ?>">
                                                <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][resultado1]" 
                                                       id="resultado1_<?php echo $indiceArray; ?>" 
                                                       value="<?php echo $jugador['resultado1'] ?? 0; ?>">
                                                <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][resultado2]" 
                                                       id="resultado2_<?php echo $indiceArray; ?>" 
                                                       value="<?php echo $jugador['resultado2'] ?? 0; ?>">
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php 
                                            // Procesar Pareja B
                                            foreach ($parejaB as $index => $jugador): 
                                                $indiceArray = 2 + $index;
                                                $puntosParejaB = $jugador['resultado1'] ?? 0;
                                        ?>
                                            <tr class="table-success">
                                                <!-- ID Usuario -->
                                                <td class="text-center font-weight-bold bg-success columna-id">
                                                    <?php echo $jugador['id_usuario']; ?>
                                                </td>
                                                
                                                <!-- Nombre (naranja si tiene tarjeta previa en partidas anteriores: advierte al administrador) -->
                                                <?php 
                                                $tarjetaPrevia = (int)($jugador['inscrito']['tarjeta_previa'] ?? $jugador['inscrito']['tarjeta'] ?? 0);
                                                $tieneTarjetaPrevia = $tarjetaPrevia >= 1; 
                                                $tituloTarjeta = $tieneTarjetaPrevia ? '⚠️ Tiene tarjeta previa. Sanción 80 pts = siguiente tarjeta (Roja/Negra).' : '';
                                                ?>
                                                <td class="columna-nombre">
                                                    <span class="font-weight-semibold <?php echo $tieneTarjetaPrevia ? 'jugador-tarjeta-previa' : ''; ?>" style="font-size: 1rem;" <?php echo $tituloTarjeta ? 'title="' . htmlspecialchars($tituloTarjeta) . '"' : ''; ?>><?php echo htmlspecialchars($jugador['nombre_completo'] ?? $jugador['nombre'] ?? 'N/A'); ?></span>
                                                </td>
                                                
                                                <!-- Puntos -->
                                                <?php if ($index == 0): ?>
                                                <td rowspan="2" class="text-center align-middle columna-puntos">
                                                    <input type="number" 
                                                           id="puntos_pareja_B"
                                                           class="form-control text-center font-weight-bold"
                                                           style="font-size: clamp(1rem, 3vw, 1.25rem);"
                                                           value="<?php echo $puntosParejaB; ?>"
                                                           min="0" 
                                                           max="999"
                                                           maxlength="3"
                                                           onfocus="this.select();"
                                                           onkeydown="manejarEnterPuntos(event, 'B', 'guardar');"
                                                           onchange="distribuirPuntos('B'); validarPuntosEnTiempoReal();"
                                                           onblur="validarPuntosInmediato(event);"
                                                           oninput="limitardigitos(this, 3); distribuirPuntos('B'); validarPuntosEnTiempoReal();"
                                                           required>
                                                </td>
                                                <?php endif; ?>
                                                
                                                <!-- Sanción: 40=amarilla (adv. adm., no resta pts); 80=sin prev→amarilla, con prev→siguiente (roja/negra) -->
                                                <td class="text-center columna-sancion" data-tarjeta-inscritos="<?php echo (int)($jugador['inscrito']['tarjeta_previa'] ?? $jugador['inscrito']['tarjeta'] ?? 0); ?>">
                                                    <input type="number" 
                                                           name="jugadores[<?php echo $indiceArray; ?>][sancion]"
                                                           class="form-control form-control-sm text-center"
                                                           value="<?php echo min((int)($jugador['sancion'] ?? 0), 80); ?>"
                                                           min="0" 
                                                           max="80"
                                                           placeholder="0"
                                                           oninput="validarSancionYTarjeta(<?php echo $indiceArray; ?>);"
                                                           onchange="validarSancionYTarjeta(<?php echo $indiceArray; ?>); validarPuntosEnTiempoReal();">
                                                    <small id="indicador_tarjeta_80_<?php echo $indiceArray; ?>" class="d-block text-muted mt-1" style="display:none !important;"></small>
                                                </td>
                                                
                                                <!-- Forfait (FF) -->
                                                <td class="text-center columna-forfait">
                                                    <input type="checkbox" 
                                                           name="jugadores[<?php echo $indiceArray; ?>][ff]"
                                                           id="ff_<?php echo $indiceArray; ?>"
                                                           class="form-check-input"
                                                           value="1"
                                                           <?php echo (isset($jugador['ff']) && $jugador['ff']) ? 'checked' : ''; ?>
                                                           onchange="validarPuntosEnTiempoReal();">
                                                </td>
                                                
                                                <!-- Tarjeta -->
                                                <td class="text-center columna-tarjeta">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button type="button" class="tarjeta-btn" 
                                                                data-tarjeta="1"
                                                                data-index="<?php echo $indiceArray; ?>"
                                                                onclick="seleccionarTarjeta(<?php echo $indiceArray; ?>, 1)"
                                                                title="Tarjeta Amarilla">🟨</button>
                                                        <button type="button" class="tarjeta-btn" 
                                                                data-tarjeta="3"
                                                                data-index="<?php echo $indiceArray; ?>"
                                                                onclick="seleccionarTarjeta(<?php echo $indiceArray; ?>, 3)"
                                                                title="Tarjeta Roja">🟥</button>
                                                        <button type="button" class="tarjeta-btn" 
                                                                data-tarjeta="4"
                                                                data-index="<?php echo $indiceArray; ?>"
                                                                onclick="seleccionarTarjeta(<?php echo $indiceArray; ?>, 4)"
                                                                title="Tarjeta Negra">⬛</button>
                                                        <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][tarjeta]" 
                                                               id="tarjeta_<?php echo $indiceArray; ?>" 
                                                               value="<?php echo $jugador['tarjeta'] ?? 0; ?>">
                                                    </div>
                                                </td>
                                                
                                                <!-- Zapato/Chancleta -->
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <label class="mb-0 cursor-pointer">
                                                            <input type="radio" 
                                                                   name="pena_<?php echo $indiceArray; ?>" 
                                                                   value="chancleta"
                                                                   class="form-check-input"
                                                                   <?php echo (isset($jugador['chancleta']) && $jugador['chancleta'] > 0) ? 'checked' : ''; ?>>
                                                            <span class="ml-1">🥿</span>
                                                        </label>
                                                        <label class="mb-0 cursor-pointer">
                                                            <input type="radio" 
                                                                   name="pena_<?php echo $indiceArray; ?>" 
                                                                   value="zapato"
                                                                   class="form-check-input"
                                                                   <?php echo (isset($jugador['zapato']) && $jugador['zapato'] > 0) ? 'checked' : ''; ?>>
                                                            <span class="ml-1">👞</span>
                                                        </label>
                                                    </div>
                                                    <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][chancleta]" 
                                                           id="chancleta_<?php echo $indiceArray; ?>" 
                                                           value="<?php echo $jugador['chancleta'] ?? 0; ?>">
                                                    <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][zapato]" 
                                                           id="zapato_<?php echo $indiceArray; ?>" 
                                                           value="<?php echo $jugador['zapato'] ?? 0; ?>">
                                                </td>
                                                
                                                <!-- Estadísticas -->
                                                <td class="text-center bg-light columna-estadisticas">
                                                    <div class="estadisticas-valores">
                                                        <?php echo (int)($jugador['inscrito']['posicion'] ?? 0); ?> | 
                                                        <?php echo (int)($jugador['inscrito']['ganados'] ?? 0); ?> | 
                                                        <?php echo (int)($jugador['inscrito']['perdidos'] ?? 0); ?> | 
                                                        <?php echo (int)($jugador['inscrito']['efectividad'] ?? 0); ?>
                                                    </div>
                                                </td>
                                                
                                                <!-- Campos Hidden -->
                                                <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][id]" 
                                                       value="<?php echo $jugador['id']; ?>">
                                                <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][id_usuario]" 
                                                       value="<?php echo $jugador['id_usuario']; ?>">
                                                <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][secuencia]" 
                                                       value="<?php echo $jugador['secuencia']; ?>">
                                                <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][resultado1]" 
                                                       id="resultado1_<?php echo $indiceArray; ?>" 
                                                       value="<?php echo $jugador['resultado1'] ?? 0; ?>">
                                                <input type="hidden" name="jugadores[<?php echo $indiceArray; ?>][resultado2]" 
                                                       id="resultado2_<?php echo $indiceArray; ?>" 
                                                       value="<?php echo $jugador['resultado2'] ?? 0; ?>">
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Observaciones -->
                            <div class="mb-4">
                                <label class="font-weight-bold mb-2">
                                    <i class="fas fa-comment-alt mr-1"></i>Observaciones
                                </label>
                                <textarea name="observaciones" 
                                          rows="3"
                                          class="form-control"
                                          placeholder="Observaciones sobre la partida (opcional)"><?php echo htmlspecialchars($observacionesMesa ?? ''); ?></textarea>
                            </div>

                            <!-- Botones de Acción -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <!-- Navegación -->
                                <div class="d-flex gap-2">
                                    <?php if ($mesaAnterior ?? null): ?>
                                        <a href="<?php echo $base_url . $action_param; ?>action=registrar_resultados&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ronda; ?>&mesa=<?php echo $mesaAnterior; ?>"
                                           class="btn btn-secondary">
                                            <i class="fas fa-arrow-left mr-2"></i>Mesa Anterior
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($mesaSiguiente ?? null): ?>
                                        <a href="<?php echo $base_url . $action_param; ?>action=registrar_resultados&torneo_id=<?php echo $torneo['id']; ?>&ronda=<?php echo $ronda; ?>&mesa=<?php echo $mesaSiguiente; ?>"
                                           class="btn btn-secondary">
                                            Mesa Siguiente<i class="fas fa-arrow-right ml-2"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Acciones -->
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <button type="button" 
                                            id="btn-limpiar"
                                            onclick="limpiarFormulario()"
                                            class="btn btn-warning">
                                        <i class="fas fa-eraser mr-2"></i>Limpiar
                                    </button>
                                    
                                    <button type="submit" 
                                            id="btn-guardar"
                                            class="btn btn-success btn-lg font-weight-bold"
                                            disabled>
                                        <i class="fas fa-save mr-2"></i>GUARDAR
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Función para mostrar/ocultar sidebar en móvil
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar-mesas');
    const overlay = document.querySelector('.overlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }
}

// Cerrar sidebar al hacer clic fuera en móvil
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar-mesas');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const overlay = document.querySelector('.overlay');
    
    if (window.innerWidth <= 768 && sidebar && overlay) {
        if (!sidebar.contains(event.target) && 
            !sidebarToggle.contains(event.target) && 
            sidebar.classList.contains('show')) {
            toggleSidebar();
        }
    }
});

// Función para cambiar de ronda
function cambiarRonda(torneoId, ronda) {
    window.location.href = '<?php echo $base_url . $action_param; ?>action=mesas&torneo_id=' + torneoId + '&ronda=' + ronda;
}

// Función para limitar dígitos
function limitardigitos(input, max) {
    if (input.value.length > max) {
        input.value = input.value.slice(0, max);
    }
}

// Función para manejar Enter en campos de puntos
function manejarEnterPuntos(event, parejaActual, siguienteAccion) {
    if (event.key === 'Enter') {
        event.preventDefault();
        
        // Solo navegar entre campos, NO guardar automáticamente
        if (siguienteAccion === 'guardar') {
            // Si es el último campo, solo enfocar el botón de guardar (NO guardar)
            const btnGuardar = document.getElementById('btn-guardar');
            if (btnGuardar) {
                btnGuardar.focus();
            }
        } else {
            // Ir al siguiente campo de puntos
            const siguienteCampo = document.getElementById('puntos_pareja_' + siguienteAccion);
            if (siguienteCampo) {
                siguienteCampo.focus();
                siguienteCampo.select();
            }
        }
    }
}


// Función para manejar Enter en el input de ir a mesa (parte superior)
function manejarEnterIrAMesa(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        irAMesaDesdeInput();
    }
}

// Función para validar el número de mesa en tiempo real
function validarNumeroMesa(input) {
    if (!input) {
        return false;
    }
    
    const valor = input.value.trim();
    const maxMesa = <?php echo !empty($todasLasMesas) ? max(array_column($todasLasMesas, 'numero')) : 0; ?>;
    
    // Si el campo está vacío, no validar aún (pero 0 sí debe validarse como inválido)
    if (valor === '') {
        input.classList.remove('is-invalid', 'is-valid');
        input.setCustomValidity('');
        return true;
    }
    
    // Si el valor es 0, marcarlo como inválido
    if (valor === '0') {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        input.setCustomValidity('Número de mesa inválido');
        return false;
    }
    
    const numeroMesa = parseInt(valor);
    
    // Validar que sea un número válido
    if (isNaN(numeroMesa)) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        input.setCustomValidity('Número de mesa inválido');
        return false;
    }
    
    // Validar que sea mayor a 0
    if (numeroMesa <= 0) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        input.setCustomValidity('Número de mesa inválido');
        return false;
    }
    
    // Validar que no exceda el máximo de mesas asignadas
    if (maxMesa > 0 && numeroMesa > maxMesa) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        input.setCustomValidity(`El número máximo de mesa asignada es ${maxMesa}`);
        return false;
    }
    
    // Verificar que la mesa existe en la lista de mesas disponibles
    const mesasDisponibles = [<?php echo !empty($todasLasMesas) ? implode(',', array_column($todasLasMesas, 'numero')) : ''; ?>];
    if (mesasDisponibles.length > 0 && !mesasDisponibles.includes(numeroMesa)) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        input.setCustomValidity(`La mesa #${numeroMesa} no está asignada en esta ronda`);
        return false;
    }
    
    // Si pasa todas las validaciones
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    input.setCustomValidity('');
    return true;
}

// Función para ir a mesa usando solo el número de mesa (ronda actual)
function irAMesaDesdeInput() {
    const inputMesa = document.getElementById('input_ir_mesa');
    
    if (!inputMesa) {
        return;
    }
    
    const valor = inputMesa.value.trim();
    
    // Validar que no esté vacío
    if (valor === '') {
        Swal.fire({
            icon: 'error',
            title: 'Mesa inválida',
            text: 'Número de mesa inválido',
            confirmButtonColor: '#667eea'
        });
        inputMesa.focus();
        inputMesa.select();
        return;
    }
    
    const numeroMesa = parseInt(valor);
    
    // Validar que sea un número válido y mayor a 0 (incluye el caso de 0)
    if (isNaN(numeroMesa) || numeroMesa <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Mesa inválida',
            text: 'Número de mesa inválido',
            confirmButtonColor: '#667eea'
        });
        inputMesa.focus();
        inputMesa.select();
        return;
    }
    
    // Validar el valor antes de proceder (validación completa)
    if (!validarNumeroMesa(inputMesa)) {
        // Si la validación falla, mostrar mensaje de error
        const mensajeError = inputMesa.validationMessage || inputMesa.getAttribute('data-error') || 'Número de mesa inválido';
        
        Swal.fire({
            icon: 'error',
            title: 'Mesa inválida',
            text: mensajeError,
            confirmButtonColor: '#667eea'
        });
        
        inputMesa.focus();
        inputMesa.select();
        return;
    }
    const torneoId = <?php echo $torneo['id']; ?>;
    const rondaActual = <?php echo $ronda; ?>;
    
    // Ir directamente a la mesa
    const url = '<?php echo $base_url . $action_param; ?>action=registrar_resultados&torneo_id=' + torneoId + '&ronda=' + rondaActual + '&mesa=' + numeroMesa;
    window.location.href = url;
}

// Función para distribuir puntos de las parejas a los jugadores individuales
function distribuirPuntos(pareja) {
    // Si se llama con 'todas', distribuir ambas parejas
    if (pareja === 'todas') {
        distribuirPuntos('A');
        distribuirPuntos('B');
        return;
    }
    
    // Obtener puntos actuales de ambas parejas
    const puntosParejaA = parseInt(document.getElementById('puntos_pareja_A').value) || 0;
    const puntosParejaB = parseInt(document.getElementById('puntos_pareja_B').value) || 0;
    
    // Determinar qué pareja estamos procesando
    let puntosPareja, puntosContraria, indices;
    if (pareja === 'A') {
        puntosPareja = puntosParejaA;
        puntosContraria = puntosParejaB;
        indices = [0, 1]; // Secuencias 1-2
    } else {
        puntosPareja = puntosParejaB;
        puntosContraria = puntosParejaA;
        indices = [2, 3]; // Secuencias 3-4
    }
    
    // Distribuir puntos a cada jugador de la pareja
    // IMPORTANTE: Siempre actualizar ambos campos (resultado1 y resultado2) para mantener sincronización
    indices.forEach(index => {
        const campoR1 = document.getElementById('resultado1_' + index);
        const campoR2 = document.getElementById('resultado2_' + index);
        
        if (campoR1) {
            campoR1.value = puntosPareja;
            console.log('Distribuido resultado1[' + index + '] = ' + puntosPareja + ' (Pareja ' + pareja + ')');
        } else {
            console.error('No se encontró resultado1_' + index);
        }
        if (campoR2) {
            campoR2.value = puntosContraria;
            console.log('Distribuido resultado2[' + index + '] = ' + puntosContraria + ' (Contraria)');
        } else {
            console.error('No se encontró resultado2_' + index);
        }
    });
    
    // IMPORTANTE: Cuando se actualiza una pareja, también actualizar la pareja contraria
    // para mantener sincronización de resultado2
    if (pareja === 'A') {
        // Si actualizamos A, actualizar resultado2 de B (que apunta a A)
        [2, 3].forEach(index => {
            const campoR2 = document.getElementById('resultado2_' + index);
            if (campoR2) {
                campoR2.value = puntosParejaA;
                console.log('Actualizado resultado2[' + index + '] = ' + puntosParejaA + ' (desde Pareja A)');
            }
        });
    } else {
        // Si actualizamos B, actualizar resultado2 de A (que apunta a B)
        [0, 1].forEach(index => {
            const campoR2 = document.getElementById('resultado2_' + index);
            if (campoR2) {
                campoR2.value = puntosParejaB;
                console.log('Actualizado resultado2[' + index + '] = ' + puntosParejaB + ' (desde Pareja B)');
            }
        });
    }
}

// Devuelve true si el textbox "Ir a Mesa" tiene un valor válido (> 0 y en lista de mesas)
function esMesaValidaEnInput() {
    const input = document.getElementById('input_ir_mesa');
    if (!input) return false;
    const num = parseInt(input.value) || 0;
    if (num <= 0) return false;
    const mesasDisponibles = [<?php echo !empty($todasLasMesas) ? implode(',', array_column($todasLasMesas, 'numero')) : ''; ?>];
    if (mesasDisponibles.length > 0 && !mesasDisponibles.includes(num)) return false;
    return true;
}

// Habilita o deshabilita todos los controles del formulario según el valor del textbox de mesa
function actualizarEstadoPorMesa() {
    const habilitado = esMesaValidaEnInput();
    const form = document.getElementById('formResultados');
    if (!form) return;
    
    const mesaInput = form.querySelector('input[name="mesa"]');
    const inputIrMesa = document.getElementById('input_ir_mesa');
    if (mesaInput && inputIrMesa && habilitado) {
        const num = parseInt(inputIrMesa.value) || 0;
        if (num > 0) mesaInput.value = num;
    }
    
    const controles = [
        document.getElementById('puntos_pareja_A'),
        document.getElementById('puntos_pareja_B'),
        document.getElementById('btn-guardar'),
        document.getElementById('btn-limpiar'),
        document.querySelector('textarea[name="observaciones"]')
    ];
    controles.forEach(el => { if (el) el.disabled = !habilitado; });
    
    for (let i = 0; i < 4; i++) {
        const sancion = document.querySelector('input[name="jugadores[' + i + '][sancion]"]');
        const ff = document.getElementById('ff_' + i);
        if (sancion) sancion.disabled = !habilitado;
        if (ff) ff.disabled = !habilitado;
        const tarjetas = document.querySelectorAll('[data-index="' + i + '"].tarjeta-btn');
        tarjetas.forEach(btn => { btn.disabled = !habilitado; });
        const penas = document.querySelectorAll('input[name="pena_' + i + '"]');
        penas.forEach(p => { p.disabled = !habilitado; });
    }
    
    actualizarEstadoBotonGuardar();
}

// Función global para habilitar/deshabilitar botón guardar según valores del formulario
function actualizarEstadoBotonGuardar() {
    const btnGuardar = document.getElementById('btn-guardar');
    if (!btnGuardar) return;
    
    if (!esMesaValidaEnInput()) {
        btnGuardar.disabled = true;
        return;
    }
    
    const puntosA = parseInt(document.getElementById('puntos_pareja_A').value) || 0;
    const puntosB = parseInt(document.getElementById('puntos_pareja_B').value) || 0;
    
    // Verificar si hay forfait marcado (cualquiera de los 4 jugadores)
    let hayForfait = false;
    for (let i = 0; i < 4; i++) {
        const ff = document.getElementById('ff_' + i);
        if (ff && ff.checked) {
            hayForfait = true;
            break;
        }
    }
    
    // Verificar si hay tarjeta grave - roja (3) o negra (4) (cualquiera de los 4 jugadores)
    let hayTarjetaGrave = false;
    for (let i = 0; i < 4; i++) {
        const campoTarjeta = document.getElementById('tarjeta_' + i);
        if (campoTarjeta) {
            const tarjeta = parseInt(campoTarjeta.value) || 0;
            if (tarjeta == 3 || tarjeta == 4) {
                hayTarjetaGrave = true;
                break;
            }
        }
    }
    
    // Habilitar si hay puntos, forfait o tarjeta grave
    if (puntosA > 0 || puntosB > 0 || hayForfait || hayTarjetaGrave) {
        btnGuardar.disabled = false;
    } else {
        btnGuardar.disabled = true;
    }
}

// Sanción 40: Amarilla (adv. adm., no resta pts). Sanción 80: 0 prev→Amarilla, ya amarilla→Roja
const SANCION_AMARILLA = 40;
const SANCION_MAXIMA = 80;

function validarSancionYTarjeta(index) {
    const input = document.querySelector('input[name="jugadores[' + index + '][sancion]"]');
    if (!input) return;
    let val = parseInt(input.value, 10);
    if (isNaN(val) || val < 0) val = 0;
    if (val > SANCION_MAXIMA) {
        val = SANCION_MAXIMA;
        input.value = val;
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Límite de sanción',
                text: 'Las sanciones no pueden superar los ' + SANCION_MAXIMA + ' puntos.',
                confirmButtonColor: '#667eea',
                timer: 2500,
                timerProgressBar: true
            });
        }
    }
    const campoHidden = document.getElementById('tarjeta_' + index);
    const tarjetaForm = campoHidden ? parseInt(campoHidden.value, 10) || 0 : 0;
    const tdSancion = input.closest('td.columna-sancion');
    const indicador = document.getElementById('indicador_tarjeta_80_' + index);
    const tarjetaInscritos = tdSancion ? parseInt(tdSancion.getAttribute('data-tarjeta-inscritos'), 10) || 0 : 0;
    const mostrarIndicador = (val === SANCION_AMARILLA) || (val === SANCION_MAXIMA) || (tarjetaForm === 1);
    if (indicador) {
        if (mostrarIndicador) {
            if (val === SANCION_AMARILLA) {
                indicador.textContent = 'Será: Amarilla (adv. adm., no resta pts)';
            } else if (val === SANCION_MAXIMA || tarjetaForm === 1) {
                indicador.textContent = tarjetaInscritos >= 1 ? 'Será: Roja (acum.)' : 'Será: Amarilla';
            } else {
                indicador.textContent = '';
            }
            indicador.style.display = 'block';
        } else {
            indicador.textContent = '';
            indicador.style.display = 'none';
        }
    }
    if (val === SANCION_AMARILLA && campoHidden) {
        if (1 !== tarjetaForm) seleccionarTarjeta(index, 1);
    } else if (val === SANCION_MAXIMA && campoHidden) {
        const nuevaTarjeta = tarjetaInscritos >= 1 ? 3 : 1;
        if (nuevaTarjeta !== tarjetaForm) seleccionarTarjeta(index, nuevaTarjeta);
    }
}

// Función para seleccionar tarjeta
function seleccionarTarjeta(index, tarjeta) {
    const campoHidden = document.getElementById('tarjeta_' + index);
    if (!campoHidden) return;
    
    const tarjetaActual = parseInt(campoHidden.value) || 0;
    
    // Si se hace clic en el mismo botón, deseleccionar
    if (tarjetaActual === tarjeta) {
        tarjeta = 0;
    }
    
    // Remover clase activo de todos los botones de este jugador
    const botones = document.querySelectorAll('[data-index="' + index + '"].tarjeta-btn');
    botones.forEach(btn => btn.classList.remove('activo'));
    
    // Si hay una tarjeta seleccionada, agregar clase activo al botón correspondiente
    if (tarjeta > 0) {
        const botonSeleccionado = document.querySelector('[data-index="' + index + '"][data-tarjeta="' + tarjeta + '"]');
        if (botonSeleccionado) {
            botonSeleccionado.classList.add('activo');
        }
    }
    
    // Actualizar campo hidden
    campoHidden.value = tarjeta;
    
    // Actualizar indicador de amarilla/roja por acumulación (cuando se selecciona amarilla directa)
    validarSancionYTarjeta(index);
    
    // Validar puntos
    validarPuntosEnTiempoReal();
    
    // Actualizar estado del botón guardar (importante para tarjetas rojas y negras)
    actualizarEstadoBotonGuardar();
}

// Función para procesar radio buttons de pena antes de enviar
function procesarPena() {
    for (let i = 0; i < 4; i++) {
        const radioChancleta = document.querySelector('input[name="pena_' + i + '"][value="chancleta"]');
        const radioZapato = document.querySelector('input[name="pena_' + i + '"][value="zapato"]');
        
        // Limpiar valores
        document.getElementById('chancleta_' + i).value = '0';
        document.getElementById('zapato_' + i).value = '0';
        
        // Asignar según radio seleccionado
        if (radioChancleta && radioChancleta.checked) {
            document.getElementById('chancleta_' + i).value = '1';
        }
        if (radioZapato && radioZapato.checked) {
            document.getElementById('zapato_' + i).value = '1';
        }
    }
}

// Función para limpiar formulario (con confirmación)
async function limpiarFormulario() {
    const result = await Swal.fire({
        title: '¿Limpiar formulario?',
        text: '¿Estás seguro de limpiar todos los campos?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, limpiar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6c757d'
    });
    
    if (result.isConfirmed) {
        limpiarFormularioSilencioso();
    }
}

// Función para limpiar formulario sin confirmación (usado después de guardar)
function limpiarFormularioSilencioso() {
    // Limpiar el textbox "Ir a Mesa" estableciéndolo en 0
    const inputMesa = document.getElementById('input_ir_mesa');
    if (inputMesa) {
        inputMesa.value = '0';
        inputMesa.classList.remove('is-invalid', 'is-valid');
        inputMesa.setCustomValidity('');
    }
    
    const puntosA = document.getElementById('puntos_pareja_A');
    const puntosB = document.getElementById('puntos_pareja_B');
    if (puntosA) puntosA.value = '0';
    if (puntosB) puntosB.value = '0';
    distribuirPuntos('todas');
    
    // Limpiar tarjetas
    for (let i = 0; i < 4; i++) {
        const botones = document.querySelectorAll('[data-index="' + i + '"].tarjeta-btn');
        botones.forEach(btn => btn.classList.remove('activo'));
        document.getElementById('tarjeta_' + i).value = 0;
        const sancion = document.querySelector('input[name="jugadores[' + i + '][sancion]"]');
        if (sancion) sancion.value = '0';
        const ff = document.getElementById('ff_' + i);
        if (ff) ff.checked = false;
        const penas = document.querySelectorAll('input[name="pena_' + i + '"]');
        penas.forEach(pena => pena.checked = false);
    }
    
    procesarPena();
    const observaciones = document.querySelector('textarea[name="observaciones"]');
    if (observaciones) observaciones.value = '';
    
    // Enfocar el primer campo después de limpiar
    if (puntosA) {
        setTimeout(() => {
            puntosA.focus();
            puntosA.select();
        }, 100);
    }
    
    validarPuntosEnTiempoReal();
    actualizarEstadoPorMesa();
}

// Función para validar puntos en tiempo real
// Función para validar puntos inmediatamente al salir del campo (onblur)
function validarPuntosInmediato(event) {
    const campo = event.target;
    const puntos = parseInt(campo.value) || 0;
    const puntosTorneo = <?php echo (int)($torneo['puntos'] ?? 100); ?>;
    // Máximo permitido: puntos del torneo + 60% = puntosTorneo * 1.6
    const maximoPermitido = Math.round(puntosTorneo * 1.6);
    
    // Remover clases de error previas
    campo.classList.remove('is-invalid', 'is-valid', 'border-danger', 'bg-danger');
    
    // Si el campo está vacío, no validar
    if (campo.value.trim() === '') {
        return;
    }
    
    // Validar máximo
    if (puntos > maximoPermitido) {
        campo.classList.add('is-invalid', 'border-danger', 'bg-danger');
        campo.setCustomValidity('El monto es exagerado');
        
        // Mostrar mensaje de error inmediatamente
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'El monto es exagerado',
            confirmButtonColor: '#667eea',
            timer: 3000,
            timerProgressBar: true
        });
        
        // Enfocar y seleccionar el campo
        setTimeout(() => {
            campo.focus();
            campo.select();
        }, 100);
    } else {
        campo.classList.remove('is-invalid');
        campo.classList.add('is-valid');
        campo.setCustomValidity('');
    }
}

function validarPuntosEnTiempoReal() {
    const puntosA = parseInt(document.getElementById('puntos_pareja_A').value) || 0;
    const puntosB = parseInt(document.getElementById('puntos_pareja_B').value) || 0;
    const puntosTorneo = <?php echo (int)($torneo['puntos'] ?? 100); ?>;
    // Máximo permitido: puntos del torneo + 60% = puntosTorneo * 1.6
    const maximoPermitido = Math.round(puntosTorneo * 1.6);
    
    const campoA = document.getElementById('puntos_pareja_A');
    const campoB = document.getElementById('puntos_pareja_B');
    const mensajeDiv = document.getElementById('mensaje-validacion');
    
    // Remover clases de error previas
    campoA.classList.remove('border-danger', 'bg-danger', 'border-warning', 'bg-warning');
    campoB.classList.remove('border-danger', 'bg-danger', 'border-warning', 'bg-warning');
    mensajeDiv.classList.remove('show', 'alert', 'alert-danger', 'alert-warning');
    mensajeDiv.innerHTML = '';
    
    // Obtener estado de forfait y tarjetas
    let hayForfait = false;
    let hayTarjetaGrave = false;
    
    for (let i = 0; i < 4; i++) {
        const ff = document.getElementById('ff_' + i);
        if (ff && ff.checked) hayForfait = true;
        const tarjeta = parseInt(document.getElementById('tarjeta_' + i).value) || 0;
        if (tarjeta == 3 || tarjeta == 4) hayTarjetaGrave = true;
    }
    
    let hayError = false;
    let mensaje = '';
    
    // Validar máximo (puntos del torneo + 60%)
    if (puntosA > maximoPermitido) {
        campoA.classList.add('border-danger', 'bg-danger');
        hayError = true;
        mensaje += '⚠️ Pareja A: El monto es exagerado. ';
    }
    
    if (puntosB > maximoPermitido) {
        campoB.classList.add('border-danger', 'bg-danger');
        hayError = true;
        mensaje += '⚠️ Pareja B: El monto es exagerado. ';
    }
    
    // Validar igualdad (solo si no hay forfait o tarjeta grave)
    if (puntosA == puntosB && puntosA > 0 && !hayForfait && !hayTarjetaGrave) {
        campoA.classList.add('border-warning', 'bg-warning');
        campoB.classList.add('border-warning', 'bg-warning');
        hayError = true;
        mensaje += '⚠️ Los puntos no pueden ser iguales. Debe haber un ganador o una falta (forfait/tarjeta roja/negra). ';
    }
    
    // Validar que solo uno alcance los puntos del torneo
    const parejaAAlcanzo = puntosA >= puntosTorneo;
    const parejaBAlcanzo = puntosB >= puntosTorneo;
    
    if (parejaAAlcanzo && parejaBAlcanzo && !hayForfait && !hayTarjetaGrave) {
        hayError = true;
        mensaje += '⚠️ Solo una pareja puede alcanzar los puntos del torneo (' + puntosTorneo + '). ';
    }
    
    // Mostrar mensaje si hay error
    if (hayError && mensaje) {
        mensajeDiv.className = 'mb-3 alert alert-warning show';
        mensajeDiv.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>' + mensaje;
    }
}

// Función para validar resultados antes de enviar
function validarResultados() {
    const form = document.getElementById('formResultados');
    const mesaInput = form ? form.querySelector('input[name="mesa"]') : null;
    const mesa = mesaInput ? parseInt(mesaInput.value) || 0 : 0;
    
    if (mesa <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Mesa no válida',
            text: 'No hay una mesa válida seleccionada. Seleccione una mesa de la lista antes de guardar.',
            confirmButtonColor: '#667eea'
        });
        const inputIrMesa = document.getElementById('input_ir_mesa');
        if (inputIrMesa) inputIrMesa.focus();
        return false;
    }
    
    const puntosA = parseInt(document.getElementById('puntos_pareja_A').value) || 0;
    const puntosB = parseInt(document.getElementById('puntos_pareja_B').value) || 0;
    const puntosTorneo = <?php echo (int)($torneo['puntos'] ?? 100); ?>;
    // Máximo permitido: puntos del torneo + 60% = puntosTorneo * 1.6
    const maximoPermitido = Math.round(puntosTorneo * 1.6);
    
    // Obtener estado de forfait y tarjetas
    let hayForfait = false;
    let hayTarjetaGrave = false;
    
    for (let i = 0; i < 4; i++) {
        const ff = document.getElementById('ff_' + i);
        if (ff && ff.checked) {
            hayForfait = true;
        }
        const tarjeta = parseInt(document.getElementById('tarjeta_' + i).value) || 0;
        if (tarjeta == 3 || tarjeta == 4) {
            hayTarjetaGrave = true;
        }
    }
    
    // Validación 1: No exceder máximo (puntos del torneo + 60%)
    if (puntosA > maximoPermitido) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'El monto es exagerado',
            confirmButtonColor: '#667eea'
        });
        const campoA = document.getElementById('puntos_pareja_A');
        if (campoA) {
            campoA.focus();
            campoA.select();
        }
        return false;
    }
    if (puntosB > maximoPermitido) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'El monto es exagerado',
            confirmButtonColor: '#667eea'
        });
        const campoB = document.getElementById('puntos_pareja_B');
        if (campoB) {
            campoB.focus();
            campoB.select();
        }
        return false;
    }
    
    // Validación 2: No deben ser iguales (a menos que haya forfait o tarjeta grave)
    if (puntosA == puntosB && puntosA > 0 && !hayForfait && !hayTarjetaGrave) {
        Swal.fire({
            icon: 'error',
            title: 'Error de validación',
            text: 'Los puntos no pueden ser iguales (' + puntosA + '). Debe haber un ganador o una falta (forfait/tarjeta roja/negra)',
            confirmButtonColor: '#667eea'
        });
        const campoActivo = document.activeElement;
        if (campoActivo && (campoActivo.id === 'puntos_pareja_A' || campoActivo.id === 'puntos_pareja_B')) {
            campoActivo.focus();
            campoActivo.select();
        }
        return false;
    }
    
    // Validación 3: Solo uno debe alcanzar o superar los puntos del torneo
    const parejaAAlcanzo = puntosA >= puntosTorneo;
    const parejaBAlcanzo = puntosB >= puntosTorneo;
    
    if (parejaAAlcanzo && parejaBAlcanzo && !hayForfait && !hayTarjetaGrave) {
        Swal.fire({
            icon: 'error',
            title: 'Error de validación',
            html: 'Solo una pareja puede alcanzar o superar los puntos del torneo (' + puntosTorneo + ').<br>Ambas alcanzaron: Pareja A: ' + puntosA + ', Pareja B: ' + puntosB,
            confirmButtonColor: '#667eea'
        });
        const campoActivo = document.activeElement;
        if (campoActivo && (campoActivo.id === 'puntos_pareja_A' || campoActivo.id === 'puntos_pareja_B')) {
            campoActivo.focus();
            campoActivo.select();
        }
        return false;
    }
    
    return true;
}

// Event listener para submit del formulario
document.addEventListener('DOMContentLoaded', function() {
    // Cuenta regresiva "Correcciones se cierran en" (se resetea a 20 min al guardar una corrección)
    const countdownCorrecciones = document.getElementById('countdown-correcciones');
    if (countdownCorrecciones) {
        const finTimestamp = parseInt(countdownCorrecciones.getAttribute('data-fin'), 10);
        function actualizar() {
            const ahora = Math.floor(Date.now() / 1000);
            let restante = finTimestamp - ahora;
            if (restante <= 0) {
                countdownCorrecciones.textContent = '00:00';
                countdownCorrecciones.closest('p').innerHTML = 'Correcciones cerradas. <span class="tabular-nums">00:00</span>';
                if (window.countdownCorreccionesInterval) clearInterval(window.countdownCorreccionesInterval);
                return;
            }
            const m = Math.floor(restante / 60), s = restante % 60;
            countdownCorrecciones.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }
        actualizar();
        window.countdownCorreccionesInterval = setInterval(actualizar, 1000);
    }

    const form = document.getElementById('formResultados');
    if (form) {
        form.addEventListener('submit', function(e) {
            // No prevenir el submit normal, solo validar y procesar
            if (!validarResultados()) {
                e.preventDefault();
                return false;
            }
            
            // Procesar radio buttons de pena
            procesarPena();
            
            // Distribuir puntos antes de enviar - asegurar que ambas parejas estén sincronizadas
            distribuirPuntos('todas');
            
            // Verificar que todos los campos estén correctamente actualizados
            for (let i = 0; i < 4; i++) {
                const r1 = document.getElementById('resultado1_' + i);
                const r2 = document.getElementById('resultado2_' + i);
                if (r1 && r2) {
                    console.log('Antes de enviar - Jugador ' + (i + 1) + ': r1=' + r1.value + ', r2=' + r2.value);
                }
            }
            
            console.log('Formulario enviado con datos actualizados');
        });
    }
    
    // Mostrar indicador de tarjeta por acumulación (80 pts) si ya viene con 80 en el formulario
    for (let i = 0; i < 4; i++) {
        validarSancionYTarjeta(i);
    }

    // Inicializar tarjetas visualmente (mostrar el estado actual)
    for (let i = 0; i < 4; i++) {
        const tarjetaInput = document.getElementById('tarjeta_' + i);
        if (tarjetaInput) {
            const tarjetaValue = parseInt(tarjetaInput.value) || 0;
            // Solo marcar si tiene una tarjeta seleccionada (1, 3 o 4), no si es 0
            if (tarjetaValue > 0) {
                // Remover clase activo de todos los botones de este jugador
                const botones = document.querySelectorAll('[data-index="' + i + '"].tarjeta-btn');
                botones.forEach(btn => btn.classList.remove('activo'));
                
                // Agregar clase activo al botón seleccionado
                const botonSeleccionado = document.querySelector('[data-index="' + i + '"][data-tarjeta="' + tarjetaValue + '"]');
                if (botonSeleccionado) {
                    botonSeleccionado.classList.add('activo');
                }
            } else {
                // Si es 0, remover cualquier selección visual
                const botones = document.querySelectorAll('[data-index="' + i + '"].tarjeta-btn');
                botones.forEach(btn => btn.classList.remove('activo'));
            }
        }
    }
    
    // Actualizar estado según textbox de mesa y botón guardar
    actualizarEstadoPorMesa();
    
    // Limpiar formulario si se acaba de guardar
    <?php if (isset($_SESSION['limpiar_formulario'])): ?>
        <?php unset($_SESSION['limpiar_formulario']); ?>
        limpiarFormularioSilencioso();
    <?php endif; ?>
    
    // Si se acaba de guardar, enfocar el textbox "ir a mesa" y limpiarlo
    <?php if (isset($_SESSION['resultados_guardados'])): ?>
        <?php unset($_SESSION['resultados_guardados']); ?>
        setTimeout(() => {
            const inputMesa = document.getElementById('input_ir_mesa');
            if (inputMesa) {
                inputMesa.value = '0';
                inputMesa.classList.remove('is-invalid', 'is-valid');
                inputMesa.setCustomValidity('');
                inputMesa.focus();
                actualizarEstadoPorMesa();
            }
        }, 100);
    <?php else: ?>
        // Enfocar el primer campo de puntos al cargar la página si no se acaba de guardar
        const puntosA = document.getElementById('puntos_pareja_A');
        if (puntosA) {
            setTimeout(() => {
                puntosA.focus();
                puntosA.select();
            }, 100);
        }
    <?php endif; ?>
    
    // Distribuir puntos inicialmente si ya hay valores cargados
    distribuirPuntos('todas');
    
    // Validar puntos al cargar la página
    validarPuntosEnTiempoReal();
    
    // Actualizar estado del botón cuando cambian los puntos
    const puntosAInput = document.getElementById('puntos_pareja_A');
    const puntosBInput = document.getElementById('puntos_pareja_B');
    if (puntosAInput) {
        puntosAInput.addEventListener('input', actualizarEstadoBotonGuardar);
        puntosAInput.addEventListener('change', actualizarEstadoBotonGuardar);
    }
    if (puntosBInput) {
        puntosBInput.addEventListener('input', actualizarEstadoBotonGuardar);
        puntosBInput.addEventListener('change', actualizarEstadoBotonGuardar);
    }
    
    // Listener en textbox de mesa para habilitar/deshabilitar controles
    const inputIrMesa = document.getElementById('input_ir_mesa');
    if (inputIrMesa) {
        inputIrMesa.addEventListener('input', actualizarEstadoPorMesa);
        inputIrMesa.addEventListener('change', actualizarEstadoPorMesa);
    }
    
    // Actualizar estado cuando cambian forfait o tarjetas
    for (let i = 0; i < 4; i++) {
        const ff = document.getElementById('ff_' + i);
        if (ff) {
            ff.addEventListener('change', function() {
                validarPuntosEnTiempoReal();
                actualizarEstadoBotonGuardar();
            });
        }
    }
    
    // Actualizar estado inicial
    actualizarEstadoBotonGuardar();
    
    
    // Procesar penas cuando cambian los radio buttons
    for (let i = 0; i < 4; i++) {
        const radios = document.querySelectorAll('input[name="pena_' + i + '"]');
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'chancleta') {
                    document.getElementById('chancleta_' + i).value = this.checked ? '1' : '0';
                    document.getElementById('zapato_' + i).value = '0';
                } else if (this.value === 'zapato') {
                    document.getElementById('zapato_' + i).value = this.checked ? '1' : '0';
                    document.getElementById('chancleta_' + i).value = '0';
                }
            });
        });
    }
});
</script>
