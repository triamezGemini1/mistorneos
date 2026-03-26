<?php



namespace Lib\UI\Components;

use Lib\UI\Component;
use Lib\Security\Sanitizer;

/**
 * Card Component - Tarjeta de contenido
 * 
 * @package Lib\UI\Components
 */
class Card extends Component
{
    protected function propSchema(): array
    {
        return [
            'title' => [
                'type' => 'string',
                'default' => null
            ],
            'subtitle' => [
                'type' => 'string',
                'default' => null
            ],
            'variant' => [
                'type' => 'string',
                'default' => 'default' // default, primary, success, danger, warning, info
            ],
            'shadow' => [
                'type' => 'bool',
                'default' => true
            ],
            'bordered' => [
                'type' => 'bool',
                'default' => true
            ]
        ];
    }

    public function render(): string
    {
        $title = $this->prop('title');
        $subtitle = $this->prop('subtitle');
        $variant = $this->prop('variant');
        $shadow = $this->prop('shadow');
        $bordered = $this->prop('bordered');

        $classes = ['card'];
        
        if ($variant !== 'default') {
            $classes[] = "card-{$variant}";
        }
        
        if ($shadow) {
            $classes[] = 'shadow-sm';
        }
        
        if (!$bordered) {
            $classes[] = 'border-0';
        }

        $html = '<div class="' . implode(' ', $classes) . '">';

        // Header
        if ($title !== null || $subtitle !== null) {
            $html .= '<div class="card-header">';
            
            if ($title) {
                $html .= '<h5 class="card-title mb-0">' . Sanitizer::escape($title) . '</h5>';
            }
            
            if ($subtitle) {
                $html .= '<p class="card-subtitle text-muted mt-1">' . Sanitizer::escape($subtitle) . '</p>';
            }
            
            $html .= '</div>';
        }

        // Body
        $html .= '<div class="card-body">';
        $html .= $this->getSlot('default', '');
        $html .= '</div>';

        // Footer
        if ($footer = $this->getSlot('footer')) {
            $html .= '<div class="card-footer">' . $footer . '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}







