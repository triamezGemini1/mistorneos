# 📊 Resumen de Filtros para Estadísticas

## 🔍 Filtros por Nivel de Acceso

### 👑 ADMIN GENERAL

| Tabla | Filtro WHERE | Parámetros | Descripción |
|-------|--------------|------------|-------------|
| **usuarios** | `(sin filtro)` | `[]` | Ve TODOS los usuarios |
| **usuarios (activos)** | `status = 'approved'` | `[]` | Solo usuarios aprobados |
| **usuarios (admin_club)** | `role = 'admin_club' AND status = 'approved'` | `[]` | Solo admins de club activos |
| **usuarios (afiliados)** | `club_id IN (?) AND role = 'usuario' AND status = 'approved'` | `[clubes_supervisados]` | Por cada admin_club |
| **clubes** | `estatus = 1` | `[]` | Solo clubes activos |
| **tournaments** | `estatus = 1` | `[]` | Solo torneos activos |
| **tournaments (futuros)** | `estatus = 1 AND fechator >= CURDATE()` | `[]` | Torneos activos futuros |
| **inscripciones** | `(sin filtro)` | `[]` | Ve TODAS las inscripciones |
| **inscripciones (activas)** | `club_id IN (?) AND t.estatus = 1 AND i.estatus IN (1, 'confirmado', 'solvente')` | `[clubes_supervisados]` | Por cada admin_club |

---

### 🏢 ADMIN CLUB

| Tabla | Filtro WHERE | Parámetros | Descripción |
|-------|--------------|------------|-------------|
| **usuarios** | `club_id IN (?) AND role = 'usuario' AND status = 'approved'` | `[clubes_supervisados]` | Solo afiliados de sus clubes |
| **clubes** | `id IN (?) AND estatus = 1` | `[clubes_supervisados]` | Solo sus clubes supervisados |
| **tournaments** | `club_responsable IN (?) AND estatus = 1` | `[clubes_supervisados]` | Torneos de sus clubes |
| **inscripciones** | `club_id IN (?) AND t.estatus = 1 AND i.estatus IN (1, 'confirmado', 'solvente')` | `[clubes_supervisados]` | Inscripciones de sus clubes en torneos activos |

**Clubes supervisados:** Obtenidos con `ClubHelper::getClubesSupervised($club_id)`
- Incluye: Club principal + clubes asociados

---

### 🎯 ADMIN TORNEO

| Tabla | Filtro WHERE | Parámetros | Descripción |
|-------|--------------|------------|-------------|
| **usuarios** | `(no tiene estadísticas específicas)` | - | - |
| **clubes** | `id = ? AND estatus = 1` | `[$user_club_id]` | Solo su club directo |
| **tournaments** | `club_responsable = ? AND estatus = 1` | `[$user_club_id]` | Solo torneos de su club |
| **inscripciones** | `t.club_responsable = ? AND t.estatus = 1` | `[$user_club_id]` | Inscripciones de torneos de su club |

---

## 📋 Filtros Base por Tabla

### Tabla: `usuarios`
```sql
-- Filtros base siempre aplicados:
role = 'usuario'        -- Solo usuarios (no admins)
status = 'approved'     -- Solo usuarios aprobados
```

### Tabla: `clubes`
```sql
-- Filtro base siempre aplicado:
estatus = 1            -- Solo clubes activos
```

### Tabla: `tournaments`
```sql
-- Filtro base siempre aplicado:
estatus = 1            -- Solo torneos activos
```

### Tabla: `inscripciones`
```sql
-- Filtros base aplicados según contexto:
t.estatus = 1          -- Torneo debe estar activo
i.estatus IN (1, 'confirmado', 'solvente')  -- Solo inscripciones válidas
```

---

## 🔧 Métodos de Filtrado

### `Auth::getClubFilterForRole($table_alias = '')`

**Retorna:** `['where' => string, 'params' => array]`

| Rol | WHERE | Parámetros |
|-----|-------|------------|
| admin_general | `''` (vacío) | `[]` |
| admin_torneo | `"{$alias}.id = ?"` | `[$user_club_id]` |
| admin_club | `"{$alias}.id IN ($placeholders)"` | `$clubes_supervisados` |

---

### `Auth::getTournamentFilterForRole($table_alias = 't')`

**Retorna:** `['where' => string, 'params' => array]`

| Rol | WHERE | Parámetros |
|-----|-------|------------|
| admin_general | `''` (vacío) | `[]` |
| admin_torneo | `"{$alias}.club_responsable = ?"` | `[$user_club_id]` |
| admin_club | `"{$alias}.club_responsable IN ($placeholders)"` | `$clubes_supervisados` |

---

## ⚠️ Problemas Corregidos

1. ✅ **Campo status de usuarios:** Corregido de `status = 1` a `status = 'approved'` en home.php línea 108
2. ✅ **Inconsistencia en inscripciones:** Se mantiene compatibilidad con ambos formatos (INT y ENUM)

---

## 📍 Ubicación de Filtros

### Archivos principales:
- **`config/auth.php`:** Métodos `getClubFilterForRole()` y `getTournamentFilterForRole()`
- **`lib/StatisticsHelper.php`:** Aplicación de filtros en consultas de estadísticas
- **`modules/home.php`:** Uso de filtros en dashboard (mezcla con consultas directas)

---

## 🎯 Recomendaciones

1. **Unificar:** Usar solo `StatisticsHelper` en lugar de consultas directas en `home.php`
2. **Consistencia:** Todos los filtros deben pasar por los métodos de `Auth.php`
3. **Documentación:** Mantener este documento actualizado cuando se agreguen nuevos filtros






