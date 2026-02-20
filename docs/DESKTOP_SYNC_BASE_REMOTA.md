# Sincronización desktop con base remota

## 1. Asignar UUID en la base remota (sin usar PHP)

Si la URL del API en el servidor devuelve "Not Found", puedes asignar los UUID **directamente en MySQL** desde el panel de tu hosting (phpMyAdmin, cPanel, MySQL remoto, etc.):

```sql
-- Comprobar que existe la columna (opcional)
SHOW COLUMNS FROM usuarios LIKE 'uuid';

-- Si no existe, créala antes (opcional):
-- ALTER TABLE usuarios ADD COLUMN uuid VARCHAR(36) NULL UNIQUE;

-- Asignar UUID a quienes no lo tienen
UPDATE usuarios SET uuid = UUID() WHERE uuid IS NULL OR uuid = '';
```

Después de ejecutar el `UPDATE`, la base remota ya tendrá UUID en todos los usuarios y el endpoint (cuando la URL sea correcta) devolverá jugadores.

---

## 2. Encontrar la URL correcta del API en tu servidor

El "Not Found" suele significar que la ruta no existe en tu hosting. Prueba en el navegador, en este orden:

Base producción: `https://laestaciondeldominohoy.com/mistorneos/public/`

| URL a probar | URL para jugadores (SYNC_WEB_URL) |
|--------------|-----------------------------------|
| `.../api/sync_check.php` | `.../api/fetch_jugadores.php` |

- Sube `public/api/sync_check.php` y `public/api/fetch_jugadores.php` al servidor en la **misma carpeta** que indique tu document root (donde está, por ejemplo, tu `index.php` o login).
- La URL que devuelva algo como `{"ok":true,"msg":"api ok"}` es la base: cambia `sync_check.php` por `fetch_jugadores.php` y esa es tu `SYNC_WEB_URL` (con `?api_key=TU_KEY` solo para probar en navegador; en el script va en config).

---

## 3. Si la URL del API nunca funciona: usar MySQL local

Mientras tanto puedes importar desde tu **MySQL local** (WAMP) al SQLite del desktop:

```bash
php desktop/import_from_web.php --local
```

Eso usa la base de tu `.env` local (los 101 usuarios con UUID que ya asignaste). Para volver a importar desde la web remota, corrige la URL en `desktop/config_sync.php` según el paso 2.
