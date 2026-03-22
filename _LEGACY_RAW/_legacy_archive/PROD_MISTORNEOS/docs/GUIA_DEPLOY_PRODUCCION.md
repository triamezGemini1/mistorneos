# Guía de Despliegue a Producción

## Información del Servidor

- **Dominio:** laestaciondeldomino.com
- **Carpeta:** `/mistorneos` (directorio public)
- **URL Completa:** https://laestaciondeldomino.com/mistorneos
- **Base de Datos Externa:** laestaci1_fvdadmin
- **Tabla Externa:** dbo.persona

---

## Checklist de Archivos y Carpetas

### ✅ ARCHIVOS/CARPETAS A SUBIR

#### Estructura Principal
```
mistorneos/
├── api/                          ✅ SUBIR TODO
├── cli/                          ✅ SUBIR TODO
├── config/                       ✅ SUBIR TODO (verificar config.production.php)
├── core/                         ✅ SUBIR TODO
├── database/                     ✅ SUBIR TODO
├── docs/                         ✅ SUBIR TODO
├── lang/                         ✅ SUBIR TODO
├── lib/                          ✅ SUBIR TODO
├── modules/                      ✅ SUBIR TODO
├── public/                       ✅ SUBIR TODO
├── schema/                       ✅ SUBIR TODO
├── sql/                          ✅ SUBIR TODO
├── storage/                      ✅ SUBIR (crear subcarpetas si no existen)
│   ├── logs/                     ✅ Crear si no existe (permisos 755)
│   ├── cache/                    ✅ Crear si no existe (permisos 755)
│   ├── sessions/                 ✅ Crear si no existe (permisos 755)
│   └── rate_limits/              ✅ Crear si no existe (permisos 755)
├── upload/                       ✅ SUBIR TODO (permisos 755)
├── uploads/                      ✅ SUBIR TODO (permisos 755)
├── composer.json                 ✅ SUBIR
├── index.html                    ✅ SUBIR
├── README.md                     ✅ SUBIR
└── .htaccess                     ✅ SUBIR (si existe)
```

#### Archivos Específicos
- ✅ `public/index.php` - Punto de entrada principal
- ✅ `public/.htaccess` - Configuración Apache (si existe)
- ✅ Todos los archivos `.php` en `public/`
- ✅ Todos los archivos `.php` en `modules/`
- ✅ Todos los archivos `.php` en `lib/`
- ✅ Todos los archivos `.php` en `config/`
- ✅ Todos los archivos de configuración SQL en `sql/`
- ✅ Todos los manuales en `docs/`

---

### ❌ ARCHIVOS/CARPETAS QUE NO DEBEN SUBIRSE

#### Archivos de Desarrollo
```
❌ .env                          (crear nuevo en servidor)
❌ .env.local                    (no subir)
❌ .env.development              (no subir)
❌ config/config.development.php (no subir)
❌ config/config.php             (usar config.production.php)
❌ debug_*.php                   (archivos de debug)
❌ temp_*.php                    (archivos temporales)
❌ test_*.php                   (archivos de prueba)
❌ *_test.php                    (archivos de prueba)
```

#### Archivos de Sistema
```
❌ .git/                         (carpeta de Git)
❌ .gitignore                    (no necesario en producción)
❌ .DS_Store                     (macOS)
❌ Thumbs.db                     (Windows)
❌ *.log                         (archivos de log locales)
❌ *.tmp                         (archivos temporales)
❌ *.bak                         (archivos de respaldo)
❌ *.backup                      (archivos de respaldo)
```

#### Carpetas de Desarrollo
```
❌ tests/                        (carpeta de pruebas)
❌ srcpppp/                      (carpeta de desarrollo antiguo)
❌ node_modules/                 (si existe, no subir)
❌ vendor/                       (instalar con composer en servidor)
```

#### Archivos Específicos a NO Subir
```
❌ debug_admin_club_stats.php
❌ debug_stats.php
❌ temp_insert.php
❌ public/add_torneo_id_to_club_photos.php (script de migración)
❌ public/verify_club_photos_structure.php (script de verificación)
❌ public/check_tournament_photos.php (script de verificación)
❌ scripts/generate_existing_pdfs.php (script de migración, opcional)
```

---

## Pasos de Instalación

### 1. Preparar Archivos de Configuración

#### a) Actualizar `config/config.production.php`
```php
// Cambiar estos valores:
'name' => 'laestaci1_fvdadmin',  // Nombre real de la BD principal
'user' => 'laestaci1_user',      // Usuario real del servidor
'pass' => 'CONTRASEÑA_REAL',     // Contraseña real del servidor
```

#### b) Actualizar `config/persona_database.production.php`
```php
// Cambiar estos valores:
private $dbname = 'laestaci1_fvdadmin';
private $username = 'laestaci1_user';  // Usuario real
private $password = 'CONTRASEÑA_REAL';  // Contraseña real
private $enabled = true;  // Habilitar en producción
```

#### c) Renombrar archivo de configuración
```bash
# En el servidor, renombrar:
config/config.production.php → config/config.php
# O asegurarse de que environment.php detecte producción correctamente
```

### 2. Subir Archivos al Servidor

#### Opción A: FTP/SFTP
1. Conectar al servidor via FTP/SFTP
2. Navegar a `/public_html/mistorneos` (o la ruta del directorio public)
3. Subir todos los archivos según el checklist

#### Opción B: Git (si está configurado)
```bash
git clone [repositorio]
cd mistorneos
# Verificar que esté en la rama correcta
```

### 3. Instalar Dependencias

```bash
# En el servidor, dentro de la carpeta mistorneos:
composer install --no-dev --optimize-autoloader
```

### 4. Configurar Permisos

```bash
# En el servidor, ejecutar:
chmod 755 storage/
chmod 755 storage/logs/
chmod 755 storage/cache/
chmod 755 storage/sessions/
chmod 755 storage/rate_limits/
chmod 755 upload/
chmod 755 uploads/
chmod 644 config/*.php
```

### 5. Crear Archivo .env (Opcional)

Si el sistema usa `.env`, crear en el servidor:
```bash
APP_ENV=production
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=laestaci1_fvdadmin
DB_USERNAME=laestaci1_user
DB_PASSWORD=CONTRASEÑA_REAL
APP_DEBUG=false
APP_BASE_URL=/mistorneos
```

### 6. Verificar Base de Datos

1. Verificar que la base de datos `laestaci1_fvdadmin` existe
2. Verificar que la tabla `dbo.persona` existe y tiene la estructura correcta
3. Ejecutar migraciones SQL si es necesario (archivos en `sql/`)

### 7. Verificar Configuración de Apache/Nginx

#### Apache (.htaccess)
Asegurarse de que existe `public/.htaccess` con:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

#### Nginx
Configurar rewrite rules para `/mistorneos`

---

## Verificación Post-Instalación

### 1. Verificar URLs
- ✅ https://laestaciondeldomino.com/mistorneos/public/landing.php
- ✅ https://laestaciondeldomino.com/mistorneos/public/index.php
- ✅ https://laestaciondeldomino.com/mistorneos/public/login.php

### 2. Verificar Conexión a Base de Datos
- Probar login de administrador
- Verificar que se cargan los datos correctamente

### 3. Verificar Conexión a Base de Datos Externa
- Probar búsqueda de persona por cédula
- Verificar que se conecta a `laestaci1_fvdadmin` y tabla `dbo.persona`

### 4. Verificar Permisos de Escritura
- Probar subida de archivos (logos, fotos, PDFs)
- Verificar que se crean logs en `storage/logs/`

### 5. Verificar Enlaces
- Revisar que no haya enlaces rotos (404)
- Verificar que todas las rutas funcionan correctamente

---

## Problemas Comunes y Soluciones

### Error: "Base de datos no encontrada"
- Verificar credenciales en `config/config.production.php`
- Verificar que la base de datos existe en el servidor

### Error: "Tabla dbo.persona no encontrada"
- Verificar que la tabla existe en `laestaci1_fvdadmin`
- Verificar el nombre exacto de la tabla (puede ser `dbo.persona` o `persona`)

### Error 404 en todas las rutas
- Verificar configuración de `.htaccess`
- Verificar que `base_url` está configurado como `/mistorneos`
- Verificar que `AppHelpers::getBaseUrl()` detecta producción correctamente

### Error de permisos al subir archivos
- Verificar permisos de `upload/` y `uploads/` (deben ser 755)
- Verificar que el usuario del servidor web tiene permisos de escritura

### Error: "Class not found"
- Ejecutar `composer dump-autoload` en el servidor
- Verificar que `vendor/` está completo

---

## Notas Importantes

1. **Seguridad:**
   - Nunca subir archivos con contraseñas hardcodeadas
   - Usar `config.production.php` con credenciales reales solo en el servidor
   - Mantener `.env` fuera del control de versiones

2. **Rendimiento:**
   - En producción, `debug` debe estar en `false`
   - `display_errors` debe estar en `false`
   - `log_errors` debe estar en `true`

3. **Backup:**
   - Hacer backup de la base de datos antes de desplegar
   - Mantener copia de los archivos de configuración originales

4. **Monitoreo:**
   - Revisar logs en `storage/logs/` regularmente
   - Monitorear errores de PHP en los logs del servidor

---

## Contacto y Soporte

Para problemas o dudas sobre el despliegue, revisar:
- `docs/MANUAL_ADMINISTRADOR_GENERAL.md`
- `docs/MANUAL_ADMINISTRADOR_CLUB.md`
- `docs/GUIA_RAPIDA.md`




