<?php

namespace Lib\Api;

/**
 * JsonResponse - Helper para respuestas API estandarizadas
 */
class JsonResponse
{
    /**
     * Respuesta exitosa
     */
    public static function success($data = null, string $message = null, int $statusCode = 200): void
    {
        self::send([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => self::getMeta()
        ], $statusCode);
    }

    /**
     * Respuesta de error
     */
    public static function error(string $message, string $code = 'ERROR', $details = null, int $statusCode = 400): void
    {
        self::send([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ],
            'meta' => self::getMeta()
        ], $statusCode);
    }

    /**
     * Error de validación
     */
    public static function validationError(array $errors): void
    {
        self::error('Errores de validación', 'VALIDATION_ERROR', $errors, 422);
    }

    /**
     * No encontrado
     */
    public static function notFound(string $message = 'Recurso no encontrado'): void
    {
        self::error($message, 'NOT_FOUND', null, 404);
    }

    /**
     * No autorizado
     */
    public static function unauthorized(string $message = 'No autorizado'): void
    {
        self::error($message, 'UNAUTHORIZED', null, 401);
    }

    /**
     * Prohibido
     */
    public static function forbidden(string $message = 'Acceso denegado'): void
    {
        self::error($message, 'FORBIDDEN', null, 403);
    }

    /**
     * Error interno
     */
    public static function serverError(string $message = 'Error interno del servidor'): void
    {
        self::error($message, 'SERVER_ERROR', null, 500);
    }

    /**
     * Respuesta paginada
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): void
    {
        self::send([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total
            ],
            'meta' => self::getMeta()
        ], 200);
    }

    /**
     * Envía la respuesta JSON
     */
    private static function send(array $data, int $statusCode): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Metadata común
     */
    private static function getMeta(): array
    {
        return [
            'timestamp' => date('c'),
            'version' => '1.0'
        ];
    }
}

/**
 * Funciones helper globales para uso rápido
 */
function api_success($data = null, string $message = null): void {
    JsonResponse::success($data, $message);
}

function api_error(string $message, string $code = 'ERROR', int $status = 400): void {
    JsonResponse::error($message, $code, null, $status);
}


