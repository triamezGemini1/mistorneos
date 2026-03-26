<?php



namespace Core\Middleware;

use Core\Http\Request;
use Core\Http\Response;

/**
 * Interface Middleware - Contract para todos los middleware
 * 
 * @package Core\Middleware
 * @version 1.0.0
 */
interface Middleware
{
    /**
     * Procesa un request antes de pasarlo al siguiente handler
     * 
     * @param Request $request
     * @param callable $next Siguiente middleware o handler final
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}









