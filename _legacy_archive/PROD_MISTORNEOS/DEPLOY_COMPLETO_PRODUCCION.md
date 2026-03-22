# 🚀 Despliegue Completo a Producción - Enero 2026

## 📋 Resumen

Este documento describe el proceso completo para desplegar la aplicación actualizada a producción, **manteniendo únicamente el archivo de configuración de producción** (`confiprrod.php` / `config/config.production.php`) y actualizando todo lo demás.

---

## ⚠️ IMPORTANTE: ANTES DE COMENZAR

1. **Backup completo de producción**:
   - Base de datos
   - Archivos actuales
   - Configuración del servidor

2. **Verificar que tienes acceso a**:
   - Servidor FTP/SFTP
   - phpMyAdmin o acceso MySQL
   - Panel de control del hosting

---

## 📦 PASO 1: Preparar el Paquete Local

### 1.1. Ejecutar Script de Preparación

```bash
php scripts/preparar_produccion.php
```

Este script:
- ✅ Verifica que todos los archivos necesarios existan
- ✅ Copia `confiprrod.php` a `config/config.production.php`
- ✅ Genera lista de archivos a excluir
- ✅ Verifica estructura de directorios

### 1.2. Crear Paquete ZIP (Opcional)

```bash
php scripts/crear_paquete_produccion.php
```

Esto crea un archivo ZIP con todos los archivos necesarios, excluyendo:
- Archivos de desarrollo
- `confiprrod.php` (se mantiene solo en local)
- Logs y cache
- node_modules, vendor, etc.

**O** puedes subir los archivos directamente vía FTP manteniendo la estructura.

---

## 🗄️ PASO 2: Migración de Base de Datos

### 2.1. Backup de Base de Datos en Producción

**Desde phpMyAdmin:**
1. Seleccionar base de datos: `laestaci1_mistorneos`
2. Ir a pestaña "Exportar"
3. Método: "Rápido"
4. Formato: "SQL"
5. Clic en "Continuar"
6. Guardar el archivo: `backup_mistorneos_antes_migracion_[fecha].sql`

**O desde línea de comandos (si tienes acceso SSH):**
```bash
mysqldump -u laestaci1_user -p laestaci1_mistorneos > backup_mistorneos_$(date +%Y%m%d_%H%M%S).sql
```

### 2.2. Ejecutar Migración SQL

**Opción A: Desde phpMyAdmin**
1. Seleccionar base de datos: `laestaci1_mistorneos`
2. Ir a pestaña "SQL"
3. Copiar y pegar el contenido de `sql/migracion_produccion_2026.sql`
4. Clic en "Continuar"
5. Verificar que no haya errores

**Opción B: Desde línea de comandos**
```bash
mysql -u laestaci1_user -p laestaci1_mistorneos < sql/migracion_produccion_2026.sql
```

### 2.3. Verificar Migración

```bash
# Si tienes acceso SSH, ejecutar:
php scripts/verificar_migracion.php

# O verificar manualmente en phpMyAdmin:
```

Consultas de verificación:
```sql
-- Verificar columnas nuevas
SHOW COLUMNS FROM tournaments LIKE 'es_evento_masivo';
SHOW COLUMNS FROM tournaments LIKE 'cuenta_id';

-- Verificar tablas nuevas
SHOW TABLES LIKE 'cuentas_bancarias';
SHOW TABLES LIKE 'reportes_pago_usuarios';

-- Verificar estructura
DESCRIBE cuentas_bancarias;
DESCRIBE reportes_pago_usuarios;
```

---

## 📁 PASO 3: Subir Archivos a Producción

### 3.1. Estructura de Directorios en Producción

La estructura debe ser:
```
public_html/mistorneos/
├── api/
├── cli/
├── config/
│   └── config.production.php  [MANTENER - No sobrescribir]
├── core/
├── lib/
├── manuales_web/
├── modules/
├── public/
├── scripts/
├── sql/
└── storage/
```

### 3.2. Archivos a Subir

**IMPORTANTE**: NO sobrescribir `config/config.production.php` si ya existe en producción.

**Archivos nuevos a subir:**
```
public/
├── inscribir_evento_masivo.php
├── reportar_pago_evento_masivo.php
├── ver_recibo_pago.php
└── api/
    ├── search_persona.php
    ├── search_user_persona.php
    └── verificar_inscripcion.php

modules/
├── cuentas_bancarias.php
├── reportes_pago_usuarios.php
├── tournament_admin/
│   ├── podios_equipos.php
│   └── equipos_detalle.php
└── gestion_torneos/
    └── panel-moderno.php (actualizado)

manuales_web/
├── admin_club_resumido.html
├── manual_usuario.php
└── assets/ (todas las imágenes)

lib/
└── BankValidator.php
```

**Archivos modificados a actualizar:**
```
public/
├── landing.php
├── includes/layout.php
└── user_portal.php

modules/
├── tournaments.php
├── tournaments/save.php
├── tournaments/update.php
├── affiliate_requests/list.php
├── affiliate_requests/send_whatsapp.php
├── torneo_gestion.php
├── gestion_torneos/panel.php
└── gestion_torneos/panel_equipos.php
```

### 3.3. Método de Subida

**Opción A: FTP/SFTP (FileZilla, WinSCP)**
1. Conectar al servidor
2. Navegar a `public_html/mistorneos/`
3. Subir archivos manteniendo estructura de directorios
4. **NO sobrescribir** `config/config.production.php` si existe

**Opción B: ZIP y Extraer**
1. Subir el ZIP creado por `crear_paquete_produccion.php`
2. Extraer en `public_html/mistorneos/`
3. **NO sobrescribir** `config/config.production.php`

**Opción C: Git (si está configurado)**
```bash
git pull origin main
# Verificar que config/config.production.php no se sobrescriba
```

---

## 🔧 PASO 4: Configuración Post-Despliegue

### 4.1. Verificar Permisos

```bash
# Si tienes acceso SSH:
chmod 755 public/
chmod 755 modules/
chmod 755 manuales_web/
chmod 644 public/*.php
chmod 644 modules/**/*.php
chmod 755 storage/
chmod 755 upload/
```

### 4.2. Verificar Configuración

Asegurarse de que `config/config.production.php` tenga la configuración correcta:

```php
// Debe tener:
'db' => [
    'name' => 'laestaci1_mistorneos',
    'user' => 'laestaci1_user',
    // ...
],
'app' => [
    'full_url' => 'https://laestaciondeldominohoy.com/mistorneos',
    // ...
]
```

### 4.3. Verificar Variables de Entorno (si se usan)

Si el servidor usa `.env`, verificar que tenga:
```env
APP_ENV=production
APP_URL=https://laestaciondeldominohoy.com/mistorneos
DB_HOST=localhost
DB_DATABASE=laestaci1_mistorneos
DB_USERNAME=laestaci1_user
DB_PASSWORD=[tu_password]
```

---

## ✅ PASO 5: Verificaciones Post-Despliegue

### 5.1. Verificación Automática

Si tienes acceso SSH, ejecutar:
```bash
php scripts/verificar_migracion.php
```

### 5.2. Verificación Manual

**Funcionalidades Básicas:**
- [ ] Login funciona: `https://laestaciondeldominohoy.com/mistorneos/public/login.php`
- [ ] Dashboard carga sin errores
- [ ] Menú muestra "Manual de Usuario"
- [ ] Crear torneo muestra checkbox "Evento Nacional"

**Nuevas Funcionalidades:**
- [ ] **Eventos Masivos**:
  - [ ] Landing muestra sección "Eventos Nacionales"
  - [ ] Formulario de inscripción pública funciona
  - [ ] Búsqueda por cédula funciona

- [ ] **Cuentas Bancarias**:
  - [ ] Menú muestra "Cuentas Bancarias"
  - [ ] Crear cuenta funciona
  - [ ] Búsqueda automática por cédula funciona

- [ ] **Reportes de Pago**:
  - [ ] Menú muestra "Reportes de Pago"
  - [ ] Formulario de reporte funciona
  - [ ] Ver recibo funciona

- [ ] **Cronómetro**:
  - [ ] Botón aparece en panel de torneo
  - [ ] Se inicializa con tiempo del torneo
  - [ ] Alarmas funcionan

- [ ] **Podios de Equipos**:
  - [ ] Botón "Podios" funciona en torneos de equipos
  - [ ] Muestra podios correctamente

- [ ] **Manual de Usuario**:
  - [ ] Enlace funciona
  - [ ] Requiere autenticación
  - [ ] Muestra contenido

### 5.3. Verificar Base de Datos

```sql
-- Verificar que las tablas existen
SELECT COUNT(*) as existe FROM information_schema.tables 
WHERE table_schema = 'laestaci1_mistorneos' 
AND table_name IN ('cuentas_bancarias', 'reportes_pago_usuarios');

-- Debe retornar: existe = 2
```

---

## 🔄 PASO 6: Rollback (Si es Necesario)

Si algo sale mal:

### 6.1. Restaurar Base de Datos
```bash
mysql -u laestaci1_user -p laestaci1_mistorneos < backup_mistorneos_antes_migracion_[fecha].sql
```

### 6.2. Restaurar Archivos
- Restaurar desde backup anterior
- O revertir cambios específicos

---

## 📝 Checklist Final

Antes de considerar el despliegue completo:

- [ ] Backup de base de datos realizado
- [ ] Backup de archivos realizado
- [ ] Script de migración ejecutado sin errores
- [ ] Todos los archivos nuevos subidos
- [ ] Archivos modificados actualizados
- [ ] `config/config.production.php` NO fue sobrescrito (verificado)
- [ ] Permisos verificados
- [ ] Funcionalidades básicas probadas
- [ ] Nuevas funcionalidades probadas
- [ ] Sin errores en logs
- [ ] Usuarios pueden acceder normalmente

---

## 🆘 Solución de Problemas

### Error: "Column 'es_evento_masivo' not found"
**Solución**: Re-ejecutar la sección de eventos masivos del script SQL.

### Error: "Table 'cuentas_bancarias' doesn't exist"
**Solución**: Verificar que el script SQL se ejecutó completamente.

### Error 500 en producción
**Solución**: 
1. Verificar logs: `storage/logs/`
2. Verificar permisos de archivos
3. Verificar que `config/config.production.php` existe y es correcto

### El manual no carga
**Solución**: 
- Verificar que `manuales_web/manual_usuario.php` existe
- Verificar permisos (644)
- Verificar que `manuales_web/admin_club_resumido.html` existe

### Los eventos masivos no aparecen
**Solución**:
- Verificar que hay torneos con `es_evento_masivo = 1`
- Verificar que `public/landing.php` está actualizado
- Verificar que la fecha del torneo es futura

---

## 📞 Notas Finales

- **Configuración de Producción**: El archivo `config/config.production.php` se mantiene igual, NO se sobrescribe.
- **Base de Datos**: Todos los cambios son aditivos, no se eliminan datos existentes.
- **Compatibilidad**: La nueva versión es compatible con la anterior, solo agrega funcionalidades.

---

**Fecha de Despliegue**: _______________

**Ejecutado por**: _______________

**Notas**: _______________

