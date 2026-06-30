<?php
/**
 * lib/ldap_contacts.php
 * Operaciones CRUD para objetos de tipo 'contact' en Active Directory
 */

require_once(__DIR__ . '/../private/config.php');
require_once(__DIR__ . '/ldap_permissions.php');

/**
 * Obtiene o crea la conexión administrativa LDAP (Singleton)
 */
function get_admin_ldap_connection() {
    static $ldap_conn = null;
    if ($ldap_conn !== null) return $ldap_conn;

    global $ldap_protocol, $ldap_host, $ldap_port, $ldap_admuser, $ldap_admpwd, $ldap_domain;
    
    $ldap_conn = @ldap_connect(get_ldap_uri());
    if (!$ldap_conn) return null;

    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    $bind_user = $ldap_admuser;
    if (strpos($bind_user, '=') === false && strpos($bind_user, '@') === false) {
        $bind_user .= '@' . ($ldap_domain[1] ?? $ldap_host);
    }
    
    if (!@ldap_bind($ldap_conn, $bind_user, $ldap_admpwd)) {
        $ldap_conn = null;
        return null;
    }

    // Cerrar la conexión limpiamente al terminar el script
    register_shutdown_function(function() use (&$ldap_conn) {
        if ($ldap_conn) {
            @ldap_unbind($ldap_conn);
            $ldap_conn = null;
        }
    });

    return $ldap_conn;
}

/**
 * Carga los datos de un contacto específico por su DN
 */
function load_contact_data($dn) {
    $ldap_conn = get_admin_ldap_connection();
    if (!$ldap_conn) return null;

    $attrs = ['displayname', 'givenname', 'sn', 'company', 'department', 'title', 'telephonenumber', 'mobile', 'mail', 'streetaddress', 'l', 'st', 'co', 'postalcode', 'wwwhomepage', 'thumbnailphoto', 'comment', 'secretary', 'info'];
    $sr = @ldap_read($ldap_conn, $dn, '(objectClass=*)', $attrs);
    
    if (!$sr) {
        return null;
    }

    $entries = ldap_get_entries($ldap_conn, $sr);
    return ($entries['count'] > 0) ? $entries[0] : null;
}

/**
 * Crea o actualiza un contacto
 */
function save_contact($dn = null) {
    global $ldap_protocol, $ldap_host, $ldap_port, $ldap_admuser, $ldap_admpwd, $ldap_dn, $ldap_domain, $ldap_contacts_dn;
    
    if (!can_edit_contact($dn)) {
        $_SESSION['mensaje'] = ['No tiene permisos para gestionar este contacto.'];
        $_SESSION['mensaje_css'] = 'no';
        return false;
    }

    $ldap_conn = get_admin_ldap_connection();
    if (!$ldap_conn) {
        $_SESSION['mensaje'] = ['Error de conexión administrativa'];
        $_SESSION['mensaje_css'] = 'no';
        return false;
    }
    
    $info = [];
    $toLdap = function($val) {
        if ($val === null || trim($val) === '') return array(); // Vacía el atributo en AD
        return trim($val);
    };

    $info['givenName'] = $toLdap($_POST['txtGivenName'] ?? '');
    $info['sn'] = $toLdap($_POST['txtSN'] ?? '');
    $info['displayName'] = $toLdap($_POST['txtDisplayName'] ?? '');
    // $info['company'] = $toLdap($_POST['txtCompany'] ?? ''); // Eliminado del formulario
    $info['department'] = $toLdap($_POST['txtDept'] ?? '');
    $info['title'] = $toLdap($_POST['txtTitle'] ?? '');
    $info['telephoneNumber'] = $toLdap($_POST['txtTel'] ?? '');
    $info['mobile'] = $toLdap($_POST['txtMobile'] ?? '');
    $info['mail'] = $toLdap($_POST['txtEmail'] ?? '');
    $info['streetAddress'] = $toLdap($_POST['txtAddress'] ?? '');
    $info['l'] = $toLdap($_POST['txtCity'] ?? '');
    $info['st'] = $toLdap($_POST['txtState'] ?? '');
    $info['co'] = $toLdap($_POST['txtCountry'] ?? '');
    $info['postalCode'] = $toLdap($_POST['txtPostalCode'] ?? '');
    $info['info'] = $toLdap($_POST['txtInfo'] ?? '');
    
    // Visibilidad (wWWHomePage: 4 dígitos)
    $s1 = $_POST['txtSwitch1'] ?? '1'; // Visible
    $s2 = $_POST['txtSwitch2'] ?? '1'; // Foto
    $s3 = $_POST['txtSwitch3'] ?? '1'; // Email
    $s4 = $_POST['txtSwitch4'] ?? '0'; // Ocultar Presencia
    $info['wWWHomePage'] = $s1 . $s2 . $s3 . $s4;

    if (!empty($_POST['txtPhoto'])) {
        $data = $_POST['txtPhoto'];
        if (preg_match('/^data:(image\/[a-z]+);base64,(.+)$/', $data, $matches)) {
            $info['thumbnailPhoto'] = base64_decode($matches[2]);
        }
    } elseif ($dn) {
        // Si la foto llega vacía en una edición, borrar el atributo en AD
        $info['thumbnailPhoto'] = [];
    }

    $success = false;
    if ($dn) {
        $target_dn = $dn;
        $success = @ldap_mod_replace($ldap_conn, $target_dn, $info);
    } else {
        // NEW CONTACT: ldap_add rejects empty arrays
        $info = array_filter($info, function($val) {
            return !is_array($val) || !empty($val);
        });

        $info['objectClass'] = ['top', 'person', 'organizationalPerson', 'contact'];
        
        $cn = $info['displayName'] ?? '';
        if (empty($cn)) {
            $gn = $info['givenName'] ?? '';
            $sn = $info['sn'] ?? '';
            $cn = trim($gn . ' ' . $sn);
        }
        
        $new_dn = "CN=" . ldap_escape($cn, '', LDAP_ESCAPE_DN) . "," . $ldap_contacts_dn;
        $success = @ldap_add($ldap_conn, $new_dn, $info);
    }

    if ($success) {
        $_SESSION['mensaje'] = ['Contacto guardado correctamente.'];
        $_SESSION['mensaje_css'] = 'yes';
    } else {
        $_SESSION['mensaje'] = ['Error LDAP (Admin): ' . ldap_error($ldap_conn)];
        $_SESSION['mensaje_css'] = 'no';
    }

    return $success;
}

/**
 * Elimina un contacto
 */
function delete_contact($dn) {
    global $ldap_protocol, $ldap_host, $ldap_port, $ldap_admuser, $ldap_admpwd, $ldap_domain;

    if (!can_edit_contact($dn)) return false;

    $ldap_conn = get_admin_ldap_connection();
    if (!$ldap_conn) return false;

    $target_dn = $dn;
    $success = @ldap_delete($ldap_conn, $target_dn);

    if ($success) {
        $_SESSION['mensaje'] = ['Contacto eliminado correctamente.'];
        $_SESSION['mensaje_css'] = 'yes';
    } else {
        $_SESSION['mensaje'] = ['Error al eliminar el contacto: ' . ldap_error($ldap_conn)];
        $_SESSION['mensaje_css'] = 'no';
    }

    return $success;
}
