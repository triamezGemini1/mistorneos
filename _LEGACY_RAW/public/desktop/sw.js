/**
 * Service Worker — Modo 100% Offline (Desktop)
 * Estrategia: Stale-While-Revalidate (mostrar caché al instante, actualizar en segundo plano)
 */
const CACHE_NAME = 'mistorneos-desktop-v1';
const PRECACHE_URLS = [
  './',
  './index.php',
  './desktop.css',
  './idb.js',
  './login_local.php'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(PRECACHE_URLS.map(function (u) { return new Request(u, { cache: 'reload' }); })).catch(function () {
        return Promise.resolve();
      });
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k !== CACHE_NAME; }).map(function (k) { return caches.delete(k); }));
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') return;
  var url = new URL(event.request.url);
  if (url.origin !== self.location.origin || url.pathname.indexOf('/desktop/') === -1) return;

  event.respondWith(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.match(event.request).then(function (cached) {
        var revalidate = fetch(event.request).then(function (response) {
          if (response && response.status === 200 && response.type === 'basic') {
            cache.put(event.request, response.clone());
          }
          return response;
        }).catch(function () { return null; });
        if (cached) {
          revalidate.catch(function () {});
          return cached;
        }
        return revalidate.then(function (net) {
          if (net && net.ok) return net;
          return cached || caches.match(event.request);
        });
      });
    }).then(function (response) {
      if (response) return response;
      return caches.match('./index.php') || caches.match('./');
    }).catch(function () {
      return caches.match('./index.php') || caches.match('./');
    })
  );
});
