(function () {
  'use strict';

  var root = document.getElementById('mn-registro-padron');
  if (!root) return;

  var apiPadron = root.getAttribute('data-api-padron') || '';
  var apiRegistro = root.getAttribute('data-api-registro') || '';
  var dashboardUrl = root.getAttribute('data-dashboard-url') || 'dashboard.php';
  var csrf = root.getAttribute('data-csrf') || '';

  var inputCed = document.getElementById('mn-reg-cedula');
  var btnBuscar = document.getElementById('mn-reg-buscar-padron');
  var msgA = document.getElementById('mn-reg-msg-a');
  var stepB = root.querySelector('[data-step="b"]');
  var stepC = root.querySelector('[data-step="c"]');
  var encontrado = document.getElementById('mn-reg-encontrado');
  var btnContinuar = document.getElementById('mn-reg-continuar');
  var formFinal = document.getElementById('mn-reg-form-final');
  var hiddenCed = document.getElementById('mn-reg-hidden-cedula');
  var msgC = document.getElementById('mn-reg-msg-c');

  var cedulaActual = '';

  function show(el, on) {
    if (!el) return;
    el.hidden = !on;
  }

  function setMsgA(text, isError) {
    if (!msgA) return;
    msgA.textContent = text || '';
    msgA.hidden = !text;
    msgA.classList.toggle('mn-hint--error', !!isError);
  }

  function primerNombre(completo) {
    var p = (completo || '').trim().split(/\s+/);
    return p[0] || 'atleta';
  }

  function resetFlujo() {
    show(stepB, false);
    show(stepC, false);
    setMsgA('');
    if (msgC) msgC.textContent = '';
    cedulaActual = '';
    if (hiddenCed) hiddenCed.value = '';
  }

  if (btnBuscar) {
    btnBuscar.addEventListener('click', function () {
      var ced = inputCed ? inputCed.value.trim() : '';
      resetFlujo();
      if (ced.length < 4) {
        setMsgA('Ingrese una cédula válida (mínimo 4 dígitos).', true);
        return;
      }
      cedulaActual = ced;
      setMsgA('Consultando padrón…', false);
      show(msgA, true);
      msgA.classList.remove('mn-hint--error');

      var url = apiPadron + (apiPadron.indexOf('?') >= 0 ? '&' : '?') + 'cedula=' + encodeURIComponent(ced);
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (!data) {
            setMsgA('Respuesta inválida.', true);
            return;
          }
          if (data.motivo === 'padron_no_configurado') {
            setMsgA(data.message || 'Padrón no configurado.', true);
            return;
          }
          if (!data.encontrado) {
            setMsgA('No aparece en el padrón con ese documento. Revise el número o la letra (V/E).', true);
            return;
          }
          setMsgA('');
          show(msgA, false);
          var nom = (data.datos && data.datos.nombre_completo) || '';
          var pn = primerNombre(nom);
          if (encontrado) {
            encontrado.innerHTML =
              '¡Te encontramos, <strong>' +
              escapeHtml(pn) +
              '</strong>! ¿Deseas completar tu perfil en mistorneos?';
          }
          show(stepB, true);
        })
        .catch(function () {
          setMsgA('No se pudo consultar el padrón.', true);
        });
    });
  }

  if (btnContinuar) {
    btnContinuar.addEventListener('click', function () {
      if (!cedulaActual) return;
      show(stepB, false);
      if (hiddenCed) hiddenCed.value = cedulaActual;
      show(stepC, true);
      var em = document.getElementById('mn-reg-email');
      if (em) em.focus();
    });
  }

  if (formFinal) {
    formFinal.addEventListener('submit', function (e) {
      e.preventDefault();
      if (msgC) {
        msgC.textContent = '';
        msgC.classList.remove('mn-hint--error');
      }
      var ced = hiddenCed ? hiddenCed.value.trim() : '';
      var email = document.getElementById('mn-reg-email');
      var tel = document.getElementById('mn-reg-tel');
      var pass = document.getElementById('mn-reg-pass');
      var payload = {
        csrf_token: csrf,
        cedula: ced,
        email: email ? email.value.trim() : '',
        telefono: tel ? tel.value.trim() : '',
        password: pass ? pass.value : '',
      };

      fetch(apiRegistro, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload),
      })
        .then(function (r) {
          return r.json().then(function (j) {
            return { ok: r.ok, body: j };
          });
        })
        .then(function (res) {
          if (res.body && res.body.ok) {
            window.location.href = dashboardUrl;
            return;
          } else {
            if (msgC) {
              msgC.textContent = (res.body && res.body.message) || 'No se pudo registrar.';
              msgC.classList.add('mn-hint--error');
            }
          }
        })
        .catch(function () {
          if (msgC) {
            msgC.textContent = 'Error de red al registrar.';
            msgC.classList.add('mn-hint--error');
          }
        });
    });
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }
})();
