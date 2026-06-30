<?php
/**
 * Librería de permisos jerárquicos para Active Directory
 */

require_once(__DIR__ . '/../private/config.php');

/**
 * Comprueba si un usuario puede editar a otro basándose en la jerarquía (manager)
 * @param mixed $ldap_conn Conexión LDAP activa
 * @param string $target_dn DN del usuario que se quiere editar
 * @return bool True si tiene permiso, False de lo contrario
 */
function can_edit_user($ldap_conn, $target_dn, $target_sam = '') {
    $current_user_dn = !empty($_SESSION['auth_user_dn']) ? $_SESSION['auth_user_dn'] : (isset($_SESSION['ldap_user_dn']) ? $_SESSION['ldap_user_dn'] : '');
    $current_sam = isset($_SESSION['ldap_user']) ? $_SESSION['ldap_user'] : '';

    // Si es él mismo (por SAM)
    if (!empty($current_sam) && !empty($target_sam) && strcasecmp(trim($current_sam), trim($target_sam)) === 0) return true;

    // Si es él mismo (por DN)
    // Usamos una comparación más robusta que tolere diferencias de escape (ej: \ vs \\) o encoding
    if (!empty($current_user_dn) && !empty($target_dn)) {
        $clean_current = strtolower(str_replace(['\\', ' '], ['', ''], $current_user_dn));
        $clean_target = strtolower(str_replace(['\\', ' '], ['', ''], $target_dn));
        if ($clean_current === $clean_target) return true;
    }

    // 1.4. Comprobación de si es ADMINISTRADOR (Permiso total)
    if (is_admin_user()) {
        return true;
    }

    // 1.5. Comprobación de gestión de CONTACTOS delegada
    // Si el objeto está en la OU de contactos y el usuario es gestor de contactos, tiene permiso.
    if (strpos(strtolower($target_dn), 'ou=contactos') !== false && can_manage_contacts()) {
        return true;
    }


    if (empty($current_user_dn)) return false;
    
    // 2. Comprobación jerárquica (Recursive manager check)
    return is_in_manager_chain($ldap_conn, $current_user_dn, $target_dn);
}

/**
 * Función recursiva para buscar en la cadena de mando
 */
function is_in_manager_chain($ldap_conn, $superior_dn, $target_dn, $depth = 0) {
    // Límite de seguridad para evitar bucles infinitos o profundidad excesiva (ej: 10 niveles)
    if ($depth > 10) return false;

    // Obtenemos el manager del target_dn usando ldap_read (mucho más fiable que search por DN)
    $attrs = array('manager');
    $sr = @ldap_read($ldap_conn, $target_dn, '(objectClass=*)', $attrs);
    
    if (!$sr) return false;
    
    $entries = ldap_get_entries($ldap_conn, $sr);
    if ($entries['count'] == 0 || !isset($entries[0]['manager'])) return false;
    
    $direct_manager_dn = $entries[0]['manager'][0];
    
    // Comparación robusta de DNs (normalizando espacios y caracteres de escape)
    $normalize = function($dn) {
        return strtolower(str_replace(['\\', ' '], ['', ''], (string)$dn));
    };

    // Si el manager directo es el usuario actual, ¡Bingo!
    $is_match = ($normalize($direct_manager_dn) === $normalize($superior_dn));
    if ($is_match) return true;
    
    // Si no, seguimos subiendo en la jerarquía
    return is_in_manager_chain($ldap_conn, $superior_dn, $direct_manager_dn, $depth + 1);
}

/**
 * Comprueba si el usuario actual tiene permisos para gestionar contactos
 * @return bool
 */
function can_manage_contacts() {
    global $config;
    if (empty($_SESSION['ldap_user'])) return false;
    
    $current_user = strtolower(trim($_SESSION['ldap_user']));
    $managers_str = isset($config['contacts']['contact_managers']) ? $config['contacts']['contact_managers'] : '';
    
    if (empty($managers_str)) return false;
    
    $managers = array_map('trim', explode(',', strtolower($managers_str)));
    
    return in_array($current_user, $managers);
}

/**
 * Comprueba si el usuario actual tiene permisos de ADMINISTRADOR (Acceso total)
 * @return bool
 */
function is_admin_user() {
    global $config;
    if (empty($_SESSION['ldap_user'])) return false;
    
    $current_user = strtolower(trim($_SESSION['ldap_user']));
    $admins_str = isset($config['contacts']['admin_users']) ? $config['contacts']['admin_users'] : '';
    
    if (empty($admins_str)) return false;
    
    $admins = array_map('trim', explode(',', strtolower($admins_str)));
    
    return in_array($current_user, $admins);
}

/**
 * Comprueba si el usuario actual puede editar un contacto específico (Delegado)
 * @param string $dn DN del contacto a editar
 * @param mixed $ldap_conn Conexión LDAP opcional para reutilizar
 * @return bool
 */
function can_edit_contact($dn, $ldap_conn = null) {
    global $ldap_protocol, $ldap_host, $ldap_port, $ldap_admuser, $ldap_admpwd, $ldap_domain;
    
    // 0. Si es administrador, tiene permiso siempre
    if (is_admin_user()) return true;

    // 1. Si es gestor de contactos global, tiene permiso siempre
    if (can_manage_contacts()) return true;

    // 2. Si no hay DN (creación), solo los gestores globales pueden (punto 1)
    if (empty($dn)) return false;

    // 3. Comprobar si es el manager del contacto específico
    $current_user_dn = !empty($_SESSION['auth_user_dn']) ? $_SESSION['auth_user_dn'] : (isset($_SESSION['ldap_user_dn']) ? $_SESSION['ldap_user_dn'] : '');
    if (empty($current_user_dn)) {
        return false;
    }

    $should_close = false;
    if (!$ldap_conn) {
        $ldap_conn = @ldap_connect(get_ldap_uri());
        if (!$ldap_conn) return false;

        @ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

        // Bind administrativo para consultar el manager
        $bind_user = $ldap_admuser;
        if (strpos($bind_user, '=') === false && strpos($bind_user, '@') === false) {
            $bind_user .= '@' . ($ldap_domain[1] ?? $ldap_host);
        }
        
        if (!@ldap_bind($ldap_conn, $bind_user, $ldap_admpwd)) {
            ldap_unbind($ldap_conn);
            return false;
        }
        $should_close = true;
    }
    
    // Usamos la lógica jerárquica existente
    $success = is_in_manager_chain($ldap_conn, $current_user_dn, $dn);

    if ($should_close) {
        ldap_unbind($ldap_conn);
    }
    return $success;
}
