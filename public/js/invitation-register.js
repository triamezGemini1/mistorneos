/**
 * Inscripción por invitación: búsqueda jerárquica y UI no bloqueante.
 * Orden de búsqueda: 1) inscritos (abortar si ya registrado), 2) usuarios, 3) API externa.
 * Uso: definir window.INVITATION_REGISTER_CONFIG = { apiBase, torneoId } antes de cargar este script.
 */
(function () {
    'use strict';

    var config = window.INVITATION_REGISTER_CONFIG || {};
    var API_BASE = config.apiBase || '';
    if (!API_BASE) {
        var pathname = (typeof window !== 'undefined' && window.location && window.location.pathname) ? window.location.pathname : '/';
        var parts = pathname.replace(/^\//, '').split('/').filter(Boolean);
        if (parts.length >= 2) {
            parts.pop();
            parts.pop();
            API_BASE = '/' + parts.join('/') + '/api';
        } else {
            API_BASE = '/api';
        }
    }
    var TORNEO_ID = config.torneoId || 0;

    var TOAST_DURATION_MS = 4500;
    var toastContainer = null;

    function getToastContainer() {
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'invitation-toast-container';
            toastContainer.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastContainer);
        }
        return toastContainer;
    }

    /**
     * Muestra un mensaje efímero (toast). No bloquea ni requiere interacción.
     */
    function showToast(message, type) {
        type = type || 'info';
        var container = getToastContainer();
        var toast = document.createElement('div');
        toast.className = 'invitation-toast invitation-toast--' + type;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function () {
            toast.classList.add('invitation-toast--out');
            setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }, TOAST_DURATION_MS);
    }

    function showLoadingIndicator() {
        var id = 'loadingIndicator';
        var indicator = document.getElementById(id);
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = id;
            indicator.className = 'alert alert-info invitation-loading';
            indicator.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Buscando datos...';
            var container = document.querySelector('.container');
            if (container) container.insertBefore(indicator, container.firstChild);
        }
        indicator.style.display = 'block';
    }

    function hideLoadingIndicator() {
        var indicator = document.getElementById('loadingIndicator');
        if (indicator) indicator.style.display = 'none';
    }

    function clearFormFields() {
        var nac = document.getElementById('nacionalidad');
        if (nac) nac.value = '';
        var ced = document.getElementById('cedula');
        if (ced) ced.value = '';
        var nom = document.getElementById('nombre');
        if (nom) nom.value = '';
        var sexo = document.getElementById('sexo');
        if (sexo) sexo.value = '';
        var fech = document.getElementById('fechnac');
        if (fech) fech.value = '';
        var tel = document.getElementById('telefono');
        if (tel) tel.value = '';
        var email = document.getElementById('email');
        if (email) email.value = '';
        if (ced) ced.focus();
    }

    function fillFormFromPersona(persona) {
        if (!persona) return;
        var n = (persona.nacionalidad || '').toString().trim().toUpperCase().substring(0, 1);
        var nac = document.getElementById('nacionalidad');
        if (nac && n && ['V','E','J','P'].indexOf(n) >= 0) nac.value = n;
        var nom = document.getElementById('nombre');
        if (nom) nom.value = (persona.nombre || '');
        var sexo = document.getElementById('sexo');
        if (sexo) sexo.value = (persona.sexo || '').toString().substring(0, 1).toUpperCase() || 'M';
        var fech = document.getElementById('fechnac');
        if (fech) fech.value = (persona.fechnac || '');
        var tel = document.getElementById('telefono');
        if (tel) tel.value = (persona.celular || persona.telefono || '');
        var email = document.getElementById('email');
        if (email) email.value = (persona.email || '');
    }

    /**
     * Búsqueda automática en orden estricto (siempre al salir del campo - onblur):
     * 1. Inscritos → si ya está inscrito: mensaje y abortar.
     * 2. Usuarios → si existe: llenar formulario.
     * 3. Base externa → último recurso (search_persona hace 2 y 3 en una llamada).
     */
    async function searchPersona() {
        var cedulaEl = document.getElementById('cedula');
        var nacEl = document.getElementById('nacionalidad');
        var cedula = (cedulaEl && cedulaEl.value || '').trim().replace(/\D/g, '');
        var nacionalidad = (nacEl && nacEl.value) || '';

        if (!cedula || !nacionalidad) return;

        try {
            showLoadingIndicator();

            // 1. Validar en inscritos
            var checkUrl = API_BASE + '/check_cedula.php?cedula=' + encodeURIComponent(cedula) + '&torneo=' + TORNEO_ID + '&nacionalidad=' + encodeURIComponent(nacionalidad);
            var checkRes = await fetch(checkUrl);
            var checkData = await checkRes.json();

            if (checkData.success && checkData.exists) {
                showToast('Jugador ya registrado', 'warning');
                clearFormFields();
                return;
            }

            // 2 y 3. Buscar en usuarios y, si no, en API externa (search_persona hace ambos en orden)
            var searchUrl = API_BASE + '/search_persona.php?cedula=' + encodeURIComponent(cedula) + '&nacionalidad=' + encodeURIComponent(nacionalidad);
            var response = await fetch(searchUrl);
            var result = await response.json();

            if ((result.encontrado || result.success) && (result.persona || result.data)) {
                fillFormFromPersona(result.persona || result.data);
            } else {
                showToast('No se encontraron datos para esta cédula', 'info');
            }
        } catch (err) {
            console.error('Error en la búsqueda:', err);
            showToast('Error al buscar datos de la cédula', 'danger');
        } finally {
            hideLoadingIndicator();
        }
    }

    function clearForm() {
        if (confirm('¿Estás seguro de que deseas limpiar todos los campos del formulario?')) {
            var form = document.getElementById('registrationForm');
            if (form) form.reset();
            clearFormFields();
        }
    }

    function init() {
        window.searchPersona = searchPersona;
        window.clearFormInvitation = clearForm;
        window.clearForm = clearForm;
        window.clearFormFieldsInvitation = clearFormFields;
        window.showToastInvitation = showToast;

        var cedulaEl = document.getElementById('cedula');
        if (cedulaEl) {
            cedulaEl.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
            // Búsqueda automática: el onblur está en el HTML del input para que siempre se dispare al salir del campo
        }

        var tel = document.getElementById('telefono');
        if (tel) {
            tel.addEventListener('input', function () {
                var v = this.value.replace(/[^0-9-]/g, '');
                if (v.length > 4 && v.indexOf('-') === -1) v = v.substring(0, 4) + '-' + v.substring(4);
                this.value = v;
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
