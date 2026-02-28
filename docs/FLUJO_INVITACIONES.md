# Secuencia de procesos: desde la generación de la invitación hasta el formulario

Este documento describe **paso a paso** cada acción desde que se genera la invitación hasta que el delegado ve el **formulario de registro express** o el **formulario de inscripción de jugadores**.

---

## Fase 1: Generación de la invitación

| Paso | Acción | Archivo / Lugar | Detalle |
|------|--------|------------------|---------|
| 1.1 | Admin entra al panel de invitación de clubes | `index.php?page=invitacion_clubes&torneo_id=X` | Módulo `modules/invitacion_clubes.php`. Lista clubes de `directorio_clubes`. |
| 1.2 | Admin marca clubes y envía el formulario | POST `action=invitar_seleccionados` | Se envían `directorio_ids[]`, `acceso1`, `acceso2`. |
| 1.3 | Por cada club seleccionado: | `invitacion_clubes.php` (bloque try, foreach) | 1) Se lee el club en `directorio_clubes` por `id` (con logo). 2) Se busca o crea el registro en `clubes` por `id_directorio_club` (homologación: nombre, direccion, delegado, telefono, email, logo; **estatus = 9** = procede del directorio). 3) Si ya existe invitación para ese torneo+id_directorio_club, se omite. |
| 1.4 | Se genera el **token** y se inserta la invitación | `invitacion_clubes.php` | `$token = bin2hex(random_bytes(32));` → INSERT en `invitaciones` (torneo_id, **club_id** del club creado/encontrado, id_directorio_club, token, estado='activa', etc.). |
| 1.5 | En la misma pantalla se construye el **enlace de acceso** | `InvitationJoinResolver::buildJoinUrl($token)` | URL final: `{base}/join?token={token}`. Se muestra en la tabla (botón copiar, WhatsApp, Telegram) y en la tarjeta digital. |

**Resultado:** Queda un registro en `invitaciones` con `token` único. En `directorio_clubes` el club puede tener `id_usuario` NULL (delegado sin cuenta) o ya asignado.

---

## Fase 2: El delegado recibe el enlace

El enlace que se comparte (WhatsApp, Telegram, email, tarjeta digital o PDF) es:

- **`{base}/join?token={token}`**

Ejemplo: `http://localhost/mistorneos/public/join?token=5fc0c78c84eea803...`

---

## Fase 3: Entrada por `/join` (punto único)

| Paso | Acción | Archivo | Detalle |
|------|--------|---------|---------|
| 3.1 | Petición GET a `/join?token=...` | Router → `modules/join_invitation.php` | Ruta en `config/routes.php`: `GET /join` (sin captura de salida; el script hace redirect o echo y `exit`). |
| 3.2 | Sin token en URL | `join_invitation.php` | Redirección a `{base}/`. |
| 3.3 | Resolver la invitación | `InvitationJoinResolver::resolve($token)` | 1) SELECT en `invitaciones` por `token` (estado activa/vinculado/0/1). 2) Si no hay `id_directorio_club`, se obtiene por nombre del club. 3) SELECT en `directorio_clubes` el `id_usuario` para ese club. |
| 3.4 | Token inválido o no encontrado | `join_invitation.php` | Respuesta 404 HTML: “Invitación no válida o expirada” + enlace a inicio. |
| 3.5 | Token válido y **delegado sin usuario** (`id_usuario` NULL o vacío) | `join_invitation.php` | Se guardan en sesión: `invitation_token`, `url_retorno`, `invitation_id_directorio_club`, cookie. **Redirección a:** `{base}/auth/register-invited?token={token}`. |
| 3.6 | Token válido y **delegado con usuario** (`id_usuario` con valor) | `join_invitation.php` | Se guardan en sesión token y `url_retorno`.
**Redirección a:** `{base}/invitation/register?token={token}` (formulario de inscripción de jugadores). |

---

## Fase 4A: Delegado sin cuenta → Registro express

| Paso | Acción | Archivo | Detalle |
|------|--------|---------|---------|
| 4A.1 | GET ` /auth/register-invited?token=...` | Router → `modules/register_invited_delegate.php` | Ruta: `GET|POST /auth/register-invited` con `ob_start`/`ob_get_clean`. |
| 4A.2 | Sin token | `register_invited_delegate.php` | Redirección a `{base}/auth/login`. |
| 4A.3 | Contexto para registro | `InvitationJoinResolver::getContextForRegistration($token)` | Devuelve club_id, id_directorio_club, entidad_id (de clubes), club_nombre, requiere_registro. |
| 4A.4 | Token inválido | `register_invited_delegate.php` | Redirección a `{base}/auth/login?error=invitacion_invalida`. |
| 4A.5 | Token ya usado (ya tiene `id_usuario`) | `register_invited_delegate.php` | Se guardan en sesión token y url_retorno. Redirección a `{base}/auth/login` (tras login irá a inscripción). |
| 4A.6 | Usuario ya logueado | `register_invited_delegate.php` | Redirección directa a `{base}/invitation/register?token=...`. |
| 4A.7 | Se muestra el **formulario de registro express** | `register_invited_delegate.php` | Campos: Nombre, Email, Contraseña, Confirmar contraseña. Ocultos: token, id_club, entidad_id (rellenados por contexto del token). |
| 4A.8 | POST del formulario (Registrarse) | `register_invited_delegate.php` | 1) Crear usuario en `usuarios` (Security::createUser) con username desde email, club_id y entidad del token, valores por defecto (cedula INV-xxx, celular N/A, fechnac 1900-01-01). 2) UPDATE `directorio_clubes` SET id_usuario = nuevo_user_id WHERE id = id_directorio_club. 3) Auth::login(username, password). 4) **Redirección a** `{base}/invitation/register?token={token}`. |

**Resultado:** El delegado queda logueado y es enviado al **formulario de inscripción de jugadores**.

---

## Fase 4B: Delegado con cuenta o que llega directo a inscripción

| Paso | Acción | Archivo | Detalle |
|------|--------|---------|---------|
| 4B.1 | GET ` /invitation/register?token=...` | Router → `modules/invitation_register.php` | Ruta: `GET|POST /invitation/register` con `ob_start`/`ob_get_clean`. |
| 4B.2 | Si no hay torneo_id/club_id en URL | `invitation_register.php` | SELECT en `invitaciones` por token para obtener torneo_id y club_id. |
| 4B.3 | Invitación no válida | `invitation_register.php` | Mensaje “Invitación no válida” / acceso denegado. |
| 4B.4 | Usuario **no** logueado y club **sin** usuario en directorio | `invitation_register.php` | Redirección a `{base}/auth/register-invited?token=...` (registro express). |
| 4B.5 | Usuario **no** logueado y club **con** usuario en directorio | `invitation_register.php` | Redirección a `{base}/auth/login` (url_retorno = inscripción con token). |
| 4B.6 | Usuario **logueado** (o tras login/registro) | `invitation_register.php` | Se valida período de acceso (acceso1–acceso2). Se muestra el **formulario de inscripción de jugadores**: datos del torneo, club, lista de inscritos, formulario para agregar jugadores (cédula, nombre, etc.) y retirar. |

**Resultado:** El delegado ve y usa el **formulario de inscripción de jugadores** (inscritos en `inscritos`, usuarios en `usuarios`).

---

## Resumen visual del flujo

```
[Panel admin] invitacion_clubes.php
    → Selección de clubes (directorio_clubes)
    → INSERT invitaciones (token, club_id, id_directorio_club, estado='activa')
    → Enlace compartido: {base}/join?token=XXX

[Delegado abre] {base}/join?token=XXX  (join_invitation.php)
    │
    ├─ Token inválido → 404 "Invitación no válida"
    │
    ├─ directorio_clubes.id_usuario VACÍO (requiere_registro = true)
    │   → Redirige a {base}/auth/register-invited?token=XXX
    │       → register_invited_delegate.php
    │       → Formulario: Nombre, Email, Contraseña, Confirmar
    │       → POST: crear usuario → UPDATE directorio_clubes.id_usuario → login
    │       → Redirige a {base}/invitation/register?token=XXX
    │
    └─ directorio_clubes.id_usuario con valor (requiere_registro = false)
        → Redirige a {base}/invitation/register?token=XXX

[Formulario final] {base}/invitation/register?token=XXX  (invitation_register.php)
    │
    ├─ No logueado + club sin usuario → register-invited (ver arriba)
    ├─ No logueado + club con usuario → auth/login (luego vuelve aquí)
    └─ Logueado → Formulario de inscripción de jugadores (alta/retiro en inscritos)
```

---

## Rutas implicadas (config/routes.php)

| Ruta | Método | Incluye | Uso |
|------|--------|---------|-----|
| `/join` | GET | `modules/join_invitation.php` | Entrada única con token; redirige a registro express o a inscripción. |
| `/auth/register-invited` | GET, POST | `modules/register_invited_delegate.php` | Formulario de registro express (nombre, email, contraseña). |
| `/invitation/register` | GET, POST | `modules/invitation_register.php` | Formulario de inscripción de jugadores. |
| `/invitation/digital` | GET | `modules/invitacion_digital.php` | Tarjeta digital (muestra enlace “Ir al formulario de acceso” = buildJoinUrl(token)). |

---

## Tablas clave

- **invitaciones:** token, torneo_id, club_id, id_directorio_club, estado, acceso1, acceso2, …
- **directorio_clubes:** id, nombre, id_usuario (delegado; NULL = sin cuenta).
- **clubes:** id, nombre, id_directorio_club (opcional), estatus, … Referenciado por invitaciones.club_id. **Estatus 9** = procede del directorio (creado al invitar desde directorio; organizacion_id, entidad, admin_club_id quedan pendientes hasta que el club acepte la invitación y se loguee).
- **usuarios:** creados en registro express; luego referenciados por directorio_clubes.id_usuario.
- **inscritos:** jugadores inscritos al torneo (formulario de inscripción de jugadores).
