# Solución para Estadísticas en Cero

## Problema Identificado

Las estadísticas del dashboard muestran 0 a pesar de tener datos en las tablas.

## Cambios Realizados

### 1. Mejoras en `StatisticsHelper.php`
- ✅ Agregada verificación de `fetchColumn()` retornando `false`
- ✅ Mejorado manejo de errores con logging detallado
- ✅ Asegurado que todos los valores sean enteros con `(int)`

### 2. Mejoras en `home.php`
- ✅ Agregado casting a `(int)` en todas las consultas
- ✅ Mejorado manejo de errores con try-catch específico
- ✅ Agregado logging de errores
- ✅ Agregada alerta visual si hay errores en StatisticsHelper

### 3. Script de Diagnóstico
- ✅ Creado `debug_stats.php` para diagnosticar problemas

## Pasos para Diagnosticar

### Paso 1: Ejecutar Script de Diagnóstico

Acceder a: `http://localhost/mistorneos/debug_stats.php`

Este script mostrará:
- Consultas directas a la BD
- Resultado de StatisticsHelper
- Estructura de tablas
- Muestra de datos

### Paso 2: Verificar Logs de Error

Revisar el archivo de log de PHP (normalmente en `C:\wamp64\logs\php_error.log` o similar) para ver si hay errores.

### Paso 3: Verificar en el Dashboard

Agregar `?debug=1` a la URL del dashboard para ver información de debug:
`http://localhost/mistorneos/index.php?page=home&debug=1`

## Posibles Causas

### 1. Error en StatisticsHelper
- **Síntoma:** Todas las estadísticas en 0
- **Solución:** Revisar logs, verificar que las tablas existan

### 2. Problema con el campo `status`
- **Síntoma:** Usuarios en 0 pero hay usuarios en la BD
- **Solución:** Verificar que el campo sea ENUM('pending','approved','rejected') y no INT

### 3. Problema con el campo `estatus` de clubes/torneos
- **Síntoma:** Clubes/Torneos en 0
- **Solución:** Verificar que `estatus = 1` sea el valor correcto

### 4. Tabla `inscripciones` no existe o tiene otro nombre
- **Síntoma:** Inscritos en 0
- **Solución:** Verificar si la tabla se llama `inscripciones` o `inscritos`

## Consultas de Verificación Rápida

Ejecutar estas consultas directamente en MySQL para verificar datos:

```sql
-- Verificar usuarios
SELECT COUNT(*) as total, 
       COUNT(CASE WHEN status = 'approved' THEN 1 END) as aprobados,
       COUNT(CASE WHEN role = 'admin_club' AND status = 'approved' THEN 1 END) as admin_clubs
FROM usuarios;

-- Verificar clubes
SELECT COUNT(*) as total, 
       COUNT(CASE WHEN estatus = 1 THEN 1 END) as activos
FROM clubes;

-- Verificar torneos
SELECT COUNT(*) as total, 
       COUNT(CASE WHEN estatus = 1 THEN 1 END) as activos
FROM tournaments;

-- Verificar inscripciones
SELECT COUNT(*) as total FROM inscripciones;
-- O si la tabla se llama inscritos:
SELECT COUNT(*) as total FROM inscritos;
```

## Solución Temporal

Si el problema persiste, se puede usar consultas directas temporalmente editando `home.php` línea 65-86 para usar solo consultas directas en lugar de StatisticsHelper.

## Próximos Pasos

1. Ejecutar `debug_stats.php` para identificar el problema exacto
2. Revisar logs de error
3. Verificar estructura de tablas
4. Ajustar consultas según los resultados






