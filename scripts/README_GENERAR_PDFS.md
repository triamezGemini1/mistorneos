# Generador de PDFs de Invitación

Este script genera automáticamente los PDFs de invitación para todos los clubes y torneos existentes en la base de datos.

## Requisitos Previos

1. **Instalar Dompdf** (librería para generar PDFs):
   ```bash
   composer require dompdf/dompdf
   ```
   
   Si no tienes Composer instalado, puedes descargarlo desde: https://getcomposer.org/
   
   O instalar Dompdf manualmente descargando desde: https://github.com/dompdf/dompdf

## Uso

Ejecutar el script desde la línea de comandos:

```bash
php scripts/generate_existing_pdfs.php
```

## Qué hace el script

1. **Conecta a la base de datos** usando las credenciales del archivo `.env`
2. **Busca todos los clubes activos** (estatus = 1)
3. **Busca todos los torneos activos** (estatus = 1)
4. **Genera PDFs de invitación** para cada uno que no tenga PDF
5. **Guarda las rutas** de los PDFs en la base de datos
6. **Muestra un resumen** con estadísticas del proceso

## Características

- ✅ **No duplica PDFs**: Verifica si ya existe un PDF antes de generarlo
- ✅ **Regenera si falta**: Si el PDF está en la BD pero el archivo no existe, lo regenera
- ✅ **Manejo de errores**: Continúa aunque falle algún PDF individual
- ✅ **Estadísticas detalladas**: Muestra cuántos se generaron, cuántos ya existían y cuántos errores hubo

## Ubicación de los PDFs

Los PDFs se guardan en: `upload/pdfs/`

Con nombres como:
- `club_invitation_{club_id}_{timestamp}.pdf`
- `tournament_invitation_{tournament_id}_{timestamp}.pdf`

## Notas

- El script es **seguro de ejecutar múltiples veces**: no duplicará PDFs existentes
- Los PDFs se generan automáticamente cuando se crean nuevos clubes o torneos
- Este script es útil para generar PDFs de elementos creados antes de implementar esta funcionalidad





