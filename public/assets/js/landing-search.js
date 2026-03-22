(function () {
  'use strict';

  var form = document.getElementById('mn-athlete-search');
  var input = document.getElementById('mn-athlete-q');
  var panel = document.getElementById('mn-athlete-results');
  var hint = document.getElementById('mn-athlete-hint');
  if (!form || !input || !panel) return;

  var endpoint = form.getAttribute('data-endpoint') || '';
  var debounceMs = 320;
  var timer = null;
  var ctrl = null;

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function renderLoading() {
    panel.hidden = false;
    panel.innerHTML = '<p class="mn-results__status">Buscando…</p>';
  }

  function renderError(msg) {
    panel.hidden = false;
    panel.innerHTML = '<p class="mn-results__status mn-results__status--error">' + esc(msg) + '</p>';
  }

  function renderRows(rows) {
    if (!rows.length) {
      panel.hidden = false;
      panel.innerHTML = '<p class="mn-results__status">Sin coincidencias (máx. 10 por consulta).</p>';
      return;
    }
    var html = '<ul class="mn-results__list">';
    for (var i = 0; i < rows.length; i++) {
      var r = rows[i];
      var userLine =
        r.username && String(r.username).trim() !== ''
          ? esc(r.username) + ' · '
          : '';
      html +=
        '<li class="mn-results__item"><span class="mn-results__name">' +
        esc(r.nombre || '') +
        '</span><span class="mn-results__meta">' +
        userLine +
        esc(r.cedula || r.id || '') +
        ' · ' +
        esc(r.nacionalidad || '') +
        '</span></li>';
    }
    html += '</ul>';
    panel.hidden = false;
    panel.innerHTML = html;
  }

  function runSearch() {
    var q = (input.value || '').trim();
    var digits = q.replace(/^[VEJP]/i, '').replace(/\D/g, '');
    if (q.length < 2 && digits.length < 3) {
      panel.innerHTML = '';
      panel.hidden = true;
      if (hint) hint.hidden = false;
      return;
    }
    if (hint) hint.hidden = true;
    if (ctrl && typeof ctrl.abort === 'function') ctrl.abort();
    ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    renderLoading();
    var url = endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
    fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      signal: ctrl ? ctrl.signal : undefined,
      headers: { Accept: 'application/json' },
    })
      .then(function (res) {
        if (!res.ok) throw new Error('Error de red');
        return res.json();
      })
      .then(function (data) {
        if (!data || !data.ok) {
          renderError((data && data.message) || 'No se pudo completar la búsqueda.');
          return;
        }
        renderRows(data.resultados || []);
      })
      .catch(function (e) {
        if (e.name === 'AbortError') return;
        renderError('No se pudo completar la búsqueda.');
      });
  }

  function schedule() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(runSearch, debounceMs);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (timer) clearTimeout(timer);
    runSearch();
  });

  input.addEventListener('input', schedule);
  input.addEventListener('search', function () {
    if (input.value === '') {
      panel.innerHTML = '';
      panel.hidden = true;
      if (hint) hint.hidden = false;
    }
  });
})();
