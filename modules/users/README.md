# Módulo de Administración de Usuarios

## Descripción
Módulo para la gestión completa de usuarios del sistema, con acceso exclusivo para usuarios con rol `admin_general`.

## Características

### Control de Acceso
- **Acceso restringido**: Solo usuarios con rol `admin_general` pueden acceder
- **Protección de rutas**: Utiliza `Auth::requireRole(['admin_general'])` para verificar permisos
- **Auto-protección**: Los usuarios no pueden eliminar o desactivar su propia cuenta

### Funcionalidades

#### 1. Listado de Usuarios
- Vista completa de todos los usuarios del sistema
- Información mostrada:
  - ID del usuario
  - Nombre de usuario
  - Email
  - Rol (con badges de colores)
  - Estado (Activo/Inactivo)
  - Fecha de creación
- Indicador visual para el usuario actual ("Tú")

#### 2. Creación de Usuarios
- Formulario modal para crear nuevos usuarios
- Campos requeridos:
  - Nombre de usuario (mínimo 3 caracteres)
  - Contraseña (mínimo 6 caracteres)
  - Rol (usuario, admin_club, admin_torneo, admin_general)
- Campo opcional:
  - Email (con validación de formato)

#### 3. Edición de Usuarios
- Formulario modal para editar usuarios existentes
- Campos editables:
  - Nombre de usuario
  - Email
  - Rol
  - Contraseña (opcional, dejar vacío para mantener la actual)

#### 4. Gestión de Estado
- Activación/desactivación de usuarios
- Botones de acción contextuales
- Confirmación mediante modal

#### 5. Eliminación de Usuarios
- Eliminación permanente de usuarios
- Confirmación mediante modal
- Protección contra auto-eliminación

### Validaciones

#### Validaciones de Entrada
- **Nombre de usuario**: Requerido, mínimo 3 caracteres, único
- **Contraseña**: Requerida para nuevos usuarios, mínimo 6 caracteres
- **Email**: Opcional, validación de formato si se proporciona
- **Rol**: Debe ser uno de los roles válidos del sistema

#### Validaciones de Negocio
- No se puede eliminar el propio usuario
- No se puede desactivar el propio usuario
- Verificación de unicidad de nombres de usuario
- Validación de roles existentes

### Interfaz de Usuario

#### Diseño
- **Framework**: Bootstrap 5.1.3
- **Iconos**: Font Awesome 6.0.0
- **Layout**: Sidebar + contenido principal
- **Responsive**: Adaptable a diferentes tamaños de pantalla

#### Componentes
- **Sidebar**: Navegación principal del sistema
- **Tabla**: Lista de usuarios con acciones
- **Modales**: Para crear, editar, eliminar y cambiar estado
- **Badges**: Indicadores visuales para roles y estados
- **Alertas**: Mensajes de éxito y error

#### Colores de Roles
- `admin_general`: Rojo (danger)
- `admin_torneo`: Amarillo (warning)
- `admin_club`: Azul (info)
- `usuario`: Gris (secondary)

### Seguridad

#### Autenticación y Autorización
- Verificación de sesión activa
- Control de acceso por roles
- Regeneración de ID de sesión

#### Protección de Datos
- Validación y sanitización de entrada
- Prepared statements para consultas SQL
- Escape de HTML en salida
- Protección CSRF (heredada del sistema)

#### Validaciones de Seguridad
- Verificación de permisos en cada acción
- Protección contra auto-eliminación
- Validación de tipos de datos
- Sanitización de entrada

### Estructura de Archivos

```
modules/
├── users.php          # Controlador principal
├── users/
│   ├── list.php       # Vista principal
│   └── README.md      # Documentación
```

### Uso

#### Acceso al Módulo
```
URL: /modules/users.php
Método: GET
Permisos: admin_general
```

#### Acciones Disponibles
- `action=list` (por defecto): Mostrar lista de usuarios
- `action=create`: Crear nuevo usuario (POST)
- `action=update`: Actualizar usuario (POST)
- `action=delete`: Eliminar usuario (POST)
- `action=toggle_status`: Cambiar estado del usuario (POST)

### Integración con el Sistema

#### Dependencias
- `config/bootstrap.php`: Inicialización del sistema
- `config/auth.php`: Sistema de autenticación
- `config/db.php`: Conexión a base de datos

#### Base de Datos
- Tabla: `users`
- Campos utilizados:
  - `id`, `username`, `password_hash`, `email`, `role`, `status`
  - `created_at`, `updated_at`

#### Sesiones
- Mensajes de éxito: `$_SESSION['success_message']`
- Mensajes de error: `$_SESSION['errors']`
- Datos de formulario: `$_SESSION['form_data']`

### Consideraciones de Seguridad

1. **Contraseñas**: Se almacenan con hash usando `password_hash()`
2. **Validación**: Todas las entradas se validan y sanitizan
3. **Autorización**: Verificación de permisos en cada acción
4. **Protección**: Los usuarios no pueden modificar su propia cuenta de manera peligrosa
5. **Auditoría**: Se mantiene registro de creación y actualización

### Mejoras Futuras

1. **Auditoría**: Log de cambios en usuarios
2. **Búsqueda**: Filtros y búsqueda en la lista
3. **Paginación**: Para listas grandes de usuarios
4. **Exportación**: Exportar lista de usuarios
5. **Importación**: Importar usuarios desde CSV
6. **Perfiles**: Perfiles de usuario más detallados
7. **Notificaciones**: Notificaciones por email al crear/editar usuarios


















