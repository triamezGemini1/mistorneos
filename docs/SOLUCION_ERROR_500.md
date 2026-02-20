# Solución de Error HTTP 500 en Producción

## Problema
Error HTTP 500 al acceder a: `https://laestaciondeldominohoy.com/mistorneos/public/`

## Causas Comunes

### 1. Dominio Incorrecto en Detección
**Problema:** El sistema detecta producción basándose en el dominio, pero el dominio real es `laestaciondeldominohoy.com` (con "hoy").

**Solución:** Ya corregido en:
- `config/environment.php` - Agregado `laestaciondeldominohoy.com`
- `lib/app_helpers.php` - Agregado `laestaciondeldominohoy.com`

### 2. Credenciales de Base de Datos No Configuradas
**Problema:** `config/config.production.php` tiene valores placeholder (`PASSWORD_AQUI`, `laestaci1_user`).

**Solución:**
1. Editar `config/config.production.php`
2. Reemplazar:
   ```php
   'user' => 'laestaci1_user',  // ← Usuario real
   'pass' => 'PASSWORD_AQUI',   // ← Contraseña real
   ```

### 3. Archivo .env Faltante o Incorrecto
**Problema:** El sistema intenta cargar `.env` que puede no existir o tener errores.

**Solución:**
- El sistema maneja la ausencia de `.env` automáticamente
- Si existe `.env`, verificar que no tenga errores de sintaxis

### 4. Permisos de Carpetas Incorrectos
**Problema:** Las carpetas `storage/`, `upload/`, `uploads/` no tienen permisos de escritura.

**Solución:**
```bash
chmod 755 storage/
chmod 755 storage/logs/
chmod 755 storage/cache/
chmod 755 storage/sessions/
chmod 755 upload/
chmod 755 uploads/
```

### 5. Errores de Sintaxis PHP
**Problema:** Errores de sintaxis en archivos PHP.

**Solución:**
- Usar el script de diagnóstico: `public/check_production.php`
- Revisar logs del servidor

### 6. Extensiones PHP Faltantes
**Problema:** Extensiones PHP requeridas no están instaladas.

**Extensiones requeridas:**
- `pdo`
- `pdo_mysql`
- `mbstring`
- `json`
- `session`
- `curl`

## Pasos de Diagnóstico

### Paso 1: Ejecutar Script de Diagnóstico
1. Acceder a: `https://laestaciondeldominohoy.com/mistorneos/public/check_production.php`
2. Revisar todos los resultados
3. Identificar problemas específicos

### Paso 2: Revisar Logs del Servidor
- Logs de Apache/Nginx
- Logs de PHP (`php_error.log`)
- Logs de la aplicación (`storage/logs/`)

### Paso 3: Verificar Configuración
1. Verificar que `config/config.production.php` existe
2. Verificar que las credenciales están actualizadas
3. Verificar que `config/environment.php` detecta producción correctamente

### Paso 4: Verificar Archivos Críticos
Asegurarse de que existen:
- `config/bootstrap.php`
- `config/environment.php`
- `config/config.production.php`
- `config/db.php`
- `lib/Env.php`
- `lib/app_helpers.php`
- `public/index.php`

## Solución Rápida

### Si el error persiste:

1. **Habilitar mostrar errores temporalmente:**
   Editar `config/bootstrap.php` (temporalmente):
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   ```

2. **Verificar archivo de configuración:**
   Asegurarse de que `config/config.production.php` tiene:
   - Credenciales reales de base de datos
   - `base_url` configurado como `/mistorneos`
   - `debug` en `false`

3. **Verificar permisos:**
   ```bash
   chmod 755 storage/
   chmod 755 upload/
   chmod 755 uploads/
   ```

4. **Verificar sintaxis:**
   ```bash
   php -l config/bootstrap.php
   php -l config/environment.php
   php -l config/config.production.php
   ```

## Verificación Post-Corrección

Después de aplicar las correcciones:

1. ✅ Acceder a `check_production.php` y verificar que todo está correcto
2. ✅ Probar acceso a `index.php`
3. ✅ Probar login
4. ✅ Verificar conexión a base de datos
5. ✅ **Eliminar `check_production.php`** por seguridad

## Contacto

Si el problema persiste después de seguir estos pasos:
1. Revisar logs detallados del servidor
2. Ejecutar `check_production.php` y compartir resultados
3. Verificar con el proveedor de hosting la configuración del servidor




