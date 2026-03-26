<?php
/**
 * Galería de Fotos del Club
 * Permite subir y gestionar fotos de un club (máximo 20 fotos)
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/file_upload.php';

// Verificar permisos
Auth::requireRole(['admin_club']);

$pdo = DB::pdo();
$user = Auth::user();

// Obtener el club del admin_club
$club_id = $user['club_id'] ?? null;

if (!$club_id) {
    require_once __DIR__ . '/../../lib/app_helpers.php';
    header('Location: ' . AppHelpers::dashboard('home&error=' . urlencode('No tiene un club asignado')));
    exit;
}

// Obtener información del club
$stmt = $pdo->prepare("SELECT id, nombre, delegado, telefono FROM clubes WHERE id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    require_once __DIR__ . '/../../lib/app_helpers.php';
    header('Location: ' . AppHelpers::dashboard('home&error=' . urlencode('Club no encontrado')));
    exit;
}

// Configuración
$maxFotos = 20; // Límite máximo de fotos por club
$maxFileSize = 10 * 1024 * 1024; // 10MB

// Obtener fotos del club
$fotos = [];
$totalFotos = 0;

try {
    // Verificar si la tabla existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'club_photos'");
    $tabla_existe = $stmt_check->rowCount() > 0;
    
    if ($tabla_existe) {
        $stmt = $pdo->prepare("
            SELECT 
                cp.*,
                u.nombre as subido_por_nombre
            FROM club_photos cp
            LEFT JOIN usuarios u ON cp.subido_por = u.id
            WHERE cp.club_id = ?
            ORDER BY cp.orden ASC, cp.fecha_subida DESC
        ");
        $stmt->execute([$club_id]);
        $fotos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalFotos = count($fotos);
    }
} catch (Exception $e) {
    error_log("Error obteniendo fotos: " . $e->getMessage());
}

// Obtener siguiente orden
$siguienteOrden = $totalFotos + 1;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0">
        <i class="fas fa-images me-2"></i>Galería de Fotos del Club
    </h2>
</div>

<!-- Información del Club -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0 text-white">
            <i class="fas fa-building me-2"></i><?= htmlspecialchars($club['nombre']) ?>
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <?php if ($club['delegado']): ?>
                    <p class="mb-2">
                        <i class="fas fa-user me-2 text-primary"></i>
                        <strong>Delegado:</strong> <?= htmlspecialchars($club['delegado']) ?>
                    </p>
                <?php endif; ?>
                <?php if ($club['telefono']): ?>
                    <p class="mb-2">
                        <i class="fas fa-phone me-2 text-primary"></i>
                        <strong>Teléfono:</strong> <?= htmlspecialchars($club['telefono']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-0">
                    <i class="fas fa-images me-2 text-primary"></i>
                    <strong>Fotos:</strong> <span class="badge bg-primary"><?= $totalFotos ?></span> / <?= $maxFotos ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Área de Subida -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-upload me-2"></i>Subir Fotos
        </h5>
    </div>
    <div class="card-body">
        <?php if ($totalFotos >= $maxFotos): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Límite alcanzado:</strong> Has alcanzado el límite máximo de <?= $maxFotos ?> fotos. Elimina algunas fotos para subir nuevas.
            </div>
        <?php else: ?>
            <div class="border border-2 border-dashed border-primary rounded p-4 text-center bg-light">
                <input type="file" 
                       id="input-foto" 
                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" 
                       multiple
                       class="d-none">
                <label for="input-foto" class="cursor-pointer">
                    <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3 d-block"></i>
                    <p class="fw-bold mb-2">Haz clic para seleccionar fotos</p>
                    <p class="text-muted mb-1">Máximo 10MB por foto</p>
                    <p class="text-muted small mb-2">Formatos: JPG, PNG, GIF, WEBP</p>
                    <span class="badge bg-primary">
                        Puedes subir hasta <?= $maxFotos - $totalFotos ?> foto(s) más
                    </span>
                </label>
            </div>
            
            <!-- Vista Previa de Fotos -->
            <div id="preview-container" class="mt-4 d-none">
                <h6 class="mb-3">
                    <i class="fas fa-eye me-2"></i>Vista Previa de Fotos Seleccionadas
                </h6>
                <div id="preview-grid" class="row g-3"></div>
                <div class="mt-3 text-end">
                    <button id="btn-subir-fotos" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Subir Fotos
                    </button>
                    <button id="btn-cancelar-preview" class="btn btn-secondary ms-2">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                </div>
            </div>
            
            <div id="upload-progress" class="d-none mt-3">
                <div class="alert alert-info">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-spinner fa-spin me-3"></i>
                        <span>Subiendo fotos...</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Galería de Fotos -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-images me-2"></i>Fotos del Club 
            <span class="badge bg-primary"><?= $totalFotos ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($fotos)): ?>
            <div class="text-center py-5">
                <i class="fas fa-images fa-4x text-muted mb-3"></i>
                <p class="text-muted mb-2">No hay fotos subidas aún</p>
                <p class="text-muted small">Sube fotos del club para crear una galería</p>
            </div>
        <?php else: ?>
            <div class="row g-3" id="galeria-fotos">
                <?php foreach ($fotos as $foto): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6" data-foto-id="<?= $foto['id'] ?>">
                        <div class="position-relative">
                            <div class="ratio ratio-1x1 rounded overflow-hidden shadow-sm mb-2">
                                <?php 
                                // Construir URL de la imagen usando app_base_url
                                $ruta_imagen = $foto['ruta_imagen'];
                                if (strpos($ruta_imagen, 'upload/') === 0) {
                                    $imagenUrl = app_base_url() . '/' . $ruta_imagen;
                                } else {
                                    $imagenUrl = app_base_url() . '/upload/clubs/photos/' . basename($ruta_imagen);
                                }
                                ?>
                                <img src="<?= htmlspecialchars($imagenUrl) ?>" 
                                     alt="Foto del club"
                                     class="w-100 h-100 object-fit-cover"
                                     loading="lazy"
                                     onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'d-flex align-items-center justify-content-center h-100 bg-light\'><i class=\'fas fa-image fa-2x text-muted\'></i></div>';">
                            </div>
                            <button onclick="eliminarFoto(<?= $foto['id'] ?>, this)" 
                                    class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle"
                                    title="Eliminar foto"
                                    style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="text-center">
                                <small class="text-muted d-block">Orden: <?= $foto['orden'] ?></small>
                                <?php if ($foto['fecha_subida']): ?>
                                    <small class="text-muted">
                                        <?= date('d/m/Y', strtotime($foto['fecha_subida'])) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const clubId = <?= $club_id ?>;
const maxFotos = <?= $maxFotos ?>;
let totalFotos = <?= $totalFotos ?>;

// Efecto hover en el área de subida
document.getElementById('input-foto')?.addEventListener('mouseenter', function() {
    const overlay = document.getElementById('upload-overlay');
    if (overlay) overlay.style.opacity = '0.1';
});

document.getElementById('input-foto')?.closest('div')?.addEventListener('mouseleave', function() {
    const overlay = document.getElementById('upload-overlay');
    if (overlay) overlay.style.opacity = '0';
});

let selectedFiles = [];
let filePreviews = new Map();

// Mostrar vista previa de fotos seleccionadas
document.getElementById('input-foto')?.addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    
    if (files.length === 0) return;
    
    // Verificar límite
    if (totalFotos + files.length > maxFotos) {
        alert(`⚠️ Solo puedes subir ${maxFotos - totalFotos} foto(s) más. El límite es de ${maxFotos} fotos por club.`);
        this.value = '';
        return;
    }
    
    // Validar archivos
    const validFiles = [];
    const invalidFiles = [];
    
    files.forEach(file => {
        // Validar tamaño (10MB)
        if (file.size > <?= $maxFileSize ?>) {
            invalidFiles.push(`${file.name}: El archivo es demasiado grande (máximo 10MB)`);
            return;
        }
        
        // Validar tipo
        if (!file.type.match(/^image\/(jpeg|jpg|png|gif|webp)$/)) {
            invalidFiles.push(`${file.name}: Formato no válido`);
            return;
        }
        
        validFiles.push(file);
    });
    
    if (invalidFiles.length > 0) {
        alert(`⚠️ Algunos archivos no son válidos:\n${invalidFiles.join('\n')}`);
    }
    
    if (validFiles.length === 0) {
        this.value = '';
        return;
    }
    
    // Agregar archivos válidos a la lista
    selectedFiles = [...selectedFiles, ...validFiles];
    
    // Mostrar vista previa
    mostrarVistaPrevia();
    
    // Limpiar input para permitir seleccionar más
    this.value = '';
});

// Función para mostrar vista previa
function mostrarVistaPrevia() {
    const previewContainer = document.getElementById('preview-container');
    const previewGrid = document.getElementById('preview-grid');
    
    if (!previewContainer || !previewGrid) return;
    
    previewContainer.classList.remove('d-none');
    previewGrid.innerHTML = '';
    
    selectedFiles.forEach((file, index) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const col = document.createElement('div');
            col.className = 'col-lg-3 col-md-4 col-sm-6';
            col.setAttribute('data-file-index', index);
            
            col.innerHTML = `
                <div class="card position-relative">
                    <div class="ratio ratio-1x1">
                        <img src="${e.target.result}" 
                             alt="Vista previa" 
                             class="w-100 h-100 object-fit-cover rounded-top">
                    </div>
                    <div class="card-body p-2">
                        <small class="text-muted d-block text-truncate" title="${file.name}">
                            ${file.name}
                        </small>
                        <small class="text-muted">
                            ${(file.size / 1024 / 1024).toFixed(2)} MB
                        </small>
                    </div>
                    <button type="button" 
                            class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle"
                            onclick="eliminarDePreview(${index})"
                            style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            previewGrid.appendChild(col);
        };
        
        reader.readAsDataURL(file);
    });
}

// Eliminar de vista previa
function eliminarDePreview(index) {
    selectedFiles.splice(index, 1);
    
    if (selectedFiles.length === 0) {
        document.getElementById('preview-container')?.classList.add('d-none');
    } else {
        mostrarVistaPrevia();
    }
}

// Botón cancelar vista previa
document.getElementById('btn-cancelar-preview')?.addEventListener('click', function() {
    selectedFiles = [];
    document.getElementById('preview-container')?.classList.add('d-none');
    document.getElementById('input-foto').value = '';
});

// Botón subir fotos
document.getElementById('btn-subir-fotos')?.addEventListener('click', async function() {
    if (selectedFiles.length === 0) {
        alert('No hay fotos seleccionadas para subir');
        return;
    }
    
    // Deshabilitar botón
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Subiendo...';
    
    // Mostrar progreso
    const progressDiv = document.getElementById('upload-progress');
    if (progressDiv) {
        progressDiv.classList.remove('d-none');
    }
    
    let subidasExitosas = 0;
    let errores = [];
    
    // Subir cada archivo
    for (const file of selectedFiles) {
        const formData = new FormData();
        formData.append('foto', file);
        formData.append('club_id', clubId);
        
        try {
            const response = await fetch('<?= app_base_url() ?>/public/api/club_photos_upload.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                subidasExitosas++;
                totalFotos++;
            } else {
                errores.push(`${file.name}: ${result.error || 'Error al subir'}`);
            }
        } catch (error) {
            errores.push(`${file.name}: Error de conexión`);
        }
    }
    
    // Ocultar progreso
    if (progressDiv) {
        progressDiv.classList.add('d-none');
    }
    
    // Restaurar botón
    this.disabled = false;
    this.innerHTML = '<i class="fas fa-upload me-2"></i>Subir Fotos';
    
    // Mostrar resultados
    if (subidasExitosas > 0) {
        if (errores.length > 0) {
            alert(`✅ ${subidasExitosas} foto(s) subida(s) correctamente.\n\n❌ Errores:\n${errores.join('\n')}`);
        } else {
            alert(`✅ ${subidasExitosas} foto(s) subida(s) correctamente.`);
        }
        
        // Limpiar vista previa
        selectedFiles = [];
        document.getElementById('preview-container')?.classList.add('d-none');
        
        window.location.reload();
    } else {
        alert(`❌ No se pudo subir ninguna foto.\n\nErrores:\n${errores.join('\n')}`);
    }
});

async function eliminarFoto(fotoId, button) {
    if (!confirm('¿Estás seguro de eliminar esta foto?\n\nEsta acción no se puede deshacer.')) {
        return;
    }
    
    // Deshabilitar botón
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.style.pointerEvents = 'none';
    
    try {
        const response = await fetch('<?= app_base_url() ?>/public/api/club_photos_delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                foto_id: fotoId,
                club_id: clubId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Foto eliminada correctamente');
            window.location.reload();
        } else {
            alert('❌ ' + (data.error || 'No se pudo eliminar la foto'));
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-trash"></i>';
            button.style.pointerEvents = 'auto';
        }
    } catch (error) {
        alert('❌ Error de conexión. Por favor, intenta nuevamente.');
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-trash"></i>';
        button.style.pointerEvents = 'auto';
    }
}
</script>

<style>
.cursor-pointer {
    cursor: pointer;
}

.cursor-pointer:hover {
    opacity: 0.8;
}

.ratio {
    position: relative;
    width: 100%;
}

.ratio::before {
    content: "";
    display: block;
    padding-bottom: 100%;
}

.ratio > * {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.object-fit-cover {
    object-fit: cover;
}
</style>
