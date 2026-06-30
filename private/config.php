<?php
// Configuración global de codificación
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Cargamos la configuración como un array.
$config = parse_ini_file(__DIR__ . '/config.ini', true);

// Variable nombre Ayto
$nameAyto = $config['medley']['nameAyto'] ?? 'Ayuntamiento';
// Entorno de la aplicación (production, development, etc.)
$app_env = getenv('APP_ENV') ?: ($config['medley']['app_env'] ?? 'development');
// Modo depuración (0=desactivado, 1=activado)
$app_debug = (int)($config['medley']['app_debug'] ?? 0);

// Dynamic LDAP TLS verification level: default 'demand' in production, 'never' in development/others. Can be overridden in config.ini.
$ldap_reqcert = (!empty($config['ldap']['ldap_reqcert'])) ? $config['ldap']['ldap_reqcert'] : ($app_env === 'production' ? 'demand' : 'never');
putenv("LDAPTLS_REQCERT=$ldap_reqcert");

// Configurable custom CA bundle for cURL/stream operations
$curl_ca_bundle = (!empty($config['medley']['curl_ca_bundle'])) ? $config['medley']['curl_ca_bundle'] : null;

$ldap_protocol = $config['ldap']['ldap_protocol'] ?? 'ldap://';
$ldap_host     = $config['ldap']['ldap_host'] ?? '';
$ldap_port     = $config['ldap']['ldap_port'] ?? 389;
$ldap_admuser  = $config['ldap']['ldap_admuser'] ?? '';
$ldap_admpwd   = $config['ldap']['ldap_admpwd'] ?? '';
$ldap_domain   = $config['ldap']['ldap_domain'] ?? array();
$ldap_user     = $config['ldap']['ldap_user'] ?? '';
$ldap_pass     = $config['ldap']['ldap_pass'] ?? '';
$ldap_dn       = $config['ldap']['ldap_dn'] ?? '';
$ldap_contacts_dn = $config['ldap']['ldap_contacts_dn'] ?? '';
$ldap_dn_ubi   = $config['ldap']['ldap_dn_ubi'] ?? '';
$ldap_computers_dn = $config['ldap']['ldap_computers_dn'] ?? '';
$filter_ubi    = $config['ldap']['filter_ubi'] ?? '';
$ldap_dn_ou    = $config['ldap']['ldap_dn_ou'] ?? array();

/**
 * Returns a space-separated string of LDAP URIs.
 * Allows using multiple servers in ldap_host (e.g. "server1, server2").
 */
function get_ldap_uri() {
    global $ldap_protocol, $ldap_host, $ldap_port;
    
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    // Si ya tenemos un host activo para esta sesión, lo usamos para garantizar consistencia de lectura/escritura (evita delay de replicación AD)
    if (!empty($_SESSION['active_ldap_host'])) {
        return $ldap_protocol . $_SESSION['active_ldap_host'] . ':' . $ldap_port;
    }
    
    $hosts = preg_split('/[\s,]+/', trim($ldap_host), -1, PREG_SPLIT_NO_EMPTY);
    
    // Probar conectividad rápida para seleccionar el DC activo y cachearlo
    foreach ($hosts as $h) {
        $port = (int)$ldap_port;
        // fsockopen con timeout corto de 0.8s para evitar bloqueos
        $test = @fsockopen($h, $port, $errno, $errstr, 0.8);
        if ($test) {
            fclose($test);
            $_SESSION['active_ldap_host'] = $h;
            return $ldap_protocol . $h . ':' . $port;
        }
    }
    
    // Fallback si falla el test rápido de sockets
    $uris = [];
    foreach ($hosts as $h) {
        $uris[] = $ldap_protocol . $h . ':' . $ldap_port;
    }
    return implode(' ', $uris);
}

// Variables para la conexión con el SMTP
$smtp_host = $config['smtp']['smtp_host'] ?? '';
$smtp_port = $config['smtp']['smtp_port'] ?? '';
$smtp_user = $config['smtp']['smtp_user'] ?? '';
$smtp_pass = $config['smtp']['smtp_pass'] ?? '';
$mail_from_address  = $config['smtp']['mail_from_address'] ?? '';
$mail_from_name     = $config['smtp']['mail_from_name'] ?? '';
$mail_reply_address = $config['smtp']['mail_reply_address'] ?? '';
$mail_reply_name    = $config['smtp']['mail_reply_name'] ?? '';

// Variables para la gestión de los tokens
$time_assignment = $config['tokens']['time_assignment'] ?? 86400;

// Variables para login fallidos
$limit_attempts = $config['attempts']['limit_attempts'] ?? 5;
$block_time = $config['attempts']['block_time'] ?? 900;

// Variables para el cambio de password 
$password_min_length = $config['gpo']['password_min_length'] ?? 8;
$user_max_attempts_allowed = $config['gpo']['user_max_attempts_allowed'] ?? 5;

// Lista de rangos permitidos para la consulta de la aplicación
$ip_range = $config['ip_filter']['ip_range'] ?? array();

// Configuración para la sincronización de presencia Saviacloud
$presence_config = $config['presence_api'] ?? array();
$presence_sync_interval = $presence_config['sync_interval'] ?? 300;

// Configuración de sesión persistente
$remember_me_days = $config['session']['remember_me_days'] ?? 30;

// Dominios de correo corporativos permitidos
$corp_domains = $config['mail_domains']['corp_domain'] ?? array('ajcalp.es');
