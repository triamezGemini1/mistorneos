# Instructivo: Configuración y Uso de Notificaciones por Telegram

## Descripción

El canal Telegram envía mensajes en lote de forma automática usando la Bot API. Los usuarios deben vincular su cuenta con Telegram proporcionando su chat_id.

## Requisitos

- Bot de Telegram creado con @BotFather.
- Token del bot configurado en el servidor.
- Los destinatarios deben tener **telegram_chat_id** registrado en su perfil.

## Configuración

### 1. Crear el bot en Telegram

1. Abrir Telegram y buscar **@BotFather**.
2. Enviar el comando `/newbot`.
3. Seguir las instrucciones:
   - Nombre del bot (ej: "Notificaciones LED").
   - Nombre de usuario (debe terminar en "bot", ej: "NotificacionesLED_bot").
4. BotFather devolverá un **token** (ej: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`).
5. Guardar el token de forma segura.

### 2. Configurar el token en el servidor

Agregar en el archivo `.env`:

```
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
```

Reemplazar por el token real recibido de BotFather.

### 3. Vincular usuarios con Telegram

Cada usuario debe obtener su **chat_id** y registrarlo en su perfil:

#### Opción A: Usando @userinfobot

1. **Importante**: El usuario debe primero abrir el bot del sistema en Telegram y enviar `/start`. Sin esto, el sistema no podrá enviarle mensajes.
2. Luego, el usuario abre Telegram y busca **@userinfobot**.
3. Inicia conversación con @userinfobot (enviar cualquier mensaje).
4. El bot responde con el **Id** (es el chat_id).
5. El usuario copia ese número.
6. En el sistema: **Mi Perfil** > campo **Telegram Chat ID** > pegar el número > Guardar.

#### Opción B: El administrador lo ingresa

1. El administrador va a **Usuarios**.
2. Edita el usuario.
3. Si el formulario incluye el campo, ingresa el telegram_chat_id.
4. Guarda.

### 4. Migración de base de datos

Ejecutar la migración para agregar la columna `telegram_chat_id`:

```sql
-- Desde MySQL o phpMyAdmin
SOURCE sql/add_notificaciones_fields.sql;
```

O ejecutar manualmente:

```sql
ALTER TABLE usuarios ADD COLUMN telegram_chat_id VARCHAR(50) NULL 
COMMENT 'Chat ID de Telegram para notificaciones' AFTER celular;
```

## Uso

### Paso 1: Acceder a Notificaciones

1. Iniciar sesión como **admin_club**, **admin_torneo** o **admin_general**.
2. En el menú lateral, hacer clic en **Notificaciones**.

### Paso 2: Seleccionar destinatarios

1. Elegir el tipo de destinatarios.
2. Hacer clic en **Actualizar**.
3. Revisar la columna **Telegram**: solo los usuarios con chat_id vinculado pueden recibir.

### Paso 3: Escribir el mensaje

1. Escribir el mensaje.
2. Usar variables: `{nombre}`, `{torneo}`, `{club}`.

### Paso 4: Enviar

1. Seleccionar el canal **Telegram**.
2. Hacer clic en **Enviar Notificaciones**.
3. El sistema enviará los mensajes automáticamente a quienes tengan chat_id.
4. Los usuarios sin chat_id aparecerán como "omitidos".

## Notas importantes

- El **chat_id** es permanente: no cambia si el usuario reinstala la app o usa otro dispositivo.
- **Requisito obligatorio**: El usuario debe haber enviado `/start` al bot del sistema en Telegram antes de poder recibir mensajes. Telegram no permite que los bots inicien conversaciones; el usuario debe contactar al bot primero.

## Solución de problemas

### "Telegram no aparece como opción"

- Verificar que `TELEGRAM_BOT_TOKEN` esté definido en `.env`.
- Reiniciar el servidor web o PHP-FPM después de cambiar `.env`.

### "Error al enviar por Telegram"

- Verificar que el token sea correcto.
- El usuario debe haber enviado `/start` al bot del sistema.
- Revisar que el chat_id sea numérico y correcto.

### Usuarios omitidos

- Los usuarios sin `telegram_chat_id` en la base de datos no recibirán mensajes.
- Indicar a los usuarios que completen el campo en **Mi Perfil**.
