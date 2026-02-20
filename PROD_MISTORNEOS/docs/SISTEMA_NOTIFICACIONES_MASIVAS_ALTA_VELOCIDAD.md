# Sistema de notificaciones masivas de alta velocidad (Telegram + Campanita Web)

Este documento describe el sistema implementado y el **procedimiento paso a paso** para ponerlo en marcha.

---

## 1. Resumen del sistema

- **Cola en BD**: Los mensajes se insertan en `notifications_queue` (bulk insert). El administrador no espera al envío.
- **Telegram**: Un script/cron procesa la cola cada 1–2 minutos y envía por Telegram (hasta ~30 msg/s para no superar límites).
- **Campanita web**: Cada usuario con sesión en el dashboard ve un badge con el número de notificaciones web pendientes; al hacer clic puede verlas y se marcan como vistas.
- **Generar ronda**: Al publicar una ronda, se encolan automáticamente notificaciones para todos los inscritos usando la plantilla "Nueva Ronda" (web + Telegram si tienen cuenta vinculada).
- **Plantillas**: Mensajes preestablecidos editables en la tabla `plantillas_notificaciones` (variables: `{nombre}`, `{ronda}`, `{torneo}`). Las notificaciones a inscritos de un torneo se envían **desde el panel del torneo** (botón "Enviar Notificación").

---

## 2. Archivos principales

| Archivo | Función |
|--------|---------|
| `sql/create_notifications_queue.sql` | Crea la tabla `notifications_queue` y asegura `telegram_chat_id` en `usuarios`. |
| `sql/create_plantillas_notificaciones.sql` | Crea la tabla `plantillas_notificaciones` e inserta plantillas iniciales (nueva_ronda, resultados, recordatorio_pago). |
| `lib/NotificationManager.php` | Motor: bulk insert, plantillas (`obtenerPlantilla`, `procesarMensaje`, `listarPlantillas`), `programarRondaMasiva`, `programarMasivoPersonalizado`, `enviarTelegram`. |
| `public/procesar_envio.php` | Procesador de la cola Telegram (ejecutar por Cron o por HTTP con clave). |
| `public/notificaciones_ajax.php` | Devuelve el número de notificaciones web pendientes (para la campanita). |
| `public/includes/layout.php` | Incluye el icono de campanita y el JS que consulta cada 30 s. |
| `modules/user_notificaciones.php` | Página "Mis notificaciones": listar y marcar como vistas. |
| `modules/torneo_gestion.php` | Tras generar ronda, llama a `programarRondaMasiva` con plantilla `nueva_ronda`; acción `notificaciones` y `enviar_notificacion_torneo` para envío desde el panel. |
| `modules/gestion_torneos/notificaciones_torneo.php` | Formulario "Enviar Notificación" en el panel del torneo: selector de plantilla, ronda, vista previa, programar envío masivo. |
| `modules/notificaciones_masivas/send.php` | Para canal Telegram, encola mensajes (no envía en línea). |

---

## 3. Procedimiento paso a paso

### Paso 1: Base de datos

1. Ejecuta el SQL en tu base de datos (MySQL):

```bash
mysql -u USUARIO -p NOMBRE_BD < sql/create_notifications_queue.sql
```

O desde phpMyAdmin / cliente SQL: copia y ejecuta el contenido de `sql/create_notifications_queue.sql`.

- Crea la tabla `notifications_queue` (id, usuario_id, canal, mensaje, url_destino, estado, fecha_creacion).
- Añade la columna `telegram_chat_id` a `usuarios` si no existe.

---

### Paso 2: Bot de Telegram

1. En Telegram, abre [@BotFather](https://t.me/BotFather).
2. Crea un bot: `/newbot` y sigue las instrucciones.
3. Copia el **token** que te da (ej. `123456789:ABCdefGHI...`).
4. En tu proyecto, abre o crea el archivo `.env` y define:

```env
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHI...
```

5. (Opcional) Si quieres que el Cron llame por HTTP en lugar de por CLI, define una clave secreta:

```env
NOTIFICATIONS_CRON_KEY=una_clave_secreta_larga
```

---

### Paso 3: Webhook / vinculación de usuarios con Telegram

Para que los jugadores reciban mensajes por Telegram, cada uno debe tener su `telegram_chat_id` guardado en `usuarios`. Opciones:

- **Webhook**: Configura el webhook de tu bot (como en tu flujo actual) para que cuando un usuario inicie el bot o envíe un comando, tu backend guarde su `chat_id` en `usuarios.telegram_chat_id` para ese usuario (por ejemplo identificándolo por código o enlace).
- **Perfil**: En "Mi Perfil" (o módulo de usuario) ya puedes tener un apartado para "Vincular Telegram" que muestre un enlace/código; al usar el bot y enviar ese código, el backend actualiza `telegram_chat_id` del usuario.

Sin este paso, los usuarios solo recibirán la notificación en la **campanita web**, no por Telegram.

---

### Paso 4: Cron para procesar la cola de Telegram

El script `public/procesar_envio.php` debe ejecutarse cada 1–2 minutos.

**Opción A – Por CLI (recomendado en servidor):**

En cPanel → Cron Jobs (o crontab del servidor), añade:

```text
*/2 * * * * php /ruta/completa/a/mistorneos/public/procesar_envio.php
```

Sustituye `/ruta/completa/a/mistorneos` por la ruta real de tu proyecto.

**Opción B – Por HTTP:**

Si defines `NOTIFICATIONS_CRON_KEY` en `.env`, puedes llamar:

```text
https://tudominio.com/mistorneos/public/procesar_envio.php?key=una_clave_secreta_larga
```

En cPanel puedes crear un Cron que ejecute cada 2 minutos:

```text
*/2 * * * * curl -s "https://tudominio.com/mistorneos/public/procesar_envio.php?key=TU_CRON_SECRET"
```

No compartas la clave ni la pongas en el front.

---

### Paso 5: Comprobar que todo funciona

1. **Campanita**: Entra al dashboard con un usuario que tenga notificaciones web pendientes en `notifications_queue` (canal `web`, estado `pendiente`). Deberías ver el número en el icono de la campanita y que se actualiza cada ~30 s.
2. **Mis notificaciones**: Haz clic en la campanita; debe abrirse la página "Mis notificaciones", listar los mensajes y al cargar la página las pendientes pasan a vistas (ya no cuentan en el badge).
3. **Generar ronda**: En un torneo, genera una ronda. Tras el éxito, en la BD deberían aparecer filas en `notifications_queue` (canal `web` y `telegram` para quienes tengan `telegram_chat_id`). En los siguientes 1–2 minutos el Cron enviará los de Telegram.
4. **Notificaciones masivas**: En el módulo de notificaciones masivas, elige canal "Telegram", destinatarios y mensaje y envía. Debe mostrarse "Mensajes encolados" y en la cola las filas correspondientes; el Cron los enviará por Telegram y la campanita mostrará las web.

---

## 4. Flujo al "Generar Ronda"

1. El admin hace clic en "Generar Ronda" en el panel del torneo.
2. El sistema genera las mesas y guarda la ronda.
3. Si todo va bien, se obtienen todos los inscritos del torneo (con `id` y `telegram_chat_id`).
4. Se llama a `NotificationManager::programarRondaMasiva($jugadores, $nombreTorneo, $numeroRonda)`.
5. Se hace un **bulk insert** en `notifications_queue`: una fila `web` por jugador y una fila `telegram` por jugador que tenga `telegram_chat_id`.
6. La respuesta al admin es inmediata (no espera al envío).
7. El Cron ejecuta `procesar_envio.php`, que lee pendientes de canal `telegram`, envía por la API de Telegram (con pausa cada 30 mensajes) y actualiza el estado a `enviado` o `fallido`.
8. Cada jugador, al entrar al dashboard (o al portal, si añades la campanita allí), ve en la campanita el número de notificaciones web pendientes; al abrir "Mis notificaciones" las ve y se marcan como vistas.

---

## 5. Plantillas de notificaciones

Las notificaciones de torneos pueden enviarse usando **plantillas preestablecidas**: el administrador elige una opción (ej. "Aviso de Ronda", "Resultados Listos", "Recordatorio de Pago") y el sistema sustituye las variables por los datos reales de cada jugador.

### Base de datos: tabla de plantillas

Ejecuta además el SQL de plantillas (una sola vez):

```bash
mysql -u USUARIO -p NOMBRE_BD < sql/create_plantillas_notificaciones.sql
```

- Crea la tabla `plantillas_notificaciones` (id, nombre_clave, titulo_visual, cuerpo_mensaje, categoria).
- Inserta plantillas iniciales: **nueva_ronda**, **resultados**, **recordatorio_pago**.

**Variables en el cuerpo del mensaje:** `{nombre}`, `{ronda}`, `{torneo}`. Puedes editar los textos en la tabla para cambiar el tono o el contenido sin tocar código.

### Envío desde el módulo de administración del torneo

Las notificaciones a inscritos de un torneo se envían **desde el panel del torneo**, no desde el menú general de notificaciones masivas:

1. Entra al torneo (Mis Torneos → elige torneo → Panel).
2. En la columna de acciones rápidas, pulsa **"Enviar Notificación"** (botón verde/teal con icono de campanita).
3. Se abre la pantalla **Notificación a inscritos** (`action=notificaciones&torneo_id=X`).
4. **Selecciona una plantilla** en el desplegable (solo se listan plantillas de categoría "torneo").
5. Indica el **número de ronda** (se usa en la variable `{ronda}`; por defecto aparece la última ronda generada).
6. Revisa la **vista previa** del mensaje (ejemplo con "Juan Pérez").
7. Pulsa **"Programar envío masivo"**. Los mensajes se encolan (Telegram + campanita web) y el cron los envía en segundo plano.

Así se mantiene todo el flujo de notificaciones de torneo dentro del módulo de administración del torneo.

### Uso automático al generar ronda

Al hacer clic en **"Generar Ronda"** en el panel del torneo, el sistema encola notificaciones usando la plantilla **nueva_ronda** (si existe en la tabla). Cada jugador recibe un mensaje personalizado con su nombre, el número de ronda y el nombre del torneo. Si la plantilla no existe, se usa el mensaje fijo por defecto.

---

## 6. Notas adicionales

- **Límite Telegram**: El procesador envía en lotes de 30 y hace una pausa de ~1,1 s entre lotes para respetar límites de la API (~30 msg/s).
- **Campanita para jugadores (rol usuario)**: Por defecto, los usuarios con rol "usuario" son redirigidos a `user_portal.php`. La campanita está en el layout del dashboard (admin_club, admin_torneo, admin_general). Si quieres que los jugadores vean la campanita en su portal, hay que añadir el mismo icono, badge y script en `user_portal.php` y un enlace a una sección o página equivalente a "Mis notificaciones".
- **Token de Telegram**: No subas el token a repositorios públicos; mantenlo solo en `.env` y en variables de entorno del servidor.
- **Editar plantillas**: Para cambiar el texto de "Aviso de Nueva Ronda" o añadir nuevas plantillas, modifica la tabla `plantillas_notificaciones` (o crea un panel de administración que edite esa tabla). La categoría `torneo` es la que se muestra en el formulario "Enviar Notificación" del panel del torneo.

Si sigues estos pasos en orden, el sistema de notificaciones masivas de alta velocidad (Telegram + campanita web) quedará operativo y integrado con "Generar Ronda", con las plantillas y con el envío desde el módulo de administración del torneo.
