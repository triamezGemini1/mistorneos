# Causa raíz del fallo al guardar equipo (inscripción en sitio)

## 1. Error de diseño original (sesión / CSRF)

- La página del formulario se sirve por **`index.php`** → carga **`session_start_early`** y luego bootstrap → sesión **A** con `csrf_token`.
- El guardado iba por **`public/api/guardar_equipo.php`** → en la versión antigua solo bootstrap → a veces **otro nombre de sesión** u otra sesión vacía → **`$_SESSION['csrf_token']` vacío** → CSRF falla → en el código viejo **`die('CSRF validation failed')`** → sin JSON, log cortado.

## 2. Por qué parecía “imposible” arreglar en servidor (OPcache)

- **OPcache** guarda el PHP **compilado en RAM** por ruta (`guardar_equipo.php`).
- Aunque subieras el archivo nuevo o lo borraras, **los workers de PHP-FPM** podían **seguir ejecutando el bytecode viejo** hasta reiniciar FPM o usar **otro nombre de archivo** que nunca estuviera en caché.

## 3. Corrección desde la base (sin borrar la funcionalidad)

**Mismo patrón que ya te funciona** (ej. `guardar_pareja_fija`, inscripción individual vía `tournament_admin_toggle_inscripcion.php` con `session_start_early`):

1. **No depender del API en `public/api/`** para este flujo.
2. Hacer el **POST al mismo entry point que ya cargó la sesión del usuario**:  
   `index.php?page=torneo_gestion&action=guardar_equipo_sitio&torneo_id=…`  
   o `admin_torneo.php?action=guardar_equipo_sitio&torneo_id=…`
3. En **`torneo_gestion.php`**, en el **`switch` de POST** (donde ya se valida CSRF global), caso **`guardar_equipo_sitio`** → llama a **`GuardarEquipoSitioService`** → responde **JSON**.
4. Lógica de negocio **una sola vez** en **`lib/GuardarEquipoSitioService.php`**.

Así:

- Misma cookie / misma sesión / mismo CSRF que la pantalla.
- **Nada de OPcache** atado a un `guardar_equipo.php` viejo en `public/api/` para este flujo.
- No hace falta “eliminar la funcionalidad”: solo **cambiar el sitio donde se ejecuta** el guardado.

## 4. Archivos tocados en el repo

| Archivo | Rol |
|--------|-----|
| `lib/GuardarEquipoSitioService.php` | Lógica de guardado (única fuente). |
| `modules/torneo_gestion.php` | `case 'guardar_equipo_sitio'` dentro del **POST** switch. |
| `modules/gestion_torneos/inscribir_equipo_sitio.php` | `fetch` a URL interna (`action=guardar_equipo_sitio`), no a `public/api/`. |

## 5. Qué subir al servidor

- Los tres anteriores + **`public/index.php`** no hace falta cambiar (ya hace POST temprano a `torneo_gestion`).
- Cualquier **`guardar_equipo.php`** viejo en `public/api/` **ya no se usa** para inscripción en sitio; puedes dejarlo o quitarlo sin afectar este flujo.

Tras desplegar, en el log deberías ver:

`=== guardar_equipo_sitio POST torneo_gestion (index/admin, sesión OK) ===`

y no las líneas antiguas de `POST recibido` del API.
