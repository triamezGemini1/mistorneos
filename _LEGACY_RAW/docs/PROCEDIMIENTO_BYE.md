# Procedimiento con los BYE (según evaluación del código)

## 1. Qué es un BYE

Cuando el número de inscritos confirmados **no es múltiplo de 4**, sobra entre **1 y 3 jugadores** por ronda. Esos jugadores no pueden formar mesa completa y se les asigna **BYE**: partida “ganada” sin rival, registrada en `partiresul` con **`mesa = 0`**.

- **Mesas reales:** `mesa > 0` (4 jugadores por mesa).
- **BYE:** `mesa = 0`, un registro por jugador por ronda.

---

## 2. Normas en código (`MesaAsignacionService.php`)

- **Máximo 3 BYE por ronda** (resto de `inscritos / 4`: 0, 1, 2 o 3).
- **Máximo 2 BYE por jugador en todo el torneo.** Quien ya tiene 2 BYE va obligatoriamente a **última mesa** en la siguiente ronda (ya no se le asigna BYE).
- **Ronda 1:** Los sobrantes tras la dispersión por clubes se asignan como BYE (en la práctica, los últimos de esa lista).
- **Rondas 2 en adelante:** El BYE se asigna entre los **últimos clasificados**, con prioridad a **no repetir BYE** (preferir 0 BYE, luego 1; evitar dar el 2.º BYE si hay alternativa).

---

## 3. Cuándo se aplican los BYE

Al **generar cada ronda** (`generarRonda()` → `MesaAsignacionService::generarAsignacionRonda()`):

1. Se obtiene la lista de inscritos confirmados y la clasificación (o criterio de la ronda).
2. Se calcula `numMesas = floor(total / 4)` y `numBye = total - (numMesas * 4)` (0, 1, 2 o 3).
3. Se asignan jugadores a mesas según la lógica de esa ronda (dispersión, líderes, suizo, etc.).
4. Los que **no entran en ninguna mesa** son los `jugadoresBye`.
5. Se llama a **`aplicarBye($torneoId, $ronda, $jugadoresBye)`**.

No hay un paso manual previo: el BYE se aplica **solo** en el momento de generar la ronda.

---

## 4. Qué hace `aplicarBye()` (registro en BD)

1. **Limpieza:** `DELETE FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = 0` (borra BYE previos de esa ronda si existían).
2. **Inserción:** Para cada jugador en `$jugadoresBye`, inserta una fila en `partiresul`:
   - `id_torneo`, `id_usuario`, `partida` = ronda, **`mesa = 0`**, `secuencia = 1`, `registrado = 0` inicialmente.
3. **Regla BYE (UPDATE):** Sobre todas las filas de esa ronda con `mesa = 0`:
   - `resultado1 = puntos_torneo` (100 % del puntaje del torneo).
   - `resultado2 = 0`.
   - `efectividad = 50 %` del puntaje del torneo (p. ej. 100 si el torneo es a 200).
   - `registrado = 1`.

Así, el BYE cuenta como **partida ganada** (`resultado1 > resultado2`) y entra en las estadísticas (ganados, efectividad, puntos) cuando se ejecuta **`actualizarEstadisticasInscritos()`**.

---

## 5. Ronda 1 vs ronda 2 en adelante

- **Ronda 1:** Orden por club y `id_usuario` (dispersión). Los últimos de esa lista que “sobran” son BYE. No se mira historial previo (no hay).
- **Ronda 2:** Se usa clasificación específica de “ronda 2” (ganadores r1 sin BYE, ganadores r1 con BYE, perdedores r1). Los sobrantes de la asignación 1-5-3-7 son BYE. Si en ronda 1 ya hubo BYE y no hay retirados, se reordena para que **los mismos jugadores de BYE en r1** queden al final de la lista y vuelvan a recibir BYE en r2 (misma política que en el código: `reordenarConByeR1AlFinal`).
- **Rondas 3 a N-1:** Clasificación actual; BYE = últimos clasificados con prioridad a **menos BYE ya asignados** (`obtenerConteoByePorJugador`, `reordenarParaLimitarBye`). Quien ya tiene 2 BYE no recibe más y va a la última mesa.
- **Última ronda:** Mismo criterio de sobrantes; BYE con la misma regla de puntos y efectividad.

---

## 6. Actualización de estadísticas

- **No** se actualizan estadísticas de inscritos dentro de `aplicarBye()` (evita N+1).
- Las estadísticas se recalculan:
  - **Antes de generar la siguiente ronda:** `generarRonda()` llama a `actualizarEstadisticasInscritos($torneo_id)`.
  - **Al guardar resultados** de mesas.
  - **Desde el panel:** botón “Actualizar estadísticas” → `actualizarEstadisticasManual()` → `actualizarEstadisticasInscritos()`.

En `actualizarEstadisticasInscritos()` se agrega **todo** `partiresul` con `registrado = 1`, incluidas filas con **`mesa = 0`** (BYE). Por tanto, los BYE ya aplicados se cuentan correctamente en ganados, efectividad y puntos.

---

## 7. Recalcular BYE (opcional)

En **PROD_MISTORNEOS** existe la acción **`recalcular_bye`** y la función **`recalcularBye($torneo_id, ...)`**: recorre todas las filas del torneo con `mesa = 0` y `registrado = 1`, y les vuelve a aplicar la regla (resultado1 = puntos_torneo, resultado2 = 0, efectividad = 50 %). Sirve para **corregir** BYE si en su momento el puntaje del torneo era distinto o hubo un error. En el código principal (`mistorneos`) puede no estar expuesta esta acción; la lógica de asignación y aplicación del BYE es la descrita arriba.

---

## 8. Resumen en pasos (operativo)

| Paso | Acción |
|------|--------|
| 1 | Inscritos confirmados listos; se va a “Generar ronda” (siguiente ronda). |
| 2 | El sistema calcula mesas y sobrantes (0–3 BYE). |
| 3 | Asigna mesas según la regla de la ronda; los no asignados son BYE. |
| 4 | `aplicarBye()` escribe en `partiresul` con `mesa = 0` y aplica 100 % puntos, 50 % efectividad, `registrado = 1`. |
| 5 | Las estadísticas (incluidos BYE) se actualizan al generar la siguiente ronda o al guardar resultados / actualizar estadísticas manual. |

No hay procedimiento manual previo para “marcar” BYE: todo se deriva de **generar la ronda** con el servicio de asignación de mesas.
