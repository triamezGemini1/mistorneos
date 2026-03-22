# Propuesta: Estructura de Archivos – Refactorización Admin General

> **IMPORTANTE:** Este documento es una propuesta. No se aplicarán cambios hasta recibir aprobación explícita.

---

## Resumen Ejecutivo

Se propone una refactorización incremental que:

1. Simplifica el Dashboard Home a solo tarjetas de estadísticas.
2. Reorganiza el menú lateral por dominios funcionales.
3. Introduce una estructura modular `actions/` y `views/` para admin_general.
4. Centraliza permisos y evita SQL en vistas.
5. **No elimina funcionalidad existente**; se mantienen compatibilidad y rutas legacy.

---

## 1. Limpieza del Dashboard (Home)

### 1.1 Archivos a crear

```
lib/
└── OrganizacionesData.php          # NUEVO: Estadísticas de entidades y organizaciones
```

### 1.2 Archivos a modificar

```
lib/
└── DashboardData.php               # MODIFICAR: Añadir método loadStatsAdminGeneralOnly()
                                    # para home simplificado (solo stats, sin torneos/entidades detalle)

public/includes/views/dashboard/
└── home.php                        # MODIFICAR: Solo grid de tarjetas (eliminar tablas)
```

### 1.3 OrganizacionesData.php – Estructura propuesta

```php
<?php
declare(strict_types=1);

class OrganizacionesData
{
    /**
     * Estadísticas globales para dashboard admin_general (solo tarjetas).
     * Retorna: total_entidades, total_organizaciones, total_usuarios, total_admin_clubs,
     *          total_clubs, total_afiliados, total_hombres, total_mujeres,
     *          total_admin_torneo, total_operadores.
     */
    public static function loadStatsGlobales(): array;

    /**
     * Mapa id/codigo => nombre de entidades (para uso en selects).
     */
    public static function loadEntidadMap(): array;
}
```

### 1.4 Vista home.php – Solo tarjetas

- **Eliminar:** Tabla de torneos, tabla de entidades, tabla de torneos por entidad.
- **Mantener:** Grid de tarjetas con:
  - Total Usuarios
  - Total Entidades (NUEVO)
  - Total Organizaciones (NUEVO)
  - Admin de Organizaciones
  - Clubes Afiliados
  - Total Afiliados
  - Hombres
  - Mujeres
  - Admin Torneo
  - Operadores
- **Header:** Bienvenida, botón "Enviar notificaciones", fecha, rol.

---

## 2. Reorganización del Menú Lateral (layout.php)

### 2.1 Estructura propuesta para admin_general

```
┌─ INICIO
│   ├── Dashboard
│   └── Calendario
│
┌─ ESTRUCTURA
│   ├── Entidades          (NUEVO en menú - page=entidades&action=index)
│   ├── Organizaciones     (page=organizaciones)
│   └── Clubes             (page=clubs)
│
┌─ AFILIACIONES (acordeón colapsable)
│   ├── Invitar Afiliados  (page=admin_clubs&action=invitar)
│   └── Solicitudes Afiliación (page=affiliate_requests) [badge pendientes]
│
├─ TORNEOS
│   └── Torneos            (page=torneo_gestion&action=index)
│
┌─ USUARIOS
│   └── Gestión de Usuarios y Roles (page=users)
│
┌─ COMUNICACIÓN (acordeón colapsable)
│   ├── Notificaciones Masivas
│   ├── Mensajes WhatsApp
│   └── Comentarios
│
┌─ HERRAMIENTAS
│   └── Control Especial   (page=control_admin)
│
└─ ENLACES
    ├── Portal Público
    └── Manual de Usuario
```

### 2.2 Archivo a modificar

```
public/includes/
└── layout.php             # Sección <?php if ($user['role'] === 'admin_general'): ?>
```

### 2.3 Variables para acordeones

- `$is_afiliaciones_open` = `in_array($current_page, ['admin_clubs', 'affiliate_requests'])`
- `$is_comunicacion_open` = `in_array($current_page, ['notificaciones_masivas', 'whatsapp_config', 'comments'])`

---

## 3. Flujo Drill-Down y Modularización

### 3.1 Estructura de directorios propuesta

```
modules/
├── admin_general/                    # NUEVO: Módulos específicos admin_general
│   ├── entidades/
│   │   ├── actions/
│   │   │   ├── index.php             # Lista entidades con resumen
│   │   │   └── detail.php            # Detalle entidad → enlaces a organizaciones
│   │   └── views/
│   │       ├── index.php
│   │       └── detail.php
│   │
│   ├── organizaciones/
│   │   ├── actions/
│   │   │   ├── index.php             # Lista por entidad (entidad_id)
│   │   │   ├── detail.php            # Detalle org → clubes
│   │   │   └── club_detail.php       # Detalle club → afiliados
│   │   └── views/
│   │       ├── index.php
│   │       ├── detail.php
│   │       └── club_detail.php
│   │
│   └── bootstrap.php                 # requireRole + helpers comunes
│
├── entidades.php                     # ROUTER LEGACY: redirige a admin_general/entidades o mantiene include
├── organizaciones.php                # ROUTER LEGACY: idem
└── ... (resto de módulos existentes)
```

### 3.2 Rutas consistentes

| Módulo         | action=index              | action=detail&id=X          |
|----------------|---------------------------|-----------------------------|
| entidades      | Lista entidades           | Detalle entidad             |
| organizaciones | Lista (por entidad_id)    | Detalle organización        |
| organizaciones | club_detail&club_id=X     | Detalle club con afiliados  |
| clubs          | list                      | detail&id=X                 |
| users          | list                      | edit&id=X                   |

### 3.3 Flujo de navegación

```
Entidades (index)
  └── [Entidad X] → entidades&action=detail&id=X
        └── Organizaciones de la entidad
              └── [Org Y] → organizaciones&action=detail&id=Y
                    └── Clubes de la org
                          └── [Club Z] → organizaciones&club_id=Z&id=Y
                                └── Afiliados del club
                                      └── Enlace a users (filtrado por club_id)
```

### 3.4 Estrategia de migración

**Fase 1 (incremental):** Mantener `entidades.php` y `organizaciones.php` como routers que incluyen los nuevos actions/views. Sin romper URLs actuales.

```
modules/entidades.php
  → require admin_general/bootstrap.php (Auth::requireRole)
  → $action = $_GET['action'] ?? 'index'
  → include "admin_general/entidades/actions/{$action}.php"
  → (el action carga datos y include la view)
```

**Fase 2 (opcional):** Si se desea, mover `page=entidades` a `page=admin_general/entidades` y añadir alias en index.php.

---

## 4. Centralización de Seguridad

### 4.1 Archivo a crear

```
config/
└── admin_general_auth.php            # NUEVO: Helper de autorización
```

### 4.2 Contenido propuesto

```php
<?php
declare(strict_types=1);

/**
 * Centraliza la verificación de rol admin_general.
 * Usar al inicio de cada módulo exclusivo.
 */
function requireAdminGeneral(): void
{
    if (!defined('APP_BOOTSTRAPPED')) {
        require_once __DIR__ . '/../config/bootstrap.php';
    }
    require_once __DIR__ . '/../config/auth.php';
    Auth::requireRole(['admin_general']);
}
```

### 4.3 Módulos que lo usarán

- `admin_clubs/*`
- `affiliate_requests/*`
- `control_admin.php`
- `entidades.php` (y acciones internas)
- `invitations/clean_database.php`

---

## 5. Archivos Nuevos – Resumen

| Archivo                         | Propósito                                        |
|---------------------------------|--------------------------------------------------|
| `lib/OrganizacionesData.php`    | Estadísticas entidades/organizaciones para home  |
| `config/admin_general_auth.php` | Centralizar requireRole admin_general            |
| `modules/admin_general/entidades/actions/index.php`   | Lista entidades          |
| `modules/admin_general/entidades/actions/detail.php`  | Detalle entidad          |
| `modules/admin_general/entidades/views/index.php`     | Vista lista              |
| `modules/admin_general/entidades/views/detail.php`    | Vista detalle            |
| `modules/admin_general/organizaciones/actions/`       | (refactor parcial)       |
| `modules/admin_general/organizaciones/views/`         | (refactor parcial)       |
| `modules/admin_general/bootstrap.php`                | Auth + helpers comunes   |

---

## 6. Archivos a Modificar – Resumen

| Archivo                         | Cambios                                           |
|---------------------------------|---------------------------------------------------|
| `lib/DashboardData.php`         | Método `loadStatsAdminGeneralOnly()` o delegar a OrganizacionesData |
| `public/includes/views/dashboard/home.php` | Solo tarjetas, sin tablas                 |
| `modules/admin_dashboard.php`   | Para admin_general, usar OrganizacionesData si view=home |
| `public/includes/layout.php`    | Nuevo menú admin_general por dominios             |
| `modules/entidades.php`         | Router que delega a admin_general/entidades       |
| `modules/organizaciones.php`    | Opcional: delegar a admin_general/organizaciones  |

---

## 7. Orden de Implementación Sugerido

1. **Fase A – Dashboard Home**
   - Crear `OrganizacionesData.php`
   - Modificar `DashboardData.php` (o admin_dashboard) para usar OrganizacionesData en admin_general
   - Simplificar `home.php` a solo tarjetas

2. **Fase B – Menú**
   - Modificar `layout.php` con la nueva estructura de menú para admin_general

3. **Fase C – Entidades modular**
   - Crear `admin_general/bootstrap.php`
   - Crear `admin_general/entidades/actions/` y `views/`
   - Modificar `entidades.php` como router

4. **Fase D – Seguridad**
   - Crear `admin_general_auth.php`
   - Ir sustituyendo `Auth::requireRole` en módulos exclusivos (opcional, bajo impacto)

5. **Fase E – Organizaciones (incremental)**
   - Extraer vistas a `admin_general/organizaciones/views/`
   - Mantener lógica en `organizaciones.php` o mover a actions/

---

## 8. Consideraciones

- **admin_club y admin_torneo:** El menú de admin_club/admin_torneo no se modifica en esta propuesta.
- **Rutas legacy:** `page=organizaciones`, `page=entidades`, `page=clubs` se mantienen.
- **include_once:** Se usará `include_once` para bootstrap, auth y helpers en cada módulo.
- **users.php y clubs.php:** No se refactorizan en esta fase; solo se documenta dónde extraer `handle*()` en el futuro.
- **whatsapp_config:** Verificar que exista el módulo; si no, el enlace apuntará a 404 hasta crearlo.

---

## 9. Validación Pre-Implementación

Antes de aplicar, confirmar:

- [ ] ¿Se aprueba la estructura de directorios `admin_general/` con `actions/` y `views/`?
- [ ] ¿Se mantiene `page=entidades` y `page=organizaciones` como URLs (solo cambia el contenido)?
- [ ] ¿El menú de admin_club y admin_torneo permanece intacto?
- [ ] ¿Las tarjetas "Total Entidades" y "Total Organizaciones" se calculan así?
  - Total Entidades: `COUNT(DISTINCT entidad) FROM organizaciones WHERE entidad IS NOT NULL AND entidad != 0`
  - Total Organizaciones: `COUNT(*) FROM organizaciones WHERE estatus = 1`

---

**Fin de la propuesta.** Pendiente de aprobación para proceder con la implementación.
