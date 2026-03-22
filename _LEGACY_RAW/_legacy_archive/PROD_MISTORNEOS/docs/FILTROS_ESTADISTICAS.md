# Sistema de Filtros para Estadísticas por Nivel de Acceso

## Resumen General

Este documento detalla todos los filtros aplicados en las consultas de estadísticas según el nivel de acceso del usuario.

---

## 1. FILTROS POR ROL - Métodos en Auth.php

### 1.1 `getClubFilterForRole($table_alias = '')`

**Ubicación:** `config/auth.php` líneas 314-353

#### Admin General
- **Filtro WHERE:** `''` (vacío - sin filtro)
- **Parámetros:** `[]`
- **Resultado:** Ve TODOS los clubes

#### Admin Torneo
- **Filtro WHERE:** `"{$table_alias}.id = ?"` o `"id = ?"`
- **Parámetros:** `[$user_club_id]`
- **Resultado:** Solo ve su club directo
- **Nota:** Si no tiene `club_id`, retorna `id = 0` (no verá nada)

#### Admin Club
- **Filtro WHERE:** `"{$table_alias}.id IN ($placeholders)"` o `"id IN ($placeholders)"`
- **Parámetros:** `$clubes` (array de IDs de clubes supervisados)
- **Resultado:** Ve su club principal + clubes asociados
- **Nota:** Si no tiene clubes, retorna `id = 0` (no verá nada)

---

### 1.2 `getTournamentFilterForRole($table_alias = 't')`

**Ubicación:** `config/auth.php` líneas 268-306

#### Admin General
- **Filtro WHERE:** `''` (vacío - sin filtro)
- **Parámetros:** `[]`
- **Resultado:** Ve TODOS los torneos

#### Admin Torneo
- **Filtro WHERE:** `"{$table_alias}.club_responsable = ?"`
- **Parámetros:** `[$user_club_id]`
- **Resultado:** Solo ve torneos de su club directo
- **Nota:** Si no tiene `club_id`, retorna `club_responsable = 0` (no verá nada)

#### Admin Club
- **Filtro WHERE:** `"{$table_alias}.club_responsable IN ($placeholders)"`
- **Parámetros:** `$clubes` (array de IDs de clubes supervisados)
- **Resultado:** Ve torneos de todos sus clubes supervisados
- **Nota:** Si no tiene clubes, retorna `club_responsable = 0` (no verá nada)

---

## 2. FILTROS POR TABLA Y ESTADÍSTICA

### 2.1 Tabla: `usuarios`

#### Admin General (StatisticsHelper)
```sql
-- Total usuarios
SELECT COUNT(*) FROM usuarios
-- Sin filtros

-- Usuarios activos
SELECT COUNT(*) FROM usuarios WHERE status = 'approved'
-- Filtro: status = 'approved'

-- Admin clubs activos
SELECT COUNT(*) FROM usuarios 
WHERE role = 'admin_club' AND status = 'approved'
-- Filtros: role = 'admin_club' AND status = 'approved'

-- Usuarios por admin_club (en getAdminsByClubStats)
SELECT COUNT(*) FROM usuarios
WHERE club_id IN (?) 
  AND role = 'usuario' 
  AND status = 'approved'
-- Filtros: club_id IN (clubes supervisados), role = 'usuario', status = 'approved'
```

#### Admin Club (StatisticsHelper)
```sql
-- Usuarios afiliados por club
SELECT ... FROM usuarios u
WHERE u.club_id IN (?) 
  AND u.role = 'usuario' 
  AND u.status = 'approved'
-- Filtros: club_id IN (clubes supervisados), role = 'usuario', status = 'approved'
```

#### Admin Torneo
- **No tiene estadísticas específicas de usuarios en StatisticsHelper**
- Usa filtros de `home.php` con `getClubFilterForRole()`

---

### 2.2 Tabla: `clubes`

#### Admin General (StatisticsHelper)
```sql
-- Total clubes activos
SELECT COUNT(*) FROM clubes WHERE estatus = 1
-- Filtro: estatus = 1
```

#### Admin General (home.php)
```sql
-- Clubes filtrados
SELECT COUNT(*) FROM clubes 
WHERE estatus = 1 [AND {filtro_rol}]
-- Filtros base: estatus = 1
-- Filtro adicional: getClubFilterForRole() (vacío para admin_general)
```

#### Admin Club (StatisticsHelper)
```sql
-- Clubes supervisados con estadísticas
SELECT ... FROM clubes c
WHERE c.id IN (?) AND c.estatus = 1
-- Filtros: id IN (clubes supervisados), estatus = 1
```

#### Admin Torneo (home.php)
```sql
-- Clubes filtrados
SELECT COUNT(*) FROM clubes 
WHERE estatus = 1 AND id = ?
-- Filtros: estatus = 1, id = club_id del usuario
```

---

### 2.3 Tabla: `tournaments`

#### Admin General (StatisticsHelper)
```sql
-- Total torneos activos
SELECT COUNT(*) FROM tournaments WHERE estatus = 1
-- Filtro: estatus = 1

-- Torneos activos (futuros)
SELECT COUNT(*) FROM tournaments 
WHERE estatus = 1 AND fechator >= CURDATE()
-- Filtros: estatus = 1, fechator >= CURDATE()
```

#### Admin General (home.php)
```sql
-- Torneos filtrados
SELECT COUNT(*) FROM tournaments 
WHERE estatus = 1 [AND {filtro_rol}]
-- Filtros base: estatus = 1
-- Filtro adicional: getTournamentFilterForRole() (vacío para admin_general)
```

#### Admin Club (home.php)
```sql
-- Torneos filtrados
SELECT COUNT(*) FROM tournaments 
WHERE estatus = 1 AND club_responsable IN (?)
-- Filtros: estatus = 1, club_responsable IN (clubes supervisados)
```

#### Admin Torneo (home.php)
```sql
-- Torneos filtrados
SELECT COUNT(*) FROM tournaments 
WHERE estatus = 1 AND club_responsable = ?
-- Filtros: estatus = 1, club_responsable = club_id del usuario
```

---

### 2.4 Tabla: `inscripciones`

#### Admin General (StatisticsHelper)
```sql
-- Total inscritos
SELECT COUNT(*) FROM inscripciones
-- Sin filtros
```

#### Admin General (home.php)
```sql
-- Inscritos filtrados por torneos accesibles
SELECT COUNT(*) FROM inscripciones r 
INNER JOIN tournaments t ON r.torneo_id = t.id 
WHERE 1=1 [AND {filtro_torneo}]
-- Filtro: getTournamentFilterForRole() aplicado a tournaments (vacío para admin_general)
```

#### Admin Club (StatisticsHelper)
```sql
-- Inscripciones activas por club
SELECT ... FROM inscripciones i
INNER JOIN clubes c ON i.club_id = c.id
INNER JOIN tournaments t ON i.torneo_id = t.id
WHERE i.club_id IN (?)
  AND t.estatus = 1
  AND (i.estatus = 1 OR i.estatus = 'confirmado' OR i.estatus = 'solvente')
-- Filtros: 
--   - club_id IN (clubes supervisados)
--   - t.estatus = 1 (torneo activo)
--   - i.estatus IN (1, 'confirmado', 'solvente')
```

#### Admin Club (home.php)
```sql
-- Inscritos filtrados por torneos accesibles
SELECT COUNT(*) FROM inscripciones r 
INNER JOIN tournaments t ON r.torneo_id = t.id 
WHERE 1=1 AND t.club_responsable IN (?)
-- Filtros: club_responsable IN (clubes supervisados)
```

#### Admin Torneo (home.php)
```sql
-- Inscritos filtrados por torneos accesibles
SELECT COUNT(*) FROM inscripciones r 
INNER JOIN tournaments t ON r.torneo_id = t.id 
WHERE 1=1 AND t.club_responsable = ?
-- Filtros: club_responsable = club_id del usuario
```

---

### 2.5 Tabla: `payments`

#### Todos los roles (home.php)
```sql
-- Pagos completados
SELECT COUNT(*) FROM payments p 
INNER JOIN tournaments t ON p.torneo_id = t.id 
WHERE p.status = 'completed' [AND {filtro_torneo}]
-- Filtros base: p.status = 'completed'
-- Filtro adicional: getTournamentFilterForRole() aplicado a tournaments

-- Pagos pendientes
SELECT COUNT(*) FROM payments p 
INNER JOIN tournaments t ON p.torneo_id = t.id 
WHERE p.status = 'pending' [AND {filtro_torneo}]
-- Filtros base: p.status = 'pending'
-- Filtro adicional: getTournamentFilterForRole() aplicado a tournaments

-- Ingresos totales
SELECT COALESCE(SUM(p.amount), 0) FROM payments p 
INNER JOIN tournaments t ON p.torneo_id = t.id 
WHERE p.status = 'completed' [AND {filtro_torneo}]
-- Filtros base: p.status = 'completed'
-- Filtro adicional: getTournamentFilterForRole() aplicado a tournaments
```

---

## 3. RESUMEN POR ROL

### 3.1 Admin General

**Filtros aplicados:**
- **Usuarios:** Sin filtros (ve todos)
- **Clubes:** Solo `estatus = 1` (activos)
- **Torneos:** Solo `estatus = 1` (activos)
- **Inscripciones:** Sin filtros (ve todas)
- **Pagos:** Filtrados por `status = 'completed'` o `'pending'`

**Método usado:** `StatisticsHelper::generateAdminGeneralStats()`

---

### 3.2 Admin Club

**Filtros aplicados:**
- **Usuarios:** 
  - `club_id IN (clubes supervisados)`
  - `role = 'usuario'`
  - `status = 'approved'`
- **Clubes:** 
  - `id IN (clubes supervisados)`
  - `estatus = 1`
- **Torneos:** 
  - `club_responsable IN (clubes supervisados)`
  - `estatus = 1`
- **Inscripciones:** 
  - `club_id IN (clubes supervisados)` (en StatisticsHelper)
  - O filtrado por `t.club_responsable IN (clubes supervisados)` (en home.php)
  - `t.estatus = 1` (torneo activo)
  - `i.estatus IN (1, 'confirmado', 'solvente')`

**Método usado:** `StatisticsHelper::generateAdminClubStats($club_id)`

**Clubes supervisados:** Obtenidos mediante `ClubHelper::getClubesSupervised($club_id)`

---

### 3.3 Admin Torneo

**Filtros aplicados:**
- **Usuarios:** No tiene estadísticas específicas en StatisticsHelper
- **Clubes:** 
  - `id = club_id del usuario`
  - `estatus = 1`
- **Torneos:** 
  - `club_responsable = club_id del usuario`
  - `estatus = 1`
- **Inscripciones:** 
  - Filtrado por `t.club_responsable = club_id del usuario`
  - `t.estatus = 1`

**Método usado:** No usa StatisticsHelper, usa consultas directas en home.php

---

## 4. PROBLEMAS IDENTIFICADOS

### 4.1 Inconsistencias en el campo `status` de usuarios

**Problema:** En `home.php` línea 108 se usa `u.status = 1` pero el campo es ENUM('pending','approved','rejected')

**Ubicación:** `modules/home.php` línea 108
```sql
LEFT JOIN usuarios u ON u.club_id = c.id AND u.role = 'usuario' AND u.status = 1
```

**Debería ser:**
```sql
LEFT JOIN usuarios u ON u.club_id = c.id AND u.role = 'usuario' AND u.status = 'approved'
```

---

### 4.2 Inconsistencias en el campo `estatus` de inscripciones

**Problema:** En `StatisticsHelper.php` línea 299 se mezclan valores numéricos y de texto:
```sql
AND (i.estatus = 1 OR i.estatus = 'confirmado' OR i.estatus = 'solvente')
```

**Nota:** El campo puede ser ENUM o INT según la versión de la BD. Se mantiene la compatibilidad con ambos.

---

## 5. RECOMENDACIONES

1. **Unificar el uso de StatisticsHelper:** `home.php` debería usar completamente StatisticsHelper en lugar de consultas directas
2. **Corregir campo status:** Cambiar `status = 1` por `status = 'approved'` en todas las consultas
3. **Documentar estructura de BD:** Crear documentación clara sobre los tipos de campos (ENUM vs INT)
4. **Centralizar filtros:** Todos los filtros deberían pasar por los métodos de Auth.php

---

## 6. MAPA DE FILTROS POR ARCHIVO

### StatisticsHelper.php
- ✅ Usa `status = 'approved'` correctamente
- ✅ Filtra por clubes supervisados para admin_club
- ✅ No aplica filtros para admin_general (correcto)

### home.php
- ⚠️ Mezcla consultas directas con StatisticsHelper
- ⚠️ Usa `status = 1` en línea 108 (debería ser 'approved')
- ✅ Usa `getClubFilterForRole()` y `getTournamentFilterForRole()` correctamente

---

## 7. FLUJO DE FILTROS

```
Usuario → Auth::getClubFilterForRole() / getTournamentFilterForRole()
    ↓
Filtro según rol (admin_general/admin_club/admin_torneo)
    ↓
Consulta SQL con filtros aplicados
    ↓
Resultado filtrado según nivel de acceso
```






