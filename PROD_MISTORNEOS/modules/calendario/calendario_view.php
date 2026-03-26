<?php
$page_title = $page_title ?? 'Calendario de Torneos';
?>
<style>
/* Calendario en dashboard - mismos estilos que landing */
#calendario-dash .cal-contenedor-anual {
    height: calc(100vh - 200px);
    min-height: 380px;
    max-height: 75vh;
    overflow: hidden;
    max-width: 1200px;
    margin: 0 auto;
}
#calendario-dash #grid-anual {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    grid-template-rows: repeat(3, 1fr);
    gap: 6px;
    height: 100%;
    overflow: hidden;
}
#calendario-dash .cal-mini {
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
#calendario-dash .cal-mini .cal-grid-unico {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-auto-rows: minmax(0, 1fr);
    gap: 1px;
    padding: 2px;
}
#calendario-dash .cal-mini .cal-dia-celda {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: clamp(6px, 1.2vw, 10px);
    border-radius: 2px;
    cursor: pointer;
    position: relative;
}
#calendario-dash .cal-indicadores-multiples {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 2px;
    margin-top: 2px;
}
#calendario-dash .cal-mini .cal-indicadores-multiples { gap: 1px; margin-top: 1px; }
#calendario-dash .cal-dot-actividad { border-radius: 50%; flex-shrink: 0; }
#calendario-dash .cal-mini .cal-dot-actividad { width: 4px; height: 4px; }
#calendario-dash .cal-mes-ampliado .cal-dot-actividad { width: 8px; height: 8px; }
#calendario-dash .cal-fondo-rojo { background-color: #dc3545 !important; }
#calendario-dash .cal-fondo-verde { background-color: #198754 !important; }
#calendario-dash .cal-fondo-azul { background-color: #0d6efd !important; }
#calendario-dash #cal-mes-header,
#calendario-dash #grid-mes-ampliado { grid-template-columns: repeat(7, minmax(0, 1fr)); }
@media (max-width: 640px) {
    #calendario-dash #grid-anual { grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(4, 1fr); }
    #calendario-dash .cal-contenedor-anual { height: calc(100vh - 160px); }
}
#calendario-dash .landing-logo-org { max-height: 60px; width: auto; height: auto; object-fit: contain; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h4 mb-0"><i class="fas fa-calendar-alt text-primary me-2"></i>Calendario de Torneos</h1>
        <a href="<?= htmlspecialchars($base_url_public) ?>landing.php#calendario" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
            <i class="fas fa-external-link-alt me-1"></i>Ver en sitio público
        </a>
    </div>

    <section id="calendario-dash" class="rounded-3 p-3 p-md-4" style="background-color: #83e3f7;">
        <p class="text-center text-muted small mb-3">Haz clic en un mes para ampliarlo. Selecciona una fecha con eventos.</p>

        <div id="vista-anual" class="cal-vista">
            <div class="d-flex justify-content-center align-items-center gap-2 mb-2">
                <button type="button" id="cal-year-prev" class="btn btn-sm btn-light"><i class="fas fa-chevron-left"></i></button>
                <h3 id="cal-year-display" class="h5 mb-0 px-3 fw-bold"></h3>
                <button type="button" id="cal-year-next" class="btn btn-sm btn-light"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="cal-contenedor-anual">
                <div id="grid-anual"></div>
            </div>
        </div>

        <div id="vista-mes" class="cal-vista d-none">
            <div id="contenedor-grid-mes">
                <a href="#" id="btn-volver-anual" class="btn btn-sm btn-light mb-3"><i class="fas fa-arrow-left me-1"></i>Volver</a>
                <div class="bg-white rounded shadow overflow-hidden max-w-5xl mx-auto cal-mes-ampliado">
                    <div class="px-4 py-3 bg-light border-bottom">
                        <h3 id="mes-ampliado-titulo" class="h5 mb-0 fw-bold"></h3>
                    </div>
                    <div class="p-4">
                        <div id="cal-mes-header" class="d-grid gap-2 mb-2" style="grid-template-columns: repeat(7, 1fr);"></div>
                        <div id="grid-mes-ampliado" class="d-grid gap-2" style="grid-template-columns: repeat(7, 1fr); min-height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div id="seccion-eventos-dia" class="d-none rounded-3 overflow-hidden text-white p-4 mt-4 shadow" style="background: linear-gradient(135deg, #6b21a8, #4c1d95);">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <h4 id="eventos-dia-titulo" class="h5 mb-0 fw-bold">Torneos del día</h4>
                    <a href="#" id="btn-volver-mes" class="btn btn-sm btn-light btn-outline-light"><i class="fas fa-arrow-left me-1"></i>Volver al calendario</a>
                </div>
                <div id="lista-eventos-dia" class="row g-3"></div>
            </div>
        </div>
    </section>
</div>

<script>
(function() {
    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const diasSemana = ['Do','Lu','Ma','Mi','Ju','Vi','Sa'];
    const eventosPorFecha = <?= json_encode($eventos_por_fecha) ?>;
    const baseUrl = <?= json_encode($base_url_public) ?>;

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

    function diaSemanaDomPrimero(date) { return date.getDay(); }

    const coloresActividad = ['#0d9488', '#d97706', '#2563eb', '#7c3aed', '#059669', '#dc2626', '#0891b2'];
    function renderIndicadoresActividad(cantidad) {
        if (cantidad <= 0) return '';
        const n = Math.min(cantidad, 6);
        let html = '<span class="cal-indicadores-multiples" title="' + cantidad + ' torneo(s)">';
        for (let i = 0; i < n; i++) {
            html += '<span class="cal-dot-actividad" style="background:' + coloresActividad[i % coloresActividad.length] + '"></span>';
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
        if (!tieneEventos) return fechaStr === hoyStr ? 'bg-warning bg-opacity-25 text-dark fw-semibold' : 'bg-white text-secondary';
        if (fechaStr < hoyStr) return 'cal-fondo-rojo text-white fw-semibold'; // pasados
        if (fechaStr === hoyStr || fechaStr === mananaStr) return 'cal-fondo-verde text-white fw-semibold'; // próximas 24h
        return 'cal-fondo-azul text-white fw-semibold'; // próximas
    }

    function renderMiniCalendario(anio, mes) {
        const primerDia = new Date(anio, mes, 1);
        const ultimoDia = new Date(anio, mes + 1, 0).getDate();
        const inicioOffset = diaSemanaDomPrimero(primerDia);
        const hoy = new Date();
        const hoyStr = hoy.getFullYear() + '-' + String(hoy.getMonth()+1).padStart(2,'0') + '-' + String(hoy.getDate()).padStart(2,'0');

        let html = '<div class="cal-mini bg-white border rounded shadow-sm">';
        html += '<a href="#" class="cal-link-mes d-block px-1 py-1 bg-light border-bottom text-center small fw-bold text-decoration-none text-dark" data-mes="' + mes + '" data-anio="' + anio + '">' + meses[mes] + '</a>';
        html += '<div class="cal-grid-unico">';
        diasSemana.forEach(function(d) { html += '<div class="cal-dia-celda bg-light small fw-semibold text-muted">' + d + '</div>'; });
        for (let i = 0; i < inicioOffset; i++) html += '<div class="cal-dia-celda bg-light"></div>';
        for (let d = 1; d <= ultimoDia; d++) {
            const fechaStr = anio + '-' + String(mes+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            const eventos = eventosPorFecha[fechaStr] || [];
            const tieneEventos = eventos.length > 0;
            const esHoy = fechaStr === hoyStr;
            let cls = 'cal-dia-celda cal-dia-mini ';
            cls += claseFondoPorFecha(fechaStr, tieneEventos);
            if (!tieneEventos && esHoy) cls += ' border border-warning';
            html += '<div class="' + cls + '" data-fecha="' + fechaStr + '" data-mes="' + mes + '" data-anio="' + anio + '">' + d + (tieneEventos ? renderIndicadoresActividad(eventos.length) : '') + '</div>';
        }
        html += '</div></div>';
        return html;
    }

    function renderVistaAnual() {
        document.getElementById('cal-year-display').textContent = calAnio;
        let html = '';
        for (let m = 0; m < 12; m++) html += renderMiniCalendario(calAnio, m);
        gridAnual.innerHTML = html;
        gridAnual.querySelectorAll('.cal-link-mes').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                calMes = parseInt(this.getAttribute('data-mes'));
                calAnio = parseInt(this.getAttribute('data-anio'));
                mostrarVistaMes(null);
            });
        });
        gridAnual.querySelectorAll('.cal-dia-mini').forEach(function(celda) {
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
        vistaAnual.classList.add('d-none');
        vistaMes.classList.remove('d-none');
        contenedorGridMes.classList.remove('d-none');
        seccionEventosDia.classList.add('d-none');
        document.getElementById('mes-ampliado-titulo').textContent = meses[calMes] + ' ' + calAnio;

        const primerDia = new Date(calAnio, calMes, 1);
        const ultimoDia = new Date(calAnio, calMes + 1, 0).getDate();
        const inicioOffset = diaSemanaDomPrimero(primerDia);
        const hoy = new Date();
        const hoyStr = hoy.getFullYear() + '-' + String(hoy.getMonth()+1).padStart(2,'0') + '-' + String(hoy.getDate()).padStart(2,'0');

        let headerHtml = '';
        diasSemana.forEach(function(d) { headerHtml += '<div class="py-2 text-center small fw-bold text-secondary bg-light rounded">' + d + '</div>'; });
        calMesHeader.innerHTML = headerHtml;

        let html = '';
        for (let i = 0; i < inicioOffset; i++) html += '<div class="p-1 rounded bg-light" style="min-height: 54px;"></div>';
        for (let d = 1; d <= ultimoDia; d++) {
            const fechaStr = calAnio + '-' + String(calMes+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            const eventos = eventosPorFecha[fechaStr] || [];
            const tieneEventos = eventos.length > 0;
            const esHoy = fechaStr === hoyStr;
            let cls = 'p-1 rounded d-flex flex-column align-items-center justify-content-center cursor-pointer ';
            cls += tieneEventos ? claseFondoPorFecha(fechaStr, true) : (esHoy ? 'bg-warning bg-opacity-25 text-dark fw-semibold' : 'bg-light text-secondary');
            if (esHoy && !tieneEventos) cls += ' ring-2 ring-warning';
            html += '<div class="cal-dia-ampliado ' + cls + '" data-fecha="' + fechaStr + '" style="min-height: 54px;">' + d + (tieneEventos ? renderIndicadoresActividad(eventos.length) : '') + '</div>';
        }
        gridMesAmpliado.innerHTML = html;

        gridMesAmpliado.querySelectorAll('.cal-dia-ampliado').forEach(function(celda) {
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

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function renderTarjetaEvento(ev) {
        var esPasado = new Date(ev.fechator) < new Date();
        var permiteOnline = parseInt(ev.permite_inscripcion_linea || 1) === 1;
        var esMasivo = [1,2,3].indexOf(parseInt(ev.es_evento_masivo || 0)) >= 0;
        var telContacto = ev.admin_celular || ev.club_telefono || '';
        var modalidad = modalidades[parseInt(ev.modalidad)||1] || 'Individual';
        var clase = clases[parseInt(ev.clase)||1] || 'Torneo';
        var fechaDmY = new Date(ev.fechator).toLocaleDateString('es-VE', { day:'2-digit', month:'2-digit', year:'numeric' });
        var nombreTorneo = escapeHtml(ev.nombre_limpio || ev.nombre || '');

        var html = '<div class="col-12 col-md-6 col-lg-4"><div class="card border-0 shadow-sm h-100 overflow-hidden">';
        html += '<div class="card-img-top bg-light d-flex flex-column align-items-center justify-content-center p-4" style="min-height: 140px;">';
        if (ev.logo_url) html += '<img src="' + escapeHtml(ev.logo_url) + '" alt="" class="landing-logo-org mb-2" loading="lazy">';
        html += '<span class="fw-bold text-dark">' + escapeHtml(ev.organizacion_nombre || 'Organizador') + '</span></div>';
        html += '<div class="card-body">';
        html += '<span class="badge bg-warning text-dark mb-2"><i class="fas fa-calendar me-1"></i>' + fechaDmY + '</span>';
        html += '<h5 class="card-title">' + nombreTorneo + '</h5>';
        html += '<p class="small text-muted mb-2"><i class="fas fa-map-marker-alt me-1 text-warning"></i>' + escapeHtml(ev.lugar || 'No especificado') + '</p>';
        html += '<div class="d-flex flex-wrap gap-1 mb-3">';
        html += '<span class="badge bg-primary">' + clase + '</span>';
        html += '<span class="badge bg-info">' + modalidad + '</span>';
        if (parseFloat(ev.costo) > 0) html += '<span class="badge bg-success">$' + parseFloat(ev.costo).toFixed(2) + '</span>';
        html += '<span class="badge bg-warning text-dark"><i class="fas fa-users me-1"></i>' + (ev.total_inscritos||0) + ' inscritos</span>';
        html += '</div>';
        if (esPasado) {
            html += '<a href="' + baseUrl + 'resultados_detalle.php?torneo_id=' + ev.id + '" class="btn btn-success btn-sm w-100"><i class="fas fa-trophy me-1"></i>Ver Resultados</a>';
        } else if (permiteOnline) {
            var urlInsc = esMasivo ? (baseUrl + 'inscribir_evento_masivo.php?torneo_id=' + ev.id) : (baseUrl + 'tournament_register.php?torneo_id=' + ev.id);
            html += '<a href="' + urlInsc + '" class="btn btn-warning btn-sm w-100"><i class="fas fa-mobile-alt me-1"></i>Inscribirme</a>';
        } else {
            html += '<div class="small text-muted">Inscripción en sitio. ';
            if (telContacto) html += '<a href="tel:' + telContacto.replace(/\D/g,'') + '" class="text-decoration-none">Contactar</a>';
            else html += 'Consulta con el organizador.';
            html += '</div>';
        }
        html += '</div></div></div>';
        return html;
    }

    function mostrarEventosEnPagina(fechaStr, eventos) {
        var parts = fechaStr.split('-');
        document.getElementById('eventos-dia-titulo').textContent = 'Torneos del ' + parts[2] + '/' + parts[1] + '/' + parts[0];
        contenedorGridMes.classList.add('d-none');
        seccionEventosDia.classList.remove('d-none');
        seccionEventosDia.scrollIntoView({ behavior: 'smooth' });

        if (eventos.length === 0) {
            listaEventosDia.innerHTML = '<div class="col-12"><p class="text-white-50 text-center py-4 mb-0">No hay torneos para esta fecha.</p></div>';
        } else {
            listaEventosDia.innerHTML = eventos.map(function(ev) { return renderTarjetaEvento(ev); }).join('');
        }
    }

    document.getElementById('btn-volver-anual').addEventListener('click', function(e) {
        e.preventDefault();
        vistaMes.classList.add('d-none');
        vistaAnual.classList.remove('d-none');
        renderVistaAnual();
    });

    document.getElementById('btn-volver-mes').addEventListener('click', function(e) {
        e.preventDefault();
        seccionEventosDia.classList.add('d-none');
        contenedorGridMes.classList.remove('d-none');
        contenedorGridMes.scrollIntoView({ behavior: 'smooth' });
    });

    document.getElementById('cal-year-prev').addEventListener('click', function() { calAnio--; renderVistaAnual(); });
    document.getElementById('cal-year-next').addEventListener('click', function() { calAnio++; renderVistaAnual(); });

    renderVistaAnual();
})();
</script>
