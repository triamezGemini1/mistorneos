# Procedimiento de sanciones en torneos

Documento que describe la lógica y el flujo para cada tipo de sanción/disciplina en el sistema.

---

## Resumen por tipo

| Tipo | Afecta efectividad / ganado-perdido | Dónde se define | Guardado |
|------|-------------------------------------|------------------|----------|
| **Sanción 40 pts** | Sí resta 40 del resultado1. **Sin tarjeta** amarilla | `SancionesHelper::procesar()` | partiresul.sancion=40, tarjeta=0 |
| **Sanción 80 pts** | Sí resta 80 del resultado1; tarjeta: si inscritos.tarjeta > 0 → siguiente (Roja/Negra), si no → Amarilla | `SancionesHelper::procesar()` | partiresul.sancion=80, tarjeta según acumulación |
| **Tarjeta directa** | Se marca en el checkbox correspondiente y se aplica el procedimiento establecido | `SancionesHelper::procesar()` + formulario | partiresul.tarjeta, partiresul.sancion |
| **Forfait (ff=1)** | Se marca el checkbox del jugador; aplican procedimientos establecidos (pierde partida; oponente 50% ef.) | `calcularEfectividadForfait()` en torneo_gestion | partiresul.ff=1, resultado1/2, efectividad |
| **Tarjeta grave** (Roja 3 / Negra 4) en mesa | Infractor: 0 pts, -puntosTorneo ef.; otro: 100% | `calcularEfectividadTarjetaGrave()` | partiresul.tarjeta, resultado1/2, efectividad |
| **Chancleta / Zapato** | Solo indicadores; **no accionan** ningún procedimiento sancionatorio | Solo contadores | partiresul.chancleta, partiresul.zapato → inscritos |

---

## 1. Sanción por puntos (40 y 80) y tarjetas – `SancionesHelper`

**Archivo:** `lib/SancionesHelper.php`

### Códigos de tarjeta

- `0` = Sin tarjeta  
- `1` = Amarilla  
- `3` = Roja  
- `4` = Negra  

### Constantes

- **40 pts** → Resta 40 del resultado1 del jugador sancionado. **Sin tarjeta** amarilla.  
- **80 pts** → Resta 80 del resultado1; se asigna tarjeta amarilla directamente; si en inscritos tarjeta > 0 se suma/escala (siguiente tarjeta) y se transfiere al checkbox correspondiente.

### Procedimiento `SancionesHelper::procesar($sancion, $tarjetaForm, $tarjetaInscritos)`

1. **Sanción = 40**
   - Tarjeta a guardar: **Ninguna** (0). Sin tarjeta amarilla.
   - `sancion_para_calculo` = **40** (se restan 40 del resultado1).
   - `sancion_guardar` = 40 (para registro en `partiresul.sancion`).

2. **Sanción ≥ 80**
   - `sancion_guardar` = 80, `sancion_para_calculo` = 80 (sí se restan 80 pts).
   - Tarjeta:
     - Si **no** tiene tarjeta previa → **Amarilla** (1).
     - Si ya tiene Amarilla → **Roja** (3).
     - Si ya tiene Roja → **Negra** (4).
     - Negra se mantiene.
   - Tarjeta “previa” se obtiene de partidas **anteriores** (`getTarjetaPreviaDesdePartidasAnteriores`) para no doble-escalar al re-editar la misma mesa.

3. **Tarjeta directa en formulario (Amarilla, sin 80)**
   - Si el form envía Amarilla (1): se aplica acumulación (si ya tenía → siguiente; si no → Amarilla).
   - `sancion_para_calculo` y `sancion_guardar` = valor de sanción enviado (puede restar puntos si lo hay).

4. **Tarjeta Roja o Negra directa en formulario**
   - Se guarda tal cual; no se cambia por acumulación.

**Dónde se usa**

- **Aprobar acta (verificar acta):** `torneo_gestion.php` → `verificarActaAprobar()`: solo se pasa `sancion_input`; tarjeta se deriva con `SancionesHelper::procesar($sancion_input, 0, $tarjeta_inscritos)`.
- **Envío de resultado desde app/jugador:** `actions/public_score_submit.php`: se llama `SancionesHelper::procesar($sancion_input, $tarjeta_form, $tarjeta_inscritos)` con sanción y tarjeta del formulario.

**Nota:** En el **ingreso manual de resultados** (`guardarResultados()` en `torneo_gestion.php`) se llama a `SancionesHelper::procesar()` con la sanción y tarjeta del formulario y la tarjeta previa desde partidas anteriores; se usa `sancion_para_calculo` para el ajuste de resultado1 y efectividad, y `tarjeta`/`sancion_guardar` para guardar en partiresul. Al leer el registro de la mesa a transcribir se traen de inscritos los registros de la mesa que tengan tarjetas aplicadas para verificar la acción a tomar.

---

## 2. Uso de la sanción en el cálculo de efectividad y ganado/perdido

### 2.1 Efectividad cuando hay sanción de puntos (sin forfait ni tarjeta grave)

- **Ajuste:** se resta la sanción del resultado1 de la pareja infractora: `resultado1Ajustado = max(0, resultado1 - sancion)`.
- **Ganado/Perdido:** se compara el monto resultante con **resultado2** (puntos de la pareja contraria).
  - **Perdido:** si `resultado1Ajustado <= resultado2`.
  - **Ganado:** si `resultado1Ajustado > resultado2`.
- **Efectividad:** función `evaluarSancionIndividual($resultado1, $resultado2, $sancion, $puntosTorneo)` en `torneo_gestion.php`:
  - Calcula `resultadoAjustado`, asigna ganado o perdido según la comparación con resultado2, y devuelve la efectividad usando `calcularEfectividadAlcanzo` / `calcularEfectividadNoAlcanzo`.

En **estadísticas** (`InscritosPartiresulHelper`), ganado/perdido con sanción se calcula así (comparando con resultado2 = puntos de la pareja contraria):

- Ganado: `(sancion = 0 AND resultado1 > resultado2) OR (sancion > 0 AND (resultado1 - sancion) > resultado2)`.
- Perdido: `(sancion = 0 AND resultado1 < resultado2) OR (sancion > 0 AND (resultado1 - sancion) <= resultado2)`.

---

## 3. Forfait (`ff = 1`)

**Función:** `calcularEfectividadForfait($tieneForfait, $puntosTorneo)` en `torneo_gestion.php`.

### Procedimiento

1. **Si el jugador tiene forfait (ff = 1):**
   - Se considera **pierde** la partida.
   - `resultado1` = 0, `resultado2` = puntos del torneo.
   - `efectividad` = **-puntos del torneo**.

2. **Si el jugador no tiene forfait (gana por inasistencia del otro):**
   - `resultado1` = puntos del torneo, `resultado2` = 0.
   - `efectividad` = **50%** del puntaje del torneo (no 100%).

Cuando en una mesa hay al menos un jugador con `ff = 1`, para **todos** los jugadores de esa mesa se usa esta lógica de forfait y no la de sanción de puntos ni la de tarjeta grave.

---

## 4. Tarjeta grave (Roja 3 / Negra 4) en la mesa

**Función:** `calcularEfectividadTarjetaGrave($tieneTarjetaGrave, $puntosTorneo)` en `torneo_gestion.php`.

### Procedimiento

1. **Jugador con tarjeta grave (infractor):**
   - `resultado1` = 0, `resultado2` = puntos del torneo.
   - `efectividad` = **-puntos del torneo**.

2. **Jugador sin tarjeta grave (contrario):**
   - `resultado1` = puntos del torneo, `resultado2` = 0.
   - `efectividad` = **puntos del torneo** (100% de efectividad; distinto al forfait que da 50%).

Cuando en la mesa hay al menos un jugador con tarjeta 3 o 4, para **todos** los de esa mesa se usa esta lógica y no la normal ni la de forfait.

---

## 5. Orden de prioridad al guardar resultados de una mesa

En `guardarResultados()` (`torneo_gestion.php`):

1. **Forfait:** si algún jugador tiene `ff = 1` → se aplica `calcularEfectividadForfait()` a cada uno.
2. **Tarjeta grave:** si no hay forfait pero algún jugador tiene tarjeta 3 o 4 → se aplica `calcularEfectividadTarjetaGrave()` a cada uno.
3. **Resto:** efectividad normal; si un jugador tiene `sancion > 0` se usa `evaluarSancionIndividual()` (resultado ajustado vs oponente); si no, `calcularEfectividad()`.

---

## 6. Chancleta y Zapato

- **partiresul:** columnas `chancleta` y `zapato` (valores numéricos por partida).
- **inscritos:** se actualizan `chancletas` y `zapatos` como **suma** de los valores de `partiresul` para ese jugador en el torneo (`InscritosPartiresulHelper` y `actualizarEstadisticasInscritos`).
- **Solo son indicadores;** no accionan ningún tipo de procedimiento sancionatorio; no intervienen en efectividad ni en ganado/perdido.

---

## 7. Actualización de estadísticas (inscritos)

Tras guardar resultados o aprobar actas se llama `actualizarEstadisticasInscritos($torneo_id)` (o equivalente que agregue desde `partiresul`). Ahí:

- Ganados/perdidos se calculan desde `partiresul` (incluyendo la regla con `resultado1 - sancion` vs oponente).
- Efectividad, puntos, `sancion`, `chancletas`, `zapatos`, `tarjeta` se agregan por jugador y se escriben en `inscritos`.

La tarjeta que queda en `inscritos` es la agregada desde `partiresul` (suma de valores por partida); para **acumulación** en la siguiente partida se usa la tarjeta **previa** desde partidas anteriores (`SancionesHelper::getTarjetaPreviaDesdePartidasAnteriores`) o el valor actual en `inscritos`, según el flujo (verificar acta vs ingreso manual).

---

## Referencia rápida de archivos

| Concepto | Archivo |
|----------|---------|
| Reglas 40/80 y tarjetas | `lib/SancionesHelper.php` |
| Cálculo efectividad forfait / tarjeta grave / sanción | `modules/torneo_gestion.php` (funciones `calcularEfectividad*`, `evaluarSancionIndividual`) |
| Guardar resultados (ingreso manual) | `modules/torneo_gestion.php` → `guardarResultados()` |
| Aprobar acta (usa SancionesHelper) | `modules/torneo_gestion.php` → `verificarActaAprobar()` |
| Envío resultado público | `actions/public_score_submit.php` |
| Ganados/perdidos y totales con sanción | `lib/InscritosPartiresulHelper.php` |
| Agregación partiresul → inscritos | `actualizarEstadisticasInscritos()` en `torneo_gestion.php` + lógica en `InscritosPartiresulHelper` |
