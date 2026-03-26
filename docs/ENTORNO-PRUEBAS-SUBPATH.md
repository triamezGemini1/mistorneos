# Entorno de pruebas en subpath (/pruebas)

Configuración para que la aplicación funcione en un servidor cPanel bajo una ruta como `https://laestaciondeldominohoy.com/pruebas`.

## Estructura en servidor

- La aplicación está en: `public_html/mistorneos_beta/`
- Punto de entrada: `public_html/mistorneos_beta/public/index.php`
- URL deseada: `https://laestaciondeldominohoy.com/pruebas`

## Pasos

### 1. .htaccess en public_html

Copia el contenido de **docs/htaccess-public_html-pruebas.txt** al archivo `public_html/.htaccess` (raíz del sitio). Ese .htaccess redirige internamente:

- `dominio.com/pruebas` → `mistorneos_beta/public/`
- `dominio.com/pruebas/public/assets/...` (CSS/JS) → `mistorneos_beta/public/assets/...` (regla específica para que no se duplique `public/` y el archivo se sirva con MIME correcto)
- `dominio.com/pruebas/auth/login` → `mistorneos_beta/public/` (el front controller de `public/.htaccess` envía a `index.php`)

### 2. APP_URL en .env (carpeta beta)

Dentro de `mistorneos_beta/` crea o edita `.env` y define:

```env
APP_URL=https://laestaciondeldominohoy.com/pruebas
```

**Sin barra final.** Con esto:

- Los assets (CSS/JS) se generan como `/pruebas/public/assets/...` y no dan 404.
- Las cookies de sesión usan el path `/pruebas/`.
- El Router recibe la URI sin el prefijo (en `index.php` se normaliza usando este valor).

### 3. Código ya preparado en el repo

- **public/index.php**: si `APP_URL` tiene path (ej. `/pruebas`), se elimina ese prefijo de `REQUEST_URI` antes de usar el Router, así las rutas modernas (`/auth/login`, `/api/...`) coinciden correctamente.
- **public/.htaccess**: incluye el front controller (peticiones que no son archivos/directorios existentes se envían a `index.php`), por lo que `/pruebas/assets/dashboard.css` se sirve como archivo real (MIME correcto) y `/pruebas/auth/login` se resuelve vía `index.php`.

## Resumen de comprobaciones

| Comprobación | Cómo verificarlo |
|--------------|------------------|
| Entrada a la app | Abrir `https://laestaciondeldominohoy.com/pruebas` y que cargue la landing o login. |
| Rutas modernas | Navegar a `/pruebas/auth/login` o `/pruebas/dashboard` sin 404. |
| Assets sin 404 | En DevTools → Network, que los CSS/JS bajo `/pruebas/public/assets/` devuelvan 200. |
| MIME type | Que los .css y .js tengan tipo correcto (no `text/html`). |
| APP_URL | En `mistorneos_beta/.env`: `APP_URL=https://laestaciondeldominohoy.com/pruebas` (sin barra final). |
