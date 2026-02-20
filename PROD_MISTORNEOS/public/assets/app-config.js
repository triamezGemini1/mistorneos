/**
 * Configuración de la aplicación para JavaScript
 * Define las URLs base y rutas de API
 */

// Esta variable será definida por PHP en cada página
const APP_CONFIG = window.APP_CONFIG || {
    publicPath: '/mistorneos/public/',
    apiPath: '/mistorneos/public/api/',
    isProduction: false
};

/**
 * Helper para construir URLs de API
 */
function apiUrl(endpoint) {
    // Remover slash inicial si existe
    endpoint = endpoint.replace(/^\//, '');
    return APP_CONFIG.apiPath + endpoint;
}

/**
 * Helper para construir URLs públicas
 */
function publicUrl(path) {
    // Remover slash inicial si existe
    path = path.replace(/^\//, '');
    return APP_CONFIG.publicPath + path;
}

// Exportar para uso global
window.apiUrl = apiUrl;
window.publicUrl = publicUrl;





