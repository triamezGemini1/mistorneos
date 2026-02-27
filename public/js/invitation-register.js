/**
 * Inscripción por invitación: cuatro bloques de búsqueda, cada uno con una acción clara.
 * BLOQUE 1 INSCRITO → accion ya_inscrito: mensaje, limpiar formulario, foco nacionalidad.
 * BLOQUE 2 USUARIO → accion encontrado_usuario: rellenar formulario, permitir inscribir.
 * BLOQUE 3 PERSONAS → accion encontrado_persona: rellenar formulario, permitir inscribir (al enviar se crea usuario).
 * BLOQUE 4 NUEVO → accion nuevo: mantener nacionalidad y cédula, limpiar resto, foco nombre; al enviar se crea usuario e inscribe.
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
    function getTorneoId() {
        var id = config.torneoId || 0;
        if (id === 0) {
            var el = document.getElementById('torneo_id') || document.querySelector('input[name="torneo_id"]');
            if (el && el.value) id = parseInt(el.value, 10) || 0;
        }
        return id;
    }

    var TOAST_DURATION_MS = 4500;
    var toastContainer = null;
    /** Bloqueo para evitar peticiones duplicadas: si true, no disparar otra búsqueda hasta que termine. */
    var isSearching = false;

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

    /**
     * Limpia todos los campos del formulario de búsqueda y deja listo para una nueva búsqueda.
     * Coloca el foco en nacionalidad.
     */
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
        var idUsuarioEl = document.getElementById('id_usuario');
        if (idUsuarioEl) idUsuarioEl.value = '';
        if (nac) nac.focus();
    }

    /**
     * Limpia solo los campos de registro (nombre, sexo, fechnac, teléfono, email, id_usuario).
     * Mantiene nacionalidad y cédula para permitir registro manual (inserción en usuarios e inscritos).
     */
    function clearFormFieldsExceptSearch() {
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
        var idUsuarioEl = document.getElementById('id_usuario');
        if (idUsuarioEl) idUsuarioEl.value = '';
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
        // Si la persona viene de usuarios (tiene id), enviar id_usuario para que el servidor no intente crear usuario (evitar "cédula duplicada")
        var idUsuarioEl = document.getElementById('id_usuario');
        if (idUsuarioEl) {
            var pid = (persona.id && parseInt(persona.id, 10) > 0) ? parseInt(persona.id, 10) : '';
            idUsuarioEl.value = pid;
        }
    }

    /**
     * Búsqueda en cuatro bloques (backend devuelve accion: ya_inscrito | encontrado_usuario | encontrado_persona | nuevo | error).
     * Cada acción ejecuta una sola respuesta: mensaje + limpiar/rellenar + foco.
     */
    async function searchPersona() {
        if (isSearching) return;
        var cedulaEl = document.getElementById('cedula');
        var nacEl = document.getElementById('nacionalidad');
        var cedula = (cedulaEl && cedulaEl.value || '').trim().replace(/\D/g, '');
        var nacionalidad = (nacEl && nacEl.value) || '';

        if (!nacionalidad) {
            showToast('Seleccione la nacionalidad primero.', 'warning');
            if (nacEl) nacEl.focus();
            return;
        }
        if (!cedula) {
            showToast('Ingrese el número de cédula.', 'warning');
            if (cedulaEl) cedulaEl.focus();
            return;
        }

        isSearching = true;
        try {
            showLoadingIndicator();

            var torneoId = getTorneoId();
            var searchUrl = (API_BASE ? API_BASE.replace(/\/$/, '') : '') + '/search_persona.php?cedula=' + encodeURIComponent(cedula) + '&nacionalidad=' + encodeURIComponent(nacionalidad) + '&torneo_id=' + torneoId;
            if (typeof console !== 'undefined' && console.log) {
                console.log('search_persona: URL=', searchUrl, 'torneo_id=', torneoId);
            }
            var response = await fetch(searchUrl);
            var result;
            try {
                result = await response.json();
            } catch (parseErr) {
                console.error('search_persona: respuesta no es JSON', parseErr);
                showToast('Error en la respuesta del servidor. Revise la consola (F12).', 'danger');
                hideLoadingIndicator();
                isSearching = false;
                return;
            }
            if (!response.ok) {
                showToast((result.mensaje || result.error || 'Error ') + (response.status ? ' (' + response.status + ')' : ''), 'danger');
                hideLoadingIndicator();
                isSearching = false;
                return;
            }

            var accion = (result.accion || result.status || '').toString().toLowerCase();

            // ─── Acción ERROR ───
            if (accion === 'error') {
                showToast(result.mensaje || result.error || 'Error en la búsqueda', 'danger');
                hideLoadingIndicator();
                isSearching = false;
                return;
            }

            // ─── Acción YA_INSCRITO (BLOQUE INSCRITO): mensaje, limpiar formulario, foco en nacionalidad ───
            if (accion === 'ya_inscrito') {
                showToast(result.mensaje || 'El jugador ya está en este torneo. Puede ingresar otra cédula.', 'info');
                clearFormFields();
                setTimeout(function () {
                    var el = document.getElementById('nacionalidad');
                    if (el) el.focus();
                }, 0);
                hideLoadingIndicator();
                isSearching = false;
                return;
            }

            // ─── Acción ENCONTRADO_USUARIO o ENCONTRADO_PERSONA (BLOQUES USUARIO / PERSONAS): rellenar formulario, permitir inscribir ───
            if (accion === 'encontrado_usuario' || accion === 'encontrado_persona' || ((result.encontrado || result.success) && (result.persona || result.data))) {
                var persona = result.persona || result.data;
                if (persona) {
                    fillFormFromPersona(persona);
                    showToast(result.mensaje || 'Datos encontrados. Revise los datos y pulse Inscribir.', 'success');
                    setTimeout(function () {
                        var el = document.getElementById('nombre');
                        if (el) el.focus();
                    }, 0);
                } else {
                    showToast('No se recibieron datos de la persona.', 'warning');
                }
                hideLoadingIndicator();
                isSearching = false;
                return;
            }

            // ─── Acción NUEVO (BLOQUE NUEVO): mantener nacionalidad y cédula, limpiar resto, foco en nombre; al enviar se crea usuario e inscribe ───
            if (accion === 'nuevo' || accion === 'no_encontrado') {
                showToast(result.mensaje || 'No encontrado. Complete nombre y el resto de datos; al pulsar Inscribir se creará el usuario y se inscribirá en el torneo.', 'info');
                clearFormFieldsExceptSearch();
                setTimeout(function () {
                    var el = document.getElementById('nombre');
                    if (el) el.focus();
                }, 0);
                hideLoadingIndicator();
                isSearching = false;
                return;
            }

            showToast(result.mensaje || 'No se encontraron datos para esta cédula.', 'info');
            clearFormFields();
            setTimeout(function () {
                var el = document.getElementById('nacionalidad');
                if (el) el.focus();
            }, 0);
        } catch (err) {
            console.error('Error en la búsqueda:', err);
            showToast('Error al buscar datos de la cédula', 'danger');
            clearFormFields();
            setTimeout(function () {
                var el = document.getElementById('nacionalidad');
                if (el) el.focus();
            }, 0);
        } finally {
            isSearching = false;
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
