<?php
/**
 * Generar Códigos QR Personalizados por Cédula
 * Permite generar códigos QR personalizados para atletas específicos
 * Incluye acceso a: mesas, resumen, listado general e incidencias
 */

$pdo = DB::pdo();
$base_url = app_base_url();

// URL base para la información del torneo
$torneo_info_url = $base_url . '/public/torneo_info.php?torneo_id=' . $torneo_id;

// Función para generar URL de QR
function generarQRUrl($data, $size = 300) {
    return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => $size . 'x' . $size,
        'data' => $data,
        'format' => 'png',
        'margin' => 10,
        'qzone' => 1
    ]);
}
?>
<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">
            <i class="fas fa-user-qrcode me-2"></i>Generar QR Personalizado por Cédula
        </h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Información:</strong> Ingrese la cédula de un atleta para generar un código QR personalizado que incluye acceso a sus mesas asignadas, resumen personal y toda la información del torneo.
        </div>
        
        <form id="formQRPersonalizado" class="row g-3 mb-4">
            <div class="col-md-8">
                <label for="cedula_qr" class="form-label fw-bold">Cédula del Atleta</label>
                <input type="text" 
                       class="form-control form-control-lg" 
                       id="cedula_qr" 
                       name="cedula" 
                       placeholder="Ej: V12345678"
                       required>
                <small class="text-muted">Ingrese la cédula sin guiones ni espacios</small>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-success btn-lg w-100">
                    <i class="fas fa-qrcode me-2"></i>Generar QR
                </button>
            </div>
        </form>
        
        <div id="qrPersonalizadoContainer" class="mt-4" style="display: none;">
            <div class="row">
                <div class="col-md-6">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white text-center">
                            <h6 class="mb-0">QR Personalizado</h6>
                        </div>
                        <div class="card-body text-center">
                            <img id="qrPersonalizadoImg" 
                                 src="" 
                                 alt="QR Personalizado" 
                                 class="img-fluid mb-3 border rounded p-2 bg-white"
                                 style="max-width: 300px;">
                            <div class="d-grid gap-2">
                                <button type="button" 
                                        class="btn btn-success"
                                        onclick="descargarQRPersonalizado()">
                                    <i class="fas fa-download me-1"></i>Descargar QR
                                </button>
                                <button type="button" 
                                        class="btn btn-outline-secondary"
                                        onclick="copiarEnlacePersonalizado()">
                                    <i class="fas fa-copy me-1"></i>Copiar Enlace
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">Enlaces Disponibles</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                El QR personalizado permite acceso a todas estas secciones:
                            </p>
                            <div class="d-grid gap-2">
                                <a id="linkGeneral" href="" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-1"></i>Listado General
                                </a>
                                <a id="linkMesas" href="" class="btn btn-outline-info">
                                    <i class="fas fa-table me-1"></i>Mis Mesas
                                </a>
                                <a id="linkResumen" href="" class="btn btn-outline-success">
                                    <i class="fas fa-chart-line me-1"></i>Mi Resumen
                                </a>
                                <a id="linkIncidencias" href="" class="btn btn-outline-warning">
                                    <i class="fas fa-info-circle me-1"></i>Incidencias
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Instrucciones -->
        <div class="alert alert-light mt-4">
            <h6 class="alert-heading">
                <i class="fas fa-lightbulb me-2"></i>Instrucciones de Uso
            </h6>
            <ol class="mb-0">
                <li>Ingrese la cédula del atleta sin guiones ni espacios</li>
                <li>Haga clic en "Generar QR" para crear el código personalizado</li>
                <li>Descargue o imprima el código QR generado</li>
                <li>Entregue el QR al atleta para que pueda acceder a su información personal</li>
                <li>El QR incluye acceso a mesas asignadas, resumen personal y toda la información del torneo</li>
            </ol>
        </div>
    </div>
</div>

<script>
let qrPersonalizadoUrl = '';
let qrPersonalizadoEnlaces = {};

function descargarQRPersonalizado() {
    if (qrPersonalizadoUrl) {
        const cedula = document.getElementById('cedula_qr').value.trim();
        descargarQR(qrPersonalizadoUrl, `qr_personalizado_${cedula}_torneo_<?= $torneo_id ?>.png`);
    }
}

function copiarEnlacePersonalizado() {
    if (qrPersonalizadoEnlaces.general) {
        copiarEnlace(qrPersonalizadoEnlaces.general);
    }
}

function descargarQR(qrUrl, filename) {
    const link = document.createElement('a');
    link.href = qrUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    mostrarNotificacion('QR descargado exitosamente', 'success');
}

function copiarEnlace(url) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            mostrarNotificacion('Enlace copiado al portapapeles', 'success');
        }).catch(() => {
            fallbackCopiar(url);
        });
    } else {
        fallbackCopiar(url);
    }
}

function fallbackCopiar(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
        mostrarNotificacion('Enlace copiado al portapapeles', 'success');
    } catch (err) {
        mostrarNotificacion('Error al copiar. Seleccione manualmente el enlace.', 'danger');
    }
    document.body.removeChild(textArea);
}

function mostrarNotificacion(mensaje, tipo) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alert.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 3000);
}

// Formulario QR Personalizado
document.getElementById('formQRPersonalizado').addEventListener('submit', function(e) {
    e.preventDefault();
    const cedula = document.getElementById('cedula_qr').value.trim();
    
    if (!cedula) {
        mostrarNotificacion('Por favor ingrese una cédula', 'warning');
        return;
    }
    
    const baseUrl = '<?= htmlspecialchars($base_url) ?>';
    const torneoId = <?= $torneo_id ?>;
    
    // Generar URLs
    const urlGeneral = `${baseUrl}/public/torneo_info.php?torneo_id=${torneoId}&seccion=general&cedula=${encodeURIComponent(cedula)}`;
    const urlMesas = `${baseUrl}/public/torneo_info.php?torneo_id=${torneoId}&seccion=mesas&cedula=${encodeURIComponent(cedula)}`;
    const urlResumen = `${baseUrl}/public/torneo_info.php?torneo_id=${torneoId}&seccion=resumen&cedula=${encodeURIComponent(cedula)}`;
    const urlIncidencias = `${baseUrl}/public/torneo_info.php?torneo_id=${torneoId}&seccion=incidencias&cedula=${encodeURIComponent(cedula)}`;
    
    // Guardar URLs
    qrPersonalizadoEnlaces = {
        general: urlGeneral,
        mesas: urlMesas,
        resumen: urlResumen,
        incidencias: urlIncidencias
    };
    
    // Generar QR con URL general (que incluye todas las opciones)
    qrPersonalizadoUrl = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodeURIComponent(urlGeneral)}&format=png&margin=10&qzone=1`;
    
    // Mostrar QR
    document.getElementById('qrPersonalizadoImg').src = qrPersonalizadoUrl;
    document.getElementById('linkGeneral').href = urlGeneral;
    document.getElementById('linkMesas').href = urlMesas;
    document.getElementById('linkResumen').href = urlResumen;
    document.getElementById('linkIncidencias').href = urlIncidencias;
    
    document.getElementById('qrPersonalizadoContainer').style.display = 'block';
    document.getElementById('qrPersonalizadoContainer').scrollIntoView({ behavior: 'smooth' });
    
    mostrarNotificacion('QR personalizado generado exitosamente', 'success');
});
</script>




