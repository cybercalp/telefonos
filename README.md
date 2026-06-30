# Directorio Corporativo

Aplicación web de directorio telefónico corporativo con autenticación LDAP/Active Directory, diseñada para consultar contactos, departamentos, ubicaciones y equipos informáticos de la organización.

## Características principales

- **Búsqueda de contactos** por nombre, apellido, departamento, ubicación, teléfono o extensión.
- **Autenticación LDAP/Active Directory** con soporte para múltiples dominios.
- **Doble factor de autenticación (2FA)** mediante TOTP (compatible con Google Authenticator, Authy, etc.).
- **Control de acceso por IP**: permite acceso sin credenciales desde rangos de IP interna (oficina) y exige login desde el exterior.
- **Versión móvil** con detección automática de dispositivo y PWA instalable.
- **Edición de datos** del usuario: foto, teléfonos, departamento, ubicación, secretaria y equipo asignado.
- **Gestión de secretarias** y asignación de equipos informáticos.
- **Cambio de contraseña** con validación de políticas GPO (longitud mínima, intentos máximos).
- **Recuperación de cuenta** por correo electrónico.
- **Sesión persistente** ("Recuérdame") con tokens seguros almacenados en SQLite.
- **Generación de vCard** para importar contactos en clientes de correo y móviles.
- **Protección CSRF** en todos los formularios.
- **Cabeceras de seguridad HTTP**: CSP, HSTS, X-Frame-Options, X-Content-Type-Options y más.
- **Cookies de sesión seguras**: HttpOnly, Secure (en producción) y SameSite=Lax.
- **Sincronización de presencia** desde API externa (Saviacloud).

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP 8+ (vanilla, procedural) |
| Frontend | HTML5, Tailwind CSS 4, Alpine.js |
| CSS | Tailwind CSS (compilado desde `css/input.css`) |
| Base de datos | SQLite (via PDO) |
| Autenticación | LDAP / Active Directory |
| 2FA | TOTP (Google Authenticator) |
| Email | PHPMailer 6 |
| QR | endroid/qr-code |
| Entorno | Apache 2.4+ con `mod_rewrite` |

## Requisitos del servidor

- **PHP 8.0 o superior** con las siguientes extensiones:
  - `ldap` — conexión con Active Directory
  - `pdo_sqlite` y `sqlite3` — base de datos local
  - `mbstring` — codificación UTF-8
  - `openssl` — cifrado y TLS
  - `gd` o `imagick` — generación de códigos QR
  - `curl` — consumo de API externa (presencia)
- **Apache 2.4+** con `mod_rewrite` habilitado.
- **Composer** para dependencias PHP.
- **Node.js y npm** para compilar el CSS con Tailwind.
- Acceso de red al controlador de dominio LDAP/AD (puerto 389 o 636 para LDAPS).

## Instalación

### 1. Clonar el repositorio

```bash
git clone <url-del-repo> .
```

O descargá el código y copialo en la raíz de tu VirtualHost de Apache (ej. `C:\xampp\htdocs\telefonos` en XAMPP o `/var/www/html/` en Linux).

### 2. Instalar dependencias PHP

```bash
composer install
```

Esto instala PHPMailer, el cliente TOTP y la librería de QR.

### 3. Instalar dependencias Node y compilar CSS

```bash
npm install
npm run build:css
```

El comando `build:css` compila `css/input.css` y genera `css/style.css` usando Tailwind CSS 4.

### 4. Configurar la aplicación

Copiá el archivo de configuración de ejemplo y editalo con tus datos:

**En Windows (PowerShell):**
```powershell
Copy-Item private\config.ini.ci private\config.ini
```

**En Linux/Mac:**
```bash
cp private/config.ini.ci private/config.ini
```

Editá `private/config.ini` con los datos reales de tu entorno. Las secciones principales son:

```ini
[medley]
nameAyto = "Nombre de tu Organización"
app_env = "production"        ; "development" o "production"
app_debug = 0                 ; 1 solo en desarrollo, NUNCA en producción

[ldap]
ldap_protocol = "ldap://"     ; o "ldaps://" para TLS
ldap_host = "dc.tuorganizacion.local"
ldap_port = 389               ; 636 para LDAPS
ldap_admuser = "cn=admin,dc=tuorganizacion,dc=local"
ldap_admpwd = "contraseña"
ldap_domain[] = "tuorganizacion.local"
ldap_user = "cn=servicio,dc=tuorganizacion,dc=local"
ldap_pass = "contraseña_servicio"
ldap_dn = "dc=tuorganizacion,dc=local"
ldap_contacts_dn = "ou=contacts,dc=tuorganizacion,dc=local"
ldap_dn_ou[] = "ou=users,dc=tuorganizacion,dc=local"
ldap_dn_ubi = "ou=locations,dc=tuorganizacion,dc=local"
ldap_computers_dn = "ou=computers,dc=tuorganizacion,dc=local"

[smtp]
smtp_host = "smtp.tuorganizacion.com"
smtp_port = 587
smtp_user = "usuario"
smtp_pass = "contraseña"
mail_from_address = "directorio@tuorganizacion.com"
mail_from_name = "Directorio Corporativo"

[ip_filter]
ip_range[] = "192.168.1.0/24"   ; IPs con acceso sin login
ip_range[] = "10.0.0.0/8"

[presence_api]                   ; solo si usás Saviacloud
sync_interval = 300

[session]
remember_me_days = 30

[gpo]
password_min_length = 8
user_max_attempts_allowed = 5

[mail_domains]
corp_domain[] = "tuorganizacion.com"
```

> ⚠️ **Importante**: `private/config.ini` contiene credenciales. Está incluido en `.gitignore` y NUNCA debe subirse al repositorio. El archivo `private/config.ini.ci` es solo una plantilla para CI con valores dummy.

### 5. Verificar extensiones PHP

Asegurate de que las extensiones estén habilitadas en `php.ini`:

```ini
extension=ldap
extension=pdo_sqlite
extension=sqlite3
extension=mbstring
extension=openssl
extension=curl
```

Reiniciá Apache después de cualquier cambio en `php.ini`.

### 6. Permisos de escritura

La carpeta `data/` debe tener permisos de escritura para el usuario de Apache:

**Linux:**
```bash
chmod -R 775 data/
chown -R www-data:www-data data/
```

**Windows (XAMPP):** normalmente no requiere cambios adicionales.

La base de datos SQLite (`data/telefonos.db`) y sus tablas se crean automáticamente en el primer acceso.

### 7. Configurar el VirtualHost (opcional pero recomendado)

```apache
<VirtualHost *:80>
    ServerName directorio.tuorganizacion.local
    DocumentRoot /ruta/a/telefonos

    <Directory /ruta/a/telefonos>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 8. Acceder a la aplicación

Abrí `http://tu-servidor/` en el navegador. Si tu IP está en `ip_range`, entrarás directamente. Si no, verás la pantalla de login.

## Estructura del proyecto

```
├── private/             # Configuración sensible (no se versiona)
│   ├── config.ini       # Credenciales reales (gitignoreado)
│   └── config.ini.ci    # Plantilla dummy para CI
├── lib/                 # Librerías y lógica de negocio
│   ├── ldap_*.php       # Funciones LDAP/AD
│   ├── db_*.php         # Funciones SQLite
│   └── ...
├── css/
│   ├── input.css        # Fuente de Tailwind CSS
│   └── style.css        # CSS compilado
├── js/                  # JavaScript
├── images/              # Iconos, fotos de perfil
├── data/                # SQLite DB, logs (acceso web bloqueado)
├── tests/               # Tests PHPUnit
├── vendor/              # Dependencias Composer
├── node_modules/        # Dependencias npm
├── index.php            # Página principal (buscador escritorio)
├── mobile.php           # Versión móvil / PWA
├── login.php            # Login, recuperación de cuenta
├── totp.php             # Verificación 2FA
├── change_pwd.php       # Cambio de contraseña
├── contact_edit.php     # Edición de contacto
├── datos_active.php     # Búsqueda y resultados (via AJAX)
├── rescue.php           # Recuperación de cuenta
├── activate.php         # Activación de usuario
├── bootstrap.php        # Seguridad: sesión, cabeceras HTTP, config
├── tailwind.config.js   # Configuración de Tailwind
├── composer.json        # Dependencias PHP
├── package.json         # Dependencias Node
├── manifest.json        # PWA manifest
└── sw.js                # Service Worker (PWA)
```

## Tests

El proyecto incluye tests unitarios con PHPUnit:

```bash
composer test
# o directamente:
php vendor/phpunit/phpunit/phpunit
```

La configuración está en `phpunit.xml`. Los tests se ejecutan contra `private/config.ini.ci` para no depender de un AD real.

## Notas adicionales

- **Acceso a `private/` y `data/`**: ambas carpetas tienen `.htaccess` que bloquean el acceso web directo. No elimines esos archivos.
- **Entorno de desarrollo**: la sincronización de presencia (Saviacloud) solo se ejecuta en `app_env = "production"`. En desarrollo se aborta automáticamente.
- **LDAPS (TLS)**: en producción se recomienda `ldap_protocol = "ldaps://"` con `ldap_port = 636`. La verificación del certificado TLS (`ldap_reqcert`) por defecto es `demand` en producción y `never` en desarrollo.
- **Cache del navegador**: la aplicación deshabilita la caché para mostrar datos de AD en tiempo real.
- **Cookies de sesión**: en producción, las cookies se configuran automáticamente con el flag `Secure` si la conexión es HTTPS.

## Licencia

Propietaria. Uso interno corporativo.
