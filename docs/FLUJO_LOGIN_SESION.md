# Flujo de login y sesión

## Orden de ejecución (cada petición)

### 1. Cualquier entrada (index, login, profile, user_portal)
- **session_start_early.php** (primera línea): inicia sesión con `path=/`, `session_name` por getenv o `mistorneos_session`. No se carga ningún otro archivo antes para no enviar salida.
- **bootstrap.php**: si la sesión ya está activa, no hace nada con sesión. Define URL_BASE, etc.

### 2. GET login.php (mostrar formulario)
- Sesión ya iniciada (cookie con path=/ si hubo peticiones previas).
- Si `$_SESSION['user']` existe → redirect a index o return_url.
- Si no → se muestra el formulario de login.

### 3. POST login.php (enviar usuario/contraseña)
1. **Auth::login($user, $pass)**  
   - Security::authenticateUser(): comprueba usuario/contraseña, auto-activa si status!=0.  
   - Si OK: `$_SESSION['user'] = [...]`, luego `session_regenerate_id(true)` (nuevo id de sesión).  
   - Devuelve true/false.
2. Si **true**:  
   - ob_end_clean(), lógica de invitación si aplica.  
   - `session_write_close()`.  
   - **Set-Cookie** con el **nuevo** session_id y **path=/** (una sola cookie para todo el dominio).  
   - **Set-Cookie** para **borrar** cookie antigua con path=URL_BASE (subcarpeta), si existe.  
   - **Location:** entry_base + `/index.php` (o return_url).  
   - exit.
3. Si **false**: se muestra mensaje de error (contraseña incorrecta, inactivo, etc.).

### 4. Petición a index.php (tras el redirect)
1. **session_start_early**: inicia sesión (o reanuda con la cookie recibida). Cookie con **path=/** debe ser la que enviamos en el paso 3.
2. **bootstrap**: sesión ya activa, no cambia nada.
3. **Auth::user()**: lee `$_SESSION['user']`.  
   - Si no hay usuario → redirect a login.php (con URL_BASE para subcarpeta).  
   - Si hay usuario → según rol (usuario → redirect a user_portal, otros → continúan en index).

### 5. profile.php / user_portal.php
- Misma secuencia: session_start_early → bootstrap.  
- Si no hay `$_SESSION['user']` → redirect a login.  
- Si hay → se muestra la página.

## Comprobaciones si la sesión se pierde

1. **Mismo nombre de sesión en toda la app**  
   session_start_early usa `getenv('SESSION_NAME') ?: 'mistorneos_session'`. En .env puede ponerse `SESSION_NAME=mistorneos_session` (o el mismo que en config) para que coincida.

2. **Una sola cookie con path=/**  
   Tras el login se envía una cookie con path=`/` y se intenta borrar la que tuviera path de subcarpeta. Revisar en DevTools (Application → Cookies) que solo exista una cookie de sesión y con path `/`.

3. **Logs [SESSION]**  
   - `Auth::login resultado=true` → credenciales OK.  
   - `login OK -> redirect | session_id=XXX` → id de la sesión donde se guardó el usuario.  
   - `index.php SIN usuario ... session_id=YYY` → si XXX ≠ YYY, el navegador está enviando otra cookie (sesión vieja).  
   - Con `SESSION_DEBUG=1` en .env hay más detalle en cada paso.

## Opciones de la página que requieren sesión

| Ruta / acción              | Comprueba sesión en        | Si no hay sesión        |
|----------------------------|----------------------------|---------------------------|
| index.php                  | Auth::user()               | Redirect a login.php     |
| user_portal.php            | $_SESSION['user']          | Redirect a login.php     |
| profile.php                | Auth::user()               | Redirect a login.php     |
| login.php (GET con sesión) | $_SESSION['user']          | Redirect a index/return_url |
