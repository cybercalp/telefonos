<?php
/**
 * Buscador ligero para el modal de 'Añadir Pasen'
 */

header('Content-Type: application/json');
require_once(__DIR__ . '/../private/config.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/csrf.php');

if (empty($_SESSION['is_authenticated'])) {
    if ($app_debug) {
        error_log('ldap_search_users: Auth fail — usuario no autenticado');
    }
    echo json_encode([]);
    exit;
}

$csrf_req = get_token_from_request();
$csrf_ok = verify_csrf_token($csrf_req);

// Debug: solo en modo desarrollo, sin exponer valores de tokens
if ($app_debug) {
    error_log(sprintf(
        'ldap_search_users: q=%s type=%s csrf_ok=%s',
        $_GET['q'] ?? '',
        $_GET['type'] ?? '',
        $csrf_ok ? 'yes' : 'no'
    ));
}

// Verificación CSRF
if (!$csrf_ok) {
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$query = isset($_GET['q']) ? $_GET['q'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'users';

if (strlen($query) < 3) {
    echo json_encode([]);
    exit;
}

// Para contactos usamos siempre admin, para usuarios intentamos usar el del usuario si tiene Pass en sesión (para ver sus propios permisos)
// Pero en el portal de modernización, la búsqueda de contactos DEBE ser con admin.
$use_admin = true;
// Robust bind logic for admin: ensure UPN format if needed
$bind_user = $ldap_admuser;
if (strpos($bind_user, '=') === false && strpos($bind_user, '@') === false) {
    $bind_user .= '@' . ($ldap_domain[1] ?? $ldap_host);
}
$bind_pass = $ldap_admpwd;

$ldap_conn = ldap_connect(get_ldap_uri());
if (!$ldap_conn) {
    echo json_encode([]);
    exit;
}

ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

if (!ldap_bind($ldap_conn, $bind_user, $bind_pass)) {
    echo json_encode([]);
    exit;
}

// Filtro de búsqueda
// Soporte multi-palabra: dividir por espacios y comas para buscar en cualquier orden.
// "Moreno Nieves", "Nieves Moreno" y "Moreno, Nieves" encuentran el mismo resultado.
$query_parts = preg_split('/[\s,]+/', trim($query), -1, PREG_SPLIT_NO_EMPTY);

if (count($query_parts) > 1) {
    // Múltiples términos: cada uno debe estar en displayname (AND, sin importar el orden)
    $multi_name_filter = '(&';
    foreach ($query_parts as $part) {
        $p_esc = ldap_escape($part, '', LDAP_ESCAPE_FILTER);
        $multi_name_filter .= "(displayname=*$p_esc*)";
    }
    $multi_name_filter .= ')';
    $name_filter = $multi_name_filter;
} else {
    // Término único: buscar en nombre, apellido, SAM...
    $q_escaped = ldap_escape($query_parts[0] ?? $query, '', LDAP_ESCAPE_FILTER);
    $name_filter = "(|(displayname=*$q_escaped*)(sn=*$q_escaped*)(givenname=*$q_escaped*)(samaccountname=*$q_escaped*))";
}

if ($type === 'contacts') {
    // Buscar exclusivamente objetos de tipo 'contact' (NO usuarios)
    $filter = "(&(objectClass=contact)(!(objectClass=user))(objectCategory=contact)$name_filter)";
} elseif ($type === 'hierarchy') {
    // Buscar tanto usuarios activos como contactos para la jerarquía
    $filter = "(&(|(&(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))(objectClass=contact))$name_filter)";
} else {
    // Usuarios activos
    $filter = "(&(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2))$name_filter)";
}

// Determinar bases de búsqueda (aplicamos la restricción de OUs para todos los tipos)
$search_bases = [];
if (is_array($ldap_dn_ou) && !empty($ldap_dn_ou)) {
    foreach ($ldap_dn_ou as $ou_name) {
        $search_bases[] = "OU=" . $ou_name . "," . $ldap_dn;
    }
} elseif (!empty($ldap_dn_ou)) {
    $search_bases[] = "OU=" . $ldap_dn_ou . "," . $ldap_dn;
} else {
    // Fallback si no hay OUs definidas
    $search_bases[] = $ldap_dn;
}

$results = [];
$attrs = array('displayname', 'distinguishedname', 'samaccountname', 'title', 'thumbnailphoto', 'objectclass');

foreach ($search_bases as $base_dn) {
    if (empty($base_dn)) continue;
    $sr = @ldap_search($ldap_conn, $base_dn, $filter, $attrs);
    if (!$sr) continue;

    $entries = ldap_get_entries($ldap_conn, $sr);
    for ($i = 0; $i < $entries['count']; $i++) {
        $dn = $entries[$i]['distinguishedname'][0];
        if (isset($results[$dn])) continue;

        // Identificar tipo de objeto
        $classes = $entries[$i]['objectclass'] ?? [];
        $objType = 'Otro';
        if (in_array('contact', $classes)) $objType = 'Contacto';
        elseif (in_array('user', $classes)) $objType = 'Usuario';
        elseif (in_array('group', $classes)) $objType = 'Grupo';

        $results[$dn] = [
            'name' => $entries[$i]['displayname'][0] ?? $entries[$i]['samaccountname'][0] ?? 'Sin nombre',
            'dn' => $dn,
            'sam' => $entries[$i]['samaccountname'][0] ?? '',
            'title' => isset($entries[$i]['title'][0]) ? $entries[$i]['title'][0] : '',
            'photo' => isset($entries[$i]['thumbnailphoto'][0]) ? base64_encode($entries[$i]['thumbnailphoto'][0]) : null,
            'type' => $objType
        ];
        
        if (count($results) >= 15) break 2; // Suficientes resultados
    }
}

ldap_unbind($ldap_conn);

// Sort results alphabetically by display name
uasort($results, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

if ($app_debug) {
    error_log('ldap_search_users: Success. Found ' . count($results) . ' results.');
}
echo json_encode(array_values($results));
