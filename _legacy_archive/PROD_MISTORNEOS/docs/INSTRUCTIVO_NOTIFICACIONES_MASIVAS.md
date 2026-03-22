# Instructivo General: Sistema de Notificaciones Masivas

## Descripción

El sistema permite enviar mensajes a usuarios registrados en un club, inscritos en un torneo o solicitantes de afiliación, mediante tres canales: **WhatsApp**, **Email** y **Telegram**.

## Acceso

- **admin_club**: Notificaciones a usuarios de sus clubes e inscritos en sus torneos.
- **admin_torneo**: Notificaciones a inscritos en torneos que gestiona.
- **admin_general**: Todo lo anterior más solicitantes de afiliación.

## Ubicación en el menú

Menú lateral > **Notificaciones** (icono de campana).

## Tipos de destinatarios

1. **Usuarios de mi club**: Todos los usuarios con rol "usuario" registrados en los clubes que gestiona.
2. **Inscritos en torneo**: Usuarios inscritos en un torneo específico de su club.
3. **Solicitantes de afiliación** (solo admin_general): Personas con solicitud de afiliación pendiente.

## Canales disponibles

| Canal | Configuración | Envío | Instructivo detallado |
|-------|---------------|-------|------------------------|
| WhatsApp | Celular en perfil de usuario | Manual (enlaces wa.me) | [INSTRUCTIVO_WHATSAPP_NOTIFICACIONES.md](INSTRUCTIVO_WHATSAPP_NOTIFICACIONES.md) |
| Email | SMTP en .env | Automático en lote | [INSTRUCTIVO_EMAIL_NOTIFICACIONES.md](INSTRUCTIVO_EMAIL_NOTIFICACIONES.md) |
| Telegram | Bot + TELEGRAM_BOT_TOKEN + chat_id en perfil | Automático en lote | [INSTRUCTIVO_TELEGRAM_NOTIFICACIONES.md](INSTRUCTIVO_TELEGRAM_NOTIFICACIONES.md) |

## Variables en el mensaje

Al escribir el mensaje, puede usar:

- `{nombre}`: Nombre del destinatario.
- `{torneo}`: Nombre del torneo (si aplica).
- `{club}`: Nombre del club del destinatario.
- `{id_usuario}`: ID del usuario en el sistema.
- `{fecha_torneo}`: Fecha del torneo (formato dd/mm/aaaa).
- `{hora_torneo}`: Hora del torneo (ej: 1:00 pm). Por defecto "1:00 pm" si no está configurada.
- `{tratamiento}`: Estimado o Estimada (según sexo del destinatario).

## Plantillas predefinidas

### Notificación Inicio Torneo

Para inscritos en un torneo:

```
Estimado o Estimada {nombre}, su identificador en el sistema es {id_usuario}, usted está inscrito en el torneo {torneo}, que comenzará a la 1 pm del día {fecha_torneo}, agradecemos su puntualidad.
```

### Invitación Torneo

Para invitar a usuarios del club o inscritos:

```
Estimado o Estimada {nombre}, usted está invitado a participar en el torneo {torneo}, que se realizará el día {fecha_torneo}, a la hora {hora_torneo} nos gustaría contar con su presencia.
```

Las plantillas aparecen cuando hay un torneo seleccionado (en "Inscritos en torneo" o en "Usuarios de mi club" con torneo para plantillas).

Ejemplo de mensaje personalizado:
```
Hola {nombre}, te informamos que hay novedades en el torneo {torneo}. 
Revisa tu inscripción en el sistema. - {club}
```

## Flujo de uso

1. Acceder a **Notificaciones**.
2. Seleccionar tipo de destinatarios (club, torneo o afiliación).
3. Si aplica, seleccionar el torneo.
4. Clic en **Actualizar**.
5. Escribir el mensaje.
6. Seleccionar el canal (WhatsApp, Email o Telegram).
7. Clic en **Enviar Notificaciones**.
8. Revisar el resultado (enviados, omitidos, errores).

## Migración de base de datos

Antes de usar Telegram, ejecutar:

```bash
mysql -u usuario -p nombre_bd < sql/add_notificaciones_fields.sql
```

O desde phpMyAdmin: importar el archivo `sql/add_notificaciones_fields.sql`.
