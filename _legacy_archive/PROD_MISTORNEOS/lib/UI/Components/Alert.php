<?php



namespace Lib\UI\Components;

use Lib\UI\Component;
use Lib\Security\Sanitizer;

/**
 * Alert Component - Mensaje de alerta
 * 
 * @package Lib\UI\Components
 */
class Alert extends Component
{
    protected function propSchema(): array
    {
        return [
            'message' => [
                'type' => 'string',
                'required' => true
            ],
            'type' => [
                'type' => 'string',
                'default' => 'info' // success, danger, warning, info
            ],
            'dismissible' => [
                'type' => 'bool',
                'default' => true
            ],
            'icon' => [
                'type' => 'bool',
                'default' => true
            ]
        ];
    }

    public function render(): string
    {
        $message = Sanitizer::escape($this->prop('message'));
        $type = $this->prop('type');
        $dismissible = $this->prop('dismissible');
        $showIcon = $this->prop('icon');

        $classes = ['alert', "alert-{$type}"];
        
        if ($dismissible) {
            $classes[] = 'alert-dismissible fade show';
        }

        $icons = [
            'success' => 'bi bi-check-circle-fill',
            'danger' => 'bi bi-exclamation-triangle-fill',
            'warning' => 'bi bi-exclamation-circle-fill',
            'info' => 'bi bi-info-circle-fill'
        ];

        $html = '<div class="' . implode(' ', $classes) . '" role="alert">';

        if ($showIcon && isset($icons[$type])) {
            $html .= '<i class="' . $icons[$type] . ' me-2"></i>';
        }

        $html .= $message;

        if ($dismissible) {
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        }

        $html .= '</div>';

        return $html;
    }
}







