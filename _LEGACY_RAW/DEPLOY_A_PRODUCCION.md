# Archivos a subir a producción

Sube estos archivos al servidor de producción (laestaciondeldominohoy.com) manteniendo la misma estructura de carpetas.

## Listado de archivos (obligatorios)

```
lib/ActasPendientesHelper.php
modules/torneo_gestion.php
public/includes/layout.php
public/public_mesa_input.php
actions/public_score_submit.php
```

## Listado de archivos (opcionales / documentación)

```
docs/PROCEDIMIENTO_ASIGNACION_RONDAS_Y_EVALUACION.md
docs/PROCEDIMIENTO_BYE.md
```

## Ruta en el servidor

Si la aplicación está en `mistorneos/` o `public_html/mistorneos/`, cada archivo va en:

- `lib/ActasPendientesHelper.php` → raíz del proyecto, carpeta `lib/`
- `modules/torneo_gestion.php` → carpeta `modules/`
- `public/includes/layout.php` → carpeta `public/includes/`
- `public/public_mesa_input.php` → carpeta `public/`
- `actions/public_score_submit.php` → carpeta `actions/`
- `docs/*.md` → carpeta `docs/` (solo si quieres actualizar documentación)

## Resumen de cambios incluidos

| Archivo | Qué incluye |
|---------|-------------|
| **torneo_gestion.php** | Verificar actas (verificar_actas_index, fallback), tabla `clasiranking` en minúsculas, **BYE en estadísticas**: incluye mesa=0 en actualizarEstadisticasInscritos y vuelve a actualizar estadísticas tras generar ronda |
| **layout.php** | Menú Auditoría QR y badge para admin_general y admin_torneo, banner de actas pendientes |
| **ActasPendientesHelper.php** | Clase completa (conteo de actas pendientes por rol) |
| **public_mesa_input.php** | Formulario ligero, sin sanciones, validaciones, compresión de imagen, “Archivo a subir”, timeout 90 s |
| **public_score_submit.php** | Validaciones (máx. puntos, empate, una pareja alcanza), tiempo límite 90 s, flujo QR sin SancionesHelper |

## Últimas modificaciones (BYE)

- **modules/torneo_gestion.php**: En `actualizarEstadisticasInscritos()` se incluyen las partidas BYE (`mesa = 0`) para que ganados, efectividad y puntos de los jugadores con BYE se reflejen en `inscritos`. Además, tras generar una ronda con éxito se llama de nuevo a `actualizarEstadisticasInscritos()` para que los BYE recién creados se actualicen de inmediato en la clasificación.

## Después de subir

1. Probar con admin: `index.php?page=torneo_gestion&action=verificar_actas_index`
2. Probar envío por QR desde el móvil (puntos + foto).
3. Probar generar una ronda con BYE y comprobar que los jugadores con BYE ven su partida ganada y puntos en posiciones.
4. Si usas migración de BD (partiresul): ejecutar `sql/migrate_partiresul_verificacion_qr.sql` en producción si aún no se ha aplicado.
