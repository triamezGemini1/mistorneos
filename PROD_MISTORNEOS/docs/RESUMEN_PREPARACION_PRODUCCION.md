# Resumen: Preparación para Producción

## ✅ Cambios Realizados

### 1. Configuración de Base de Datos

#### `config/config.production.php`
- ✅ Actualizado con información del dominio: `laestaciondeldomino.com`
- ✅ Configurado `base_url` como `/mistorneos`
- ✅ Configurado base de datos externa: `laestaci1_fvdadmin`
- ⚠️ **PENDIENTE:** Actualizar credenciales reales (usuario y contraseña)

#### `config/persona_database.production.php`
- ✅ Configurado para usar `laestaci1_fvdadmin`
- ✅ Configurado para usar tabla `dbo.persona`
- ✅ Habilitado (`enabled = true`)
- ⚠️ **PENDIENTE:** Actualizar credenciales reales (usuario y contraseña)

### 2. Corrección de Enlaces Hardcodeados

#### Archivos Corregidos:
- ✅ `lib/whatsapp_sender.php` - Ahora usa `app_base_url()` con fallback inteligente
- ✅ `public/tournament_register.php` - Usa `app_base_url()` en lugar de localhost
- ✅ `lib/InvitationPDFGenerator.php` - Usa `app_base_url()` (2 ocurrencias)
- ✅ `modules/tournaments/invitation_link.php` - Usa `app_base_url()`

#### Sistema de Detección Automática:
- ✅ `lib/app_helpers.php` - Detecta automáticamente producción por dominio
- ✅ `config/environment.php` - Detecta producción por dominio
- ✅ `config/bootstrap.php` - Usa `app_base_url()` con fallback

### 3. Documentación Creada

- ✅ `docs/GUIA_DEPLOY_PRODUCCION.md` - Guía completa de despliegue
  - Checklist de archivos a subir/no subir
  - Pasos de instalación
  - Verificación post-instalación
  - Solución de problemas comunes

---

## ⚠️ Tareas Pendientes ANTES de Subir

### 1. Actualizar Credenciales de Base de Datos

**Archivo:** `config/config.production.php`
```php
// Cambiar estos valores:
'user' => 'laestaci1_user',      // ← Usuario real del servidor
'pass' => 'PASSWORD_AQUI',       // ← Contraseña real del servidor
```

**Archivo:** `config/persona_database.production.php`
```php
// Cambiar estos valores:
private $username = 'laestaci1_user';  // ← Usuario real
private $password = 'PASSWORD_AQUI';   // ← Contraseña real
```

### 2. Verificar Estructura de Base de Datos

- ✅ Verificar que existe la base de datos `laestaci1_fvdadmin`
- ✅ Verificar que existe la tabla `dbo.persona` con la estructura correcta
- ✅ Verificar permisos de usuario de base de datos

### 3. Verificar Permisos de Carpetas

En el servidor, ejecutar:
```bash
chmod 755 storage/
chmod 755 storage/logs/
chmod 755 storage/cache/
chmod 755 storage/sessions/
chmod 755 storage/rate_limits/
chmod 755 upload/
chmod 755 uploads/
```

### 4. Instalar Dependencias

```bash
composer install --no-dev --optimize-autoloader
```

---

## 📋 Checklist Pre-Subida

### Archivos de Configuración
- [ ] Actualizar credenciales en `config/config.production.php`
- [ ] Actualizar credenciales en `config/persona_database.production.php`
- [ ] Verificar que `config/environment.php` detecta producción correctamente
- [ ] Verificar que `lib/app_helpers.php` detecta producción correctamente

### Base de Datos
- [ ] Verificar que existe `laestaci1_fvdadmin`
- [ ] Verificar que existe tabla `dbo.persona`
- [ ] Verificar estructura de `dbo.persona` (campos: Nombre1, Nombre2, Apellido1, Apellido2, FNac, Sexo, Nac, IDUsuario)
- [ ] Probar conexión a base de datos con credenciales reales

### Archivos a NO Subir
- [ ] Eliminar `config/config.development.php` del servidor
- [ ] Eliminar `debug_*.php` del servidor
- [ ] Eliminar `temp_*.php` del servidor
- [ ] Eliminar `.env` local (crear nuevo en servidor si es necesario)
- [ ] Eliminar carpeta `tests/` si existe
- [ ] Eliminar carpeta `srcpppp/` si existe

### Verificación de Enlaces
- [ ] Verificar que no hay enlaces a `localhost` en el código
- [ ] Verificar que todos los enlaces usan `app_base_url()` o `AppHelpers::getBaseUrl()`
- [ ] Probar que los enlaces generan URLs correctas en producción

---

## 🔍 Verificación Post-Subida

### URLs a Probar
1. ✅ https://laestaciondeldomino.com/mistorneos/public/landing.php
2. ✅ https://laestaciondeldomino.com/mistorneos/public/index.php
3. ✅ https://laestaciondeldomino.com/mistorneos/public/login.php
4. ✅ https://laestaciondeldomino.com/mistorneos/public/resultados.php
5. ✅ https://laestaciondeldomino.com/mistorneos/public/galeria_fotos.php

### Funcionalidades a Probar
- [ ] Login de administradores
- [ ] Búsqueda de persona por cédula (conexión a `dbo.persona`)
- [ ] Subida de archivos (logos, fotos, PDFs)
- [ ] Generación de PDFs de invitación
- [ ] Envío de enlaces de WhatsApp
- [ ] Generación de códigos QR
- [ ] Visualización de resultados
- [ ] Galería de fotos

### Logs a Revisar
- [ ] `storage/logs/` - Verificar que se crean logs correctamente
- [ ] Logs del servidor web - Verificar errores de PHP
- [ ] Logs de base de datos - Verificar conexiones

---

## 📝 Notas Importantes

1. **Detección Automática de Producción:**
   - El sistema detecta automáticamente si está en producción basándose en el dominio
   - Si el dominio contiene `laestaciondeldomino.com`, se activa modo producción
   - No es necesario cambiar código manualmente

2. **URLs Relativas vs Absolutas:**
   - El sistema usa `app_base_url()` para generar URLs absolutas
   - En producción, todas las URLs incluyen `/mistorneos` automáticamente
   - No hay necesidad de cambiar rutas en el código

3. **Base de Datos Externa:**
   - La conexión a `dbo.persona` se hace a través de `PersonaDatabase`
   - Si falla la conexión, el sistema continúa funcionando sin búsqueda externa
   - Los errores se registran en logs pero no interrumpen el flujo

4. **Seguridad:**
   - `debug = false` en producción
   - `display_errors = false` en producción
   - `log_errors = true` en producción
   - Las contraseñas NO deben estar en el código, solo en archivos de configuración

---

## 🆘 Soporte

Para más información, consultar:
- `docs/GUIA_DEPLOY_PRODUCCION.md` - Guía completa de despliegue
- `docs/MANUAL_ADMINISTRADOR_GENERAL.md` - Manual de administrador
- `docs/GUIA_RAPIDA.md` - Guía rápida de uso




