# Auth
- Inicio/cierre de sesión con `password_hash` y `password_verify`.
- `Auth::requireRole([...])` protege rutas por rol.
- Recomendaciones: agregar rate limiting (p.ej. 5 intentos/15 min), CAPTCHA opcional, forzar HTTPS, cookies `SameSite=Lax` y `HttpOnly`.
