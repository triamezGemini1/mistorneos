/**
 * Motor único de búsqueda e inscripción (Niveles 1-4 + guardado con club).
 * Requiere: window.INSCRIPCIONES_CONFIG = { API_URL, BUSCAR_API, TORNEOS_ID, CSRF_TOKEN }
 * Opcional: onInscritoExitoso(data), onRegistrarInscribirExitoso(data) para actualizar tabla/redirect.
 */
(function () {
    'use strict';

    var config = window.INSCRIPCIONES_CONFIG || {};
    var API_URL = config.API_URL || '';
    var BUSCAR_API = config.BUSCAR_API || '';
    var TORNEOS_ID = config.TORNEOS_ID || 0;
    var CSRF_TOKEN = config.CSRF_TOKEN || '';

    /** Bloqueo de peticiones: si true, no disparar fetch hasta que termine la búsqueda anterior. */
    var busquedaEnCurso = false;

    // --- Selectores unificados (Inscripción en Sitio: select_club_cedula, form_club; otros: club_id, select_club) ---
    function getCedulaField() {
        return document.getElementById('cedula') || document.getElementById('input_cedula');
    }
    function getNacionalidadField() {
        return document.getElementById('nacionalidad') || document.getElementById('select_nacionalidad_cedula');
    }
    /** Valor del club para inscribir usuario existente (Inscripción en Sitio: select_club_cedula) */
    function getClubIdForInscribir() {
        var el = document.getElementById('club_id') || document.getElementById('select_club_cedula') || document.getElementById('select_club');
        return el ? (el.value || '').trim() : '';
    }
    /** Valor del club para registrar e inscribir (Inscripción en Sitio: form_club) */
    function getClubIdForRegistrar() {
        var el = document.getElementById('club_id') || document.getElementById('form_club') || document.getElementById('select_club');
        return el ? (el.value || '').trim() : '';
    }
    /** Si existe selector de club en el DOM para esta acción, es obligatorio. */
    function requireClubForInscribir() {
        var el = document.getElementById('select_club_cedula') || document.getElementById('club_id') || document.getElementById('select_club');
        return !!el;
    }
    function requireClubForRegistrar() {
        var el = document.getElementById('form_club') || document.getElementById('club_id') || document.getElementById('select_club');
        return !!el;
    }

    var mensajeForm = null;
    var resultadoBusqueda = null;
    var infoUsuario = null;
    var wrapAcciones = null;
    var wrapEstatus = null;
    var wrapBtnInscribir = null;
    var formNuevo = null;
    var usuarioEncontrado = null;

    function cacheRefs() {
        mensajeForm = document.getElementById('mensaje_formulario_cedula');
        resultadoBusqueda = document.getElementById('resultado_busqueda');
        infoUsuario = document.getElementById('info_usuario_encontrado');
        wrapAcciones = document.getElementById('wrap_acciones_cedula');
        wrapEstatus = document.getElementById('wrap_estatus_cedula');
        wrapBtnInscribir = document.getElementById('wrap_btn_inscribir_cedula');
        formNuevo = document.getElementById('form_nuevo_usuario_inscribir');
    }

    function mostrarMensajeForm(html, tipo) {
        if (!mensajeForm) return;
        mensajeForm.innerHTML = html;
        mensajeForm.className = 'mb-3 alert alert-' + (tipo || 'info');
        mensajeForm.classList.remove('d-none');
        mensajeForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function limpiarMensajeForm() {
        if (mensajeForm) {
            mensajeForm.innerHTML = '';
            mensajeForm.classList.add('d-none');
        }
    }

    function rellenarFormularioDatos(nac, num, p) {
        p = p || {};
        var formNac = document.getElementById('form_nac');
        var formCedula = document.getElementById('form_cedula');
        var formNombre = document.getElementById('form_nombre');
        var formFechnac = document.getElementById('form_fechnac');
        var formSexo = document.getElementById('form_sexo');
        var formTelefono = document.getElementById('form_telefono');
        var formEmail = document.getElementById('form_email');
        if (formNac) formNac.value = p.nacionalidad || nac;
        if (formCedula) formCedula.value = p.cedula || num;
        if (formNombre) formNombre.value = p.nombre || '';
        if (formFechnac) formFechnac.value = p.fechnac || '';
        if (formSexo) formSexo.value = (p.sexo || 'M').toUpperCase();
        if (formTelefono) formTelefono.value = p.telefono || p.celular || '';
        if (formEmail) formEmail.value = p.email || '';
    }

    /** Limpia el formulario y deja foco en Nacionalidad para el siguiente jugador. */
    function limpiarBusquedaCedula() {
        var inputCedula = getCedulaField();
        var selectNac = getNacionalidadField();
        if (inputCedula) inputCedula.value = '';
        if (selectNac) selectNac.value = 'V';
        if (resultadoBusqueda) resultadoBusqueda.classList.add('d-none');
        if (wrapAcciones) wrapAcciones.classList.add('d-none');
        if (wrapEstatus) wrapEstatus.classList.add('d-none');
        if (wrapBtnInscribir) wrapBtnInscribir.classList.add('d-none');
        if (formNuevo) formNuevo.classList.add('d-none');
        limpiarMensajeForm();
        usuarioEncontrado = null;
        if (selectNac) {
            selectNac.focus();
        }
    }

    /**
     * Búsqueda única (Niveles 1-4): solo lectura. Siempre envía torneo_id. Bloqueada con isSearching para evitar duplicados.
     */
    function buscarJugador() {
        if (busquedaEnCurso) return;
        cacheRefs();
        var selectNac = getNacionalidadField();
        var inputCedula = getCedulaField();
        var nac = (selectNac && selectNac.value) ? selectNac.value : 'V';
        var num = (inputCedula && inputCedula.value ? inputCedula.value : '').replace(/\D/g, '');

        if (num.length < 4) return;
        if (!BUSCAR_API) {
            console.warn('INSCRIPCIONES: falta BUSCAR_API en INSCRIPCIONES_CONFIG');
            return;
        }

        if (resultadoBusqueda) resultadoBusqueda.classList.add('d-none');
        if (wrapAcciones) wrapAcciones.classList.add('d-none');
        if (wrapBtnInscribir) wrapBtnInscribir.classList.add('d-none');
        if (formNuevo) formNuevo.classList.add('d-none');
        limpiarMensajeForm();
        usuarioEncontrado = null;

        busquedaEnCurso = true;
        var url = BUSCAR_API + '?torneo_id=' + (TORNEOS_ID || 0) + '&nacionalidad=' + encodeURIComponent(nac) + '&cedula=' + encodeURIComponent(num);

        if (typeof Swal !== 'undefined') {
            Swal.fire({ title: 'Buscando...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
        } else {
            mostrarMensajeForm('<i class="fas fa-spinner fa-spin me-2"></i>Buscando (inscritos → usuarios → base externa)...', 'info');
        }

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                busquedaEnCurso = false;
                if (typeof Swal !== 'undefined') { Swal.close(); } else { limpiarMensajeForm(); }
                if (!data.success) {
                    var msg = data.mensaje || 'No se pudo realizar la búsqueda.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: msg });
                    } else {
                        mostrarMensajeForm('<strong>Error:</strong> ' + msg, 'danger');
                    }
                    return;
                }
                // NIVEL 1: Ya inscrito
                if (data.resultado === 'ya_inscrito') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Jugador ya inscrito',
                            text: 'Esta cédula ya está registrada en este torneo.'
                        }).then(function () {
                            limpiarBusquedaCedula();
                            rellenarFormularioDatos('V', '', {});
                            if (wrapBtnInscribir) wrapBtnInscribir.classList.add('d-none');
                            if (selectNac) selectNac.focus();
                        });
                    } else {
                        limpiarBusquedaCedula();
                        mostrarMensajeForm('Esta cédula ya está registrada en este torneo.', 'warning');
                    }
                    return;
                }
                // NIVEL 2 / NIVEL 3: Usuario local o persona externa
                if (data.resultado === 'usuario' || data.resultado === 'persona_externa') {
                    var esExterno = data.resultado === 'persona_externa';
                    var p = esExterno ? (data.persona || {}) : data.usuario;
                    rellenarFormularioDatos(nac, num, p);
                    if (data.resultado === 'usuario') {
                        usuarioEncontrado = data.usuario;
                        if (document.getElementById('btn_registrar_inscribir')) document.getElementById('btn_registrar_inscribir').classList.add('d-none');
                        if (resultadoBusqueda) resultadoBusqueda.classList.remove('d-none');
                        if (infoUsuario) infoUsuario.innerHTML = '<div class="alert alert-success mb-0"><strong><i class="fas fa-check-circle me-2"></i>Usuario encontrado</strong><br>ID: ' + usuarioEncontrado.id + ' &middot; ' + (usuarioEncontrado.nombre || usuarioEncontrado.username || '') + '</div>';
                        if (wrapAcciones) wrapAcciones.classList.remove('d-none');
                        if (wrapEstatus) wrapEstatus.classList.remove('d-none');
                        if (wrapBtnInscribir) wrapBtnInscribir.classList.remove('d-none');
                    } else {
                        if (document.getElementById('btn_registrar_inscribir')) document.getElementById('btn_registrar_inscribir').classList.remove('d-none');
                    }
                    if (formNuevo) formNuevo.classList.remove('d-none');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Jugador Localizado',
                            text: 'Datos cargados correctamente.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function () {
                            var tel = document.getElementById('form_telefono');
                            var eml = document.getElementById('form_email');
                            if (esExterno) {
                                if (tel) tel.focus();
                            } else {
                                if (tel && !tel.value.trim()) tel.focus();
                                else if (eml && !eml.value.trim()) eml.focus();
                                else if (tel) tel.focus();
                            }
                        });
                    } else {
                        var tel = document.getElementById('form_telefono');
                        if (esExterno && tel) setTimeout(function () { tel.focus(); }, 100);
                        else if (tel) setTimeout(function () { tel.focus(); }, 100);
                    }
                    return;
                }
                // NIVEL 4: No encontrado — formulario manual
                if (data.resultado === 'no_encontrado') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'question',
                            title: 'Sin registros',
                            text: 'El jugador no existe. Por favor, complete los datos manualmente.'
                        }).then(function () {
                            rellenarFormularioDatos(nac, num, {});
                            if (formNuevo) formNuevo.classList.remove('d-none');
                            if (document.getElementById('btn_registrar_inscribir')) document.getElementById('btn_registrar_inscribir').classList.remove('d-none');
                            var nom = document.getElementById('form_nombre');
                            if (nom) nom.focus();
                        });
                    } else {
                        rellenarFormularioDatos(nac, num, {});
                        if (formNuevo) formNuevo.classList.remove('d-none');
                        if (document.getElementById('btn_registrar_inscribir')) document.getElementById('btn_registrar_inscribir').classList.remove('d-none');
                        var nom = document.getElementById('form_nombre');
                        if (nom) setTimeout(function () { nom.focus(); }, 100);
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'info', title: 'Sin resultados', text: data.mensaje || 'Sin resultados.' });
                } else {
                    mostrarMensajeForm((data.mensaje || 'Sin resultados.'), 'secondary');
                }
            })
            .catch(function (err) {
                busquedaEnCurso = false;
                console.error(err);
                if (typeof Swal !== 'undefined') {
                    Swal.close();
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar. Revise la consola.' });
                } else {
                    mostrarMensajeForm('<strong>Error:</strong> No se pudo conectar. Revise la consola.', 'danger');
                }
            });
    }

    /**
     * Guardar inscripción de usuario existente (action=inscribir).
     * Club obligatorio si existe selector de club en el DOM (Inscripción en Sitio: select_club_cedula).
     */
    function guardarInscripcionUsuarioExistente() {
        cacheRefs();
        if (!usuarioEncontrado || !usuarioEncontrado.id) return;
        var clubId = getClubIdForInscribir();
        if (requireClubForInscribir() && !clubId) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Club requerido', text: 'Debe seleccionar un club.' });
            } else if (mensajeForm) {
                mostrarMensajeForm('Debe seleccionar un club.', 'warning');
            }
            return;
        }
        var fd = new FormData();
        fd.append('action', 'inscribir');
        fd.append('torneo_id', TORNEOS_ID);
        fd.append('id_usuario', usuarioEncontrado.id);
        fd.append('id_club', clubId);
        fd.append('estatus', 1);
        fd.append('csrf_token', CSRF_TOKEN);

        var btn = document.getElementById('btn_inscribir_cedula');
        if (btn) btn.disabled = true;

        fetch(API_URL, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (btn) btn.disabled = false;
                if (data.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Inscrito',
                            text: data.message || 'Jugador inscrito exitosamente.',
                            showConfirmButton: true,
                            confirmButtonText: 'OK'
                        }).then(function () {
                            limpiarBusquedaCedula();
                            var selNac = getNacionalidadField();
                            if (selNac) selNac.focus();
                            if (typeof config.onInscritoExitoso === 'function') {
                                config.onInscritoExitoso(data, usuarioEncontrado, clubId);
                            } else {
                                window.location.reload();
                            }
                        });
                    } else {
                        limpiarBusquedaCedula();
                        var selNac = getNacionalidadField();
                        if (selNac) selNac.focus();
                        if (typeof config.onInscritoExitoso === 'function') config.onInscritoExitoso(data, usuarioEncontrado, clubId);
                        else window.location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo inscribir.' });
                    } else if (mensajeForm) {
                        mostrarMensajeForm('<strong>Error:</strong> ' + (data.error || ''), 'danger');
                    }
                }
            })
            .catch(function (err) {
                if (btn) btn.disabled = false;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                } else if (mensajeForm) {
                    mostrarMensajeForm('<strong>Error:</strong> ' + err.message, 'danger');
                }
            });
    }

    /**
     * Guardar registrar e inscribir (action=registrar_inscribir).
     * Club obligatorio si existe form_club en el DOM (Inscripción en Sitio).
     */
    function guardarRegistrarInscribir() {
        cacheRefs();
        var nac = (document.getElementById('form_nac') && document.getElementById('form_nac').value) || 'V';
        var ced = (document.getElementById('form_cedula') && document.getElementById('form_cedula').value) ? document.getElementById('form_cedula').value.replace(/\D/g, '') : '';
        var nom = (document.getElementById('form_nombre') && document.getElementById('form_nombre').value) ? document.getElementById('form_nombre').value.trim() : '';

        if (ced.length < 4 || nom.length < 2) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Datos obligatorios', text: 'Cédula (mín. 4 dígitos) y nombre son obligatorios.' });
            } else {
                mostrarMensajeForm('Cédula (mín. 4 dígitos) y nombre son obligatorios.', 'danger');
            }
            return;
        }

        var clubId = getClubIdForRegistrar();
        if (requireClubForRegistrar() && !clubId) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Club requerido', text: 'Debe seleccionar un club.' });
            } else if (mensajeForm) {
                mostrarMensajeForm('Debe seleccionar un club.', 'warning');
            }
            return;
        }

        var fd = new FormData();
        fd.append('action', 'registrar_inscribir');
        fd.append('torneo_id', TORNEOS_ID);
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('nacionalidad', nac);
        fd.append('cedula', ced);
        fd.append('nombre', nom);
        fd.append('fechnac', (document.getElementById('form_fechnac') && document.getElementById('form_fechnac').value) || '');
        fd.append('sexo', (document.getElementById('form_sexo') && document.getElementById('form_sexo').value) || 'M');
        fd.append('telefono', (document.getElementById('form_telefono') && document.getElementById('form_telefono').value) || '');
        fd.append('email', (document.getElementById('form_email') && document.getElementById('form_email').value) || '');
        fd.append('id_club', clubId);
        fd.append('estatus', 1);

        var btn = document.getElementById('btn_registrar_inscribir');
        if (btn) btn.disabled = true;

        fetch(API_URL, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (btn) btn.disabled = false;
                if (data.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Registrado e inscrito',
                            text: data.message || 'Usuario registrado e inscrito correctamente.',
                            showConfirmButton: true,
                            confirmButtonText: 'OK'
                        }).then(function () {
                            limpiarBusquedaCedula();
                            var selNac = getNacionalidadField();
                            if (selNac) selNac.focus();
                            if (typeof config.onRegistrarInscribirExitoso === 'function') {
                                config.onRegistrarInscribirExitoso(data, nom, nac + ced, clubId);
                            } else {
                                window.location.reload();
                            }
                        });
                    } else {
                        limpiarBusquedaCedula();
                        var selNac = getNacionalidadField();
                        if (selNac) selNac.focus();
                        if (typeof config.onRegistrarInscribirExitoso === 'function') config.onRegistrarInscribirExitoso(data, nom, nac + ced, clubId);
                        else window.location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo registrar.' });
                    } else if (mensajeForm) {
                        mostrarMensajeForm('<strong>Error:</strong> ' + (data.error || ''), 'danger');
                    }
                }
            })
            .catch(function (err) {
                if (btn) btn.disabled = false;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                } else if (mensajeForm) {
                    mostrarMensajeForm('<strong>Error:</strong> ' + err.message, 'danger');
                }
            });
    }

    function showMessage(message, type) {
        if (typeof window.INSCRIPCIONES_CONFIG !== 'undefined' && typeof window.INSCRIPCIONES_CONFIG.showMessage === 'function') {
            window.INSCRIPCIONES_CONFIG.showMessage(message, type);
            return;
        }
        var alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-' + type + ' alert-dismissible fade show';
        alertDiv.innerHTML = message + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        var cardBody = document.querySelector('.card-body');
        if (cardBody) {
            cardBody.insertBefore(alertDiv, cardBody.firstChild);
            setTimeout(function () { alertDiv.remove(); }, 3000);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        cacheRefs();
        var elCedula = getCedulaField();
        if (elCedula) {
            elCedula.addEventListener('blur', function () {
                if (!formNuevo || formNuevo.classList.contains('d-none')) {
                    buscarJugador();
                }
            });
            elCedula.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (!formNuevo || formNuevo.classList.contains('d-none')) buscarJugador();
                }
            });
        }

        var btnOtra = document.getElementById('btn_otra_busqueda_cedula');
        if (btnOtra) btnOtra.addEventListener('click', limpiarBusquedaCedula);

        var btnInscribir = document.getElementById('btn_inscribir_cedula');
        if (btnInscribir) btnInscribir.addEventListener('click', guardarInscripcionUsuarioExistente);

        var btnRegistrar = document.getElementById('btn_registrar_inscribir');
        if (btnRegistrar) btnRegistrar.addEventListener('click', guardarRegistrarInscribir);

        var btnCancelar = document.getElementById('btn_cancelar_form_nuevo');
        if (btnCancelar) {
            btnCancelar.addEventListener('click', function () {
                if (formNuevo) formNuevo.classList.add('d-none');
                if (mensajeForm) { mensajeForm.innerHTML = ''; mensajeForm.classList.add('d-none'); }
            });
        }
    });

    window.buscarJugador = buscarJugador;
    window.limpiarBusquedaCedula = limpiarBusquedaCedula;
    window.inscripcionesGetClubIdForInscribir = getClubIdForInscribir;
    window.inscripcionesGetClubIdForRegistrar = getClubIdForRegistrar;
    console.log('inscripciones.js: motor único de búsqueda (Niveles 1-4) y guardado con club cargado.');
})();
