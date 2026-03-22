/**
 * Panel de control torneo — acciones JS (Refactorización 2026)
 * Cronómetro de ronda, cuenta atrás cierre, SweetAlert2, importación masiva, copiar enlace.
 */
(function () {
  'use strict';

  function initCronometroRonda() {
    var overlayEl = document.getElementById('cronometroOverlay');
    if (!overlayEl) return;

    var minutosTorneo = parseInt(overlayEl.getAttribute('data-tiempo-minutos'), 10) || 35;
    if (minutosTorneo < 1) minutosTorneo = 35;
    var tiempoRestante = minutosTorneo * 60;
    var tiempoOriginal = minutosTorneo * 60;
    var cronometroInterval = null;
    var estaCorriendo = false;
    var alarmaReproducida = false;
    var alarmaRepetida = false;
    var overlay = overlayEl;
    var btnLbl = document.getElementById('lblCronometro');

    function formatear(s) {
      var m = Math.floor(s / 60);
      var se = s % 60;
      return String(m).padStart(2, '0') + ':' + String(se).padStart(2, '0');
    }

    function actualizarDisplayCron() {
      var d = document.getElementById('tiempoDisplayCron');
      var e = document.getElementById('estadoDisplayCron');
      if (!d || !e) return;
      d.textContent = formatear(tiempoRestante);
      d.style.color = tiempoRestante <= 30 ? '#ef4444' : tiempoRestante <= 60 ? '#fbbf24' : 'white';
      d.style.animation = tiempoRestante <= 30 ? 'cronPulse 1s infinite' : 'none';
      e.innerHTML = estaCorriendo
        ? '<i class="fas fa-play-circle me-1"></i>EN EJECUCIÓN'
        : '<i class="fas fa-pause-circle me-1"></i>DETENIDO';
      e.style.color = estaCorriendo ? '#86efac' : 'rgba(255,255,255,0.9)';
      if (btnLbl) {
        btnLbl.textContent = estaCorriendo ? 'RETORNAR AL CRONÓMETRO' : 'ACTIVAR CRONÓMETRO DE RONDA';
      }
    }

    function reproducirAlarma() {
      try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        for (var i = 0; i < 5; i++) {
          var o = ctx.createOscillator();
          var g = ctx.createGain();
          o.connect(g);
          g.connect(ctx.destination);
          o.frequency.setValueAtTime(400, ctx.currentTime + i * 0.8);
          o.frequency.exponentialRampToValueAtTime(800, ctx.currentTime + i * 0.8 + 0.6);
          o.type = 'sine';
          g.gain.setValueAtTime(0, ctx.currentTime + i * 0.8);
          g.gain.linearRampToValueAtTime(0.5, ctx.currentTime + i * 0.8 + 0.1);
          g.gain.linearRampToValueAtTime(0, ctx.currentTime + i * 0.8 + 0.6);
          o.start(ctx.currentTime + i * 0.8);
          o.stop(ctx.currentTime + i * 0.8 + 0.6);
        }
      } catch (err) {
        if (navigator.vibrate) navigator.vibrate([300, 100, 300, 100, 300]);
      }
    }

    function reproducirAlarma2() {
      try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        for (var i = 0; i < 3; i++) {
          var o = ctx.createOscillator();
          var g = ctx.createGain();
          o.connect(g);
          g.connect(ctx.destination);
          o.frequency.setValueAtTime(60, ctx.currentTime + i * 1.2);
          o.frequency.exponentialRampToValueAtTime(120, ctx.currentTime + i * 1.2 + 0.5);
          o.type = 'sawtooth';
          g.gain.setValueAtTime(0, ctx.currentTime + i * 1.2);
          g.gain.linearRampToValueAtTime(0.6, ctx.currentTime + i * 1.2 + 0.2);
          g.gain.linearRampToValueAtTime(0, ctx.currentTime + i * 1.2 + 1);
          o.start(ctx.currentTime + i * 1.2);
          o.stop(ctx.currentTime + i * 1.2 + 1);
        }
      } catch (err) {
        if (navigator.vibrate) navigator.vibrate([500, 200, 500]);
      }
    }

    window.iniciarCronometro = function () {
      if (tiempoRestante <= 0) tiempoRestante = tiempoOriginal;
      estaCorriendo = true;
      alarmaReproducida = false;
      alarmaRepetida = false;
      document.getElementById('btnIniciarCron').disabled = true;
      document.getElementById('btnDetenerCron').disabled = false;
      cronometroInterval = setInterval(function () {
        tiempoRestante--;
        actualizarDisplayCron();
        if (tiempoRestante <= 0) {
          clearInterval(cronometroInterval);
          cronometroInterval = null;
          estaCorriendo = false;
          document.getElementById('btnIniciarCron').disabled = false;
          document.getElementById('btnDetenerCron').disabled = true;
          if (!alarmaReproducida) {
            reproducirAlarma();
            alarmaReproducida = true;
            setTimeout(function () {
              if (!alarmaRepetida) {
                reproducirAlarma2();
                alarmaRepetida = true;
              }
            }, 180000);
          }
          actualizarDisplayCron();
        }
      }, 1000);
      actualizarDisplayCron();
    };

    window.detenerCronometro = function () {
      estaCorriendo = false;
      clearInterval(cronometroInterval);
      cronometroInterval = null;
      document.getElementById('btnIniciarCron').disabled = false;
      document.getElementById('btnDetenerCron').disabled = true;
      actualizarDisplayCron();
    };

    window.toggleConfigCron = function () {
      var p = document.getElementById('configPanelCron');
      if (!p) return;
      p.style.display = p.style.display === 'none' ? 'block' : 'none';
    };

    window.aplicarConfigCron = function () {
      var m = parseInt(document.getElementById('configMinutosCron').value, 10) || minutosTorneo;
      var s = parseInt(document.getElementById('configSegundosCron').value, 10) || 0;
      tiempoRestante = m * 60 + s;
      tiempoOriginal = tiempoRestante;
      if (!estaCorriendo) actualizarDisplayCron();
      document.getElementById('configPanelCron').style.display = 'none';
    };

    window.ocultarCronometroOverlay = function () {
      overlay.style.display = 'none';
    };

    var btnCron = document.getElementById('btnCronometro');
    if (btnCron) {
      btnCron.onclick = function () {
        overlay.style.display = 'flex';
      };
    }
    actualizarDisplayCron();

    (function initDragCron() {
      var header = overlayEl.querySelector('.cron-header');
      if (!header) return;
      var dragging = false;
      var startX;
      var startY;
      var startLeft;
      var startTop;
      header.addEventListener('mousedown', function (e) {
        if (e.target.closest('button')) return;
        dragging = true;
        var r = overlayEl.getBoundingClientRect();
        startLeft = r.left;
        startTop = r.top;
        startX = e.clientX;
        startY = e.clientY;
        overlayEl.style.right = 'auto';
        overlayEl.style.bottom = 'auto';
        overlayEl.style.left = startLeft + 'px';
        overlayEl.style.top = startTop + 'px';
        e.preventDefault();
      });
      document.addEventListener('mousemove', function (e) {
        if (!dragging) return;
        var dx = e.clientX - startX;
        var dy = e.clientY - startY;
        overlayEl.style.left = startLeft + dx + 'px';
        overlayEl.style.top = startTop + dy + 'px';
        startLeft += dx;
        startTop += dy;
        startX = e.clientX;
        startY = e.clientY;
      });
      document.addEventListener('mouseup', function () {
        dragging = false;
      });
    })();
  }

  /**
   * Cuenta regresiva hasta cierre oficial del torneo (bloques .countdown-tiempo-restante).
   */
  function actualizarCronometro() {
    var countdownEls = document.querySelectorAll('.countdown-tiempo-restante');
    var countdownEl = countdownEls[0];
    if (!countdownEl) return;

    var finTimestamp = parseInt(countdownEl.getAttribute('data-fin'), 10);
    if (!finTimestamp) return;

    var intervalId;

    function tick() {
      var ahora = Math.floor(Date.now() / 1000);
      var restante = finTimestamp - ahora;
      var m = Math.floor(restante / 60);
      var s = restante <= 0 ? 0 : restante % 60;
      var texto =
        restante <= 0
          ? '00:00'
          : (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
      countdownEls.forEach(function (el) {
        el.textContent = texto;
      });
      if (restante <= 0) {
        var listoHtml =
          '<p class="text-sm font-medium text-white"><i class="fas fa-check-circle"></i> Listo para finalizar. Recargando…</p>';
        var topBlock = document.getElementById('countdown-cierre-torneo-top') || countdownEl.closest('.mb-4');
        if (topBlock) topBlock.innerHTML = listoHtml;
        var col = document.getElementById('countdown-cierre-torneo');
        if (col) col.innerHTML = listoHtml;
        window.clearInterval(intervalId);
        setTimeout(function () {
          window.location.reload();
        }, 1500);
      }
    }

    tick();
    intervalId = window.setInterval(tick, 1000);
  }

  function initFormGenerarRonda() {
    var formGenerarRonda = document.getElementById('form-generar-ronda');
    if (!formGenerarRonda) return;
    formGenerarRonda.addEventListener('submit', function () {
      var btnGenerar = document.getElementById('btn-generar-ronda');
      if (btnGenerar && !btnGenerar.disabled) {
        btnGenerar.disabled = true;
        btnGenerar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Generando...';
      }
    });
  }

  window.actualizarEstadisticasConfirmar = async function (event) {
    var result = await window.Swal.fire({
      title: '¿Actualizar estadísticas?',
      text: '¿Actualizar estadísticas de todos los inscritos?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, actualizar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#3b82f6',
      cancelButtonColor: '#6b7280',
    });
    if (result.isConfirmed) {
      event.target.submit();
    }
  };

  window.eliminarRondaConfirmar = async function (event, ronda, tieneResultadosMesas) {
    var form = event.target;
    var inputConfirmar = document.getElementById('confirmar_eliminar_con_resultados');
    if (inputConfirmar) inputConfirmar.value = '';

    if (typeof window.Swal === 'undefined') {
      if (tieneResultadosMesas) {
        var texto = prompt(
          'La ronda ' +
            ronda +
            ' tiene resultados registrados. Para eliminar de todas formas escriba exactamente: ELIMINAR'
        );
        if (texto === 'ELIMINAR' && inputConfirmar) {
          inputConfirmar.value = 'ELIMINAR';
          form.submit();
        }
      } else {
        if (
          confirm(
            '¿Eliminar la ronda ' + ronda + '? Se eliminarán las asignaciones de mesas de esta ronda.'
          )
        ) {
          form.submit();
        }
      }
      return;
    }

    if (tieneResultadosMesas) {
      var resStrict = await window.Swal.fire({
        title: 'Confirmación estricta',
        html:
          '<p class="text-left">La ronda <strong>' +
          ronda +
          '</strong> tiene <strong>resultados de mesas registrados</strong>.</p>' +
          '<p class="text-left text-gray-600">Eliminar borrará todos los resultados y asignaciones de esta ronda. Esta acción no se puede deshacer.</p>' +
          '<p class="text-left mt-3 font-semibold">Para continuar, escriba exactamente: <code class="bg-gray-200 px-1">ELIMINAR</code></p>',
        icon: 'warning',
        input: 'text',
        inputPlaceholder: 'Escriba ELIMINAR',
        inputValidator: function (value) {
          if (value !== 'ELIMINAR') return 'Debe escribir exactamente: ELIMINAR';
        },
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar la ronda',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
      });
      if (resStrict.isConfirmed && resStrict.value === 'ELIMINAR' && inputConfirmar) {
        inputConfirmar.value = 'ELIMINAR';
        form.submit();
      }
      return;
    }

    var result = await window.Swal.fire({
      title: '¿Eliminar ronda?',
      html:
        '¿Está seguro de eliminar la ronda <strong>' +
        ronda +
        '</strong>?<br><small class="text-gray-500">Se eliminarán las asignaciones de mesas de esta ronda.</small>',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
    });
    if (result.isConfirmed) {
      form.submit();
    }
  };

  window.confirmarCierreTorneo = async function (event) {
    await window.Swal.fire({
      title: '<i class="fas fa-lock text-gray-700"></i> Finalizar torneo',
      html:
        '<div class="text-left text-sm">' +
        '<div class="bg-red-50 border-l-4 border-red-500 p-3 mb-3">' +
        '<p class="text-red-700 font-semibold"><i class="fas fa-exclamation-triangle mr-1"></i> Acción irreversible</p>' +
        '</div>' +
        '<p class="mb-2">Esta acción <strong>finalizará definitivamente</strong> el torneo. A partir de ese momento <strong>no será posible modificar datos</strong>; solo consulta:</p>' +
        '<ul class="list-disc pl-5 mb-3 text-gray-600">' +
        '<li>Inscripciones</li><li>Resultados</li><li>Rondas</li><li>Reasignaciones</li></ul>' +
        '<div class="bg-amber-50 border-l-4 border-amber-500 p-3">' +
        '<p class="text-amber-700"><i class="fas fa-info-circle mr-1"></i> Ya han pasado 20 minutos desde el último resultado; puede finalizar para evitar manipulaciones.</p>' +
        '</div></div>',
      icon: null,
      showCancelButton: true,
      confirmButtonText: '<i class="fas fa-lock mr-1"></i> Sí, finalizar torneo',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#111827',
      cancelButtonColor: '#6b7280',
      reverseButtons: true,
      focusCancel: true,
      customClass: { popup: 'rounded-xl' },
    }).then(function (res) {
      if (res.isConfirmed) {
        event.target.submit();
      }
    });
  };

  /**
   * Copia texto al portapapeles. Uso: onclick="copiarEnlace(this.dataset.url)" o copiarEnlace('https://...')
   */
  window.copiarEnlace = function (texto) {
    if (texto == null || texto === '') return;
    var t = String(texto);
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(t).then(function () {
        if (window.Swal) {
          window.Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Enlace copiado',
            showConfirmButton: false,
            timer: 2000,
          });
        }
      });
    } else {
      var ta = document.createElement('textarea');
      ta.value = t;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
      } catch (e) {}
      document.body.removeChild(ta);
    }
  };

  function initImportacionMasiva() {
    var fileEl = document.getElementById('importMasivaFile');
    if (!fileEl) return;

    var CAMPOS = ['nacionalidad', 'cedula', 'nombre', 'sexo', 'fecha_nac', 'telefono', 'email', 'club', 'organizacion'];
    var CAMPOS_LABEL = { organizacion: 'Organización' };
    var COLORS = { omitir: '#3b82f6', inscribir: '#eab308', crear_inscribir: '#22c55e', error: '#ef4444' };
    var CAMPO_ALIASES = {
      nombre: ['nombre', 'nombres y apellidos', 'nombres', 'nombres y apellido'],
      cedula: ['cedula', 'cédula', 'cedula de identidad'],
      organizacion: ['organizacion', 'organización', 'entidad', 'asociacion', 'asociación'],
      club: ['club', 'club_nombre', 'club nombre'],
    };
    var importMasivaHeaders = [];
    var importMasivaRows = [];
    var importMasivaValidacion = [];

    function detectEncodingAndDecode(buffer) {
      var bytes = new Uint8Array(buffer);
      var utf8 = new TextDecoder('utf-8').decode(bytes);
      var mojibakePattern = /Ã[Âª©®¯°±²³´µ¶·¸¹º»¼½¾¿ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏ]/;
      if (mojibakePattern.test(utf8) || (utf8.indexOf('Ã') !== -1 && utf8.indexOf('©') !== -1)) {
        try {
          return new TextDecoder('windows-1252').decode(bytes);
        } catch (e) {
          try {
            return new TextDecoder('iso-8859-1').decode(bytes);
          } catch (e2) {
            return utf8;
          }
        }
      }
      return utf8;
    }

    function parseCSV(text) {
      var lines = [];
      var cur = '';
      var inQuotes = false;
      for (var i = 0; i < text.length; i++) {
        var c = text[i];
        if (c === '"') {
          inQuotes = !inQuotes;
          continue;
        }
        if (!inQuotes && (c === '\n' || c === '\r')) {
          if (c === '\r' && text[i + 1] === '\n') i++;
          if (cur.trim()) lines.push(cur);
          cur = '';
          continue;
        }
        cur += c;
      }
      if (cur.trim()) lines.push(cur);
      return lines.map(function (line) {
        var out = [];
        var cell = '';
        inQuotes = false;
        for (var j = 0; j < line.length; j++) {
          var ch = line[j];
          if (ch === '"') {
            inQuotes = !inQuotes;
            continue;
          }
          if (!inQuotes && (ch === ',' || ch === ';')) {
            out.push(cell.trim());
            cell = '';
            continue;
          }
          cell += ch;
        }
        out.push(cell.trim());
        return out;
      });
    }

    function getTorneoId() {
      var m = window.location.href.match(/torneo_id=(\d+)/);
      return m
        ? m[1]
        : document.querySelector('input[name="torneo_id"]') && document.querySelector('input[name="torneo_id"]').value;
    }

    function getCsrfToken() {
      var el = document.querySelector('input[name="csrf_token"]');
      return el ? el.value : '';
    }

    function applyParsedData() {
      var row = document.getElementById('importMasivaMappingRow');
      row.innerHTML = '';
      CAMPOS.forEach(function (campo) {
        var div = document.createElement('div');
        div.className = 'col-6 col-md-4 col-lg-3';
        var label = CAMPOS_LABEL[campo] || campo;
        var opts = importMasivaHeaders
          .map(function (h, i) {
            var head = (String(h || 'Col ' + (i + 1))).trim().toLowerCase();
            var aliases = CAMPO_ALIASES[campo];
            var selected = aliases && aliases.indexOf(head) !== -1 ? ' selected' : '';
            if (
              !selected &&
              campo === 'organizacion' &&
              (head === 'entidad' ||
                head === 'organizacion' ||
                head === 'organización' ||
                head === 'asociacion' ||
                head === 'asociación')
            ) {
              selected = ' selected';
            }
            return '<option value="' + i + '"' + selected + '>' + (h || 'Col ' + (i + 1)) + '</option>';
          })
          .join('');
        div.innerHTML =
          '<label class="form-label small mb-0">' +
          label +
          '</label><select class="form-select form-select-sm map-select" data-campo="' +
          campo +
          '"><option value="">-- No usar --</option>' +
          opts +
          '</select>';
        row.appendChild(div);
      });
      document.getElementById('importMasivaMapping').classList.remove('d-none');
      document.getElementById('importMasivaPreviewWrap').classList.remove('d-none');
      document.getElementById('importMasivaPreviewCount').textContent = importMasivaRows.length;
      buildPreviewTable();
    }

    function buildPreviewTable() {
      var map = {};
      document.querySelectorAll('.map-select').forEach(function (s) {
        if (s.value !== '') map[s.dataset.campo] = parseInt(s.value, 10);
      });
      var thead = ['#'].concat(
        CAMPOS.map(function (c) {
          return CAMPOS_LABEL[c] || c;
        })
      );
      var tbody = importMasivaRows.map(function (r, i) {
        var row = [i + 1];
        CAMPOS.forEach(function (c) {
          row.push(map[c] !== undefined ? r[map[c]] || '' : '');
        });
        return row;
      });
      var table = document.getElementById('importMasivaPreviewTable');
      table.innerHTML =
        '<thead class="table-light"><tr>' +
        thead
          .map(function (h) {
            return '<th>' + h + '</th>';
          })
          .join('') +
        '</tr></thead><tbody id="importMasivaTbody"></tbody>';
      var tbodyEl = document.getElementById('importMasivaTbody');
      tbody.forEach(function (row) {
        var tr = document.createElement('tr');
        tr.innerHTML = row
          .map(function (cell) {
            return '<td>' + (cell !== undefined && cell !== null ? String(cell) : '') + '</td>';
          })
          .join('');
        tbodyEl.appendChild(tr);
      });
      importMasivaValidacion = [];
    }

    fileEl.addEventListener('change', function (e) {
      var file = e.target.files[0];
      if (!file) return;
      var ext = (file.name.split('.').pop() || '').toLowerCase();
      document.getElementById('importMasivaLoading').classList.remove('d-none');
      if (ext === 'xls' || ext === 'xlsx' || ext === 'csv') {
        var fd = new FormData();
        fd.append('archivo', file);
        fd.append('csrf_token', getCsrfToken());
        fetch('api/tournament_import_parse.php', { method: 'POST', body: fd })
          .then(function (r) {
            return r.json();
          })
          .then(function (data) {
            document.getElementById('importMasivaLoading').classList.add('d-none');
            if (!data.success) {
              alert(data.error || 'Error al leer el archivo');
              return;
            }
            importMasivaHeaders = data.headers || [];
            importMasivaRows = data.rows || [];
            if (importMasivaHeaders.length === 0 || importMasivaRows.length === 0) {
              alert('El archivo debe tener cabecera y al menos una fila de datos.');
              return;
            }
            applyParsedData();
          })
          .catch(function () {
            document.getElementById('importMasivaLoading').classList.add('d-none');
            alert('Error de conexión al procesar el archivo.');
          });
      } else {
        var reader = new FileReader();
        reader.onload = function (ev) {
          var buffer = ev.target.result;
          var text = detectEncodingAndDecode(buffer);
          text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
          var parsed = parseCSV(text);
          document.getElementById('importMasivaLoading').classList.add('d-none');
          if (parsed.length < 2) {
            alert('El archivo debe tener al menos cabecera y una fila.');
            return;
          }
          importMasivaHeaders = parsed[0];
          importMasivaRows = parsed.slice(1);
          applyParsedData();
        };
        reader.readAsArrayBuffer(file);
      }
    });

    var mappingRow = document.querySelector('#importMasivaMappingRow');
    if (mappingRow) {
      mappingRow.addEventListener('change', function () {
        if (importMasivaRows.length) buildPreviewTable();
      });
    }

    function getFilasMapeadas() {
      var map = {};
      document.querySelectorAll('.map-select').forEach(function (s) {
        if (s.value !== '') map[s.dataset.campo] = parseInt(s.value, 10);
      });
      return importMasivaRows.map(function (r) {
        var obj = {};
        CAMPOS.forEach(function (c) {
          if (map[c] !== undefined) {
            var val = r[map[c]];
            obj[c] = val != null ? String(val).trim() : '';
          }
        });
        if (obj.nacionalidad === undefined || obj.nacionalidad === '') {
          obj.nacionalidad = 'V';
        }
        return obj;
      });
    }

    document.getElementById('btnImportMasivaValidar').addEventListener('click', function () {
      var filas = getFilasMapeadas();
      if (!filas.length) {
        alert('No hay filas para validar.');
        return;
      }
      var fd = new FormData();
      fd.append('action', 'validar');
      fd.append('torneo_id', getTorneoId());
      fd.append('filas', JSON.stringify(filas));
      fd.append('csrf_token', getCsrfToken());
      document.getElementById('importMasivaLoading').classList.remove('d-none');
      fetch('api/tournament_import_masivo.php', { method: 'POST', body: fd })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          document.getElementById('importMasivaLoading').classList.add('d-none');
          if (!data.success) {
            alert(data.error || 'Error al validar');
            return;
          }
          importMasivaValidacion = data.validacion || [];
          var tbody = document.getElementById('importMasivaTbody');
          if (tbody) {
            [].forEach.call(tbody.querySelectorAll('tr'), function (tr, i) {
              var v = importMasivaValidacion[i];
              tr.style.backgroundColor = v && COLORS[v.estado] ? COLORS[v.estado] : '';
              tr.style.color =
                v && v.estado === 'error' ? '#fff' : v && COLORS[v.estado] ? '#fff' : '';
              tr.title = v ? v.mensaje : '';
            });
          }
        })
        .catch(function () {
          document.getElementById('importMasivaLoading').classList.add('d-none');
          alert('Error de conexión');
        });
    });

    document.getElementById('btnImportMasivaProcesar').addEventListener('click', function () {
      var filas = getFilasMapeadas();
      if (!filas.length) {
        alert('No hay filas para procesar.');
        return;
      }
      var fd = new FormData();
      fd.append('action', 'importar');
      fd.append('torneo_id', getTorneoId());
      fd.append('filas', JSON.stringify(filas));
      fd.append('csrf_token', getCsrfToken());
      document.getElementById('importMasivaLoading').classList.remove('d-none');
      fetch('api/tournament_import_masivo.php', { method: 'POST', body: fd })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          document.getElementById('importMasivaLoading').classList.add('d-none');
          if (!data.success) {
            alert(data.error || 'Error');
            return;
          }
          var tieneErrores = data.errores && data.errores.length > 0;
          var html =
            '<p>Procesados: <strong>' +
            (data.procesados || 0) +
            '</strong></p><p>Nuevos (creados e inscritos): <strong>' +
            (data.nuevos || 0) +
            '</strong></p><p>Omitidos (ya inscritos): <strong>' +
            (data.omitidos || 0) +
            '</strong></p>' +
            (tieneErrores ? '<p class="text-danger">Errores: ' + data.errores.length + '</p>' : '');
          var opts = {
            title: 'Importación finalizada',
            html: html,
            icon: tieneErrores ? 'warning' : 'success',
            confirmButtonText: 'Aceptar',
          };
          if (tieneErrores && data.archivo_errores_base64) {
            opts.showDenyButton = true;
            opts.denyButtonText = 'Descargar Log de Errores';
            opts.denyButtonColor = '#6b7280';
          }
          window.Swal.fire(opts).then(function (res) {
            if (res.isDenied && data.archivo_errores_base64) {
              var bin = atob(data.archivo_errores_base64);
              var blob = new Blob([bin], { type: 'text/plain;charset=utf-8' });
              var a = document.createElement('a');
              a.href = URL.createObjectURL(blob);
              a.download = 'log_errores_importacion_' + new Date().toISOString().slice(0, 10) + '.txt';
              a.click();
              URL.revokeObjectURL(a.href);
            }
            if (data.success && (data.procesados > 0 || data.omitidos > 0)) window.location.reload();
          });
        })
        .catch(function () {
          document.getElementById('importMasivaLoading').classList.add('d-none');
          alert('Error de conexión');
        });
    });

    if (window.location.hash === '#importacion-masiva') {
      var btnImp = document.getElementById('btnAbrirImportacionMasiva');
      if (btnImp) {
        setTimeout(function () {
          btnImp.click();
        }, 300);
      }
    }
  }

  window.actualizarCronometro = actualizarCronometro;

  document.addEventListener('DOMContentLoaded', function () {
    initCronometroRonda();
    initFormGenerarRonda();
    actualizarCronometro();
    initImportacionMasiva();
  });
})();

