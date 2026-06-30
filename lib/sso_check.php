<?php
/**
 * sso_check.php
 * Comprueba si Apache ha autenticado al usuario mediante SSPI/NTLM (mod_auth_sspi)
 * y, si es así, rellena la sesión sin necesidad de formulario de login.
 *
 * Variables de sesión que establece (igual que el login normal):
 *   $_SESSION['ldap_user']        => samAccountName
 *   $_SESSION['is_authenticated'] => true
 *   $_SESSION['sso_login']        => true  (marcador para saber que fue SSO)
 *
 * Como NO tenemos la contraseña del usuario, NO almacenamos ldap_pass.
 * Las operaciones que la requieran (cambio de pwd, etc.) seguirán pidiendo credenciales.
 */

require_once(__DIR__ . '/../private/config.php');

function sso_check() {
    // Si ya hay sesión activa, no hacemos nada
    if (!empty($_SESSION['is_authenticated'])) {
        return false;
    }

    // Si el usuario está intentando un login manual (POST), respetamos su intención 
    // y no intentamos el SSO para evitar conflictos.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return false;
    }

    // Apache pone el usuario autenticado en REMOTE_USER o AUTH_USER
    $remoteUser = isset($_SERVER['REMOTE_USER'])
                    ? $_SERVER['REMOTE_USER']
                    : (isset($_SERVER['AUTH_USER']) ? $_SERVER['AUTH_USER'] : null);

    if (empty($remoteUser)) {
        return false; // Apache no ha hecho SSO, sigamos al formulario normal
    }

    // Extraer solo el samAccountName (quitar DOMINIO\ o @dominio.local)
    $sam = $remoteUser;
    if (strpos($sam, '\\') !== false) {
        // Formato DOMINIO\usuario
        $parts = explode('\\', $sam);
        $sam = end($parts);
    } elseif (strpos($sam, '@') !== false) {
        // Formato usuario@dominio
        $parts = explode('@', $sam);
        $sam = reset($parts);
    }
    $sam = strtolower(trim($sam));

    if (empty($sam)) {
        return false;
    }

    // Verificar que el usuario existe y está activo en AD (usando credenciales de servicio)
    global $ldap_protocol, $ldap_host, $ldap_port, $ldap_dn, $ldap_user, $ldap_pass;

    $ldap_conn = ldap_connect(get_ldap_uri());
    if (!$ldap_conn) {
        error_log('[SSO] No se pudo conectar al servidor LDAP para verificar SSO.');
        return false;
    }

    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    if (!ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
        error_log('[SSO] Fallo al hacer bind de servicio LDAP en sso_check.');
        return false;
    }

    $filter = '(&(samaccountname=' . ldap_escape($sam, '', LDAP_ESCAPE_FILTER) . ')'
            . '(objectClass=user)(objectCategory=person)'
            . '(!(userAccountControl:1.2.840.113556.1.4.803:=2)))'; // cuenta activa

    $attrs = array('samaccountname', 'displayname', 'pager', 'objectguid', 'distinguishedname');
    $result = ldap_search($ldap_conn, $ldap_dn, $filter, $attrs);

    if (!$result) {
        error_log('[SSO] Búsqueda LDAP fallida para sam: ' . $sam);
        ldap_unbind($ldap_conn);
        return false;
    }

    $entries = ldap_get_entries($ldap_conn, $result);
    ldap_unbind($ldap_conn);

    if ($entries['count'] === 0) {
        error_log('[SSO] Usuario SSO no encontrado o inactivo en AD: ' . $sam);
        return false;
    }

    // ✅ Usuario válido — rellenar sesión
    $_SESSION['ldap_user']        = $sam;
    $_SESSION['is_authenticated'] = true;
    $_SESSION['sso_login']        = true;
    $_SESSION['auth_user_dn']     = $entries[0]['distinguishedname'][0];

    // Recuperar 2FA si lo tiene (igual que en el login normal)
    if (isset($entries[0]['objectguid']) && isset($entries[0]['pager'])) {
        require_once(__DIR__ . '/bintoGUID.php');
        require_once(__DIR__ . '/crypt.php');
        $guid = binToGUID($entries[0]['objectguid'][0]);
        $_SESSION['secretkey'] = decryptSecretGCM($entries[0]['pager'][0], $guid);
    } else {
        unset($_SESSION['secretkey']);
    }

    error_log('[SSO] Login SSO exitoso para: ' . $sam);
    return true;
}
?>
