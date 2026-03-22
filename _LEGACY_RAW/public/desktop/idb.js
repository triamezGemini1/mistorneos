/**
 * IndexedDB — Modo 100% Offline (Desktop)
 * Estructura: jugadores, usuarios, cola_sincronizacion
 * Compatible con localhost (HTTP) y producción (HTTPS).
 */
(function (global) {
  var DB_NAME = 'mistorneos_offline';
  var DB_VERSION = 1;
  var STORES = { jugadores: 'jugadores', usuarios: 'usuarios', cola_sincronizacion: 'cola_sincronizacion' };
  var _db = null;

  function isIndexedDBAvailable() {
    try {
      return typeof global.indexedDB === 'object' && global.indexedDB !== null;
    } catch (e) {
      return false;
    }
  }

  function open() {
    if (!isIndexedDBAvailable()) {
      return Promise.reject(new Error('IndexedDB no disponible en este navegador'));
    }
    if (_db) return Promise.resolve(_db);
    return new Promise(function (resolve, reject) {
      try {
        var r = indexedDB.open(DB_NAME, DB_VERSION);
        r.onerror = function () {
          if (typeof console !== 'undefined' && console.warn) {
            console.warn('[MisTorneos IDB] Error al abrir:', r.error ? r.error.message : 'unknown');
          }
          reject(r.error || new Error('IndexedDB open failed'));
        };
        r.onsuccess = function () {
          _db = r.result;
          if (typeof console !== 'undefined' && console.log) {
            console.log('[MisTorneos IDB] Base de datos inicializada:', DB_NAME, 'origen:', global.location ? global.location.origin : 'unknown');
          }
          resolve(_db);
        };
      } catch (e) {
        reject(e);
      }
      r.onupgradeneeded = function (e) {
        var db = e.target.result;
        if (!db.objectStoreNames.contains(STORES.jugadores)) {
          var js = db.createObjectStore(STORES.jugadores, { keyPath: 'id', autoIncrement: false });
          js.createIndex('uuid', 'uuid', { unique: true });
        }
        if (!db.objectStoreNames.contains(STORES.usuarios)) {
          db.createObjectStore(STORES.usuarios, { keyPath: 'id', autoIncrement: false });
        }
        if (!db.objectStoreNames.contains(STORES.cola_sincronizacion)) {
          var cs = db.createObjectStore(STORES.cola_sincronizacion, { keyPath: 'id', autoIncrement: true });
          cs.createIndex('creado_en', 'creado_en', { unique: false });
        }
      };
    });
  }

  function jugadoresAdd(item) {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORES.jugadores, 'readwrite');
        var st = tx.objectStore(STORES.jugadores);
        var r = st.put(item);
        r.onsuccess = function () { resolve(r.result); };
        r.onerror = function () { reject(r.error); };
      });
    });
  }

  function jugadoresGetAll() {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORES.jugadores, 'readonly');
        var r = tx.objectStore(STORES.jugadores).getAll();
        r.onsuccess = function () { resolve(r.result || []); };
        r.onerror = function () { reject(r.error); };
      });
    });
  }

  function colaAdd(tipo, payload) {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORES.cola_sincronizacion, 'readwrite');
        var st = tx.objectStore(STORES.cola_sincronizacion);
        var entry = { tipo: tipo, payload: payload, creado_en: new Date().toISOString() };
        var r = st.add(entry);
        r.onsuccess = function () { resolve(r.result); };
        r.onerror = function () { reject(r.error); };
      });
    });
  }

  function colaGetAll() {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORES.cola_sincronizacion, 'readonly');
        var r = tx.objectStore(STORES.cola_sincronizacion).getAll();
        r.onsuccess = function () { resolve(r.result || []); };
        r.onerror = function () { reject(r.error); };
      });
    });
  }

  function colaRemove(id) {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORES.cola_sincronizacion, 'readwrite');
        var r = tx.objectStore(STORES.cola_sincronizacion).delete(id);
        r.onsuccess = function () { resolve(); };
        r.onerror = function () { reject(r.error); };
      });
    });
  }

  function colaClear() {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORES.cola_sincronizacion, 'readwrite');
        var r = tx.objectStore(STORES.cola_sincronizacion).clear();
        r.onsuccess = function () { resolve(); };
        r.onerror = function () { reject(r.error); };
      });
    });
  }

  global.MistorneosIDB = {
    open: open,
    jugadores: { add: jugadoresAdd, getAll: jugadoresGetAll },
    cola: { add: colaAdd, getAll: colaGetAll, remove: colaRemove, clear: colaClear }
  };
})(typeof window !== 'undefined' ? window : this);
