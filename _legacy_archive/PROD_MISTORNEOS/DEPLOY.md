# Guía de Despliegue a Producción

## Información del Servidor
- **Dominio:** laestaciondeldominohoy.com
- **Ruta:** public_html/mistorneos
- **URL Final:** https://laestaciondeldominohoy.com/mistorneos

## Pasos de Despliegue

### 1. Preparar Archivos

#### Crear archivo .env
1. Copiar `config/env.production.example` a `.env` en la raíz del proyecto
2. Editar `.env` con las credenciales reales:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://laestaciondeldominohoy.com/mistorneos

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=laestaci1_fvdadmin
DB_USERNAME=TU_USUARIO
DB_PASSWORD=TU_PASSWORD
```

### 2. Subir Archivos al Servidor

Subir TODO el contenido de `mistorneos/` a `public_html/mistorneos/`

**Estructura esperada:**
```
public_html/
└── mistorneos/
    ├── .htaccess
    ├── .env                    (crear en servidor)
    ├── config/
    ├── lib/
    ├── modules/
    ├── public/
    │   ├── index.php          (Dashboard)
    │   ├── landing.php        (Página principal pública)
    │   ├── login.php
    │   ├── admin_torneo.php
    │   ├── resultados.php
    │   └── ...
    └── upload/
```

### 3. Permisos de Archivos

```bash
# Directorios con permisos de escritura
chmod 755 upload/
chmod 755 upload/tournaments/
chmod 755 upload/logos/
chmod 755 storage/

# Archivos sensibles (solo lectura)
chmod 644 .env
chmod 644 .htaccess
```

### 4. Verificar Configuración

#### URLs principales que deben funcionar:
- https://laestaciondeldominohoy.com/mistorneos/public/landing.php (Inicio)
- https://laestaciondeldominohoy.com/mistorneos/public/login.php (Login)
- https://laestaciondeldominohoy.com/mistorneos/public/index.php (Dashboard)
- https://laestaciondeldominohoy.com/mistorneos/public/resultados.php (Resultados)
- https://laestaciondeldominohoy.com/mistorneos/public/admin_torneo.php (Panel Torneos)

### 5. Base de Datos

1. Importar el esquema de base de datos
2. Verificar que las tablas existan:
   - usuarios
   - tournaments
   - clubes
   - inscritos
   - partiresul
   - invitaciones
   - club_photos

### 6. Verificación Post-Despliegue

- [ ] Landing page carga correctamente
- [ ] Login funciona
- [ ] Dashboard accesible después de login
- [ ] Resultados de torneos visibles
- [ ] Panel de administración de torneos funcional
- [ ] Subida de imágenes funciona
- [ ] Generación de credenciales PDF funciona

## Archivos Importantes

| Archivo | Descripción |
|---------|-------------|
| `.htaccess` | Reglas de reescritura y seguridad |
| `config/config.production.php` | Configuración de producción |
| `config/environment.php` | Detección automática de entorno |
| `lib/app_helpers.php` | Helper de URLs (detecta dominio) |

## Solución de Problemas

### Error 500
- Verificar permisos de archivos
- Revisar logs de PHP: `error_log` del servidor
- Verificar `.htaccess` sea compatible con el servidor

### Error de Base de Datos
- Verificar credenciales en `.env`
- Verificar que MySQL esté accesible
- Probar conexión: visitar `/public/check_mysql.php` (borrar después)

### URLs no funcionan
- Verificar `mod_rewrite` está habilitado
- Verificar `.htaccess` tiene permisos correctos
- Revisar `AllowOverride All` en configuración de Apache

### Sesiones no persisten
- Verificar directorio de sesiones tiene permisos
- Verificar `session.save_path` en php.ini

## Contacto
Para soporte técnico, contactar al administrador del sistema.











