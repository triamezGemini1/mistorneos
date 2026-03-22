<?php


/**
 * Clase de Paginación Moderna y Reutilizable
 * 
 * Uso:
 * $pagination = new Pagination($total_records, $current_page, $per_page);
 * echo $pagination->render();
 */
class Pagination {
    private int $total_records;
    private int $current_page;
    private int $per_page;
    private int $total_pages;
    private int $offset;
    private array $query_params;
    
    /**
     * Constructor
     * 
     * @param int $total_records Total de registros
     * @param int $current_page Página actual (default: 1)
     * @param int $per_page Registros por página (default: 25)
     */
    public function __construct(int $total_records, int $current_page = 1, int $per_page = 25) {
        $this->total_records = max(0, $total_records);
        $this->per_page = max(1, $per_page);
        $this->current_page = max(1, min($current_page, $this->calculateTotalPages()));
        $this->total_pages = $this->calculateTotalPages();
        $this->offset = ($this->current_page - 1) * $this->per_page;
        
        // Obtener parámetros de URL actuales (excluyendo 'p' y 'per_page')
        $this->query_params = $_GET;
        unset($this->query_params['p'], $this->query_params['per_page']);
    }
    
    /**
     * Calcular total de páginas
     */
    private function calculateTotalPages(): int {
        if ($this->total_records === 0) {
            return 1;
        }
        return (int)ceil($this->total_records / $this->per_page);
    }
    
    /**
     * Obtener offset para SQL
     */
    public function getOffset(): int {
        return $this->offset;
    }
    
    /**
     * Obtener límite para SQL
     */
    public function getLimit(): int {
        return $this->per_page;
    }
    
    /**
     * Obtener página actual
     */
    public function getCurrentPage(): int {
        return $this->current_page;
    }
    
    /**
     * Obtener total de páginas
     */
    public function getTotalPages(): int {
        return $this->total_pages;
    }
    
    /**
     * Obtener total de registros
     */
    public function getTotalRecords(): int {
        return $this->total_records;
    }
    
    /**
     * Construir URL para una página específica
     */
    private function buildUrl(int $page, ?int $per_page = null): string {
        $params = $this->query_params;
        $params['p'] = $page;
        if ($per_page !== null) {
            $params['per_page'] = $per_page;
        } else {
            $params['per_page'] = $this->per_page;
        }
        return '?' . http_build_query($params);
    }
    
    /**
     * Renderizar información de registros
     */
    public function renderInfo(): string {
        if ($this->total_records === 0) {
            return '<div class="text-muted">No hay registros para mostrar</div>';
        }
        
        $from = $this->offset + 1;
        $to = min($this->offset + $this->per_page, $this->total_records);
        
        return sprintf(
            '<div class="text-muted">Mostrando <strong>%d</strong> a <strong>%d</strong> de <strong>%d</strong> registros</div>',
            $from,
            $to,
            $this->total_records
        );
    }
    
    /**
     * Renderizar selector de items por página
     */
    public function renderPerPageSelector(): string {
        $options = [10, 25, 50, 100];
        $html = '<div class="d-flex align-items-center gap-2">';
        $html .= '<label class="mb-0 text-muted">Mostrar:</label>';
        $html .= '<select class="form-select form-select-sm" style="width: auto;" onchange="window.location.href=this.value">';
        
        foreach ($options as $option) {
            $selected = ($option === $this->per_page) ? ' selected' : '';
            $url = $this->buildUrl($this->current_page, $option);
            $html .= sprintf('<option value="%s"%s>%d</option>', htmlspecialchars($url), $selected, $option);
        }
        
        $html .= '</select>';
        $html .= '<span class="text-muted">registros</span>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Renderizar botones de paginación
     */
    public function renderButtons(): string {
        if ($this->total_pages <= 1) {
            return '';
        }
        
        $html = '<nav aria-label="Navegación de páginas">';
        $html .= '<ul class="pagination pagination-sm mb-0">';
        
        // Botón Primera
        $disabled = ($this->current_page === 1) ? ' disabled' : '';
        $url = $this->buildUrl(1);
        $html .= sprintf(
            '<li class="page-item%s"><a class="page-link" href="%s" title="Primera página"><i class="fas fa-angle-double-left"></i></a></li>',
            $disabled,
            htmlspecialchars($url)
        );
        
        // Botón Anterior
        $disabled = ($this->current_page === 1) ? ' disabled' : '';
        $url = $this->buildUrl(max(1, $this->current_page - 1));
        $html .= sprintf(
            '<li class="page-item%s"><a class="page-link" href="%s" title="Página anterior"><i class="fas fa-angle-left"></i></a></li>',
            $disabled,
            htmlspecialchars($url)
        );
        
        // Números de página (mostrar máximo 7 botones)
        $range = $this->calculatePageRange();
        
        if ($range['start'] > 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        
        for ($i = $range['start']; $i <= $range['end']; $i++) {
            $active = ($i === $this->current_page) ? ' active' : '';
            $url = $this->buildUrl($i);
            $html .= sprintf(
                '<li class="page-item%s"><a class="page-link" href="%s">%d</a></li>',
                $active,
                htmlspecialchars($url),
                $i
            );
        }
        
        if ($range['end'] < $this->total_pages) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        
        // Botón Siguiente
        $disabled = ($this->current_page === $this->total_pages) ? ' disabled' : '';
        $url = $this->buildUrl(min($this->total_pages, $this->current_page + 1));
        $html .= sprintf(
            '<li class="page-item%s"><a class="page-link" href="%s" title="Página siguiente"><i class="fas fa-angle-right"></i></a></li>',
            $disabled,
            htmlspecialchars($url)
        );
        
        // Botón Última
        $disabled = ($this->current_page === $this->total_pages) ? ' disabled' : '';
        $url = $this->buildUrl($this->total_pages);
        $html .= sprintf(
            '<li class="page-item%s"><a class="page-link" href="%s" title="Última página"><i class="fas fa-angle-double-right"></i></a></li>',
            $disabled,
            htmlspecialchars($url)
        );
        
        $html .= '</ul>';
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Calcular rango de páginas a mostrar
     */
    private function calculatePageRange(): array {
        $max_buttons = 5; // Número máximo de botones numéricos
        $half = (int)floor($max_buttons / 2);
        
        $start = max(1, $this->current_page - $half);
        $end = min($this->total_pages, $this->current_page + $half);
        
        // Ajustar si estamos cerca del inicio
        if ($this->current_page <= $half) {
            $end = min($this->total_pages, $max_buttons);
        }
        
        // Ajustar si estamos cerca del final
        if ($this->current_page > $this->total_pages - $half) {
            $start = max(1, $this->total_pages - $max_buttons + 1);
        }
        
        return ['start' => $start, 'end' => $end];
    }
    
    /**
     * Renderizar paginación completa (info + selector + botones)
     */
    public function render(): string {
        if ($this->total_records === 0) {
            return '<div class="d-flex justify-content-between align-items-center mt-3">' 
                 . $this->renderInfo() 
                 . '</div>';
        }
        
        $html = '<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4">';
        $html .= '<div>' . $this->renderInfo() . '</div>';
        $html .= '<div class="d-flex gap-3 align-items-center flex-wrap">';
        $html .= $this->renderPerPageSelector();
        $html .= $this->renderButtons();
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Aplicar paginación a una consulta SQL (helper)
     */
    public function applySql(string $base_query): string {
        return $base_query . " LIMIT {$this->per_page} OFFSET {$this->offset}";
    }
}














