# Instructivo: Configuración y Uso de Notificaciones por Email

## Descripción

El canal Email envía mensajes en lote de forma automática usando PHPMailer y un servidor SMTP configurado.

## Requisitos

- Servidor SMTP configurado (Gmail, Outlook, etc.).
- Los destinatarios deben tener **email** válido registrado.
- PHPMailer instalado (vía Composer).

## Configuración

### 1. Variables de entorno (.env)

Agregar o verificar en el archivo `.env`:

```
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_correo@gmail.com
MAIL_PASSWORD=contraseña_de_aplicacion
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME=La Estación del Dominó
```

### 2. Gmail

Si usa Gmail:

1. Activar **verificación en 2 pasos** en la cuenta de Google.
2. Ir a **Seguridad** > **Contraseñas de aplicaciones**.
3. Generar una contraseña de aplicación para "Correo" o "Otro".
4. Usar esa contraseña en `MAIL_PASSWORD` (no la contraseña normal).

### 3. Otros proveedores

| Proveedor | MAIL_HOST | MAIL_PORT |
|-----------|-----------|-----------|
| Gmail | smtp.gmail.com | 587 |
| Outlook | smtp.office365.com | 587 |
| Yahoo | smtp.mail.yahoo.com | 587 |
| Servidor propio | smtp.tudominio.com | 587 o 465 |

### 4. Verificar PHPMailer

Ejecutar en la raíz del proyecto:

```
composer require phpmailer/phpmailer
```

## Uso

### Paso 1: Acceder a Notificaciones

1. Iniciar sesión como **admin_club**, **admin_torneo** o **admin_general**.
2. En el menú lateral, hacer clic en **Notificaciones**.

### Paso 2: Seleccionar destinatarios

1. Elegir el tipo de destinatarios (club, torneo o afiliación).
2. Si aplica, seleccionar el torneo.
3. Hacer clic en **Actualizar**.

### Paso 3: Escribir el mensaje

1. Escribir el mensaje en el cuadro de texto.
2. Usar variables: `{nombre}`, `{torneo}`, `{club}`.

### Paso 4: Enviar

1. Seleccionar el canal **Email**.
2. Hacer clic en **Enviar Notificaciones**.
3. El sistema enviará los correos automáticamente.
4. Se mostrará el resultado: enviados, errores y omitidos (sin email).

## Solución de problemas

### "PHPMailer no disponible"

- Ejecutar: `composer require phpmailer/phpmailer`
- Verificar que Composer esté instalado.

### "Error de conexión SMTP"

- Revisar `MAIL_HOST` y `MAIL_PORT`.
- Verificar que el firewall permita conexiones salientes al puerto 587 o 465.
- En Gmail, usar contraseña de aplicación, no la contraseña normal.

### Emails no llegan

- Revisar carpeta de spam.
- Verificar que `MAIL_FROM_ADDRESS` coincida con el dominio del servidor.
- Revisar logs en `logs/email_debug.log` (si está habilitado).
