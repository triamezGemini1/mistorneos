# Archivos a subir a producción (laestaciondeldominohoy.com)

## 1. API de sincronización (obligatorio para desktop ↔ web)

Sube estos archivos a **la misma ruta** en el servidor (p. ej. `mistorneos/public/api/`):

| Archivo | Descripción |
|---------|-------------|
| `public/api/fetch_jugadores.php` | Devuelve jugadores (y opción `?asignar_uuid=1`). |
| `public/api/fetch_staff.php` | Devuelve administradores/staff para la app desktop. |
| `public/api/sync_api.php` | Recibe POST con jugadores/staff y actualiza MySQL (incluye `is_active`). |
| `public/api/sync_check.php` | Opcional: prueba de ruta (`?api_key=...` → `{"ok":true}`). |

**URLs resultantes (si la app está en `/mistorneos/public/`):**
- `https://laestaciondeldominohoy.com/mistorneos/public/api/fetch_jugadores.php`
- `https://laestaciondeldominohoy.com/mistorneos/public/api/fetch_staff.php`
- `https://laestaciondeldominohoy.com/mistorneos/public/api/sync_api.php`

---

## 2. Base de datos MySQL en producción

Ejecuta **una vez** en el MySQL del servidor (phpMyAdmin o cliente):

- **`sql/migrate_is_active_usuarios.sql`** — añade la columna `is_active` a la tabla `usuarios` si no existe.

Sin esta columna, `sync_api.php` y la actualización de permisos desde el desktop fallarán.

---

## 3. Configuración en el servidor

En el **.env** del proyecto en producción (raíz del sitio), debe existir:

```env
SYNC_API_KEY=el_mismo_valor_que_en_desktop_config_sync.php
```

Sin esta variable, las peticiones a `fetch_jugadores.php`, `fetch_staff.php` y `sync_api.php` responderán "No autorizado".

---

## 4. App desktop en producción (opcional)

Si quieres que la **interfaz desktop** (registro de jugadores, panel de torneo, gestión de administradores, sincronización) se use desde el navegador en la URL del servidor, sube toda la carpeta:

- **`public/desktop/`** (todos los `.php`, `config_sync.php`, etc.)

y mantén la misma estructura. La base SQLite se creará en `public/desktop/data/` en el servidor.

Si la app desktop solo se usa **en local** (WAMP), no es necesario subir `public/desktop/` a producción; solo hace falta lo indicado en la sección 1.

---

## 5. Resumen mínimo (solo sync API)

Para que el desktop local pueda **importar** y **exportar** con la web:

1. Subir: `public/api/fetch_jugadores.php`, `public/api/fetch_staff.php`, `public/api/sync_api.php`.
2. Ejecutar en MySQL: `sql/migrate_is_active_usuarios.sql`.
3. Tener en `.env`: `SYNC_API_KEY` con el mismo valor que en `desktop/config_sync.php` (o `public/desktop/config_sync.php`).

Con eso, desde tu PC podrás ejecutar la importación y la exportación de permisos contra producción.
