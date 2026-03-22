(function () {
  'use strict';

  var C = window.MN_CHECKIN;
  if (!C || !C.torneoId) return;

  var q = document.getElementById('mn-checkin-q');
  var btnBuscar = document.getElementById('mn-checkin-buscar');
  var result = document.getElementById('mn-checkin-result');
  var tbody = document.getElementById('mn-checkin-tbody');
  var ratificadosEl = document.getElementById('mn-checkin-ratificados');
  var btnRonda1 = document.getElementById('mn-btn-ronda1');

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function fetchJson(url, opts) {
    return fetch(url, Object.assign({ credentials: 'same-origin', headers: { Accept: 'application/json' } }, opts || {})).then(function (r) {
      return r.json().catch(function () {
        return { ok: false };
      });
    });
  }

  function postJson(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body),
    }).then(function (r) {
      return r.json().catch(function () {
        return { ok: false };
      });
    });
  }

  function updateWatcher(w) {
    if (!w || !ratificadosEl || !btnRonda1) return;
    ratificadosEl.textContent = String(w.ratificados != null ? w.ratificados : 0);
    btnRonda1.disabled = !w.puede_ronda1;
  }

  function makeToggle(inscritoId, campo, checked) {
    var id = 'mn-chk-' + campo + '-' + inscritoId;
    var wrap = document.createElement('label');
    wrap.className = 'mn-checkin-switch';
    wrap.setAttribute('for', id);
    var input = document.createElement('input');
    input.type = 'checkbox';
    input.id = id;
    input.checked = !!checked;
    input.dataset.inscritoId = String(inscritoId);
    input.dataset.campo = campo;
    var span = document.createElement('span');
    span.className = 'mn-checkin-switch__ui';
    span.setAttribute('aria-hidden', 'true');
    wrap.appendChild(input);
    wrap.appendChild(span);
    input.addEventListener('change', function () {
      var v = input.checked ? 1 : 0;
      input.disabled = true;
      postJson(C.apiToggle, {
        csrf_token: C.csrf,
        inscrito_id: inscritoId,
        campo: campo,
        valor: v,
      }).then(function (data) {
        input.disabled = false;
        if (data && data.ok && data.watcher) {
          updateWatcher(data.watcher);
        } else {
          input.checked = !input.checked;
        }
      });
    });
    return wrap;
  }

  function renderLista(data) {
    if (!tbody) return;
    tbody.innerHTML = '';
    var rows = (data && data.inscritos) || [];
    rows.forEach(function (row) {
      var tr = document.createElement('tr');
      var id = row.id;
      var r = row.ratificado == 1 || row.ratificado === '1';
      var p = row.presente_sitio == 1 || row.presente_sitio === '1';
      tr.innerHTML =
        '<td class="mn-checkin-td-name">' +
        esc(row.nombre) +
        '</td><td class="mn-checkin-td-doc">' +
        esc(row.cedula) +
        '</td><td class="mn-checkin-td-tog"></td><td class="mn-checkin-td-tog"></td>';
      tr.querySelector('.mn-checkin-td-tog').appendChild(makeToggle(id, 'ratificado', r));
      tr.querySelectorAll('.mn-checkin-td-tog')[1].appendChild(makeToggle(id, 'presente_sitio', p));
      tbody.appendChild(tr);
    });
    if (data && data.watcher) updateWatcher(data.watcher);
  }

  function loadLista() {
    var sep = C.apiLista.indexOf('?') >= 0 ? '&' : '?';
    var url = C.apiLista + sep + 'torneo_id=' + encodeURIComponent(String(C.torneoId));
    return fetchJson(url).then(function (data) {
      if (data && data.ok) renderLista(data);
    });
  }

  function pollWatcher() {
    if (document.hidden) return;
    var sep = C.apiWatcher.indexOf('?') >= 0 ? '&' : '?';
    var url = C.apiWatcher + sep + 'torneo_id=' + encodeURIComponent(String(C.torneoId));
    fetchJson(url).then(function (data) {
      if (data && data.ok && ratificadosEl && btnRonda1) {
        ratificadosEl.textContent = String(data.ratificados);
        btnRonda1.disabled = !data.puede_generar_ronda1;
      }
    });
  }

  function renderBusqueda(data) {
    if (!result) return;
    result.hidden = false;
    var html = '';
    var maestro = (data && data.maestro) || [];
    if (maestro.length) {
      html += '<p class="mn-checkin-result__title">En maestra</p><ul class="mn-checkin-result__list">';
      maestro.forEach(function (u) {
        var uid = u.id;
        var nom = esc(u.nombre || '');
        var doc = esc(u.cedula || '');
        html +=
          '<li class="mn-checkin-result__item"><span class="mn-checkin-result__meta">' +
          nom +
          ' · ' +
          doc +
          '</span> <button type="button" class="mn-btn mn-btn--success mn-checkin-add" data-usuario-id="' +
          String(uid) +
          '">Añadir</button></li>';
      });
      html += '</ul>';
    }
    if (data && data.padron && data.padron.encontrado) {
      var p = data.padron;
      html +=
        '<p class="mn-checkin-result__title">Padrón</p><p class="mn-hint">' +
        esc(p.nombre_completo || p.nombre || 'Persona en padrón') +
        '</p>';
    }
    if (data && data.sugerencia_registro) {
      html +=
        '<p class="mn-hint mn-hint--error">No hay usuario en maestra con ese documento. Use <a href="index.php#mn-registro-padron">registro desde padrón</a> o cree la cuenta antes de inscribir.</p>';
    }
    if (!html) {
      html = '<p class="mn-hint">Sin coincidencias.</p>';
    }
    result.innerHTML = html;
    result.querySelectorAll('.mn-checkin-add').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var uid = parseInt(btn.getAttribute('data-usuario-id'), 10);
        if (!uid) return;
        btn.disabled = true;
        postJson(C.apiInscribir, { csrf_token: C.csrf, torneo_id: C.torneoId, id_usuario: uid }).then(function (res) {
          btn.disabled = false;
          if (res && res.ok) {
            result.innerHTML = '<p class="mn-hint">' + (res.error === 'ya_inscrito' ? 'Ya estaba inscrito.' : 'Inscrito.') + '</p>';
            loadLista();
          } else {
            result.insertAdjacentHTML('beforeend', '<p class="mn-hint mn-hint--error">No se pudo inscribir.</p>');
          }
        });
      });
    });
  }

  function buscar() {
    if (!q || !result) return;
    var term = q.value.trim();
    if (term.length < 4) {
      result.hidden = false;
      result.innerHTML = '<p class="mn-hint mn-hint--error">Mínimo 4 caracteres.</p>';
      return;
    }
    result.hidden = false;
    result.innerHTML = '<p class="mn-hint">Buscando…</p>';
    var sep = C.apiBuscar.indexOf('?') >= 0 ? '&' : '?';
    var url =
      C.apiBuscar + sep + 'torneo_id=' + encodeURIComponent(String(C.torneoId)) + '&q=' + encodeURIComponent(term);
    fetchJson(url).then(function (data) {
      if (data && data.ok) renderBusqueda(data);
      else result.innerHTML = '<p class="mn-hint mn-hint--error">Error de búsqueda.</p>';
    });
  }

  if (btnBuscar) btnBuscar.addEventListener('click', buscar);
  if (q) {
    q.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        buscar();
      }
    });
  }

  if (btnRonda1) {
    btnRonda1.addEventListener('click', function () {
      if (btnRonda1.disabled) return;
      btnRonda1.disabled = true;
      postJson(C.apiRonda1, { csrf_token: C.csrf, torneo_id: C.torneoId }).then(function (data) {
        btnRonda1.disabled = false;
        if (data && data.ok) {
          alert(data.message || 'Listo.');
          pollWatcher();
        } else {
          alert((data && data.message) || 'No se pudo generar.');
          pollWatcher();
        }
      });
    });
  }

  loadLista();
  setInterval(pollWatcher, 4000);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) pollWatcher();
  });
})();
