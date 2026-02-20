# ÁRBOL DE FUNCIONAMIENTO - MÓDULO DE TORNEOS

## SISTEMA HÍBRIDO (2 Sistemas Funcionando)

### SISTEMA 1: LEGACY (modules/)
**Ruta de acceso:** `index.php?page=torneo_gestion&action=panel&torneo_id={id}`

**Flujo:**
```
public/index.php
  └─> modules/torneo_gestion.php (línea 137)
      └─> Decide qué vista usar:
          ├─ Si standalone (admin_torneo.php):
          │   └─> modules/gestion_torneos/panel-moderno.php ⚠️
          └─ Si normal (index.php?page=...):
              └─> modules/gestion_torneos/panel.php
```

**Archivos Legacy:**
- `modules/torneo_gestion.php` - Controlador principal
- `modules/gestion_torneos/panel-moderno.php` - Vista moderna (standalone)
- `modules/gestion_torneos/panel.php` - Vista normal

---

### SISTEMA 2: MVC MODERNO (src/)
**Ruta de acceso:** `/gestion-torneos/panel/{id}`

**Flujo:**
```
public/index.php (sistema moderno)
  └─> src/Core/Router.php
      └─> src/Controllers/TorneoGestionController.php
          └─> método panel() (línea 95)
              └─> $this->view('gestion-torneos/panel-moderno', ...) (línea 154)
                  └─> src/Views/gestion-torneos/panel-moderno.php ✅ (ESTE ES EL CORRECTO)
```

**Archivos MVC:**
- `src/Controllers/TorneoGestionController.php` - Controlador
- `src/Views/gestion-torneos/panel-moderno.php` - Vista (ESTE ES EL QUE DEBES EDITAR)
- `src/Views/gestion-torneos/panel-v2.php` - Vista alternativa (no se usa)
- `src/Views/gestion-torneos/panel.php` - Vista antigua (no se usa)

---

## ARCHIVOS ENCONTRADOS

### Sistema MVC (src/):
1. ✅ `src/Views/gestion-torneos/panel-moderno.php` - **EN USO ACTUAL**
2. ❌ `src/Views/gestion-torneos/panel-v2.php` - No se usa
3. ❌ `src/Views/gestion-torneos/panel.php` - No se usa

### Sistema Legacy (modules/):
4. ⚠️ `modules/gestion_torneos/panel-moderno.php` - Solo si accedes por admin_torneo.php
5. ⚠️ `modules/gestion_torneos/panel.php` - Solo si accedes por index.php?page=torneo_gestion

---

## CONCLUSIÓN

**El archivo que se está usando actualmente es:**
- `src/Views/gestion-torneos/panel-moderno.php` ✅

**El controlador que lo llama es:**
- `src/Controllers/TorneoGestionController.php` → método `panel()` (línea 95)

**Ruta de acceso:**
- `/gestion-torneos/panel/{id}` (sistema MVC moderno)

**Archivos que puedes eliminar (si no se usan):**
- `src/Views/gestion-torneos/panel-v2.php`
- `src/Views/gestion-torneos/panel.php`
- `modules/gestion_torneos/panel-moderno.php` (solo si no usas admin_torneo.php)
- `modules/gestion_torneos/panel.php` (solo si no usas index.php?page=torneo_gestion)









