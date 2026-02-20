<?php
/**
 * Clase para validar comprobantes bancarios mediante APIs
 * Soporta múltiples proveedores bancarios
 */

class BankValidator
{
    private $proveedor;
    private $endpoint;
    private $token;
    private $config;
    
    /**
     * Constructor
     * 
     * @param string $proveedor Nombre del proveedor (banesco, mercantil, venezuela, etc.)
     * @param string $endpoint URL del endpoint de la API
     * @param string $token Token de autenticación
     * @param array $config Configuración adicional
     */
    public function __construct($proveedor, $endpoint, $token, $config = [])
    {
        $this->proveedor = strtolower($proveedor);
        $this->endpoint = $endpoint;
        $this->token = $token;
        $this->config = $config;
    }
    
    /**
     * Validar un comprobante de pago
     * 
     * @param array $datos Datos del comprobante:
     *   - referencia: Número de referencia
     *   - monto: Monto del pago
     *   - fecha: Fecha del pago (Y-m-d)
     *   - banco: Nombre del banco
     *   - cuenta: Número de cuenta destino
     *   - tipo_pago: transferencia, pagomovil, efectivo
     * 
     * @return array Resultado de la validación:
     *   - valido: bool
     *   - mensaje: string
     *   - datos: array (datos adicionales del comprobante)
     *   - error: string (si hay error)
     */
    public function validarComprobante($datos)
    {
        try {
            // Validar que la API esté habilitada
            if (empty($this->endpoint) || empty($this->token)) {
                return [
                    'valido' => false,
                    'mensaje' => 'API bancaria no configurada',
                    'error' => 'CONFIGURACION_INCOMPLETA'
                ];
            }
            
            // Validar datos requeridos
            if (empty($datos['referencia']) || empty($datos['monto']) || empty($datos['fecha'])) {
                return [
                    'valido' => false,
                    'mensaje' => 'Datos incompletos para validar el comprobante',
                    'error' => 'DATOS_INCOMPLETOS'
                ];
            }
            
            // Llamar al método específico según el proveedor
            $metodo = 'validar' . ucfirst($this->proveedor);
            if (method_exists($this, $metodo)) {
                return $this->$metodo($datos);
            } else {
                // Método genérico para proveedores no específicos
                return $this->validarGenerico($datos);
            }
            
        } catch (Exception $e) {
            error_log("BankValidator Error: " . $e->getMessage());
            return [
                'valido' => false,
                'mensaje' => 'Error al validar comprobante: ' . $e->getMessage(),
                'error' => 'ERROR_INTERNO'
            ];
        }
    }
    
    /**
     * Validación genérica (para APIs estándar REST)
     */
    private function validarGenerico($datos)
    {
        $ch = curl_init();
        
        $payload = [
            'referencia' => $datos['referencia'],
            'monto' => (float)$datos['monto'],
            'fecha' => $datos['fecha'],
            'banco' => $datos['banco'] ?? null,
            'cuenta' => $datos['cuenta'] ?? null,
            'tipo_pago' => $datos['tipo_pago'] ?? 'transferencia'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'valido' => false,
                'mensaje' => 'Error de conexión con la API bancaria',
                'error' => 'ERROR_CONEXION',
                'detalle' => $error
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'valido' => false,
                'mensaje' => 'Error en la respuesta de la API bancaria (HTTP ' . $httpCode . ')',
                'error' => 'ERROR_API',
                'http_code' => $httpCode
            ];
        }
        
        $resultado = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valido' => false,
                'mensaje' => 'Respuesta inválida de la API bancaria',
                'error' => 'RESPUESTA_INVALIDA'
            ];
        }
        
        // Interpretar respuesta según estructura esperada
        // Estructura esperada: { "valido": true/false, "mensaje": "...", "datos": {...} }
        if (isset($resultado['valido'])) {
            return [
                'valido' => (bool)$resultado['valido'],
                'mensaje' => $resultado['mensaje'] ?? 'Validación completada',
                'datos' => $resultado['datos'] ?? [],
                'raw' => $resultado
            ];
        } else {
            // Si la API no sigue el formato estándar, intentar interpretar
            return $this->interpretarRespuesta($resultado, $datos);
        }
    }
    
    /**
     * Interpretar respuesta de API no estándar
     */
    private function interpretarRespuesta($resultado, $datos)
    {
        // Buscar indicadores comunes de validez
        $valido = false;
        $mensaje = 'Comprobante no encontrado o inválido';
        
        // Patrones comunes
        if (isset($resultado['status']) && in_array(strtolower($resultado['status']), ['success', 'valid', 'found', 'ok'])) {
            $valido = true;
            $mensaje = 'Comprobante validado exitosamente';
        } elseif (isset($resultado['found']) && $resultado['found'] === true) {
            $valido = true;
            $mensaje = 'Comprobante encontrado';
        } elseif (isset($resultado['exists']) && $resultado['exists'] === true) {
            $valido = true;
            $mensaje = 'Comprobante existe';
        }
        
        // Validar monto si está presente
        if ($valido && isset($resultado['amount']) || isset($resultado['monto'])) {
            $monto_api = (float)($resultado['amount'] ?? $resultado['monto'] ?? 0);
            $monto_esperado = (float)$datos['monto'];
            
            // Permitir diferencia de hasta 0.01 (redondeo)
            if (abs($monto_api - $monto_esperado) > 0.01) {
                $valido = false;
                $mensaje = 'El monto no coincide. Esperado: $' . number_format($monto_esperado, 2) . ', Recibido: $' . number_format($monto_api, 2);
            }
        }
        
        return [
            'valido' => $valido,
            'mensaje' => $mensaje,
            'datos' => $resultado,
            'raw' => $resultado
        ];
    }
    
    /**
     * Validación específica para Banesco
     * (Ejemplo de implementación específica)
     */
    private function validarBanesco($datos)
    {
        // Implementación específica para Banesco
        // Ajustar según la documentación real de la API de Banesco
        return $this->validarGenerico($datos);
    }
    
    /**
     * Validación específica para Banco de Venezuela
     * (Ejemplo de implementación específica)
     */
    private function validarVenezuela($datos)
    {
        // Implementación específica para Banco de Venezuela
        // Ajustar según la documentación real de la API
        return $this->validarGenerico($datos);
    }
    
    /**
     * Validación específica para Mercantil
     * (Ejemplo de implementación específica)
     */
    private function validarMercantil($datos)
    {
        // Implementación específica para Mercantil
        // Ajustar según la documentación real de la API
        return $this->validarGenerico($datos);
    }
    
    /**
     * Crear instancia desde configuración de torneo
     * 
     * @param array $torneo Datos del torneo con campos de API bancaria
     * @return BankValidator|null
     */
    public static function desdeTorneo($torneo)
    {
        if (empty($torneo['api_banco_habilitada']) || (int)$torneo['api_banco_habilitada'] !== 1) {
            return null;
        }
        
        if (empty($torneo['api_banco_endpoint']) || empty($torneo['api_banco_token'])) {
            return null;
        }
        
        $config = [];
        if (!empty($torneo['api_banco_config'])) {
            $config = json_decode($torneo['api_banco_config'], true) ?: [];
        }
        
        return new self(
            $torneo['api_banco_proveedor'] ?? 'generico',
            $torneo['api_banco_endpoint'],
            $torneo['api_banco_token'],
            $config
        );
    }
}

