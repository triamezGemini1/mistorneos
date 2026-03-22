/**
 * Utilidades para formularios de registro
 * - Debounce, validación en tiempo real, prevención de doble envío
 */
(function() {
    'use strict';

    /**
     * Debounce: retrasa la ejecución de fn hasta que pasen ms ms sin nuevas llamadas
     */
    window.debounce = function debounce(fn, ms) {
        let timeoutId;
        return function(...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn.apply(this, args), ms);
        };
    };

    /**
     * Valida formato de email
     */
    window.validateEmail = function validateEmail(email) {
        if (!email || typeof email !== 'string') return { valid: false, message: 'Email requerido' };
        const trimmed = email.trim();
        if (!trimmed) return { valid: false, message: 'Email requerido' };
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(trimmed) 
            ? { valid: true } 
            : { valid: false, message: 'Formato de email inválido' };
    };

    /**
     * Valida formato de cédula (V/E + 6-8 dígitos, o solo dígitos)
     */
    window.validateCedula = function validateCedula(cedula) {
        if (!cedula || typeof cedula !== 'string') return { valid: false, message: 'Cédula requerida' };
        const cleaned = cedula.replace(/^[VEJPvejp]/i, '').replace(/\D/g, '');
        if (cleaned.length < 6) return { valid: false, message: 'Cédula debe tener al menos 6 dígitos' };
        if (cleaned.length > 8) return { valid: false, message: 'Cédula inválida' };
        return { valid: true };
    };

    /**
     * Muestra error en campo (Bootstrap is-invalid)
     */
    window.showFieldError = function showFieldError(inputId, message) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        let feedback = document.getElementById(inputId + '-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.id = inputId + '-feedback';
            feedback.className = 'invalid-feedback';
            input.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
        feedback.setAttribute('role', 'alert');
    };

    /**
     * Limpia error de campo
     */
    window.clearFieldError = function clearFieldError(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
        const feedback = document.getElementById(inputId + '-feedback');
        if (feedback) feedback.remove();
    };

    /**
     * Previene doble envío: deshabilita botón submit y muestra estado de carga
     */
    window.preventDoubleSubmit = function preventDoubleSubmit(form) {
        if (!form) return;
        form.addEventListener('submit', function() {
            const btn = form.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                const originalHtml = btn.innerHTML;
                btn.setAttribute('data-original-html', btn.innerHTML);
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando...';
                // Re-habilitar tras 10s por si falla la navegación
                setTimeout(function() {
                    btn.disabled = false;
                    btn.innerHTML = btn.getAttribute('data-original-html') || originalHtml;
                }, 10000);
            }
        });
    };

    /**
     * Inicializa validación en tiempo real para email
     */
    window.initEmailValidation = function initEmailValidation(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const validate = function() {
            const result = validateEmail(input.value);
            if (input.value.trim() === '') {
                clearFieldError(inputId);
            } else if (!result.valid) {
                showFieldError(inputId, result.message);
            } else {
                clearFieldError(inputId);
            }
        };
        input.addEventListener('blur', validate);
        input.addEventListener('input', debounce(validate, 300));
    };

    /**
     * Inicializa validación en tiempo real para cédula
     */
    window.initCedulaValidation = function initCedulaValidation(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const validate = function() {
            const result = validateCedula(input.value);
            if (input.value.trim() === '') {
                clearFieldError(inputId);
            } else if (!result.valid) {
                showFieldError(inputId, result.message);
            } else {
                clearFieldError(inputId);
            }
        };
        input.addEventListener('blur', validate);
        input.addEventListener('input', debounce(validate, 300));
    };
})();
