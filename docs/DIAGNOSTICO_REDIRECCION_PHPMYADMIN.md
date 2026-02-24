# Diagnóstico: redirección a phpMyAdmin al enviar invitaciones

## Resumen

La URL de éxito **funciona** al escribirla a mano:  
`index.php?page=invitacion_clubes&torneo_id=8&success=...`  
Pero al hacer clic en **"Invitar clubes seleccionados"**, el navegador termina en phpMyAdmin.

## Causa raíz (desarrollo)

En **`public/index.php`** se **reescribe** `$_SERVER['REQUEST_URI']` cuando la app está bajo un subpath (p. ej. `/mistorneos/public`): se quita ese prefijo y queda solo `/index.php?page=...`. Si en `invitacion_clubes.php` se construía la URL de redirección y el `action` del formulario usando el path de `REQUEST_URI`, el path resultante era **`/index.php`** (sin `/mistorneos/public`). Así la URL final era `http://localhost/index.php?page=...`, es decir la **raíz del servidor**, que en WAMP suele ser phpMyAdmin u otra herramienta por defecto.

**Solución aplicada:** usar **`$_SERVER['SCRIPT_NAME']`** en lugar de `REQUEST_URI` para construir la URL. `SCRIPT_NAME` es la ruta real del script ejecutado (p. ej. `/mistorneos/public/index.php`) y no se modifica en index.php.

---

## 1. Origen del problema: `<base href>` en el layout

El desvío **no** está en el backend (el `header('Location: ...')` ya se corrigió para construirse desde la petición actual).  
El desvío ocurre en el **frontend**, por cómo el navegador resuelve la **acción del formulario**.

### Archivo exacto donde se desvía la URL

| Archivo | Líneas | Qué hace |
|--------|--------|----------|
| **`public/includes/layout.php`** | **8** y **39** | Define la base de todas las URLs de la página y la usa en `<base href="...">`. |

Código relevante:

```php
// Línea 8
$layout_asset_base = AppHelpers::getPublicUrl();

// Línea 39 (dentro del <head>)
<base href="<?= htmlspecialchars($layout_asset_base) ?>/">
```

- El formulario de invitaciones tiene `action="index.php?page=invitacion_clubes&torneo_id=..."` (URL **relativa**).
- Cualquier URL relativa se resuelve respecto a `<base href>`.
- Si `AppHelpers::getPublicUrl()` devuelve algo como `http://localhost/phpmyadmin/public` (por configuración incorrecta), entonces:
  - `index.php?page=...` se convierte en  
    `http://localhost/phpmyadmin/public/index.php?page=...`
  - El **envío del formulario** va a phpMyAdmin, no a la app.

Por tanto, el punto donde la URL “se desvía” hacia phpMyAdmin es la **combinación** de:

1. **`public/includes/layout.php`** (líneas 8 y 39): uso de `$layout_asset_base` en `<base href>`.
2. **`lib/app_helpers.php`** (`getBaseUrl()` / `getPublicUrl()`): valor que puede venir de **configuración** (`.env` o `config.*.php`).

Si en **`.env`** tienes por ejemplo:

- `APP_URL=http://localhost/phpmyadmin`  
o en el config de la app la **base_url** apunta a la carpeta/herramienta de phpMyAdmin, entonces `getPublicUrl()` será esa base + `/public`, y el `<base href>` hará que **todos** los enlaces y formularios relativos (incluido el de invitaciones) apunten a phpMyAdmin.

---

## 2. Comprobaciones recomendadas

1. **Revisar `.env`**  
   - Debe tener algo como:  
     `APP_URL=http://localhost/mistorneos`  
     (sin `/public`; la app añade `/public` donde corresponde).  
   - No debe ser la URL de phpMyAdmin ni de otra herramienta.

2. **Revisar `config/config.development.php`** (o el config que use tu entorno)  
   - Si existe `base_url`, debe ser la base de la aplicación (por ejemplo `http://localhost/mistorneos`), no la de phpMyAdmin.

3. **En el navegador (DevTools → pestaña Red)**  
   - Al hacer clic en "Invitar clubes seleccionados", ver a qué URL se envía el POST.  
   - Si esa URL es `http://localhost/phpmyadmin/...`, confirma que el problema es el `<base href>` + configuración anterior.

---

## 3. Correcciones aplicadas en código (sin tocar el layout de forma global)

Para que el flujo de invitaciones **no dependa** de `<base href>` ni de `APP_URL`:

1. **Backend (`modules/invitacion_clubes.php`)**  
   - La redirección tras crear invitaciones se construye con **scheme + host + path de la petición actual** (`$_SERVER['REQUEST_URI']`, `HTTP_HOST`, `HTTPS`), no con `AppHelpers::url()` ni `getPublicUrl()`.  
   - Así el `Location` apunta siempre a la misma app que recibió el POST.

2. **Formulario (`modules/invitacion_clubes.php`)**  
   - El `action` del formulario de invitaciones se cambió a una **URL absoluta** construida con el mismo criterio (scheme + host + path de la petición actual + `?page=invitacion_clubes&torneo_id=...`).  
   - Así el navegador envía el POST a la app (por ejemplo `http://localhost/mistorneos/public/index.php?...`) aunque el `<base href>` esté mal configurado.

Con esto, el flujo de invitaciones queda aislado de una posible `APP_URL`/base_url apuntando a phpMyAdmin.  
Sigue siendo recomendable corregir **`.env`** y/o el **config** para que el resto de la app (otros formularios y enlaces relativos) también use la URL correcta.
