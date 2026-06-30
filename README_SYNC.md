# Sync Presence (Saviacloud) – Nota de despliegue

Este proyecto incluye el script **`lib/sync_presence.php`** que sincroniza la presencia de empleados desde la API de **Saviacloud** y almacena los datos en la base de datos SQLite `data/telefonos.db` (tabla `presencia`).

---

## Almacenamiento de datos — Migración a SQLite

A partir de la versión actual, **todo el almacenamiento local de datos se ha migrado de ficheros JSON a SQLite**.

### Base de datos: `data/telefonos.db`

| Tabla | Contenido |
|---|---|
| `attempts_login` | Intentos de login fallidos por IP y tiempos de bloqueo |
| `pending_users` | Tokens de activación de usuarios pendientes |
| `remember_me` | Tokens de sesiones persistentes ("Recuérdame") |
| `presencia` | Estado de presencia de empleados (sincronizado desde Saviacloud) |
| `presencia_meta` | Metadatos de sincronización (`last_sync`) |

La BD se **crea automáticamente** en el primer acceso. No requiere ningún script de inicialización.

### Requisito del servidor: extensión `pdo_sqlite`

La aplicación usa PDO para acceder a SQLite. Es necesario que el servidor tenga instalada y activa la extensión `pdo_sqlite`.

**En XAMPP (Windows):** Normalmente ya está incluida. Verificar en `php.ini` que las dos líneas están descomentadas:
```ini
extension=sqlite3
extension=pdo_sqlite
```
Reiniciar Apache desde el panel de control de XAMPP.

**En Linux (Ubuntu/Debian):**
```bash
sudo apt-get install php-sqlite3
sudo systemctl restart apache2
```

**En Linux (CentOS/RHEL/AlmaLinux):**
```bash
sudo dnf install php-pdo php-sqlite3
sudo systemctl restart httpd
```

Para verificar que está activo:
```bash
php -m | grep -i sqlite
# Debe mostrar: PDO_SQLite  y  SQLite3
```

### Archivos JSON — ya no se usan

Los siguientes ficheros JSON han quedado obsoletos y pueden eliminarse del servidor:

```
data/attempts_login.json
data/pending_users.json
data/remember_me.json
data/presencia.json
```

> **Nota:** El `.htaccess` de la carpeta `data/` ya bloquea el acceso web directo a todos los ficheros, incluido `telefonos.db`.

### Ficheros WAL de SQLite (normales)

Durante las operaciones de escritura pueden aparecer temporalmente en `data/` los ficheros:
- `telefonos.db-wal` — Write-Ahead Log
- `telefonos.db-shm` — Shared Memory

Son **ficheros auxiliares del motor SQLite** en modo WAL (configurado para evitar bloqueos entre lecturas y escrituras concurrentes). Desaparecen al finalizar la petición. Si quedaran permanentes tras un crash del servidor, SQLite los recupera automáticamente en el siguiente acceso sin pérdida de datos.

---

## ¿Por qué el script de sincronización no funciona en localhost?

En entornos de desarrollo (por ejemplo, XAMPP en tu máquina local) la IP del servidor **no está autorizada** para acceder a la API de Saviacloud. La API devuelve `403 Forbidden` aunque el token OAuth2 sea válido, porque la lista de IPs permitidas está configurada en el portal de Saviacloud para el entorno de **producción**.

## Solución implementada

Se ha añadido una **protección de entorno** al inicio de `lib/sync_presence.php`:

```php
if ($app_env !== 'production') {
    log_sync("Entorno actual: $app_env. Sincronización abortada (solo en producción).");
    exit;
}
```

El valor de `app_env` se lee de `private/config.ini` (sección `[medley]`, clave `app_env`). Si se prefiere, también puede sobreescribirse con la variable de entorno del sistema `APP_ENV`.

## Cómo habilitar la sincronización en producción

1. **Establecer `app_env = "production"`** en `private/config.ini` (sección `[medley]`), o bien definir la variable de entorno `APP_ENV=production` en Apache:
   ```apache
   SetEnv APP_ENV production
   ```
2. **Asegurarse de que la IP del servidor está incluida** en la lista de IPs permitidas en el portal de Saviacloud.
3. **Comprobar que los valores de `client_id`, `client_secret`, `scope` y `subscription_key`** en la sección `[presence_api]` de `private/config.ini` son correctos.

## Notas adicionales

- El script registra toda la actividad en `data/sync_debug.log` (solo cuando `app_debug = 1` en `config.ini`).
- Para ejecutar la sincronización manualmente en producción:
  ```bash
  php lib/sync_presence.php
  ```
- En entornos locales puedes comentar la línea `exit;` temporalmente si deseas probar la lógica de base de datos sin contactar la API.

---

*Esta documentación está incluida en el repositorio para que cualquier desarrollador entienda la arquitectura de almacenamiento local y el comportamiento de la sincronización de presencia.*
