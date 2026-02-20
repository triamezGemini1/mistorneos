<?php



namespace Lib\UI;

use Lib\Security\Sanitizer;
use Lib\Security\Csrf;

/**
 * Form Builder - Constructor de formularios con validación y CSRF
 * 
 * Características:
 * - CSRF protection automático
 * - Validación client-side
 * - Accesibilidad (labels, aria-*)
 * - Estilos Bootstrap 5
 * 
 * @package Lib\UI
 * @version 1.0.0
 */
class FormBuilder
{
    private string $method = 'POST';
    private string $action = '';
    private array $attributes = [];
    private array $fields = [];
    private bool $csrfProtection = true;
    private array $errors = [];

    /**
     * Inicia formulario
     * 
     * @param string $action
     * @param string $method
     * @return self
     */
    public static function create(string $action, string $method = 'POST'): self
    {
        $form = new self();
        $form->action = $action;
        $form->method = strtoupper($method);
        return $form;
    }

    /**
     * Agrega atributo al form
     * 
     * @param string $key
     * @param string $value
     * @return self
     */
    public function attr(string $key, string $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Deshabilita CSRF protection
     * 
     * @return self
     */
    public function withoutCsrf(): self
    {
        $this->csrfProtection = false;
        return $this;
    }

    /**
     * Establece errores de validación
     * 
     * @param array $errors ['field' => 'error message']
     * @return self
     */
    public function withErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * Agrega campo de texto
     * 
     * @param string $name
     * @param string $label
     * @param array $options
     * @return self
     */
    public function text(string $name, string $label, array $options = []): self
    {
        $this->fields[] = $this->buildInput('text', $name, $label, $options);
        return $this;
    }

    /**
     * Agrega campo de email
     * 
     * @param string $name
     * @param string $label
     * @param array $options
     * @return self
     */
    public function email(string $name, string $label, array $options = []): self
    {
        $this->fields[] = $this->buildInput('email', $name, $label, $options);
        return $this;
    }

    /**
     * Agrega campo de password
     * 
     * @param string $name
     * @param string $label
     * @param array $options
     * @return self
     */
    public function password(string $name, string $label, array $options = []): self
    {
        $this->fields[] = $this->buildInput('password', $name, $label, $options);
        return $this;
    }

    /**
     * Agrega campo numérico
     * 
     * @param string $name
     * @param string $label
     * @param array $options
     * @return self
     */
    public function number(string $name, string $label, array $options = []): self
    {
        $this->fields[] = $this->buildInput('number', $name, $label, $options);
        return $this;
    }

    /**
     * Agrega campo de fecha
     * 
     * @param string $name
     * @param string $label
     * @param array $options
     * @return self
     */
    public function date(string $name, string $label, array $options = []): self
    {
        $this->fields[] = $this->buildInput('date', $name, $label, $options);
        return $this;
    }

    /**
     * Agrega textarea
     * 
     * @param string $name
     * @param string $label
     * @param array $options
     * @return self
     */
    public function textarea(string $name, string $label, array $options = []): self
    {
        $value = Sanitizer::escapeAttr($options['value'] ?? '');
        $required = $options['required'] ?? false;
        $rows = $options['rows'] ?? 3;
        $placeholder = Sanitizer::escapeAttr($options['placeholder'] ?? '');
        $helpText = $options['help'] ?? null;

        $html = '<div class="mb-3">';
        $html .= sprintf(
            '<label for="%s" class="form-label">%s%s</label>',
            $name,
            Sanitizer::escape($label),
            $required ? ' <span class="text-danger">*</span>' : ''
        );

        $classes = ['form-control'];
        if (isset($this->errors[$name])) {
            $classes[] = 'is-invalid';
        }

        $html .= sprintf(
            '<textarea id="%s" name="%s" class="%s" rows="%d" placeholder="%s" %s>%s</textarea>',
            $name,
            $name,
            implode(' ', $classes),
            $rows,
            $placeholder,
            $required ? 'required' : '',
            $value
        );

        if (isset($this->errors[$name])) {
            $html .= '<div class="invalid-feedback">' . Sanitizer::escape($this->errors[$name]) . '</div>';
        }

        if ($helpText) {
            $html .= '<div class="form-text">' . Sanitizer::escape($helpText) . '</div>';
        }

        $html .= '</div>';

        $this->fields[] = $html;
        return $this;
    }

    /**
     * Agrega select
     * 
     * @param string $name
     * @param string $label
     * @param array $options ['value' => 'label']
     * @param array $attrs
     * @return self
     */
    public function select(string $name, string $label, array $options, array $attrs = []): self
    {
        $value = $attrs['value'] ?? '';
        $required = $attrs['required'] ?? false;
        $helpText = $attrs['help'] ?? null;

        $html = '<div class="mb-3">';
        $html .= sprintf(
            '<label for="%s" class="form-label">%s%s</label>',
            $name,
            Sanitizer::escape($label),
            $required ? ' <span class="text-danger">*</span>' : ''
        );

        $classes = ['form-select'];
        if (isset($this->errors[$name])) {
            $classes[] = 'is-invalid';
        }

        $html .= sprintf(
            '<select id="%s" name="%s" class="%s" %s>',
            $name,
            $name,
            implode(' ', $classes),
            $required ? 'required' : ''
        );

        $html .= '<option value="">Seleccione...</option>';

        foreach ($options as $optValue => $optLabel) {
            $selected = (string)$optValue === (string)$value ? 'selected' : '';
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                Sanitizer::escapeAttr((string)$optValue),
                $selected,
                Sanitizer::escape($optLabel)
            );
        }

        $html .= '</select>';

        if (isset($this->errors[$name])) {
            $html .= '<div class="invalid-feedback">' . Sanitizer::escape($this->errors[$name]) . '</div>';
        }

        if ($helpText) {
            $html .= '<div class="form-text">' . Sanitizer::escape($helpText) . '</div>';
        }

        $html .= '</div>';

        $this->fields[] = $html;
        return $this;
    }

    /**
     * Agrega checkbox
     * 
     * @param string $name
     * @param string $label
     * @param array $options
     * @return self
     */
    public function checkbox(string $name, string $label, array $options = []): self
    {
        $checked = $options['checked'] ?? false;
        $value = $options['value'] ?? '1';

        $html = '<div class="mb-3 form-check">';
        $html .= sprintf(
            '<input type="checkbox" class="form-check-input" id="%s" name="%s" value="%s" %s>',
            $name,
            $name,
            Sanitizer::escapeAttr($value),
            $checked ? 'checked' : ''
        );
        $html .= sprintf(
            '<label class="form-check-label" for="%s">%s</label>',
            $name,
            Sanitizer::escape($label)
        );
        $html .= '</div>';

        $this->fields[] = $html;
        return $this;
    }

    /**
     * Agrega campo de archivo
     * 
     * @param string $name
     * @param string $label
     * @param array $options
     * @return self
     */
    public function file(string $name, string $label, array $options = []): self
    {
        $required = $options['required'] ?? false;
        $accept = $options['accept'] ?? null;
        $helpText = $options['help'] ?? null;

        $html = '<div class="mb-3">';
        $html .= sprintf(
            '<label for="%s" class="form-label">%s%s</label>',
            $name,
            Sanitizer::escape($label),
            $required ? ' <span class="text-danger">*</span>' : ''
        );

        $classes = ['form-control'];
        if (isset($this->errors[$name])) {
            $classes[] = 'is-invalid';
        }

        $previewId = $name . '-preview';
        $attrs = sprintf('id="%s" name="%s" class="%s" data-preview-target="%s"', $name, $name, implode(' ', $classes), Sanitizer::escapeAttr($previewId));
        
        if ($required) {
            $attrs .= ' required';
        }
        
        if ($accept) {
            $attrs .= sprintf(' accept="%s"', Sanitizer::escapeAttr($accept));
        }

        $html .= sprintf('<input type="file" %s>', $attrs);
        $html .= sprintf('<div id="%s"></div>', Sanitizer::escapeAttr($previewId));

        if (isset($this->errors[$name])) {
            $html .= '<div class="invalid-feedback">' . Sanitizer::escape($this->errors[$name]) . '</div>';
        }

        if ($helpText) {
            $html .= '<div class="form-text">' . Sanitizer::escape($helpText) . '</div>';
        }

        $html .= '</div>';

        $this->fields[] = $html;
        return $this;
    }

    /**
     * Agrega botón submit
     * 
     * @param string $text
     * @param array $options
     * @return self
     */
    public function submit(string $text = 'Guardar', array $options = []): self
    {
        $variant = $options['variant'] ?? 'primary';
        $size = $options['size'] ?? 'md';
        
        $html = sprintf(
            '<button type="submit" class="btn btn-%s btn-%s">%s</button>',
            $variant,
            $size,
            Sanitizer::escape($text)
        );

        $this->fields[] = $html;
        return $this;
    }

    /**
     * Construye campo input
     * 
     * @param string $type
     * @param string $name
     * @param string $label
     * @param array $options
     * @return string
     */
    private function buildInput(string $type, string $name, string $label, array $options): string
    {
        $value = Sanitizer::escapeAttr($options['value'] ?? '');
        $required = $options['required'] ?? false;
        $placeholder = Sanitizer::escapeAttr($options['placeholder'] ?? '');
        $helpText = $options['help'] ?? null;
        $min = $options['min'] ?? null;
        $max = $options['max'] ?? null;
        $step = $options['step'] ?? null;

        $html = '<div class="mb-3">';
        
        // Label
        $html .= sprintf(
            '<label for="%s" class="form-label">%s%s</label>',
            $name,
            Sanitizer::escape($label),
            $required ? ' <span class="text-danger">*</span>' : ''
        );

        // Input
        $classes = ['form-control'];
        if (isset($this->errors[$name])) {
            $classes[] = 'is-invalid';
        }

        $attrs = sprintf(
            'type="%s" id="%s" name="%s" class="%s" value="%s" placeholder="%s"',
            $type,
            $name,
            $name,
            implode(' ', $classes),
            $value,
            $placeholder
        );

        if ($required) {
            $attrs .= ' required';
        }
        
        if ($min !== null) {
            $attrs .= sprintf(' min="%s"', $min);
        }
        
        if ($max !== null) {
            $attrs .= sprintf(' max="%s"', $max);
        }
        
        if ($step !== null) {
            $attrs .= sprintf(' step="%s"', $step);
        }

        $html .= "<input $attrs>";

        // Error
        if (isset($this->errors[$name])) {
            $html .= '<div class="invalid-feedback">' . Sanitizer::escape($this->errors[$name]) . '</div>';
        }

        // Help text
        if ($helpText) {
            $html .= '<div class="form-text">' . Sanitizer::escape($helpText) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Renderiza formulario completo
     * 
     * @return string
     */
    public function render(): string
    {
        $attrs = ['method' => $this->method, 'action' => $this->action];
        $attrs = array_merge($attrs, $this->attributes);

        // Si usa archivos, agregar enctype
        if (!isset($attrs['enctype'])) {
            foreach ($this->fields as $field) {
                if (str_contains($field, 'type="file"')) {
                    $attrs['enctype'] = 'multipart/form-data';
                    break;
                }
            }
        }

        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= sprintf(' %s="%s"', $key, Sanitizer::escapeAttr($value));
        }

        $html = "<form{$attrString}>\n";

        // CSRF Token
        if ($this->csrfProtection && in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $html .= Csrf::field() . "\n";
        }

        // Fields
        foreach ($this->fields as $field) {
            $html .= $field . "\n";
        }

        $html .= "</form>";

        return $html;
    }

    /**
     * Convierte a string
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->render();
    }
}







