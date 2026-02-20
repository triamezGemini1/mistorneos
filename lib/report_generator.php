<?php
/**
 * Generador Base de Reportes en PDF
 * Clase reutilizable para crear reportes profesionales con Dompdf
 */



// Verificar que Dompdf esté disponible
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use Dompdf\Dompdf;
use Dompdf\Options;

class ReportGenerator {
    
    private $dompdf;
    private $options;
    private $html;
    private $title;
    private $orientation;
    
    /**
     * Constructor
     * @param string $title Título del reporte
     * @param string $orientation Orientación: 'portrait' o 'landscape'
     */
    public function __construct(string $title = 'Reporte', string $orientation = 'portrait') {
        $this->title = $title;
        $this->orientation = $orientation;
        
        // Configurar opciones de Dompdf
        $this->options = new Options();
        $this->options->set('isHtml5ParserEnabled', true);
        $this->options->set('isRemoteEnabled', true);
        $this->options->set('defaultFont', 'Arial');
        $this->options->set('chroot', __DIR__ . '/..');
        
        $this->dompdf = new Dompdf($this->options);
    }
    
    /**
     * Genera el encabezado HTML estándar para el reporte
     */
    private function generateHeader(): string {
        return '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>' . htmlspecialchars($this->title) . '</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: Arial, sans-serif;
                    font-size: 10pt;
                    color: #333;
                    padding: 20px;
                }
                
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 15px;
                    border-bottom: 3px solid #667eea;
                }
                
                .header h1 {
                    color: #667eea;
                    font-size: 22pt;
                    margin-bottom: 5px;
                }
                
                .header .subtitle {
                    color: #666;
                    font-size: 10pt;
                }
                
                .header .date {
                    color: #999;
                    font-size: 9pt;
                    margin-top: 5px;
                }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                
                table thead {
                    background-color: #667eea;
                    color: white;
                }
                
                table thead th {
                    padding: 10px;
                    text-align: left;
                    font-size: 10pt;
                    font-weight: bold;
                }
                
                table tbody tr {
                    border-bottom: 1px solid #ddd;
                }
                
                table tbody tr:nth-child(even) {
                    background-color: #f8f9fa;
                }
                
                table tbody td {
                    padding: 8px 10px;
                    font-size: 9pt;
                }
                
                .info-section {
                    background-color: #f0f7ff;
                    border-left: 4px solid #667eea;
                    padding: 15px;
                    margin: 20px 0;
                }
                
                .info-section h3 {
                    color: #667eea;
                    font-size: 12pt;
                    margin-bottom: 10px;
                }
                
                .info-grid {
                    display: table;
                    width: 100%;
                }
                
                .info-row {
                    display: table-row;
                }
                
                .info-label {
                    display: table-cell;
                    font-weight: bold;
                    padding: 5px 10px 5px 0;
                    width: 30%;
                }
                
                .info-value {
                    display: table-cell;
                    padding: 5px 0;
                }
                
                .stats-grid {
                    display: table;
                    width: 100%;
                    margin: 20px 0;
                }
                
                .stat-box {
                    display: table-cell;
                    text-align: center;
                    padding: 15px;
                    background-color: #f8f9fa;
                    border: 1px solid #ddd;
                    width: 25%;
                }
                
                .stat-box .number {
                    font-size: 24pt;
                    font-weight: bold;
                    color: #667eea;
                }
                
                .stat-box .label {
                    font-size: 9pt;
                    color: #666;
                    margin-top: 5px;
                }
                
                .logo {
                    max-width: 120px;
                    max-height: 80px;
                    margin: 10px auto;
                }
                
                .badge {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 8pt;
                    font-weight: bold;
                }
                
                .badge-success {
                    background-color: #d4edda;
                    color: #155724;
                }
                
                .badge-warning {
                    background-color: #fff3cd;
                    color: #856404;
                }
                
                .badge-danger {
                    background-color: #f8d7da;
                    color: #721c24;
                }
                
                .badge-info {
                    background-color: #d1ecf1;
                    color: #0c5460;
                }
                
                .footer {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    text-align: center;
                    font-size: 8pt;
                    color: #999;
                    padding: 10px;
                    border-top: 1px solid #ddd;
                }
                
                .page-break {
                    page-break-after: always;
                }
                
                h2 {
                    color: #667eea;
                    font-size: 14pt;
                    margin: 20px 0 10px 0;
                    border-bottom: 2px solid #667eea;
                    padding-bottom: 5px;
                }
                
                h3 {
                    color: #764ba2;
                    font-size: 12pt;
                    margin: 15px 0 10px 0;
                }
                
                p {
                    margin: 10px 0;
                    line-height: 1.5;
                }
            </style>
        </head>
        <body>';
    }
    
    /**
     * Genera el pie de página HTML
     */
    private function generateFooter(): string {
        return '
            <div class="footer">
                Generado el ' . date('d/m/Y H:i:s') . ' | Federación Venezolana de Dominó - Sistema de Gestión de Clubes LED
            </div>
        </body>
        </html>';
    }
    
    /**
     * Genera el encabezado del reporte con logo opcional
     */
    public function addReportHeader(string $subtitle = '', ?string $logoPath = null): string {
        $html = '<div class="header">';
        
        if ($logoPath && file_exists($logoPath)) {
            $html .= '<img src="' . $logoPath . '" class="logo" alt="Logo">';
        }
        
        $html .= '<h1>' . htmlspecialchars($this->title) . '</h1>';
        
        if ($subtitle) {
            $html .= '<div class="subtitle">' . htmlspecialchars($subtitle) . '</div>';
        }
        
        $html .= '<div class="date">Generado el: ' . date('d/m/Y H:i:s') . '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Genera una tabla HTML a partir de datos
     */
    public function generateTable(array $headers, array $rows, bool $striped = true): string {
        $html = '<table>';
        
        // Encabezados
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead>';
        
        // Filas
        $html .= '<tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . $cell . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Genera una sección de información
     */
    public function generateInfoSection(string $title, array $data): string {
        $html = '<div class="info-section">';
        $html .= '<h3>' . htmlspecialchars($title) . '</h3>';
        $html .= '<div class="info-grid">';
        
        foreach ($data as $label => $value) {
            $html .= '<div class="info-row">';
            $html .= '<div class="info-label">' . htmlspecialchars($label) . ':</div>';
            $html .= '<div class="info-value">' . $value . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Genera cajas de estadísticas
     */
    public function generateStatsBoxes(array $stats): string {
        $html = '<div class="stats-grid">';
        
        foreach ($stats as $stat) {
            $html .= '<div class="stat-box">';
            $html .= '<div class="number">' . $stat['number'] . '</div>';
            $html .= '<div class="label">' . htmlspecialchars($stat['label']) . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Genera un badge HTML
     */
    public static function badge(string $text, string $type = 'info'): string {
        return '<span class="badge badge-' . $type . '">' . htmlspecialchars($text) . '</span>';
    }
    
    /**
     * Establece el contenido HTML del reporte
     */
    public function setContent(string $content): void {
        $this->html = $this->generateHeader();
        $this->html .= $content;
        $this->html .= $this->generateFooter();
    }
    
    /**
     * Genera y descarga el PDF
     */
    public function generate(string $filename = 'reporte.pdf', bool $download = true): void {
        // Cargar HTML
        $this->dompdf->loadHtml($this->html);
        
        // Configurar tamaño de página y orientación
        $this->dompdf->setPaper('letter', $this->orientation);
        
        // Renderizar PDF
        $this->dompdf->render();
        
        // Enviar al navegador
        $this->dompdf->stream($filename, [
            'Attachment' => $download ? 1 : 0
        ]);
    }
    
    /**
     * Guarda el PDF en un archivo
     */
    public function save(string $filepath): bool {
        // Cargar HTML
        $this->dompdf->loadHtml($this->html);
        
        // Configurar tamaño de página y orientación
        $this->dompdf->setPaper('letter', $this->orientation);
        
        // Renderizar PDF
        $this->dompdf->render();
        
        // Guardar archivo
        $output = $this->dompdf->output();
        return file_put_contents($filepath, $output) !== false;
    }
    
    /**
     * Formatea una fecha
     */
    public static function formatDate(?string $date): string {
        if (!$date) return 'N/A';
        $timestamp = strtotime($date);
        return $timestamp ? date('d/m/Y', $timestamp) : 'N/A';
    }
    
    /**
     * Formatea una fecha y hora
     */
    public static function formatDateTime(?string $datetime): string {
        if (!$datetime) return 'N/A';
        $timestamp = strtotime($datetime);
        return $timestamp ? date('d/m/Y H:i:s', $timestamp) : 'N/A';
    }
    
    /**
     * Formatea un número como moneda
     */
    public static function formatCurrency(float $amount): string {
        return '$' . number_format($amount, 2, ',', '.');
    }
}

