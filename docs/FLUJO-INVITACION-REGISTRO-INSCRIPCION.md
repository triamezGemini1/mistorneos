# Secuencia de procesos: Enlace de invitación → Formularios

Desde que el delegado abre el enlace de invitación hasta que ve el **formulario de registro express** (crear cuenta) o el **formulario de inscripción de jugadores**.

---

## Entrada única: `/join?token=XXX`

**URL ejemplo:** `http://localhost/mistorneos/public/join?token=5fc0c78c84eea80395385621b560d2fc...`

---

## PASO 1 — Llega la petición al servidor

| # | Acción | Dónde |
|---|--------|--------|
| 1.1 | El navegador solicita `GET /join?token=XXX` (o `.../public/join?token=XXX`). | Cliente |
| 1.2 | El servidor ejecuta `public/index.php`. | `public/index.php` |
| 1.3 | Se normaliza `REQUEST_URI`: si existe `base_url` (ej. `/mistorneos/public`), se quita del path para que quede `/join?token=XXX`. | `index.php` (líneas ~29–43) |
| 1.4 | Se comprueba si el path empieza por alguna ruta “moderna”: `/auth/`, `/invitation/`, **`/join`**, etc. | `index.php` → `$modernRoutes` |
| 1.5 | Si coincide con `/join`, se usa el **Router** (no el flujo legacy). | `index.php` |
| 1.6 | El Router ejecuta la ruta **GET `/join`**: se hace `include` de `modules/join_invitation.php`. | `config/routes.php` → `modules/join_invitation.php` |

---

## PASO 2 — Join: validar token y decidir destino

| # | Acción | Dónde |
|---|--------|--------|
| 2.1 | Se lee el **token** de `$_GET['token']`. Si no hay token, **redirección a `/`**. | `join_invitation.php` |
| 2.2 | Se calcula **`$base`** (URL base de la app) con `AppHelpers::getPublicUrl()`, `base_url` o `REQUEST_SCHEME` + `HTTP_HOST` + `SCRIPT_NAME`. | `join_invitation.php` |
| 2.3 | Se llama a **`InvitationJoinResolver::resolve($token)`**. | `join_invitation.php` → `lib/InvitationJoinResolver.php` |

### 2.4 — Qué hace `resolve($token)`

| Subpaso | Acción |
|---------|--------|
| A | Busca en `invitaciones` una fila con ese `token` y estado `activa`, `vinculado`, `0` o `1`. |
| B | Si no hay fila → devuelve **`null`**. |
| C | Obtiene `id_directorio_club` (de la invitación o buscando en `directorio_clubes` por nombre del club). |
| D | Si existe columna `id_usuario` en `directorio_clubes`, lee `id_usuario` para ese `id_directorio_club`. |
| E | Define **`requiere_registro`**: `true` si `id_usuario` es NULL o 0; `false` si ya tiene usuario. |
| F | Devuelve `['invitation', 'id_directorio_club', 'id_usuario_delegado', 'requiere_registro']` o `null`. |

---

## PASO 3 — Decisión en Join

| Condición | Acción (redirección o página) |
|-----------|-------------------------------|
| **Token inválido** (`resolve` devuelve `null`) | Respuesta **404** con HTML “Invitación no válida o expirada” y enlace a inicio. |
| **`requiere_registro === true`** (delegado sin usuario) | Redirección a **`/auth/register-invited?token=XXX`** → ver **RAMA A** (registro express). |
| **`requiere_registro === false`** (delegado ya tiene usuario) | Redirección a **`/invitation/register?token=XXX`** → ver **RAMA B** (inscripción de jugadores). |

En ambos casos se guardan en sesión: `invitation_token`, `url_retorno` (invitation/register?token=...) y, si aplica, `invitation_id_directorio_club`.

---

# RAMA A — Formulario de registro EXPRESS

**URL:** `.../auth/register-invited?token=XXX`  
**Módulo:** `modules/register_invited_delegate.php`  
**Objetivo:** Crear cuenta del delegado (sin elegir club ni entidad; van por token) y dejarlo logueado para ir a inscripción.

### A.1 — Entrada a register-invited

| # | Acción |
|---|--------|
| A.1.1 | Sin token en URL/POST → redirección a **`/auth/login`**. |
| A.1.2 | Se llama a **`InvitationJoinResolver::getContextForRegistration($token)`** (invitación + club_id, id_directorio_club, entidad_id, club_nombre, requiere_registro). |
| A.1.3 | Si el token no es válido o no existe contexto → redirección a **`/auth/login?error=invitacion_invalida`**. |
| A.1.4 | Si **no** requiere registro (token “ya usado”) → se guarda en sesión `invitation_token` y `url_retorno` y redirección a **`/auth/login`** (tras login irá a inscripción). |
| A.1.5 | Si el usuario **ya está logueado** → redirección directa a **`/invitation/register?token=XXX`** (inscripción). |
| A.1.6 | Si requiere registro y no está logueado → **se muestra el formulario express**. |

### A.2 — Contenido del formulario EXPRESS

- **Campos visibles:** Nombre completo, Email, Contraseña, Confirmar contraseña.
- **Ocultos (rellenados por token):** `token`, `id_club`, `entidad_id`.
- **Texto:** “Delegado de [nombre del club]”.

### A.3 — POST (Enviar registro)

| # | Acción |
|---|--------|
| A.3.1 | Validar CSRF, nombre, email, contraseña y confirmación. |
| A.3.2 | Revalidar token con `getContextForRegistration`; si ya no requiere registro → mensaje “Este enlace ya fue utilizado…”. |
| A.3.3 | Generar `username` único (por ejemplo desde el email). |
| A.3.4 | **Crear usuario** en `usuarios` con `Security::createUser()` (incluye club_id, entidad, celular/cedula por defecto, etc.). |
| A.3.5 | **Actualizar** `directorio_clubes SET id_usuario = nuevo_id WHERE id = id_directorio_club`. |
| A.3.6 | **Auto-login** con `Auth::login($username, $password)`. |
| A.3.7 | **Redirección** a **`/invitation/register?token=XXX`** → se pasa a **formulario de inscripción de jugadores** (RAMA B, ya logueado). |

Si algo falla (por ejemplo el UPDATE), se muestra mensaje de error y no se redirige.

---

# RAMA B — Formulario de INSCRIPCIÓN DE JUGADORES

**URL:** `.../invitation/register?token=XXX`  
**Módulo:** `modules/invitation_register.php`  
**Objetivo:** Pantalla donde el delegado inscribe jugadores al torneo (buscar/agregar, listado, retirar).

### B.1 — Entrada a invitation/register

| # | Acción |
|---|--------|
| B.1.1 | Si no hay `torneo_id`/`club_id` en la URL, se obtienen de la tabla **invitaciones** usando el **token** (estado activa/vinculado/0). |
| B.1.2 | Si no se encuentra invitación o faltan torneo/club → mensaje “Parámetros de acceso inválidos” / “Invitación no válida”. |
| B.1.3 | Se cargan datos de la invitación, torneo, club (y de `directorio_clubes` si aplica). |
| B.1.4 | Se determina si el club tiene delegado con usuario: `directorio_clubes.id_usuario` o `clubes.delegado_user_id` → **`$club_tiene_usuario`**. |
| B.1.5 | **Si el usuario NO está logueado** (`!$current_user`): |

### B.1.6 — Usuario no logueado en invitation/register

| Condición | Acción |
|-----------|--------|
| `$base` definido y token presente | Se guardan en sesión `url_retorno` y `invitation_token`. |
| **Club tiene usuario** (`$club_tiene_usuario`) | Redirección a **`/auth/login`**. Tras login, el login redirige a `url_retorno` = **`/invitation/register?token=XXX`** → se muestra el formulario de inscripción. |
| **Club no tiene usuario** | Redirección a **`/auth/register-invited?token=XXX`** → **formulario express** (RAMA A). Tras registrarse y auto-login, se redirige a **`/invitation/register?token=XXX`** → formulario de inscripción. |

Si por alguna razón no se redirige (p. ej. `$base` vacío), se usa un “stand-by” con datos del torneo/club pero sin redirección.

### B.2 — Usuario SÍ logueado en invitation/register

| # | Comprobación | Resultado |
|---|----------------|-----------|
| B.2.1 | Es admin (general o torneo) | Puede ver y gestionar inscripciones. |
| B.2.2 | Invitación vinculada a otro delegado (`id_usuario_vinculado` distinto del usuario actual) | Mensaje “Esta invitación ya está siendo gestionada por otro delegado” y se bloquea acceso. |
| B.2.3 | Resto de casos (delegado correcto, inscripciones abiertas, etc.) | **Se muestra el formulario de inscripción de jugadores**: búsqueda por cédula, alta de jugadores, listado de inscritos, retirar, etc. |

---

# Resumen en diagrama (flujo simplificado)

```
[Usuario abre: /join?token=XXX]
           |
           v
   [join_invitation.php]
   Resolver::resolve(token)
           |
     +-----+-----+
     |           |
  null      no null
     |           |
     v           +-- requiere_registro? --+
[404 / "Invitación no válida"]   |                    |
                                 v                    v
                        [SÍ]                    [NO]
                         |                        |
                         v                        v
            [/auth/register-invited?token=XXX]   [/invitation/register?token=XXX]
                         |                        |
            [Formulario REGISTRO EXPRESS]         |
            Nombre, Email, Password               |
                         |                        |
                  [POST → crear usuario,          |
                   update directorio_clubes,      |
                   auto-login]                    |
                         |                        |
                         +-------- redirige ------+
                                  |
                                  v
                    [/invitation/register?token=XXX]
                                  |
                    ¿Usuario logueado?
                         |           |
                        [SÍ]       [NO]
                         |           |
                         |           +-- club tiene usuario? → /auth/login
                         |           +-- club sin usuario  → /auth/register-invited
                         |
                         v
            [Formulario INSCRIPCIÓN DE JUGADORES]
            (buscar jugador, agregar, listado, retirar)
```

---

# Archivos implicados (referencia rápida)

| Paso / Pantalla | Archivo(s) |
|-----------------|------------|
| Entrada y ruta `/join` | `public/index.php`, `config/routes.php` |
| Decisión join (token, requiere_registro) | `modules/join_invitation.php`, `lib/InvitationJoinResolver.php` |
| Formulario registro express | `modules/register_invited_delegate.php` |
| Formulario inscripción jugadores | `modules/invitation_register.php` |
| Login y vuelta a inscripción | `modules/auth/login.php` (usa `url_retorno` e `invitation_token`) |

---

# Dónde se ve cada formulario

- **Formulario de registro EXPRESS** (nombre, email, contraseña):  
  Solo en **`/auth/register-invited?token=XXX`**, cuando el token es válido y el club aún no tiene `id_usuario` en `directorio_clubes`.

- **Formulario de inscripción de JUGADORES**:  
  En **`/invitation/register?token=XXX`**, cuando la invitación es válida, el usuario está logueado, y el flujo no lo ha redirigido a login ni a registro express.
