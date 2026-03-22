# Módulo de Invitaciones - Replanteado

Sistema completo de gestión de invitaciones a torneos, replanteado siguiendo la lógica de negocio del sistema invitorfvd.

## 📋 Estructura del Módulo

```
modules/invitations/
├── index.php                    # Listado de invitaciones con estadísticas y filtros
├── create.php                   # Crear nueva invitación
├── edit.php                     # Editar invitación existente
├── delete.php                   # Eliminar invitación
├── toggle_estado.php            # Cambiar estado (activa/expirada/cancelada)
├── imprimir_invitacion.php      # Vista imprimible con token de acceso
├── inscripciones/               # Módulo de inscripciones por token
│   ├── login.php                # Login con token de invitación
│   ├── logout.php               # Cerrar sesión
│   ├── index.php                # Panel de inscripciones
│   ├── inscribir_jugador.php    # API para inscribir jugador
│   ├── retirar_jugador.php      # API para retirar jugador
│   └── _guard.php               # Protección de rutas
└── README.md                    # Esta documentación
```

## 🎯 Características Principales

### 1. Gestión de Invitaciones
- ✅ **Listado completo** con paginación (15 por página)
- ✅ **Estadísticas en tiempo real**: Total, Activas, Expiradas, Canceladas
- ✅ **Filtros avanzados**: Por torneo y estado
- ✅ **CRUD completo**: Crear, Editar, Eliminar invitaciones
- ✅ **Control de estados**: Activar, Expirar, Cancelar
- ✅ **Tokens únicos**: Generación automática de tokens de 64 caracteres
- ✅ **Validaciones**: No duplicados (torneo + club)

### 2. Sistema de Invitación Imprimible
- ✅ **Diseño profesional** para impresión o PDF
- ✅ **Información completa**: Torneo, Club, Vigencia
- ✅ **Token destacado** para fácil visualización
- ✅ **Instrucciones claras** para el usuario
- ✅ **Logos** de club organizador y club invitado
- ✅ **Botón de impresión** y enlace a WhatsApp

### 3. Sistema de Inscripciones por Token
- ✅ **Login seguro** mediante token de invitación
- ✅ **Validación de vigencia**: Fecha inicio y fin
- ✅ **Sesión protegida** para cada club
- ✅ **Panel de control** con estadísticas
- ✅ **Inscripción de jugadores** por cédula
- ✅ **Gestión de inscritos**: Listar y retirar jugadores

## 🔐 Seguridad

1. **Autenticación por roles**: Solo admin_general y admin_torneo pueden gestionar invitaciones
2. **Tokens únicos**: Cada invitación tiene un token criptográfico único
3. **Validación de vigencia**: Control de fechas de acceso
4. **Protección CSRF**: En formularios de creación y edición
5. **Sesiones seguras**: Para el sistema de inscripciones
6. **Validación de pertenencia**: Los clubes solo ven sus propios inscritos

## 📊 Base de Datos

### Tabla: `invitations`

```sql
CREATE TABLE IF NOT EXISTS invitations (
  id INT NOT NULL AUTO_INCREMENT,
  torneo_id INT NOT NULL,
  club_id INT NOT NULL,
  acceso1 DATE NOT NULL,
  acceso2 DATE NOT NULL,
  usuario VARCHAR(255) NULL,
  token VARCHAR(64) NOT NULL,
  estado ENUM('activa','expirada','cancelada') DEFAULT 'activa',
  fecha_creacion TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token (token),
  UNIQUE KEY unique_torneo_club (torneo_id, club_id),
  KEY idx_torneo_id (torneo_id),
  KEY idx_club_id (club_id),
  KEY idx_estado (estado),
  CONSTRAINT fk_inv_torneo FOREIGN KEY (torneo_id) REFERENCES tournaments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_inv_club FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 🚀 Uso del Sistema

### Para Administradores

1. **Crear Invitación**:
   - Ir a `modules/invitations/index.php`
   - Clic en "Nueva Invitación"
   - Seleccionar torneo y club
   - Definir fechas de vigencia
   - El sistema genera automáticamente el token

2. **Enviar Invitación**:
   - Clic en el botón "📄" para ver la invitación imprimible
   - Guardar como PDF o imprimir
   - Enviar por WhatsApp, Email o entregar físicamente

3. **Gestionar Estados**:
   - ⏰ Marcar como expirada
   - ❌ Cancelar invitación
   - ✅ Re-activar invitación

### Para Delegados de Clubes

1. **Acceder al Sistema**:
   - Ir a `modules/invitations/inscripciones/login.php`
   - Ingresar el token recibido en la invitación
   - El sistema valida vigencia y estado

2. **Inscribir Jugadores**:
   - Ingresar cédula del jugador
   - El sistema busca al jugador en la base de datos del club
   - Confirmar inscripción

3. **Gestionar Inscritos**:
   - Ver lista completa de inscritos
   - Ver estadísticas (Total, Hombres, Mujeres)
   - Retirar jugadores si es necesario

## 🔄 Flujo de Trabajo

```
1. Admin crea invitación
   ↓
2. Sistema genera token único
   ↓
3. Admin imprime/envía invitación
   ↓
4. Delegado recibe token
   ↓
5. Delegado accede con token
   ↓
6. Sistema valida vigencia
   ↓
7. Delegado inscribe jugadores
   ↓
8. Jugadores quedan registrados en el torneo
```

## 🎨 Características de UI/UX

- **Diseño responsive**: Bootstrap 5
- **Estadísticas visuales**: Tarjetas de colores
- **Feedback inmediato**: Mensajes de éxito/error
- **Confirmaciones**: Para acciones destructivas
- **Filtros persistentes**: Se mantienen en la URL
- **Paginación**: Para grandes cantidades de datos

## 🔧 Configuración

### URLs del Sistema

Actualizar en `imprimir_invitacion.php` la URL base:

```php
$url_base = $protocol . '://' . $host . '/mistorneos/';
```

### Permisos de Acceso

- **Gestión de invitaciones**: `admin_general`, `admin_torneo`
- **Sistema de inscripciones**: Token de invitación válido

## 📝 Notas Técnicas

1. **Tokens**: Se generan con `bin2hex(random_bytes(32))` = 64 caracteres hexadecimales
2. **Sesiones**: El sistema de inscripciones usa sesiones PHP estándar
3. **AJAX**: Inscripción y retiro de jugadores usan fetch API
4. **PDO**: Todas las consultas usan prepared statements
5. **Validaciones**: En frontend y backend

## 🐛 Solución de Problemas

### Error: "Token inválido"
- Verificar que el token esté completo (64 caracteres)
- Verificar que la invitación esté activa
- Verificar fechas de vigencia

### Error: "Jugador no encontrado"
- El jugador debe estar registrado previamente en el módulo de registrants
- Verificar que pertenezca al club correcto

### Error: "Ya existe una invitación"
- No se pueden crear invitaciones duplicadas para el mismo torneo y club
- Editar la invitación existente o eliminarla primero

## 🔮 Mejoras Futuras

- [ ] Envío automático de invitaciones por email
- [ ] Envío automático por WhatsApp con API
- [ ] Notificaciones de nuevas inscripciones
- [ ] Exportación de lista de inscritos a Excel/PDF
- [ ] Estadísticas avanzadas por torneo
- [ ] Sistema de cuotas/límites de inscripciones por club
- [ ] Historial de cambios de estado
- [ ] QR Code en las invitaciones

## 📄 Licencia

Este módulo es parte del sistema de gestión de torneos y sigue la misma licencia del proyecto principal.
