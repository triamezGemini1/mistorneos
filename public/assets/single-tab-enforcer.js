/**
 * Single Tab Enforcer - Evita que una misma sesión abra más de una pantalla/pestaña activa.
 * Cuando se detecta otra pestaña, se muestra un overlay. El usuario puede elegir cuál mantener activa.
 */
(function () {
  'use strict';

  const CHANNEL_NAME = 'mistorneos_single_tab';
  const STORAGE_KEY = 'mistorneos_active_tab';
  const HEARTBEAT_INTERVAL = 3000;
  const STALE_THRESHOLD = 8000;

  if (typeof BroadcastChannel === 'undefined') return;

  const channel = new BroadcastChannel(CHANNEL_NAME);
  const tabId = 'tab_' + Date.now() + '_' + Math.random().toString(36).slice(2);
  let overlayShown = false;
  let heartbeatTimer = null;

  function showOverlay() {
    if (overlayShown) return;
    overlayShown = true;
    const overlay = document.createElement('div');
    overlay.id = 'single-tab-overlay';
    overlay.innerHTML = [
      '<div class="single-tab-overlay-content">',
      '  <div class="single-tab-overlay-icon"><i class="fas fa-exclamation-triangle"></i></div>',
      '  <h4>Solo se permite una ventana activa</h4>',
      '  <p>Se ha detectado otra pestaña o ventana abierta con esta sesión. Por favor cierre las demás o use el botón para continuar en esta ventana.</p>',
      '  <button type="button" class="btn btn-primary btn-lg" id="single-tab-use-this">Usar esta ventana</button>',
      '</div>'
    ].join('');
    overlay.style.cssText = [
      'position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;',
      'background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;',
      'font-family:system-ui,-apple-system,sans-serif;'
    ].join('');
    const content = overlay.querySelector('.single-tab-overlay-content');
    content.style.cssText = 'background:#fff;padding:2rem;border-radius:12px;max-width:420px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.3);';
    overlay.querySelector('.single-tab-overlay-icon').style.cssText = 'font-size:4rem;color:#f59e0b;margin-bottom:1rem;';
    overlay.querySelector('h4').style.cssText = 'margin:0 0 0.5rem;color:#1f2937;';
    overlay.querySelector('p').style.cssText = 'color:#6b7280;margin:0 0 1.5rem;line-height:1.5;';
    document.body.appendChild(overlay);

    document.getElementById('single-tab-use-this').addEventListener('click', function () {
      overlay.remove();
      overlayShown = false;
      takeOver();
    });
  }

  function takeOver() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ tabId: tabId, ts: Date.now() }));
      channel.postMessage({ type: 'take_over', tabId: tabId });
    } catch (e) {}
  }

  function startHeartbeat() {
    if (heartbeatTimer) clearInterval(heartbeatTimer);
    heartbeatTimer = setInterval(function () {
      try {
        if (document.visibilityState === 'visible') {
          localStorage.setItem(STORAGE_KEY, JSON.stringify({ tabId: tabId, ts: Date.now() }));
        }
      } catch (e) {}
    }, HEARTBEAT_INTERVAL);
  }

  channel.onmessage = function (e) {
    const d = e.data || {};
    if (d.tabId === tabId) return;

    if (d.type === 'tab_opened') {
      try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
          const parsed = JSON.parse(stored);
          if (parsed.tabId !== tabId && (Date.now() - (parsed.ts || 0)) < STALE_THRESHOLD) {
            showOverlay();
            return;
          }
        }
        takeOver();
      } catch (err) {
        takeOver();
      }
    } else if (d.type === 'take_over') {
      showOverlay();
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (parsed.tabId && parsed.tabId !== tabId && (Date.now() - (parsed.ts || 0)) < STALE_THRESHOLD) {
          channel.postMessage({ type: 'tab_opened', tabId: tabId });
          takeOver();
          startHeartbeat();
          return;
        }
      }
      takeOver();
      startHeartbeat();
    } catch (e) {
      channel.postMessage({ type: 'tab_opened', tabId: tabId });
      takeOver();
      startHeartbeat();
    }
  });
})();
