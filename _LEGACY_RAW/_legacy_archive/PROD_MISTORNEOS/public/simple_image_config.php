<?php
/**
 * Configuración y funciones helper para el procesamiento de imágenes simples.
 * Usa la función central image_url() para que las imágenes se muestren en cualquier contexto.
 */

// Asegurar que AppHelpers esté disponible (bootstrap ya cargado desde clubs u otros módulos)
if (!class_exists('AppHelpers')) {
    require_once __DIR__ . '/../config/bootstrap.php';
}

/**
 * URL centralizada para mostrar cualquier imagen (logos, fotos, etc.).
 * Usar SIEMPRE esta función para enlazar imágenes y evitar rutas rotas.
 * @param string|null $image_path Ruta relativa al proyecto, ej: upload/logos/logo_1.jpg
 * @return string URL para usar en src="..." o string vacío
 */
function image_url(?string $image_path): string {
    return AppHelpers::imageUrl($image_path);
}

/**
 * @deprecated Usar image_url() en su lugar. Se mantiene por compatibilidad.
 */
function getSimpleImageUrl(?string $image_path): string {
    return image_url($image_path);
}

/**
 * Muestra el logo del club en una tabla
 * @param array $club Datos del club
 * @return string HTML del logo
 */
function displayClubLogoTable(array $club): string {
    $logo = $club['logo'] ?? '';
    
    if (empty($logo)) {
        return '<span class="text-muted"><i class="fas fa-image"></i> Sin logo</span>';
    }
    
    $logo_url = image_url($logo);
    
    return sprintf(
        '<img src="%s" alt="Logo %s" class="img-thumbnail" style="max-width: 50px; max-height: 50px; cursor: pointer;" onclick="showLogoModal(\'%s\', \'%s\')">',
        htmlspecialchars($logo_url),
        htmlspecialchars($club['nombre'] ?? ''),
        htmlspecialchars($logo_url),
        htmlspecialchars($club['nombre'] ?? 'Club')
    );
}

/**
 * Muestra el logo del club en vista detalle
 * @param array $club Datos del club
 * @return string HTML del logo
 */
function displayClubLogoView(array $club): string {
    $logo = $club['logo'] ?? '';
    
    if (empty($logo)) {
        return '<p class="text-muted"><i class="fas fa-image me-2"></i>No hay logo disponible</p>';
    }
    
    $logo_url = image_url($logo);
    
    return sprintf(
        '<div class="text-center">
            <img src="%s" alt="Logo %s" class="img-thumbnail" style="max-width: 200px; max-height: 200px; cursor: pointer;" onclick="showLogoModal(\'%s\', \'%s\')">
            <p class="text-muted mt-2"><small>Click para ver en tamaño completo</small></p>
        </div>',
        htmlspecialchars($logo_url),
        htmlspecialchars($club['nombre'] ?? ''),
        htmlspecialchars($logo_url),
        htmlspecialchars($club['nombre'] ?? 'Club')
    );
}

/**
 * Muestra el logo del club en formulario de edición
 * @param array $club Datos del club
 * @return string HTML del logo
 */
function displayClubLogoEdit(array $club): string {
    $logo = $club['logo'] ?? '';
    
    if (empty($logo)) {
        return '';
    }
    
    $logo_url = image_url($logo);
    
    return sprintf(
        '<div class="mt-2">
            <img src="%s" alt="Logo actual" class="img-thumbnail" style="max-width: 150px; max-height: 150px; cursor: pointer;" onclick="showLogoModal(\'%s\', \'%s\')">
            <p class="text-muted mt-1"><small>Click para ver en tamaño completo</small></p>
        </div>',
        htmlspecialchars($logo_url),
        htmlspecialchars($logo_url),
        htmlspecialchars($club['nombre'] ?? 'Club')
    );
}

/**
 * Muestra el logo del club en páginas de invitación (organizador/invitado).
 * Usa la función central image_url().
 * @param array $club Datos del club (debe tener 'logo' y 'nombre')
 * @param string $role 'organizador' o 'invitado'
 * @return string HTML del logo
 */
function displayClubLogoInvitation(array $club, string $role = 'organizador'): string {
    $logo = $club['logo'] ?? '';
    if (empty($logo)) {
        return '<span class="text-muted"><i class="fas fa-image"></i> Sin logo</span>';
    }
    $logo_url = image_url($logo);
    $alt = htmlspecialchars($club['nombre'] ?? 'Club') . ' (' . $role . ')';
    return '<img src="' . htmlspecialchars($logo_url) . '" alt="' . $alt . '" class="img-thumbnail" style="max-width: 120px; max-height: 120px; object-fit: contain;">';
}









