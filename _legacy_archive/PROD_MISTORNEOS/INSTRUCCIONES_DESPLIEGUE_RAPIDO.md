# 🚀 Instrucciones Rápidas de Despliegue a Producción

## ⚡ Resumen Ejecutivo

Esta aplicación está **lista para producción**. Solo necesitas:

1. ✅ **Backup de base de datos** (CRÍTICO)
2. ✅ **Ejecutar migración SQL**
3. ✅ **Subir archivos** (manteniendo `config/config.production.php`)

---

## 📋 PASO 1: Preparación Local (Ya Completado)

```bash
# Ya ejecutado - verifica que todo esté listo
php scripts/preparar_produccion.php
```

**Resultado esperado**: ✅ La aplicación está lista para producción.

---

## 🗄️ PASO 2: Backup y Migración en Producción

### 2.1. Backup de Base de Datos

**Desde phpMyAdmin:**
1. Seleccionar: `laestaci1_mistorneos`
2. Exportar → Rápido → SQL
3. Guardar: `backup_antes_migracion_[fecha].sql`

### 2.2. Ejecutar Migración

**Desde phpMyAdmin:**
1. Seleccionar: `laestaci1_mistorneos`
2. Pestaña "SQL"
3. Copiar contenido de: `sql/migracion_produccion_2026.sql`
4. Ejecutar

**Verificar:**
```sql
SHOW TABLES LIKE 'cuentas_bancarias';
SHOW TABLES LIKE 'reportes_pago_usuarios';
SHOW COLUMNS FROM tournaments LIKE 'es_evento_masivo';
```

---

## 📁 PASO 3: Subir Archivos

### 3.1. Método Recomendado: FTP/SFTP

**IMPORTANTE**: 
- ✅ Subir TODOS los archivos nuevos y modificados
- ❌ **NO sobrescribir** `config/config.production.php` si ya existe en producción
- ✅ Mantener estructura de directorios

### 3.2. Archivos Críticos a Subir

**Nuevos:**
```
public/inscribir_evento_masivo.php
public/reportar_pago_evento_masivo.php
public/ver_recibo_pago.php
public/api/search_persona.php
public/api/search_user_persona.php
public/api/verificar_inscripcion.php
modules/cuentas_bancarias.php
modules/reportes_pago_usuarios.php
modules/tournament_admin/podios_equipos.php
modules/tournament_admin/equipos_detalle.php
manuales_web/ (todo el directorio)
lib/BankValidator.php
```

**Modificados:**
```
public/landing.php
public/includes/layout.php
public/user_portal.php
modules/tournaments.php
modules/tournaments/save.php
modules/tournaments/update.php
modules/affiliate_requests/list.php
modules/affiliate_requests/send_whatsapp.php
modules/torneo_gestion.php
modules/gestion_torneos/panel.php
modules/gestion_torneos/panel-moderno.php
modules/gestion_torneos/panel_equipos.php
```

### 3.3. Estructura en Producción

```
public_html/mistorneos/
├── config/
│   └── config.production.php  [NO SOBRESCRIBIR si existe]
├── public/
├── modules/
├── manuales_web/
├── lib/
└── ... (resto de estructura)
```

---

## ✅ PASO 4: Verificación Rápida

### 4.1. Verificar Funcionalidades

1. **Login**: `https://laestaciondeldominohoy.com/mistorneos/public/login.php`
2. **Dashboard**: Debe cargar sin errores
3. **Menú**: Debe mostrar "Manual de Usuario"
4. **Crear Torneo**: Debe mostrar checkbox "Evento Nacional"
5. **Landing Público**: Debe mostrar sección "Eventos Nacionales"

### 4.2. Probar Nuevas Funcionalidades

- [ ] Crear un evento masivo (marcar "Evento Nacional")
- [ ] Crear una cuenta bancaria
- [ ] Acceder al manual desde el menú
- [ ] Probar cronómetro en panel de torneo

---

## 🔄 Rollback (Si Algo Sale Mal)

### Restaurar Base de Datos:
```sql
-- Desde phpMyAdmin: Importar backup_antes_migracion_[fecha].sql
```

### Restaurar Archivos:
- Restaurar desde backup anterior
- O revertir archivos específicos

---

## 📞 Checklist Final

- [ ] Backup de BD realizado
- [ ] Migración SQL ejecutada
- [ ] Archivos subidos
- [ ] `config/config.production.php` NO fue sobrescrito
- [ ] Login funciona
- [ ] Dashboard carga
- [ ] Nuevas funcionalidades probadas

---

## 🆘 Problemas Comunes

| Problema | Solución |
|----------|----------|
| Error 500 | Verificar logs en `storage/logs/` |
| Columna no encontrada | Re-ejecutar migración SQL |
| Manual no carga | Verificar permisos de `manuales_web/` |
| Eventos masivos no aparecen | Verificar que hay torneos con `es_evento_masivo = 1` |

---

**¿Listo?** → Sigue `DEPLOY_COMPLETO_PRODUCCION.md` para instrucciones detalladas.

