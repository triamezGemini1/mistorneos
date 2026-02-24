# Consejos de seguridad para Mistorneos

Recomendaciones para mantener el sistema lo más seguro posible.

---

## 1. Autenticación y usuarios

### 1.1 Username único
- **Mantener UNIQUE en `usuarios.username`.** No permitir varios usuarios con el mismo nombre de usuario. Facilita auditoría, evita ambigüedad en sesiones y cumple buenas prácticas.
- Para atletas generados desde `atletas`, usar un identificador único por persona, por ejemplo: **user00** + numfvd (o user00 + id atleta si no hay numfvd). Ver `scripts/crear_usuarios_desde_atletas.php`.

### 1.2 Contraseñas
- Las contraseñas se almacenan solo como **hash** (bcrypt vía `Security::hashPassword`). Nunca guardar la contraseña en texto plano.
- **Mínimo 6 caracteres** en validación; recomendable exigir complejidad (mayúsculas, números, símbolos) en registro público.
- Para atletas que entran con cédula como contraseña: considerar en el futuro **obligar cambio de contraseña** en primer acceso o enviar enlace de restablecimiento por email.

### 1.3 Sesiones
- Sesiones con **HttpOnly**, **Secure** (HTTPS en producción) y **SameSite** ya configurados en bootstrap.
- Regenerar ID de sesión tras login (`session_regenerate_id(true)`) para evitar fijación de sesión.
- Cerrar sesión correctamente (limpiar `$_SESSION`, destruir sesión y cookie) en logout.

### 1.4 Bloqueo por inactividad / desactivación
- Respetar `usuarios.status` (0 = activo, 1 = inactivo) e `is_active` (desactivado por admin): no permitir login a usuarios inactivos o desactivados.

---

## 2. Acceso a datos y autorización

### 2.1 Roles y permisos
- Todas las operaciones sensibles deben comprobar rol vía `Auth::requireRole()` o `Auth::requireRoleOrTournamentResponsible()`.
- No confiar en datos del cliente para decidir permisos; siempre validar en servidor (torneo_id, club_id, etc.).

### 2.2 Consultas SQL
- Usar **consultas preparadas** (PDO prepared statements) con parámetros para evitar inyección SQL. No concatenar entrada del usuario en SQL.
- Restringir por rol (por ejemplo `getTournamentFilterForRole`, `getClubFilterForRole`) en listados y reportes.

### 2.3 Datos sensibles
- No exponer en logs ni en respuestas API: contraseñas, tokens de recuperación, hashes. Los logs de autenticación fallida pueden registrar solo username/identificador, no la contraseña.

---

## 3. Infraestructura y despliegue

### 3.1 HTTPS
- En producción, forzar **HTTPS** (ya contemplado en bootstrap según `APP_ENV` y opción en .env).
- No enviar cookies de sesión por HTTP en producción.

### 3.2 Configuración y secretos
- **.env** no debe subirse al repositorio (estar en .gitignore). Claves de BD, APP_KEY, tokens y URLs internas solo en entorno.
- Permisos de archivos: directorios de uploads y logs con permisos restrictivos; no ejecutable para ficheros subidos.

### 3.3 Base de datos
- Usuario de la aplicación con **mínimos privilegios** necesarios (SELECT/INSERT/UPDATE/DELETE en tablas de la app; evitar DROP, GRANT, etc.).
- Copias de seguridad periódicas y, si aplica, cifrado de backups.

---

## 4. Invitaciones y tokens

### 4.1 Tokens de invitación
- Tokens largos y aleatorios; no predecibles.
- Validar estado de la invitación (activa, no expirada) antes de permitir registro o uso.
- No exponer en URLs de más de lo necesario; usar POST cuando se envíen datos sensibles.

### 4.2 Recuperación de contraseña
- Tokens de un solo uso y caducidad limitada. Invalidar tras uso o tras cambio de contraseña.

---

## 5. Entrada de usuario y salida

### 5.1 Validación y saneamiento
- Validar y normalizar en servidor: email, cédula, números, fechas. No confiar en validación solo en cliente.
- Al mostrar datos en HTML, usar **htmlspecialchars** (o equivalente) para evitar XSS.

### 5.2 Subida de archivos
- Validar tipo y extensión; comprobar contenido cuando sea posible. No ejecutar archivos subidos.
- Guardar fuera del document root o en rutas no ejecutables; servir con cabeceras seguras si se descargan.

---

## 6. Monitoreo y respuesta

### 6.1 Logs
- Registrar intentos de login fallidos, cambios de rol o de contraseña y accesos denegados, sin incluir datos sensibles.
- Revisar logs de errores y de seguridad de forma periódica.

### 6.2 Actualizaciones
- Mantener PHP, servidor web, BD y dependencias actualizadas. Aplicar parches de seguridad con prontitud.

---

## Resumen rápido

| Área              | Acción principal |
|-------------------|------------------|
| Usuarios          | Username único (ej. user00+numfvd); contraseñas solo hasheadas |
| Sesiones          | HttpOnly, Secure, SameSite; regenerar ID tras login |
| Autorización      | Comprobar rol y alcance en servidor en cada acción |
| SQL               | Siempre prepared statements; filtrar por rol |
| Secretos          | Solo en .env; .env fuera del repo |
| Producción        | HTTPS forzado; usuario BD con mínimos privilegios |
| Invitaciones      | Tokens aleatorios y con caducidad; validar estado |
| Salida HTML       | Escapar con htmlspecialchars para evitar XSS |
