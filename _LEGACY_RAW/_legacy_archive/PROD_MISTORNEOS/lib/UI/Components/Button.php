<?php



namespace Lib\UI\Components;

use Lib\UI\Component;
use Lib\Security\Sanitizer;

/**
 * Button Component - Botón reutilizable con variantes
 * 
 * Variantes: primary, secondary, success, danger, warning, info
 * Tamaños: sm, md, lg
 * 
 * @package Lib\UI\Components
 */
class Button extends Component
{
    protected function propSchema(): array
    {
        return [
            'text' => [
                'type' => 'string',
                'required' => true
            ],
            'type' => [
                'type' => 'string',
                'default' => 'button' // button, submit, reset
            ],
            'variant' => [
                'type' => 'string',
                'default' => 'primary' // primary, secondary, success, danger, warning, info
            ],
            'size' => [
                'type' => 'string',
                'default' => 'md' // sm, md, lg
            ],
            'disabled' => [
                'type' => 'bool',
                'default' => false
            ],
            'loading' => [
                'type' => 'bool',
                'default' => false
            ],
            'icon' => [
                'type' => 'string',
                'default' => null
            ],
            'href' => [
                'type' => 'string',
                'default' => null
            ],
            'block' => [
                'type' => 'bool',
                'default' => false
            ],
            'outline' => [
                'type' => 'bool',
                'default' => false
            ]
        ];
    }

    public function render(): string
    {
        $text = Sanitizer::escape($this->prop('text'));
        $variant = $this->prop('variant');
        $size = $this->prop('size');
        $disabled = $this->prop('disabled') || $this->prop('loading');
        $loading = $this->prop('loading');
        $icon = $this->prop('icon');
        $href = $this->prop('href');
        $block = $this->prop('block');
        $outline = $this->prop('outline');

        // Clases CSS
        $classes = ['btn'];
        $classes[] = $outline ? "btn-outline-{$variant}" : "btn-{$variant}";
        $classes[] = "btn-{$size}";
        
        if ($block) {
            $classes[] = 'btn-block';
        }
        
        if ($loading) {
            $classes[] = 'btn-loading';
        }

        // Contenido
        $content = '';
        
        if ($loading) {
            $content .= '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>';
        } elseif ($icon) {
            $content .= '<i class="' . Sanitizer::escapeAttr($icon) . ' me-2"></i>';
        }
        
        $content .= $text;

        // Atributos
        $attrs = [
            'class' => implode(' ', $classes)
        ];

        if ($disabled) {
            $attrs['disabled'] = null;
        }

        // Renderizar como link o botón
        if ($href !== null) {
            $attrs['href'] = $href;
            if ($disabled) {
                $attrs['aria-disabled'] = 'true';
                $attrs['tabindex'] = '-1';
            }
            return sprintf('<a %s>%s</a>', $this->renderAttributes($attrs), $content);
        }

        $attrs['type'] = $this->prop('type');
        return sprintf('<button %s>%s</button>', $this->renderAttributes($attrs), $content);
    }
}







