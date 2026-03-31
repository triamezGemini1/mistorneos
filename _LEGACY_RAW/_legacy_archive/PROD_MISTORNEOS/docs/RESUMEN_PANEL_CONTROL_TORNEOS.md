# Resumen del Panel de Control de Torneos

**Documento de análisis: funcionalidad, rendimiento y posibles mejoras**

---

## 1. Contexto General

El sistema cuenta con **dos interfaces** para gestionar torneos:

| Módulo | Acceso | Descripción |
|--------|--------|-------------|
| **tournament_admin** | `index.php?page=tournament_admin&torneo_id=X` | Panel simplificado con menú lateral |
| **torneo_gestion** | `index.php?page=torneo_gestion` / `panel_torneo.php` | Gestión completa con cuadrícula, hojas de anotación, MesaAsignacionService |

El **tournament_admin** es el panel principal que se accede desde la lista de torneos ("Panel de Control"). Algunas acciones (hojas_anotacion, tabla_asignacion, cuadrícula) **no tienen archivo propio** en `tournament_admin/` y caen al dashboard por defecto; la lógica real está en `gestion_torneos/` y `torneo_gestion.php`.

---

## 2. Inscripciones en Sitio y Admin de Inscripciones

### 2.1 Inscribir en Sitio (`inscribir_sitio.php`)

| Aspecto | Descripción |
|---------|-------------|
| **Problema que resuelve** | Permitir inscribir jugadores el día del torneo cuando no se inscribieron en línea |
| **Cómo funciona** | Lista de usuarios del territorio del administrador (entidad/club); se selecciona jugador, club y se confirma. Inserta en `inscritos` con `estatus = 'confirmado'` |
| **Bloqueo** | Se bloquea cuando el torneo ya inició (equipos: 1 ronda; individual/parejas: 2 rondas) |
| **Permisos** | Admin general: todos los usuarios. Admin club/torneo: usuarios de su entidad |
| **Dependencias** | Tabla `inscritos`, `usuarios`, `clubes`, ClubHelper (clubes supervisados) |

**Evaluación:**
- **Funcionalidad:** ✅ Cumple con inscribir en sitio
- **Rendimiento:** Consultas múltiples; podría optimizarse con una sola query de usuarios disponibles
- **Mejoras:** Búsqueda por cédula/nombre, validación de duplicados más clara, soporte para modalidad equipos (inscripción por equipo)

### 2.2 Revisar Inscripciones (`revisar_inscripciones.php`)

| Aspecto | Descripción |
|---------|-------------|
| **Problema que resuelve** | Ver y gestionar la lista de inscritos del torneo antes/durante el evento |
| **Cómo funciona** | Muestra inscripciones agrupadas por club, con datos de jugador, estatus (confirmado, retirado, etc.), delegado |
| **Permisos** | Admin general: todos. Admin club: su club y clubes supervisados. Admin torneo: solo su club |
| **Acciones** | Visualización; el cambio de estatus (confirmar/retirar) se hace desde otro módulo (inscripciones en gestion_torneos) |

**Evaluación:**
- **Funcionalidad:** ✅ Listado correcto
- **Mejoras:** Falta integración con acciones de cambiar estatus (confirmar/retirar) desde esta misma vista; unificar con `gestion_torneos/inscripciones.php`

---

## 3. Generación de Rondas

### 3.1 Generar Rondas (`tournament_admin/generar_rondas.php`)

| Aspecto | Descripción |
|---------|-------------|
| **Problema que resuelve** | Crear las mesas de una ronda a partir de los inscritos confirmados |
| **Algoritmo actual** | **Simple**: distribuye inscritos en N mesas de forma secuencial (`ceil(total/mesas)` por mesa). No usa Swiss ni restricciones de compañeros/oponentes |
| **Fuente** | Solo inscritos con `estatus = 'confirmado'` |
| **Salida** | Inserciones en `partiresul` (partida, mesa, secuencia, id_usuario) con resultado en 0 |

**Limitaciones importantes:**
- **No usa MesaAsignacionService** (lógica avanzada de primera ronda, segunda ronda, rondas intermedias, BYE, separación de líderes)
- La lógica completa está en `torneo_gestion.php` + `MesaAsignacionService.php` / `MesaAsignacionEquiposService.php`
- No maneja BYE ni restricciones de compañeros/oponentes

**Evaluación:**
- **Funcionalidad:** ⚠️ Parcial – genera mesas básicas pero sin reglas de asignación profesional
- **Rendimiento:** Aceptable para torneos pequeños
- **Mejoras:** Integrar con MesaAsignacionService para primera/segunda/intermedias/última ronda; soporte para modalidad equipos; BYE automático

### 3.2 Proceso de generación (MesaAsignacionService – torneo_gestion)

Según `PROCEDIMIENTO_ASIGNACION_RONDAS_Y_EVALUACION.md`:

| Ronda | Método | Lógica |
|-------|--------|--------|
| **1ª** | `generarPrimeraRonda` | Orden por club; dispersión por clubes; mesas 1-5-3-7 |
| **2ª** | `generarSegundaRonda` | Clasificación: ganadores r1 sin BYE → con BYE → perdedores; patrón 1-5-3-7; validación de compañeros r1 |
| **3 a N-1** | `generarRondaIntermedia` | Clasificación actual; matriz de compañeros y oponentes para evitar repeticiones |
| **Última (N)** | `generarUltimaRonda` | Patrón intercalado 1+3 vs 2+4 |
| **BYE** | `aplicarBye` | Sobrantes (hasta 3) con partida ganada, 100% puntos, 50% efectividad |

**No presentes:** Al generar ronda 3, se marcan como retirados los inscritos pendientes sin participación en ronda 1 ni 2.

---

## 4. Eliminación de Rondas

### 4.1 Eliminar Última Ronda (`eliminar_ronda.php`)

| Aspecto | Descripción |
|---------|-------------|
| **Problema que resuelve** | Deshacer una ronda generada por error antes de registrar resultados |
| **Cómo funciona** | Formulario para elegir número de ronda; DELETE en `partiresul` WHERE id_torneo AND partida = X |
| **Restricción** | No permite eliminar si hay partidas con `registrado = 1` |
| **Seguridad** | CSRF, verificación de permisos |

**Evaluación:**
- **Funcionalidad:** ✅ Correcta para rondas sin resultados
- **Mejoras:** Confirmación explícita; opción de eliminar solo la última ronda generada; mensaje claro cuando hay resultados ya registrados

---

## 5. Cuadrícula y Hojas de Anotación

### 5.1 Cuadrícula (`gestion_torneos/cuadricula.php`)

| Aspecto | Descripción |
|---------|-------------|
| **Problema que resuelve** | Mostrar la asignación de jugadores a mesas y letras (A, C, B, D) para una ronda |
| **Estructura** | 22 filas x 9 segmentos (3 columnas: ID usuario, Mesa+Letra, separador) |
| **Uso** | Impresión; formato carta; secuencia 1=A, 2=C, 3=B, 4=D (Pareja AC vs BD) |
| **Acceso** | Vía `torneo_gestion` con `action=cuadricula&torneo_id=X&ronda=Y` |

**Evaluación:**
- **Funcionalidad:** ✅ Cumple
- **Problema:** El menú de `tournament_admin` enlaza a `action=tabla_asignacion` pero **no existe** `tabla_asignacion.php` en tournament_admin; se muestra el dashboard. La cuadrícula real está en torneo_gestion.

### 5.2 Hojas de Anotación (`gestion_torneos/hojas-anotacion.php`)

| Aspecto | Descripción |
|---------|-------------|
| **Problema que resuelve** | Imprimir hojas para anotar resultados de cada mesa (Pareja AC vs Pareja BD) |
| **Estructura** | Formato carta; Pareja AC (sec 1,2) vs Pareja BD (sec 3,4); campos para resultado1, resultado2, firmas |
| **Uso** | Impresión por mesa; selector de mesas |
| **Acceso** | Vía `torneo_gestion` con `action=hojas_anotacion&torneo_id=X&ronda=Y` |

**Evaluación:**
- **Funcionalidad:** ✅ Cumple
- **Problema:** Igual que cuadrícula: `tournament_admin` tiene enlace a `hojas_anotacion` pero **no hay** `hojas_anotacion.php` en tournament_admin; se cae al dashboard

---

## 6. Ingreso de Resultados

### 6.1 Ingreso de Resultados (`ingreso_resultados.php`)

| Aspecto | Descripción |
|---------|-------------|
| **Problema que resuelve** | Registrar resultados de cada partida (resultado1, resultado2, efectividad, FF, tarjeta, sanciones, etc.) |
| **Cómo funciona** | Selector de ronda y mesa; formulario con filas por jugador en esa mesa; UPDATE en `partiresul` |
| **Tras guardar** | Llama a `actualizarEstadisticasInscritos()` para recalcular ganados, perdidos, puntos, efectividad en `inscritos` |
| **Bloqueo** | Torneos finalizados (excepto admin_general en modo corrección) |

**Evaluación:**
- **Funcionalidad:** ✅ Completa
- **Rendimiento:** Transacciones correctas; actualización de estadísticas puede ser costosa en torneos grandes
- **Mejoras:** Validación cruzada resultado1+resultado2 vs efectividad; interfaz más clara para FF y tarjetas; batch por varias mesas

---

## 7. Mostrar Resultados, Podios y Resultados por Equipos

### 7.1 Mostrar Resultados (`mostrar_resultados.php`)

| Aspecto | Descripción |
|---------|-------------|
| **Problema que resuelve** | Ver clasificación general del torneo |
| **Cómo funciona** | Usa `InscritosPartiresulHelper::obtenerClasificacion()`; muestra pos, jugador, club, partidas, G, P, efectividad, puntos, ranking |
| **Estadísticas** | Total rondas, total partidas, partidas registradas |

**Evaluación:** ✅ Funcional

### 7.2 Podios (`podios.php`, `podios_equipos.php`)

| Aspecto | Descripción |
|---------|-------------|
| **Problema que resuelve** | Mostrar podio visual (1°, 2°, 3°) para impresión o pantalla |
| **Modalidad Individual/Parejas** | `podios.php` – clasificación por inscritos con lógica de ganadas por forfait |
| **Modalidad Equipos** | `podios_equipos.php` – podio por equipos |
| **Datos** | Misma lógica que posiciones: ptosrnk, efectividad, ganados, puntos |

**Evaluación:** ✅ Funcional

### 7.3 Resultados por Club (`resultados_por_club.php`)

| Aspecto | Descripción |
|---------|-------------|
| **Problema que resuelve** | Ver clasificación agrupada por club |
| **Cómo funciona** | Agrupa inscritos por club; muestra total de puntos, jugadores, posición |

**Evaluación:** ✅ Funcional

### 7.4 Resultados por Equipos (`resultados_equipos_resumido.php`, `resultados_equipos_detallado.php`, `equipos_detalle.php`)

| Archivo | Uso |
|---------|-----|
| **resultados_equipos_resumido** | Tabla resumida por equipo (código_equipo); paginación |
| **resultados_equipos_detallado** | Detalle por equipo con jugadores |
| **equipos_detalle** | Vista de equipos inscritos en el torneo |

**Solo para modalidad equipos (modalidad = 3).**

**Evaluación:** ✅ Funcional para torneos por equipos

---

## 8. Actualización de Estadísticas

| Momento | Dónde |
|---------|-------|
| Antes de generar siguiente ronda | Dentro de `generarRonda()` en MesaAsignacionService |
| Al guardar resultados | En `ingreso_resultados.php` tras UPDATE en partiresul |
| Manual desde panel | Botón "Actualizar estadísticas" en torneo_gestion |

**Flujo:**
1. Limpieza de duplicados en `partiresul`
2. Agregación por (id_usuario, id_torneo) desde partiresul (registrado=1)
3. UPDATE inscritos (ganados, perdidos, efectividad, puntos, sanciones, etc.)
4. Poner a 0 a inscritos sin partidas registradas
5. `recalcularPosiciones()` → posicion, ptosrnk
6. Si equipos: `actualizarEstadisticasEquipos()` y `asignarNumeroSecuencialPorEquipo()`

**Evaluación:** ✅ Lógica correcta según documentación

---

## 9. Otros Módulos del Menú

| Módulo | Descripción | Estado |
|--------|-------------|--------|
| **Invitar por WhatsApp** | Envío de invitaciones por WhatsApp | ✅ |
| **Galería de Fotos** | Subida y visualización de fotos del torneo | ✅ |
| **QR General / QR Personal** | Generación de códigos QR | ✅ |

---

## 10. Evaluación Global

### Funcionalidad

| Área | Estado | Observaciones |
|------|--------|---------------|
| Inscripciones en sitio | ✅ | Funcional; bloqueo por torneo iniciado |
| Revisar inscripciones | ✅ | Listado correcto; falta integración con cambiar estatus |
| Generar rondas (tournament_admin) | ⚠️ | Algoritmo simple; no usa MesaAsignacionService |
| Generar rondas (torneo_gestion) | ✅ | Lógica completa con BYE y restricciones |
| Eliminar ronda | ✅ | Solo si no hay resultados registrados |
| Cuadrícula / Tabla asignación | ⚠️ | Existe en gestion_torneos; en tournament_admin no hay archivo → cae a dashboard |
| Hojas de anotación | ⚠️ | Igual que cuadrícula; en tournament_admin no hay archivo |
| Ingreso resultados | ✅ | Completo con actualización de estadísticas |
| Mostrar resultados / Podios | ✅ | Funcional |
| Resultados por club / equipos | ✅ | Funcional |

### Rendimiento

- Consultas N+1 en varios listados
- Actualización de estadísticas puede ser pesada con muchos inscritos/partidas
- Falta caché o índices específicos para clasificación

### Posibles Mejoras

1. **Unificar rutas:** Crear `hojas_anotacion.php` y `tabla_asignacion.php` en tournament_admin que redirijan o incluyan la vista de gestion_torneos con los parámetros correctos.
2. **Generar rondas:** Usar MesaAsignacionService desde tournament_admin en lugar del algoritmo simple actual.
3. **Revisar inscripciones:** Añadir acciones de confirmar/retirar desde la misma pantalla.
4. **Inscribir en sitio:** Búsqueda por cédula/nombre; validación de equipos para modalidad equipos.
5. **Rendimiento:** Índices en (torneo_id, partida, mesa), (id_usuario, id_torneo); evitar N+1 en clasificación.
6. **UX:** Flujo más guiado (paso 1 inscripciones → paso 2 revisar → paso 3 generar ronda 1 → …).

---

## 11. Esperando Instrucciones

Este documento es un **resumen de análisis**. No se han implementado cambios. Indique qué mejoras desea priorizar para proceder con la implementación.
