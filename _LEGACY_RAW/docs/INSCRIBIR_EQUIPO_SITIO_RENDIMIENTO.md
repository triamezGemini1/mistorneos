# Diagnóstico y optimización: `inscribir_equipo_sitio`

## Ruta lógica

| Paso | Ubicación |
|------|-----------|
| Router | `modules/torneo_gestion.php` → `case 'inscribir_equipo_sitio'` |
| Datos | `obtenerDatosInscribirEquipoSitio($torneo_id)` (mismo archivo) |
| Vista | `modules/gestion_torneos/inscribir_equipo_sitio.php` |
| Búsqueda por cédula | `public/api/buscar_jugador_inscripcion.php` |

## Causas probables de TTFB alto (antes)

1. **Consulta masiva (admin general)**  
   Un solo `SELECT` de **todos** los `usuarios` con `role = 'usuario'` no inscritos o sin `codigo_equipo`, con `LEFT JOIN inscritos` por torneo. Con cientos de miles / millones de usuarios, el motor devuelve un conjunto enorme y PHP construye HTML gigante → TTFB y memoria inaceptables.

2. **N+1 en clubes**  
   Por cada equipo inscrito con `id_club` no listado, se hacía `SELECT ... FROM clubes WHERE id = ?`. Sustituido por **un único** `WHERE id IN (...)`.

3. **Índices**  
   Sin índice en `(torneo_id, id_usuario)` o `(torneo_id, codigo_equipo)` en `inscritos`, los JOIN y filtros por torneo escanean muchas filas.

4. **DOM**  
   Miles de `.jugador-item` en el HTML pesan MB y enlentecen el navegador aunque el servidor ya respondió.

## Cambios aplicados en código

- **Admin general:** ya **no** se ejecuta el listado completo. `jugadores_disponibles = []`, flag `jugadores_lista_lazy = true`. La inscripción sigue igual: **cédula por fila** + API existente.
- **Admin club / territorio:** se mantiene **una** query acotada por `club_id IN (...)`.
- **Clubes extra:** de N queries a **1 query** `IN (...)`.
- **Caché torneo:** APCu 120 s sobre fila `tournaments` (si APCu disponible); reduce lecturas repetidas.

## Índices SQL sugeridos

Ver `docs/SQL_INDICES_INSCRIBIR_EQUIPO_SITIO.sql`.

Resumen mínimo:

- `inscritos (torneo_id, id_usuario)`
- `inscritos (torneo_id, codigo_equipo)`
- `usuarios (club_id, role)` y `usuarios (cedula)`
- `equipos (id_torneo)`
- `partiresul (id_torneo, mesa)` (o columnas reales de su tabla de rondas)

## Objetivo &lt; 500 ms

- Con admin general, el cuello de botella principal era el volumen de filas + HTML; al quitarlo, el TTFB debería bajar a lo que cuesten: torneo + clubes + equipos + batch jugadores por código + permisos.
- Para admin **club**, asegurar índices anteriores y que `clubes_ids` no sea excesivo.
- Si aún hay lentitud, perfilar con `EXPLAIN` las queries restantes y revisar tabla `persona` solo si participa en esta pantalla.

## PSR-12

Refactor en `obtenerDatosInscribirEquipoSitio` alineado con el estilo del archivo (arrays cortos, comentarios claros).
