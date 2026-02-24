# Configuración en subcarpeta (/pruebas/public/ o /mistorneos_beta/public/)

Cuando la aplicación se despliega en una subcarpeta (ej. `https://laestaciondeldominohoy.com/pruebas/public/`), la sesión y los redirects deben usar esa ruta. Si no, el login no persiste y los menús llevan al landing o a la raíz del dominio.

## 0. Constante URL_BASE y BASE_PATH (recomendado en producción)

En **`config/bootstrap.php`** se define la constante **`URL_BASE`** (path de la aplicación, p. ej. `/pruebas/public/`). Todas las redirecciones y enlaces del menú la usan para no apuntar a la raíz del dominio.

- **En producción** bajo `/pruebas/public/`, define en **`.env`**:
  ```env
  BASE_PATH=/pruebas/public/
  ```
  Así `URL_BASE` será `/pruebas/public/` y la cookie de sesión, los `header('Location: ...')` y los enlaces del menú quedarán anclados a esa ruta.

- Si no defines `BASE_PATH`, la app deduce la base desde `SCRIPT_NAME` (p. ej. en `/mistorneos/public/` dará `/mistorneos/public/`).
- Si no se puede deducir, se usa por defecto `/pruebas/public/`.

Uso correcto de redirecciones:
```php
header('Location: ' . URL_BASE . 'index.php?page=dashboard');
header('Location: ' . URL_BASE . 'login.php');
```

## 1. Sesión (cookie path)

En **`config/bootstrap.php`** el path de la cookie de sesión usa **`URL_BASE`**: así la cookie se envía en la subcarpeta correcta (`/pruebas/public/`, etc.) y el login persiste.

## 2. Redirects tras login

- **`public/login.php`** y **`modules/auth/login.php`** usan **`AppHelpers::getRequestEntryUrl()`** para construir la URL de redirección tras el login. Esa función usa el path actual de la petición (`SCRIPT_NAME`), por lo que el redirect queda en la misma subcarpeta.
- Ejemplo: si entras por `https://laestaciondeldominohoy.com/pruebas/public/login.php`, tras loguear se redirige a `https://laestaciondeldominohoy.com/pruebas/public/index.php` (no a la raíz del dominio).

## 3. Menú y enlaces

En **`public/includes/layout.php`** los enlaces del menú usan **`URL_BASE`** cuando está definida: `URL_BASE . 'index.php?page=...'`, de modo que no empiezan con `/` suelto y apuntan a la subcarpeta correcta. Si `URL_BASE` no está definida, se usa `$menu_base` desde `SCRIPT_NAME` y **`$dashboard_href()`** / **`$menu_url()`**.

## 4. .htaccess (RewriteBase)

Si usas reglas de reescritura y la app está en una subcarpeta, hay que indicar esa base:

- En **`public/.htaccess`** (junto a `index.php`), descomenta y ajusta **RewriteBase** según la URL real:

```apache
# Para https://laestaciondeldominohoy.com/pruebas/public/
RewriteBase /pruebas/public/

# Para https://laestaciondeldominohoy.com/mistorneos_beta/public/
# RewriteBase /mistorneos_beta/public/
```

- En la **raíz del proyecto** (`.htaccess` que redirige a `public/`), si el sitio se sirve desde `/pruebas/`:

```apache
RewriteBase /pruebas/
RewriteRule ^(.*)$ public/$1 [L]
```

## 5. APP_URL en .env (opcional pero recomendable)

En producción, define en **`.env`** la URL base real para que el resto de la app (correos, enlaces externos, etc.) use la misma base:

```env
# Para entorno en /pruebas/public/
APP_URL=https://laestaciondeldominohoy.com/pruebas/public

# Para entorno en /mistorneos_beta/public/
# APP_URL=https://laestaciondeldominohoy.com/mistorneos_beta/public
```

(No pongas barra final; la app añade `/` donde haga falta.)

## 6. Comprobar que el login redirige bien

Tras loguear, la respuesta debe ser algo como:

```http
Location: https://laestaciondeldominohoy.com/pruebas/public/index.php
```

y **no**:

```http
Location: index.php
```
ni una URL que apunte a la raíz del dominio (sin `/pruebas/public/`).

Si sigue yendo a la raíz, revisa que en el servidor `SCRIPT_NAME` sea el path correcto (p. ej. `/pruebas/public/login.php` o `/pruebas/public/index.php` según cómo se llame al login). Si usas un proxy o una regla de reescritura que cambie el script, puede que haya que ajustar la configuración del servidor para que ese path se refleje bien.
