# Checklist Producción - laestaciondeldominohoy.com

## Configuración previa

### 1. Archivo .env
Copiar `config/env.production.example` a `.env` en la raíz del proyecto y completar:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://laestaciondeldominohoy.com/mistorneos

# BD principal
DB_HOST=localhost
DB_DATABASE=laestaci1_mistorneos
DB_USERNAME=laestaci1_user
DB_PASSWORD=TU_PASSWORD_REAL

# BD auxiliar (persona)
DB_SECONDARY_HOST=localhost
DB_SECONDARY_DATABASE=laestaci1_fvdadmin
DB_SECONDARY_USERNAME=laestaci1_user
DB_SECONDARY_PASSWORD=TU_PASSWORD_REAL
```

### 2. Estructura de carpetas
- **Ubicación:** `public_html/mistorneos/`
- **URL base:** `https://laestaciondeldominohoy.com/mistorneos`

### 3. Bases de datos
- **Principal:** `laestaci1_mistorneos` (torneos, usuarios, inscripciones)
- **Auxiliar:** `laestaci1_fvdadmin` (tabla `persona` para búsqueda de cédulas)

### 4. Tabla persona
En `laestaci1_fvdadmin` debe existir la tabla `persona` (o `dbo_persona`) con columnas:
- `IDUsuario`, `Nac`, `Nombre1`, `Nombre2`, `Apellido1`, `Apellido2`, `FNac`, `Sexo`

---

## URLs a verificar (sin 404)

| Página | URL |
|--------|-----|
| Inicio (SPA) | https://laestaciondeldominohoy.com/mistorneos/public/ |
| Landing | https://laestaciondeldominohoy.com/mistorneos/public/landing-spa.php |
| Login | https://laestaciondeldominohoy.com/mistorneos/public/login.php |
| Registro | https://laestaciondeldominohoy.com/mistorneos/public/register_by_club.php |
| Resultados | https://laestaciondeldominohoy.com/mistorneos/public/resultados.php |
| API Landing | https://laestaciondeldominohoy.com/mistorneos/public/api/landing_data.php |
| API Search Persona | https://laestaciondeldominohoy.com/mistorneos/public/api/search_user_persona.php |

---

## Script de verificación

Tras el deploy, acceder a:
```
https://laestaciondeldominohoy.com/mistorneos/public/verificar_produccion.php
```

Comprueba:
- Conexión BD principal
- Conexión BD secundaria
- Existencia tabla persona
- Enlaces a URLs críticas

**Eliminar o proteger este archivo después de verificar.**

---

## Error "Access denied" en BD

Si aparece `Access denied for user 'laestaci1_user'@'localhost'`:

1. **Ejecutar diagnóstico:** `https://laestaciondeldominohoy.com/mistorneos/public/diagnostico_db.php`
   - Verifica si existe y se lee el `.env`
   - Muestra la configuración usada (sin contraseña)
   - Prueba conexión con `localhost` y `127.0.0.1`

2. **Revisar en cPanel → MySQL® Databases:**
   - Usuario `laestaci1_user` asignado a `laestaci1_mistorneos` y `laestaci1_fvdadmin`
   - Contraseña correcta (cambiar si hace falta y actualizar `.env`)

3. **Probar host alternativo:** En `.env` cambiar `DB_HOST=127.0.0.1` si `localhost` falla.

4. **Contraseña con caracteres especiales:** Usar comillas en `.env`: `DB_PASSWORD="tu_password"`

**Eliminar `diagnostico_db.php` después de resolver.**

---

## .htaccess

El `.htaccess` en la raíz usa `RewriteBase /mistorneos/`. Si la app está en otra ruta, ajustar.

---

## Archivos modificados para producción

- `config/config.production.php` - Config con laestaci1_*
- `config/persona_database.php` - laestaci1_fvdadmin, tabla persona
- `lib/app_helpers.php` - URLs dinámicas desde APP_URL
- `lib/image_helper.php` - Base path dinámico
- Módulos equipos, gestionar_inscripciones - rutas API dinámicas
- `public/api/verificar_jugador_equipo.php` - Copia para acceso web
