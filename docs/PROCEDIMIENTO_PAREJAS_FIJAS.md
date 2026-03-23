# Procedimiento: Torneo de Parejas Fijas

Documento de diseño para la modalidad **Parejas Fijas** (modalidad = 4). Inscripción en pares con nombre y código de equipo; organización de rondas por número (consecutivo por club) y tipo de torneo (interclubes / suizo / suizo puro).

---

## 1. Resumen

| Aspecto | Regla |
|--------|--------|
| **Modalidad** | 4 = Parejas fijas (igual que equipos pero 2 jugadores por “equipo”) |
| **Inscripción** | En pares: nombre de equipo + código de equipo (mismo formato que equipos). Campo `numero` = consecutivo por club (1 a N). No se permiten inscripciones incompletas. |
| **Ronda 1** | Clasificación por `numero`; emparejar al azar: 1 con 1, 2 con 2, etc. Aleatorio por código de club (no en bloque por club). |
| **Rondas 2+** | Según tipo de torneo: **interclubes** (evitar mismo club cuando sea posible), **suizo** (no repetir parejas), **suizo puro** (todos por rendimiento). Siempre mejores en primeros lugares. |

---

## 2. Reutilización de tablas y servicios

- **Tabla `equipos`**: se reutiliza. Cada “pareja” es un equipo de 2 jugadores (mismo formato `codigo_equipo`, `nombre_equipo`, `consecutivo_club`).
- **Tabla `inscritos`**: `codigo_equipo`, `numero` (= consecutivo por club, 1..N). Cada pareja = 2 filas en `inscritos` con el mismo `codigo_equipo`.
- **Tabla `partiresul`**: 4 jugadores por mesa (2 parejas × 2 jugadores). Secuencias 1–2 = pareja A, 3–4 = pareja B. Sin cambios de estructura.
- **EquiposHelper**: no se usa tal cual (está pensado para 4 jugadores). Se usa **ParejasFijasHelper** para crear parejas y validar que estén completas (2 jugadores).

---

## 3. Formato código de equipo (igual que equipos)

- `codigo_equipo` = `LPAD(id_club, 3, '0') . '-' . LPAD(consecutivo_club, 3, '0')`  
  Ejemplo: club 5, consecutivo 2 → `"005-002"`.
- `numero` en inscritos = consecutivo por club (1 a N) para esa pareja; coincide con `consecutivo_club` del equipo.

---

## 4. Inscripción

- Solo inscripciones completas: una pareja = 2 jugadores + nombre de equipo + código generado.
- Validación: no guardar ni “medio equipo” ni parejas con un solo jugador.
- Flujo análogo a “gestionar inscripciones equipos”: crear equipo (pareja), asignar 2 jugadores, asignar `numero` por club.
- Archivos: **lib/ParejasFijasHelper.php** (lógica), vistas en **modules/gestion_torneos/** (HTML separado de la lógica).

---

## 5. Ronda 1

1. Obtener todas las parejas (agrupando por `codigo_equipo`), con su `numero` (consecutivo por club).
2. Clasificar parejas por `numero` (los 1, los 2, los 3, …).
3. Dentro de cada grupo de mismo `numero`, ordenar/emparejar **al azar** (por código de club u otro criterio aleatorio), **no** en bloque por club: mezclar clubes.
4. Asignar mesas: cada mesa = 2 parejas = 4 jugadores. Secuencias 1–2 para una pareja, 3–4 para la otra.
5. Implementación: **config/MesaAsignacionParejasFijasService.php** (método para ronda 1).

---

## 6. Rondas 2 en adelante (según tipo de torneo)

- **Interclubes**: En lo posible, evitar que dos parejas del mismo club se enfrenten. Emparejar por rendimiento respetando esa restricción.
- **Suizo (sin repetir parejas)**: Evitar que una pareja enfrente dos veces a la misma pareja. Emparejar por puntuación/rendimiento.
- **Suizo puro**: Emparejar estrictamente por rendimiento (mejores con mejores). Siempre mejores en primeros lugares.

El tipo de torneo (interclubes / suizo / suizo puro) puede venir de un campo en `tournaments` (por ejemplo `tipo_ronda` o reutilizar `pareclub`/campo existente). Definir en BD y leer en el servicio.

---

## 7. Archivos de referencia

| Concepto | Archivo |
|----------|---------|
| Lógica parejas (crear, código, numero, validar completas) | **lib/ParejasFijasHelper.php** |
| Asignación rondas parejas fijas | **config/MesaAsignacionParejasFijasService.php** |
| Generar ronda (elegir servicio por modalidad) | **modules/torneo_gestion.php** → `generarRonda()` |
| Inscripción equipos (referencia UI/flujo) | **modules/gestion_torneos/gestionar_inscripciones_equipos.php** |
| Crear equipo / código (referencia formato) | **lib/EquiposHelper.php** |

---

## 8. Integración en torneo_gestion

- Si `modalidad === 4`: usar **MesaAsignacionParejasFijasService** en `generarRonda()`.
- Panel e inscripción: mostrar opción “Parejas fijas” cuando `modalidad == 4`; enlace a gestionar inscripciones de parejas (vista específica que use ParejasFijasHelper).
- No modificar validaciones de token ni de usuario existentes.
