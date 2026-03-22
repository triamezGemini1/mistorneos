(function () {
  'use strict';

  var wrap = document.querySelector('.mn-carga-res-forms');
  if (!wrap) return;

  var objetivo = parseInt(wrap.getAttribute('data-puntos-objetivo'), 10);
  if (!objetivo || objetivo < 1) objetivo = 100;

  var modo = wrap.getAttribute('data-modo') || 'estandar';

  function sumInputs(selector) {
    var nodes = wrap.querySelectorAll(selector);
    var t = 0;
    for (var i = 0; i < nodes.length; i++) {
      var v = parseFloat(nodes[i].value);
      if (!isNaN(v)) t += v;
    }
    return t;
  }

  function showMsg(id, text) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = text || '';
    el.hidden = !text;
  }

  var formEst = document.getElementById('mn-form-carga-estandar');
  if (formEst && modo === 'estandar') {
    formEst.addEventListener('submit', function (e) {
      var s = sumInputs('input[data-campo="puntos"]');
      if (Math.round(s) !== objetivo) {
        e.preventDefault();
        showMsg(
          'mn-carga-valid-estandar',
          'La suma de puntos de los jugadores debe ser exactamente ' + objetivo + ' (actual: ' + Math.round(s) + ').'
        );
        return;
      }
      showMsg('mn-carga-valid-estandar', '');
    });
  }

  var formPar = document.getElementById('mn-form-carga-parejas');
  if (formPar && modo === 'parejas') {
    formPar.addEventListener('submit', function (e) {
      var a = parseFloat(document.getElementById('puntos_pareja_a').value) || 0;
      var b = parseFloat(document.getElementById('puntos_pareja_b').value) || 0;
      if (Math.round(a + b) !== objetivo) {
        e.preventDefault();
        showMsg(
          'mn-carga-valid-parejas',
          'Puntos pareja 1 + pareja 2 deben sumar ' + objetivo + ' (actual: ' + Math.round(a + b) + ').'
        );
        return;
      }
      showMsg('mn-carga-valid-parejas', '');
    });
  }
})();
