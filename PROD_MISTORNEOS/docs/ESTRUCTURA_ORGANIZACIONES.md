# Estructura de Organizaciones

## Principio rector

**Toda la información en la app parte del principio de organización.** Ver documento [ESQUEMA_FUNCIONAMIENTO_ORGANIZACION.md](ESQUEMA_FUNCIONAMIENTO_ORGANIZACION.md) para el esquema completo.

En resumen:

- Un **administrador general** y las **solicitudes de afiliación de organización** están en el nivel superior.
- Cada organización tiene una **identificación geográfica (entidad)** definida en la solicitud.
- **Todo lo que está bajo ese administrador** (clubes, torneos, operadores, afiliados, etc.) pertenece a **su ámbito territorial (entidad)** y a **su organización** (pueden existir varias organizaciones en la misma entidad).

## Concepto

Separación clara entre **Organizaciones** y **Clubes**:

- **Organizaciones**: Entidades superiores (federaciones, asociaciones) registradas por los **administradores de organización**; cada una tiene una **entidad** geográfica.
- **Clubes**: Pertenecen a una organización y quedan bajo su entidad y ámbito

## Estructura de Tablas

### Tabla `organizaciones`

```sql
CREATE TABLE organizaciones (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(255) NOT NULL,
  direccion VARCHAR(255) NULL,
  responsable VARCHAR(100) NULL COMMENT 'Nombre del responsable/presidente',
  telefono VARCHAR(50) NULL,
  email VARCHAR(100) NULL,
  entidad INT NOT NULL DEFAULT 0 COMMENT 'Código de entidad geográfica (estado/región)',
  admin_user_id INT NOT NULL COMMENT 'Usuario administrador de organización que registró/gestiona',
  logo VARCHAR(255) NULL,
  estatus TINYINT NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tabla `clubes` (actualizada)

Ahora incluye el campo `organizacion_id`:

```sql
ALTER TABLE clubes 
ADD COLUMN organizacion_id INT NULL COMMENT 'Organización a la que pertenece';
```

### Tabla `tournaments` (actualizada)

El campo `club_responsable` ahora almacena el **ID de la organización**:

```sql
-- club_responsable = ID de la organización (NO del club)
-- La FK a clubes fue eliminada
```

## Relaciones

```
usuarios (admin_club)
    ↓ (admin_user_id)
organizaciones
    ↓ (organizacion_id)     ↓ (club_responsable = organizacion.id)
clubes                    tournaments
```

## Flujo de Creación

### 1. Solicitud de afiliación de organización
- Una persona solicita afiliación; indica datos de la **organización** y la **entidad** geográfica.
- Al aprobarse se crea un usuario (administrador de organización) y la **organización** con esa **entidad**.

### 2. Organización y entidad
- La organización queda con `entidad` fijada (ámbito territorial).
- Todo lo creado bajo ese administrador pertenece a esa organización y a esa entidad.

### 3. Creación de Clubes
- El administrador de organización crea clubes.
- Cada club tiene `organizacion_id` (y opcionalmente `admin_club_id` para permisos).
- Los clubes quedan en el ámbito de la organización y su entidad.

### 4. Creación de Torneos
- `club_responsable` en `tournaments` es el **ID de la organización** que organiza el torneo.
- Se obtiene de la organización del administrador que crea el torneo.
- Si es admin_general y elige un club, se usa la organización de ese club.

## Migración de Datos Existentes

El script `sql/create_organizaciones_table.sql` incluye:

1. Creación de tabla `organizaciones`
2. Agregar `organizacion_id` a `clubes` y `tournaments`
3. Crear organizaciones automáticas para admin_club existentes
4. Asignar organizaciones a clubes existentes
5. Asignar organizaciones a torneos existentes

## Archivos Modificados

### Scripts SQL
- `sql/create_organizaciones_table.sql` - Creación y migración

### Schema
- `schema/schema.sql` - Definición actualizada

### Módulos
- `modules/tournaments/save.php` - Asigna `organizacion_id` al crear torneo

## Ventajas de esta Estructura

1. **Principio de organización**: Toda la información queda bajo una organización y su entidad.
2. **Ámbito territorial**: La entidad signa todo lo que está bajo la organización (clubes, torneos, operadores, etc.).
3. **Varias organizaciones por entidad**: Pueden coexistir varias organizaciones en la misma entidad, cada una con su propio alcance.
4. **Jerarquía**: Organización (entidad) → Clubes → Torneos → Inscripciones, etc.
5. **Trazabilidad**: Cada torneo/club/operador pertenece a una organización y por tanto a una entidad.

## Permisos de Administrador de organización

El **administrador de organización** trabaja a nivel de su organización (y su entidad), sin necesidad de tener un club asignado (`club_id`):

- ✅ Puede crear torneos para cualquier club de su organización
- ✅ Puede ver todos los torneos de su organización
- ✅ Puede gestionar todos los clubes de su organización
- ❌ No puede eliminar usuarios (limitación específica)
- ❌ No puede eliminar torneos (limitación específica)
- ❌ No puede eliminar datos de torneos finalizados (limitación específica)

### Métodos de Auth

```php
// Obtener la organización del administrador de organización
Auth::getUserOrganizacionId(): ?int

// Filtrar torneos por organización (club_responsable = id organización)
Auth::canAccessTournament($tournament_id)
```

## Referencia

- [ESQUEMA_FUNCIONAMIENTO_ORGANIZACION.md](ESQUEMA_FUNCIONAMIENTO_ORGANIZACION.md) — Principio de organización y alcance por entidad.
