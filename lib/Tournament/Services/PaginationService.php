<?php

declare(strict_types=1);

namespace Tournament\Services;

/**
 * LIMIT / OFFSET y conteo de páginas para listados (inscritos, posiciones, etc.).
 */
final class PaginationService
{
    private function __construct()
    {
    }

    /**
     * @return array{
     *   page: int,
     *   per_page: int,
     *   offset: int,
     *   limit: int,
     *   total_pages: int,
     *   total_items: int
     * }
     */
    public static function getParams(int $total, int $paginaActual, int $porPagina): array
    {
        $porPagina = max(1, min(500, $porPagina));
        $totalPages = max(1, (int) ceil($total / $porPagina));
        $page = max(1, min($paginaActual, $totalPages));
        $offset = ($page - 1) * $porPagina;

        return [
            'page' => $page,
            'per_page' => $porPagina,
            'offset' => $offset,
            'limit' => $porPagina,
            'total_pages' => $totalPages,
            'total_items' => $total,
        ];
    }
}
