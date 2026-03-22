(function () {
  'use strict';

  var backdrop = document.getElementById('mn-auth-backdrop');
  var panel = document.getElementById('mn-auth-panel');
  var openBtn = document.getElementById('mn-open-admin');
  var closeBtn = document.getElementById('mn-auth-close');
  var form = document.getElementById('mn-auth-form');
  var user = document.getElementById('mn-auth-user');
  var pass = document.getElementById('mn-auth-pass');
  var userStatus = document.getElementById('mn-auth-user-status');
  var passStatus = document.getElementById('mn-auth-pass-status');

  function openModal() {
    if (!backdrop || !panel) return;
    backdrop.classList.add('is-open');
    panel.classList.add('is-open');
    document.body.classList.add('mn-modal-open');
    if (user) user.focus();
  }

  function closeModal() {
    if (!backdrop || !panel) return;
    backdrop.classList.remove('is-open');
    panel.classList.remove('is-open');
    document.body.classList.remove('mn-modal-open');
  }

  function setFieldStatus(el, statusEl, ok, emptyMsg, invalidMsg) {
    if (!el || !statusEl) return;
    var v = el.value.trim();
    el.classList.toggle('mn-input--error', !ok && v.length > 0);
    statusEl.classList.remove('is-invalid', 'is-valid');
    statusEl.textContent = '';
    if (v.length === 0) {
      statusEl.textContent = emptyMsg;
      statusEl.classList.add('is-invalid');
      return;
    }
    if (!ok) {
      statusEl.textContent = invalidMsg;
      statusEl.classList.add('is-invalid');
      return;
    }
    statusEl.classList.add('is-valid');
    statusEl.textContent = 'OK';
  }

  function validateUser() {
    var v = user ? user.value.trim() : '';
    var ok = v.length >= 3 && v.length <= 128;
    setFieldStatus(user, userStatus, ok, 'Indique usuario o correo.', 'Entre 3 y 128 caracteres.');
    return ok;
  }

  function validatePass() {
    var v = pass ? pass.value : '';
    var ok = v.length >= 8;
    setFieldStatus(pass, passStatus, ok, 'La contraseña es obligatoria.', 'Mínimo 8 caracteres.');
    return ok;
  }

  if (openBtn) openBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (backdrop) backdrop.addEventListener('click', closeModal);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });

  if (user) {
    user.addEventListener('input', validateUser);
    user.addEventListener('blur', validateUser);
  }
  if (pass) {
    pass.addEventListener('input', validatePass);
    pass.addEventListener('blur', validatePass);
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      var uOk = validateUser();
      var pOk = validatePass();
      if (!uOk || !pOk) {
        e.preventDefault();
      }
    });
  }
})();
