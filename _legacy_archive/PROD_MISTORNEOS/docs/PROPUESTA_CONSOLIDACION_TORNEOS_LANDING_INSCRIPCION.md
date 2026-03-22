# Propuesta: Consolidación de Creación de Torneos, Ámbito, Publicación en Landing e Inscripción Online

**Documento de propuesta – NO implementar hasta autorización explícita**

---

## 1. Diagnóstico del estado actual

### 1.1 Creación de torneos
- **Entradas**: `modules/tournaments.php` (router), `modules/tournaments/save.php`, `modules/tournaments/update.php`
- **Campos clave**: `nombre`, `fechator`, `lugar`, `clase`, `modalidad`, `es_evento_masivo` (0–4), `permite_inscripcion_linea`, `club_responsable` (= ID organización)
- **Problemas**:
  - `club_responsable` se usa como ID de organización, pero el nombre sugiere “club”
  - Lógica de permisos por rol (admin_general/admin_club/admin_torneo) repartida en varios archivos
  - No existe un flujo claro de “crear → publicar” explícito

### 1.2 Ámbito de torneos
- **`es_evento_masivo`**:
  - 0 = Ninguno (torneo normal)
  - 1 = Evento Nacional
  - 2 = Evento Regional
  - 3 = Evento Local
  - 4 = Evento Privado (visible pero sin inscripción online)
- **`entidad`**: ámbito territorial de la organización
- **Problemas**:
  - Reglas de inscripción online dispersas (tournament_register vs inscribir_evento_masivo)
  - Eventos 2 y 3 exigen historial de inscripciones previas (InscritosHelper::puedeInscribirseEnLinea), no documentado para el usuario final

### 1.3 Landing y publicidad
- **Archivos**: `landing.php` (PHP), `landing-spa.php` (Vue SPA), `api/landing_data.php`
- **Problemas**:
  - Lógica duplicada en PHP y API
  - Criterio de publicación implícito: `estatus = 1` y `fechator >= CURDATE()`
  - Sin flag explícito “publicar en landing”
  - Filtros por entidad/club del usuario poco consistentes

### 1.4 Acceso a información pública
- **`torneo_detalle.php`**: info completa (organización, afiche, invitación, normas)
- **`torneo_info.php`**: mesas, resumen, incidencias (usa JOIN con `clubes` pero `club_responsable` es org)
- **Problemas**: `torneo_info.php` asume club, cuando en realidad se trata de organización

### 1.5 Inscripción online
- **Dos flujos**:
  1. `tournament_register.php`: usuarios registrados; torneos 0 y 4
  2. `inscribir_evento_masivo.php`: eventos 1, 2, 3; permite crear usuario nuevo
- **Validaciones**: torneo `permite_inscripcion_linea`, club `permite_inscripcion_linea`, mismo ámbito (`entidad`), reglas específicas para masivos 2 y 3
- **Problemas**:
  - Dos formularios distintos con reglas distintas
  - Usuario debe elegir entre dos URLs según el tipo de evento
  - Mensajes de error poco claros cuando no cumple condiciones (ej. historial en eventos 2 y 3)

---

## 2. Objetivos de la consolidación

1. Un solo flujo de creación de torneos con reglas claras.
2. Un solo punto de inscripción online que aplique las reglas según tipo de torneo.
3. Criterios explícitos de publicación en landing.
4. Un modelo de ámbito coherente (entidad + tipo de evento + visibilidad).
5. Un único origen de datos para landing (API compartida por PHP y SPA).

---

## 3. Propuesta de solución

### 3.1 Modelo de datos unificado

| Campo | Uso propuesto | Motivo |
|-------|----------------|--------|
| `club_responsable` | Mantener como ID de organización | Cambiar sería migración grande; documentar que es org_id |
| `publicar_landing` | Nuevo TINYINT(1) DEFAULT 1 | Publicación explícita; por defecto sí |
| `es_evento_masivo` | Mantener 0–4 | Ya implementado y consistente |
| `permite_inscripcion_linea` | Mantener | Bien entendido |
| `entidad` | Mantener | Ámbito territorial ya definido |

### 3.2 Flujo único de creación/edición de torneos

**Ubicación**: `modules/tournaments/` (save.php, update.php) como único punto de guardado.

**Pasos sugeridos**:
1. Formulario unificado en `modules/tournaments.php` con:
   - Datos básicos (nombre, fecha, lugar, clase, modalidad)
   - Tipo de evento (es_evento_masivo) con ayuda contextual
   - Checkbox “Publicar en landing” (por defecto sí)
   - Checkbox “Permitir inscripción online” (por defecto según tipo)
   - Organización responsable (obligatorio para admin_general)
2. Validaciones centralizadas:
   - Nacional (1) → sin ranking, permitir inscripción online por defecto
   - Privado (4) → permitir inscripción online por defecto NO
   - Regional (2) y Local (3) → permitir inscripción online con historial si aplica
3. Al guardar: recalcular o actualizar `entidad` desde la organización.

**Beneficio**: Un solo lugar para crear/editar y un conjunto claro de reglas por tipo de evento.

### 3.3 Reglas explícitas de ámbito y visibilidad

Definir en documento y en código una tabla tipo:

| es_evento_masivo | Descripción | Landing | Inscripción online | Restricciones |
|------------------|-------------|---------|--------------------|---------------|
| 0 | Torneo normal | Sí si publicar_landing=1 | Sí si permite_inscripcion=1 | Misma entidad |
| 1 | Nacional | Sí | Sí (usuarios registrados y nuevos) | Misma entidad para nuevos |
| 2 | Regional | Sí | Sí | Misma entidad + historial previo |
| 3 | Local | Sí | Sí | Misma entidad + historial previo |
| 4 | Privado | Sí | No | Solo inscripción en sitio |

**Implementación**: clase helper `TournamentScopeHelper` con métodos:
- `getVisibilityRules(int $es_evento_masivo): array`
- `canRegisterOnline(array $torneo, array $usuario): array` (puede, mensaje)
- `getLandingFilter(array $torneo): bool`

**Beneficio**: Reglas en un solo sitio y mensajes coherentes.

### 3.4 API única para el landing

**Crear**: `lib/LandingDataService.php`

Responsabilidades:
1. Obtener eventos futuros (con criterios de publicación explícitos)
2. Obtener eventos realizados
3. Aplicar filtros por entidad/club del usuario (si está logueado)
4. Separar eventos por tipo: normales, masivos, privados
5. Enriquecer con total_inscritos, logo, etc.

**Usar en**:
- `public/api/landing_data.php` (JSON para SPA)
- `public/landing.php` (incluir el servicio y reemplazar queries directas)

**Beneficio**: Un solo origen de verdad y mantenimiento centralizado.

### 3.5 Punto único de inscripción online

**Crear**: `public/inscribir_torneo.php` (o `tournament_register.php` unificado)

Flujo:
1. Recibir `torneo_id` (y opcionalmente `user_id` para invitaciones).
2. Cargar torneo y comprobar:
   - Existe y está activo
   - Fecha futura
   - `permite_inscripcion_linea = 1`
3. Determinar tipo de usuario:
   - No autenticado: redirigir a login con return_url; o mostrar formulario de inscripción directa si es evento masivo (1, 2, 3)
   - Autenticado: comprobar ámbito y reglas vía `TournamentScopeHelper::canRegisterOnline`
4. Mostrar un solo formulario según contexto:
   - Usuario registrado: confirmar inscripción (como hoy en tournament_register)
   - Usuario nuevo en masivos: formulario completo (como en inscribir_evento_masivo)
5. Procesar inscripción con `InscritosHelper::insertarInscrito` (ya centralizado).

**Redirecciones legacy**:
- `tournament_register.php?torneo_id=X` → `inscribir_torneo.php?torneo_id=X`
- `inscribir_evento_masivo.php?torneo_id=X` → `inscribir_torneo.php?torneo_id=X`

**Beneficio**: Una sola URL para inscripción y un flujo coherente para el usuario.

### 3.6 Acceso público a información

**`torneo_detalle.php`**:
- Mantener como página principal de información del torneo.
- Ajustar JOIN: soportar `club_responsable` como org o club según contexto (hoy ya se usa COALESCE o/co con organizaciones/clubes).
- Añadir botón “Inscribirme” que apunte siempre a `inscribir_torneo.php?torneo_id=X`.

**`torneo_info.php`**:
- Corregir JOIN: cuando `club_responsable` es org_id, usar `organizaciones` en lugar de `clubes` para nombre, delegado, teléfono.
- Mantener funcionalidad de mesas, resumen e incidencias.

**Beneficio**: Información correcta y enlaces consistentes.

### 3.7 Documentación para el usuario

En el formulario de creación/edición de torneo:
- Texto de ayuda junto a “Tipo de evento”:
  - Nacional: abierto a todos, sin ranking
  - Regional/Local: requiere historial de participación
  - Privado: visible, inscripción solo en sitio
- Texto junto a “Permitir inscripción online”:
  - Cuándo activarlo/desactivarlo
  - Relación con la configuración del club

En la página de inscripción:
- Si no puede inscribirse: mensaje claro (ej. “Para eventos regionales/locales necesitas haber participado en al menos 2 torneos previos”).

---

## 4. Plan de implementación sugerido

| Fase | Tarea | Riesgo | Dependencias |
|------|-------|--------|--------------|
| 1 | Crear `TournamentScopeHelper` con reglas de ámbito | Bajo | Ninguna |
| 2 | Crear `LandingDataService` y migrar landing + API | Medio | Fase 1 |
| 3 | Añadir `publicar_landing` (migración SQL + formulario) | Bajo | Fase 1 |
| 4 | Unificar inscripción en `inscribir_torneo.php` | Alto | Fase 1 |
| 5 | Corregir `torneo_info.php` (JOIN organización) | Bajo | Ninguna |
| 6 | Redirecciones legacy y deprecar URLs antiguas | Bajo | Fase 4 |
| 7 | Documentación en formularios y ayuda | Bajo | Fases 1–4 |

---

## 5. Cómo resuelve las situaciones actuales

| Situación | Solución |
|-----------|----------|
| Usuario no sabe qué URL usar para inscribirse | Una sola URL: `inscribir_torneo.php?torneo_id=X` |
| Torneo aparece en landing sin quererlo | Flag `publicar_landing` para control explícito |
| Reglas de inscripción confusas | `TournamentScopeHelper` centraliza y genera mensajes claros |
| Lógica duplicada en landing PHP y SPA | `LandingDataService` como único origen |
| torneo_info muestra datos erróneos | JOIN correcto con organizaciones cuando corresponde |
| Admin no entiende tipo de evento | Textos de ayuda en el formulario |
| Historial para eventos 2 y 3 poco claro | Mensaje explícito en la página de inscripción |

---

## 6. Resumen ejecutivo

La propuesta:
- Centraliza la lógica de torneos en helpers y servicios.
- Unifica inscripción en un solo punto con reglas explícitas.
- Introduce publicación explícita en landing.
- Corrige inconsistencias en información pública.
- Mantiene compatibilidad mediante redirecciones legacy.

**Esfuerzo estimado**: 3–5 días de desarrollo más pruebas.

**Autorización requerida**: Confirmar esta propuesta antes de iniciar la implementación.
