<?php
/**
 * Obtener Tasa de Cambio del BCV
 * API para obtener la tasa del dólar según el BCV
 */

header('Content-Type: application/json');

/**
 * Función para obtener la tasa del BCV desde la API oficial
 * Si falla, intenta obtener de una fuente alternativa
 */
function obtenerTasaBCV() {
    // Intentar desde la API del BCV
    $tasa_bcv = obtenerDesdeBCVAPI();
    
    if ($tasa_bcv) {
        return [
            'success' => true,
            'tasa' => $tasa_bcv,
            'fuente' => 'BCV',
            'fecha' => date('Y-m-d H:i:s')
        ];
    }
    
    // Si falla, intentar desde fuente alternativa (ejemplo: exchangerate-api)
    $tasa_alternativa = obtenerDesdeAlternativa();
    
    if ($tasa_alternativa) {
        return [
            'success' => true,
            'tasa' => $tasa_alternativa,
            'fuente' => 'Alternativa',
            'fecha' => date('Y-m-d H:i:s')
        ];
    }
    
    // Si todo falla, retornar error
    return [
        'success' => false,
        'tasa' => 0,
        'error' => 'No se pudo obtener la tasa de cambio',
        'fecha' => date('Y-m-d H:i:s')
    ];
}

/**
 * Obtener tasa desde la API del BCV
 */
function obtenerDesdeBCVAPI() {
    try {
        // URL de la API del BCV (puede variar)
        $url = 'https://s3.amazonaws.com/dolartoday/data.json';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            $data = json_decode($response, true);
            
            // Intentar obtener la tasa del USD
            if (isset($data['USD']['promedio_real'])) {
                return floatval($data['USD']['promedio_real']);
            }
            
            if (isset($data['USD']['dolartoday'])) {
                return floatval($data['USD']['dolartoday']);
            }
        }
    } catch (Exception $e) {
        // Silenciar errores y continuar con alternativa
    }
    
    return null;
}

/**
 * Obtener tasa desde fuente alternativa
 */
function obtenerDesdeAlternativa() {
    try {
        // Usar API alternativa gratuita
        $url = 'https://api.exchangerate-api.com/v4/latest/USD';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['rates']['VES'])) {
                return floatval($data['rates']['VES']);
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
    }
    
    return null;
}

/**
 * Guardar tasa en caché (opcional)
 */
function guardarTasaEnCache($tasa) {
    // Guardar en archivo temporal
    $cache_file = __DIR__ . '/cache_tasa_bcv.json';
    $cache_data = [
        'tasa' => $tasa,
        'fecha' => time()
    ];
    file_put_contents($cache_file, json_encode($cache_data));
}

/**
 * Obtener tasa desde caché si es reciente (menos de 1 hora)
 */
function obtenerTasaDesdeCache() {
    $cache_file = __DIR__ . '/cache_tasa_bcv.json';
    
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        
        // Si la caché tiene menos de 1 hora
        if ($cache_data && (time() - $cache_data['fecha']) < 3600) {
            return $cache_data['tasa'];
        }
    }
    
    return null;
}

// Primero intentar desde caché
$tasa_cache = obtenerTasaDesdeCache();

if ($tasa_cache) {
    echo json_encode([
        'success' => true,
        'tasa' => $tasa_cache,
        'fuente' => 'Caché',
        'fecha' => date('Y-m-d H:i:s')
    ]);
} else {
    // Si no hay caché o está vencida, obtener tasa nueva
    $resultado = obtenerTasaBCV();
    
    // Si se obtuvo correctamente, guardar en caché
    if ($resultado['success']) {
        guardarTasaEnCache($resultado['tasa']);
    }
    
    echo json_encode($resultado);
}

