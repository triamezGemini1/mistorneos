# Plan de Despliegue a Producción - Enero 2026

## 📋 Resumen de Cambios

Esta actualización incluye las siguientes nuevas funcionalidades:

1. **Eventos Masivos Nacionales**: Inscripción pública desde dispositivos móviles
2. **Sistema de Cuentas Bancarias**: Gestión de cuentas para recibir pagos
3. **Reportes de Pago de Usuarios**: Sistema para que usuarios reporten pagos
4. **Cronómetro de Ronda**: Herramienta de control de tiempo con alarmas
5. **Podios de Equipos**: Visualización mejorada para torneos de equipos
6. **Manual de Usuario**: Documentación accesible desde el sistema

---

## 🗄️ PASO 1: Migración de Base de Datos

### 1.1. Hacer Backup de la Base de Datos

```bash
# Desde el servidor de producción
mysqldump -u laestaci1_user -p laestaci1_mistorneos > backup_mistorneos_$(date +%Y%m%d_%H%M%S).sql
```

**⚠️ IMPORTANTE**: Guardar el backup en un lugar seguro antes de continuar.

### 1.2. Ejecutar Script de Migración

```bash
# Opción A: Desde línea de comandos MySQL
mysql -u laestaci1_user -p laestaci1_mistorneos < sql/migracion_produccion_2026.sql

# Opción B: Desde phpMyAdmin
# 1. Abrir phpMyAdmin
# 2. Seleccionar la base de datos: laestaci1_mistorneos
# 3. Ir a la pestaña "SQL"
# 4. Copiar y pegar el contenido de sql/migracion_produccion_2026.sql
# 5. Ejecutar
```

### 1.3. Verificar Migración

Ejecutar estas consultas para verificar:

```sql
-- Verificar columna es_evento_masivo
SHOW COLUMNS FROM tournaments LIKE 'es_evento_masivo';

-- Verificar columna cuenta_id
SHOW COLUMNS FROM tournaments LIKE 'cuenta_id';

-- Verificar tabla cuentas_bancarias
SHOW TABLES LIKE 'cuentas_bancarias';

-- Verificar tabla reportes_pago_usuarios
SHOW TABLES LIKE 'reportes_pago_usuarios';
```

---

## 📁 PASO 2: Subir Archivos a Producción

### 2.1. Archivos Nuevos a Subir

```
public/
├── inscribir_evento_masivo.php          [NUEVO]
├── reportar_pago_evento_masivo.php      [NUEVO]
├── ver_recibo_pago.php                  [NUEVO]
└── api/
    ├── search_persona.php               [NUEVO]
    ├── search_user_persona.php          [NUEVO]
    └── verificar_inscripcion.php         [NUEVO]

modules/
├── cuentas_bancarias.php                [NUEVO]
├── reportes_pago_usuarios.php           [NUEVO]
├── tournament_admin/
│   ├── podios_equipos.php               [NUEVO]
│   └── equipos_detalle.php              [NUEVO]
└── gestion_torneos/
    └── panel-moderno.php                [MODIFICADO - Cronómetro]

manuales_web/
├── admin_club_resumido.html             [NUEVO]
├── manual_usuario.php                   [NUEVO]
└── assets/                              [NUEVO - Imágenes del manual]

lib/
└── BankValidator.php                    [NUEVO]
```

### 2.2. Archivos Modificados

```
public/
├── landing.php                          [MODIFICADO - Sección eventos nacionales]
├── includes/
│   └── layout.php                       [MODIFICADO - Enlace manual]
└── user_portal.php                      [MODIFICADO - Enlace manual]

modules/
├── tournaments.php                      [MODIFICADO - Checkbox evento masivo, selector cuenta]
├── tournaments/
│   ├── save.php                         [MODIFICADO - Guardar es_evento_masivo, cuenta_id]
│   └── update.php                       [MODIFICADO - Actualizar es_evento_masivo, cuenta_id]
├── affiliate_requests/
│   ├── list.php                         [MODIFICADO - Link manual en notificaciones]
│   └── send_whatsapp.php                [MODIFICADO - Link manual en mensajes]
├── torneo_gestion.php                   [MODIFICADO - Podios equipos]
└── gestion_torneos/
    ├── panel.php                        [MODIFICADO - Link podios equipos]
    └── panel_equipos.php                [MODIFICADO - Link podios equipos]
```

### 2.3. Método de Subida

**Opción A: FTP/SFTP**
```bash
# Usar cliente FTP (FileZilla, WinSCP, etc.)
# Subir todos los archivos manteniendo la estructura de directorios
```

**Opción B: Git (si está configurado)**
```bash
git pull origin main
# O la rama correspondiente
```

**Opción C: ZIP y Extraer**
```bash
# En local: crear ZIP con archivos nuevos/modificados
# En servidor: extraer manteniendo estructura
```

---

## 🔧 PASO 3: Verificar Permisos

```bash
# Permisos de directorios
chmod 755 public/
chmod 755 modules/
chmod 755 manuales_web/
chmod 755 lib/

# Permisos de archivos PHP
find public/ -name "*.php" -exec chmod 644 {} \;
find modules/ -name "*.php" -exec chmod 644 {} \;

# Permisos de directorio de uploads (si existe)
chmod 755 upload/
chmod 755 upload/tournaments/
```

---

## ✅ PASO 4: Verificaciones Post-Despliegue

### 4.1. Verificar Funcionalidades Básicas

- [ ] Login funciona correctamente
- [ ] Dashboard carga sin errores
- [ ] Menú de navegación muestra "Manual de Usuario"
- [ ] Crear torneo muestra checkbox "Evento Nacional"
- [ ] Selector de cuenta bancaria aparece cuando se marca "Evento Nacional"

### 4.2. Verificar Nuevas Funcionalidades

- [ ] **Eventos Masivos**:
  - [ ] Landing público muestra sección "Eventos Nacionales"
  - [ ] Formulario de inscripción pública funciona
  - [ ] Búsqueda por cédula funciona
  - [ ] Creación automática de usuarios funciona

- [ ] **Cuentas Bancarias**:
  - [ ] Menú muestra "Cuentas Bancarias"
  - [ ] Crear cuenta bancaria funciona
  - [ ] Búsqueda automática por cédula funciona
  - [ ] Asociar cuenta a torneo funciona

- [ ] **Reportes de Pago**:
  - [ ] Menú muestra "Reportes de Pago"
  - [ ] Formulario de reporte funciona
  - [ ] Ver recibo funciona
  - [ ] Administrador puede confirmar/rechazar pagos

- [ ] **Cronómetro**:
  - [ ] Botón "ACTIVAR CRONÓMETRO" aparece en panel de torneo
  - [ ] Cronómetro se inicializa con tiempo del torneo
  - [ ] Alarmas funcionan (tsunami y terremoto)

- [ ] **Podios de Equipos**:
  - [ ] Botón "Podios" en torneos de equipos funciona
  - [ ] Muestra podios con fotos y estadísticas
  - [ ] Página de detalle de equipos funciona

- [ ] **Manual de Usuario**:
  - [ ] Enlace en menú funciona
  - [ ] Requiere autenticación para acceder
  - [ ] Muestra contenido correctamente

### 4.3. Verificar Base de Datos

```sql
-- Verificar que las tablas existen
SELECT TABLE_NAME 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'laestaci1_mistorneos'
AND TABLE_NAME IN ('cuentas_bancarias', 'reportes_pago_usuarios');

-- Verificar columnas en tournaments
DESCRIBE tournaments;
-- Debe mostrar: es_evento_masivo, cuenta_id

-- Verificar índices
SHOW INDEX FROM tournaments WHERE Key_name LIKE '%evento_masivo%';
SHOW INDEX FROM tournaments WHERE Key_name LIKE '%cuenta_id%';
```

---

## 🐛 PASO 5: Solución de Problemas Comunes

### Error: "Column 'es_evento_masivo' not found"
**Solución**: Ejecutar nuevamente la migración SQL, específicamente la parte de eventos masivos.

### Error: "Table 'cuentas_bancarias' doesn't exist"
**Solución**: Verificar que el script de migración se ejecutó completamente. Re-ejecutar la sección de cuentas bancarias.

### Error: "Foreign key constraint fails"
**Solución**: Verificar que la tabla `cuentas_bancarias` existe antes de crear las foreign keys. Ejecutar las creaciones de tablas primero.

### El manual no carga / Error 404
**Solución**: 
- Verificar que `manuales_web/manual_usuario.php` existe
- Verificar permisos del archivo (644)
- Verificar que `manuales_web/admin_club_resumido.html` existe

### El cronómetro no aparece
**Solución**:
- Verificar que `modules/gestion_torneos/panel-moderno.php` está actualizado
- Verificar que el torneo tiene campo `tiempo` configurado
- Verificar consola del navegador para errores JavaScript

### Los eventos masivos no aparecen en el landing
**Solución**:
- Verificar que `public/landing.php` está actualizado
- Verificar que hay torneos con `es_evento_masivo = 1`
- Verificar que la fecha del torneo es futura

---

## 📝 PASO 6: Notas Adicionales

### Configuración de Producción

El archivo `config/config.production.php` **NO necesita cambios**. Se mantiene igual.

### Variables de Entorno

Verificar que estas variables estén configuradas en `.env` o en el servidor:

```env
APP_URL=https://laestaciondeldominohoy.com/mistorneos
DB_HOST=localhost
DB_DATABASE=laestaci1_mistorneos
DB_USERNAME=laestaci1_user
DB_PASSWORD=[tu_password]
```

### Archivos que NO se deben subir

- `config/config.development.php`
- `scripts/` (solo si no se usan en producción)
- `tests/`
- `.git/`
- `node_modules/` (si existe)
- Archivos de backup SQL

---

## 🔄 PASO 7: Rollback (Si es Necesario)

Si algo sale mal, seguir estos pasos:

1. **Restaurar Base de Datos**:
```bash
mysql -u laestaci1_user -p laestaci1_mistorneos < backup_mistorneos_[fecha].sql
```

2. **Restaurar Archivos**:
   - Restaurar desde backup anterior
   - O revertir cambios con Git si está configurado

3. **Verificar Funcionalidad**:
   - Probar que todo funciona como antes
   - Verificar que no se perdieron datos

---

## 📞 Contacto y Soporte

Si encuentras problemas durante el despliegue:

1. Revisar los logs del servidor: `storage/logs/`
2. Revisar logs de PHP: configuración del servidor
3. Verificar permisos de archivos y directorios
4. Verificar que todas las dependencias están instaladas

---

## ✅ Checklist Final

Antes de considerar el despliegue completo:

- [ ] Backup de base de datos realizado
- [ ] Script de migración ejecutado sin errores
- [ ] Todos los archivos nuevos subidos
- [ ] Todos los archivos modificados actualizados
- [ ] Permisos verificados
- [ ] Funcionalidades básicas probadas
- [ ] Nuevas funcionalidades probadas
- [ ] Sin errores en logs
- [ ] Usuarios pueden acceder normalmente
- [ ] Manual de usuario accesible

---

**Fecha de Despliegue**: _______________

**Ejecutado por**: _______________

**Notas**: _______________

