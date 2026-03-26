# Sistema de Comentarios

## Instalación

Para instalar el sistema de comentarios, ejecuta el siguiente script SQL:

```sql
-- Ejecutar desde la línea de comandos MySQL o phpMyAdmin
SOURCE sql/create_comentarios_table.sql;
```

O copia y pega el contenido de `sql/create_comentarios_table.sql` en tu cliente MySQL.

## Características

- ✅ Comentarios públicos visibles para todos
- ✅ Requiere login para publicar comentarios
- ✅ Sistema de moderación (pendiente, aprobado, rechazado)
- ✅ Tipos de comentarios: comentario, sugerencia, testimonio
- ✅ Sistema de calificación opcional (1-5 estrellas)
- ✅ Protección anti-spam (límite de comentarios por IP)
- ✅ Panel de administración para moderar comentarios

## Uso

### Para Usuarios

1. Los usuarios pueden ver todos los comentarios aprobados en el landing page
2. Para publicar un comentario, deben iniciar sesión
3. Los comentarios quedan en estado "pendiente" hasta que un administrador los apruebe

### Para Administradores

1. Acceder al menú "Comentarios" en el dashboard
2. Ver comentarios pendientes, aprobados y rechazados
3. Aprobar o rechazar comentarios según corresponda
4. Filtrar por estado y tipo de comentario

## Seguridad

- Validación CSRF en todos los formularios
- Sanitización de entrada para prevenir XSS
- Límite de comentarios por IP (5 por hora)
- Registro de IP y User Agent para auditoría
- Solo usuarios autenticados pueden comentar
- Solo administradores pueden moderar

## Estructura de la Tabla

- `id`: ID único del comentario
- `usuario_id`: ID del usuario (NULL si es anónimo, pero requiere login)
- `nombre`: Nombre del autor
- `email`: Email (opcional)
- `tipo`: comentario, sugerencia, testimonio
- `contenido`: Texto del comentario
- `calificacion`: 1-5 estrellas (opcional)
- `estatus`: pendiente, aprobado, rechazado
- `ip_address`: IP del autor
- `user_agent`: User agent del navegador
- `fecha_creacion`: Fecha de creación
- `fecha_aprobacion`: Fecha de aprobación
- `aprobado_por`: ID del administrador que aprobó
- `motivo_rechazo`: Motivo del rechazo (si aplica)




