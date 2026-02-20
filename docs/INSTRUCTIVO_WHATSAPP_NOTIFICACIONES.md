# Instructivo: Configuración y Uso de Notificaciones por WhatsApp

## Descripción

El canal WhatsApp permite enviar mensajes a usuarios mediante enlaces wa.me. El administrador hace clic en cada enlace para abrir WhatsApp con el mensaje prellenado y lo envía manualmente.

## Requisitos

- Los destinatarios deben tener **celular** registrado en su perfil o en la base de datos.
- El celular debe incluir código de país (ej: Venezuela 58).
- No se requiere configuración adicional en el servidor.

## Configuración

### 1. Datos de contacto de usuarios

Para que los usuarios reciban notificaciones por WhatsApp:

1. Los usuarios deben tener el campo **celular** completado en su perfil.
2. El administrador puede editar usuarios desde **Usuarios** y agregar el celular.
3. Los usuarios pueden actualizar su celular desde **Mi Perfil** (si el formulario lo permite).

### 2. Formato del número

- Incluir código de país: Venezuela = 58 (ej: 584241234567).
- Sin espacios ni guiones.
- Si el número empieza con 0, se elimina automáticamente.

## Uso

### Paso 1: Acceder a Notificaciones

1. Iniciar sesión como **admin_club**, **admin_torneo** o **admin_general**.
2. En el menú lateral, hacer clic en **Notificaciones**.

### Paso 2: Seleccionar destinatarios

1. Elegir el tipo:
   - **Usuarios de mi club**: Todos los usuarios registrados en los clubes que gestiona.
   - **Inscritos en torneo**: Usuarios inscritos en un torneo específico.
   - **Solicitantes de afiliación** (solo admin_general): Personas con solicitud pendiente.

2. Si eligió "Inscritos en torneo", seleccionar el torneo.

3. Hacer clic en **Actualizar**.

### Paso 3: Escribir el mensaje

1. En el cuadro de texto, escribir el mensaje.
2. Variables disponibles:
   - `{nombre}`: Nombre del destinatario.
   - `{torneo}`: Nombre del torneo (si aplica).
   - `{club}`: Nombre del club.

Ejemplo:
```
Hola {nombre}, te informamos que hay novedades en el torneo {torneo}. 
Revisa tu inscripción en el sistema.
```

### Paso 4: Enviar

1. Seleccionar el canal **WhatsApp**.
2. Hacer clic en **Enviar Notificaciones**.
3. Se mostrará una lista de botones, uno por cada destinatario con celular.
4. Hacer clic en cada botón para abrir WhatsApp Web o la app con el mensaje listo.
5. Enviar manualmente cada mensaje desde WhatsApp.

## Limitaciones

- Envío manual: debe hacer clic en cada enlace y enviar el mensaje uno por uno.
- Solo llega a usuarios con celular registrado.
- No hay historial de envío automático.
