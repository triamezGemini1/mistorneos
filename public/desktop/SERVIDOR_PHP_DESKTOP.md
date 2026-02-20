# Servidor PHP integrado — Desktop

## Diagnóstico de rutas

- **Raíz del proyecto:** `c:\wamp64\www\mistorneos`
- **Index del escritorio (desktop):** `c:\wamp64\www\mistorneos\public\desktop\index.php`
- **Causa habitual de "Not Found":** Si ejecutas `php -S localhost:8000` desde la raíz del proyecto, la raíz documental es `mistorneos`. La app desktop está en `public/desktop/`, no en `desktop/` (que es la carpeta del core). Por eso:
  - `http://localhost:8000/desktop/` busca `mistorneos/desktop/index.php` → **no existe** → Not Found.
  - La URL correcta con esa raíz sería `http://localhost:8000/public/desktop/`.

## Comando recomendado (raíz documental = public)

Desde la raíz del proyecto:

```bash
cd c:\wamp64\www\mistorneos
php -S localhost:8000 -t public
```

**URL del escritorio en el navegador:**

```
http://localhost:8000/desktop/
```

**Otras URLs útiles:**

- App principal: `http://localhost:8000/`
- Login desktop: `http://localhost:8000/desktop/login_local.php`
- Panel: `http://localhost:8000/desktop/admin_panel.php`

## Alternativa: solo desktop en la raíz de la URL

Si quieres que `http://localhost:8000/` sea directamente el desktop:

```bash
cd c:\wamp64\www\mistorneos
php -S localhost:8000 -t public/desktop
```

**URL en el navegador:** `http://localhost:8000/`

## Permisos

El archivo `public/desktop/index.php` tiene permisos normales (lectura/ejecución para usuarios). No es necesario cambiar permisos para que PHP lo sirva.
