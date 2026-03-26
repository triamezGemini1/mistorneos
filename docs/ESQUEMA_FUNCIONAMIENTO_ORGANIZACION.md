# Esquema de funcionamiento: principio de organización

Toda la información generada en la aplicación parte del **principio de organización** y de la **identificación geográfica (entidad)**. Este documento define el modelo conceptual y las reglas de alcance.

---

## 1. Niveles del sistema

1. **Administrador general**  
   Nivel superior; puede ver y gestionar todas las organizaciones y entidades.

2. **Solicitud de afiliación de organización**  
   Una persona solicita afiliarse como **organización**. Al aprobarse la solicitud se crea:
   - Un usuario con rol administrador de organización (admin_club).
   - Una **organización** vinculada a ese usuario.

3. **Organización**  
   Cada organización tiene en su estructura una **identificación geográfica** definida como **entidad** (estado/región). La entidad se establece en la solicitud de afiliación y queda asociada a la organización.

---

## 2. Principio de alcance

**A partir de la organización, todo lo que está bajo su estructura queda signado por:**

- La **entidad** a la cual se suscribió cuando hizo la solicitud (ámbito territorial).
- La **organización** concreta (ámbito institucional).

**Pueden existir muchas organizaciones en una misma entidad.** Cada una tiene su propio alcance: clubes, torneos, operadores, afiliados, etc. pertenecen a **su** organización y, por tanto, al ámbito territorial de **su** entidad.

---

## 3. Elementos bajo la organización

Todo lo que crea o gestiona el administrador de la organización pertenece a su ámbito territorial y a su organización:

| Elemento        | Relación con la organización        | Alcance territorial |
|-----------------|--------------------------------------|----------------------|
| **Clubes**      | `clubes.organizacion_id`            | Heredan la entidad de la organización |
| **Torneos**     | `tournaments.club_responsable` = ID de la organización | Misma entidad/organización |
| **Operadores**  | Asignados a torneos/clubes de la organización | Ámbito de la organización |
| **Admin torneo**| Usuario asociado a un club de la organización | Club → organización → entidad |
| **Afiliados**   | `usuarios.club_id` → club → `organizacion_id` | Pertenecientes a clubes de la organización |
| **Inscripciones** | Por torneo (organización) y club (organización) | Todo dentro del mismo ámbito |

Las operaciones (crear torneos, inscribir jugadores, registrar resultados, gestionar clubes, etc.) se consideran realizadas **bajo** esa organización y en su ámbito territorial (entidad).

---

## 4. Reglas de consistencia

- **No se permite registrar** un club, un operador ni un torneo **sin identidad de entidad**. Todos deben estar bajo una entidad, y esa entidad debe ser la del administrador de la organización o de la organización propiamente dicha.
- **Alta de organización:** la entidad se fija en la solicitud de afiliación y se guarda en `organizaciones.entidad`.
- **Clubes:** deben tener `organizacion_id` y `entidad` (esta última tomada de la organización). No se puede crear un club sin organización con entidad definida.
- **Torneos:** `club_responsable` es el ID de la **organización** que organiza el torneo; la `entidad` del torneo es la de esa organización. El admin general debe seleccionar organización al crear un torneo.
- **Operadores y Admin Torneo (usuarios):** al crearlos o asignarlos desde un administrador de organización, su `entidad` se fija a la de la organización; no puede ser otra.
- **Consultas y listados:** para administrador de organización, los filtros deben restringir por su organización (y así implícitamente por su entidad).
- **Admin general:** puede ver todas las entidades y todas las organizaciones; los reportes por entidad agrupan por organización cuando aplica.

---

## 5. Resumen

- **Una solicitud de afiliación** crea una **organización** con una **entidad** definida.
- **Todo lo que está bajo ese administrador** (clubes, torneos, operadores, afiliados, etc.) **pertenece a su organización y a su ámbito territorial (entidad)**.
- **Varias organizaciones** pueden compartir la **misma entidad**; cada una tiene su propio conjunto de clubes, torneos y datos.

Este esquema es la base para permisos, filtros, reportes y cualquier operación que deba respetar el ámbito de la organización y la entidad.
