<?php
/**
 * Backend para añadir/quitar usuarios en la lista 'secretary' (Pasar llamadas a)
 * Usa 'comment' para persistir el orden (límite 1024 caracteres)
 */

header('Content-Type: application/json');
require_once(__DIR__ . '/../private/config.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('./ldap_permissions.php');
require_once(__DIR__ . '/csrf.php');

if (empty($_SESSION['is_authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Verificación CSRF
if (!verify_csrf_token(get_token_from_request())) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$user_dn = isset($_POST['target_dn']) ? $_POST['target_dn'] : '';
$secretary_dn = isset($_POST['secretary_dn']) ? $_POST['secretary_dn'] : '';

if (!$action || !$user_dn || !$secretary_dn) {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
    exit;
}

$ldap_conn = ldap_connect(get_ldap_uri());
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

$session_dn = isset($_SESSION['auth_user_dn']) ? $_SESSION['auth_user_dn'] : (isset($_SESSION['ldap_user_dn']) ? $_SESSION['ldap_user_dn'] : '');
$isSelf = !empty($session_dn) && (strcasecmp(trim(str_replace(['\\', ' '], ['', ''], $session_dn)), trim(str_replace(['\\', ' '], ['', ''], $user_dn))) === 0);

// Robust bind logic for admin: ensure UPN format if needed
$bind_user = $ldap_admuser;
if (strpos($bind_user, '=') === false && strpos($bind_user, '@') === false) {
    $bind_user .= '@' . ($ldap_domain[1] ?? $ldap_host);
}

if (!@ldap_bind($ldap_conn, $bind_user, $ldap_admpwd)) {
    echo json_encode(['success' => false, 'message' => 'Error de bindeo admin']);
    ldap_unbind($ldap_conn);
    exit;
}

if (!can_edit_user($ldap_conn, $user_dn)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    ldap_unbind($ldap_conn);
    exit;
}

// Eliminamos utf8_encode ya que puede causar doble codificación si los datos ya vienen en UTF-8
$target_dn = $user_dn;
$sec_dn = $secretary_dn;

// 1. Obtener estado actual
$sr = ldap_read($ldap_conn, $target_dn, '(objectClass=*)', ['comment', 'secretary']);
$entries = ldap_get_entries($ldap_conn, $sr);

$ordered_dns = [];
if (isset($entries[0]['comment'][0]) && strpos($entries[0]['comment'][0], 'SEC-ORDER:') === 0) {
    $raw = substr($entries[0]['comment'][0], 10);
    $ordered_dns = array_filter(explode('|', $raw));
} elseif (isset($entries[0]['secretary'])) {
    for ($i=0; $i<$entries[0]['secretary']['count']; $i++) {
        $ordered_dns[] = $entries[0]['secretary'][$i];
    }
}

// 2. Aplicar cambio
if ($action === 'add') {
    if (!in_array($sec_dn, $ordered_dns)) $ordered_dns[] = $sec_dn;
} else {
    $ordered_dns = array_filter($ordered_dns, function($d) use ($sec_dn) {
        return strcasecmp($d, $sec_dn) !== 0;
    });
}

// 3. Persistir
$modifs = [
    'secretary' => array_values($ordered_dns),
    'comment' => 'SEC-ORDER:' . implode('|', $ordered_dns)
];

if (empty($modifs['secretary'])) {
    $modifs['secretary'] = [];
    $modifs['comment'] = []; // Limpiar si no hay nadie
}

$result = @ldap_mod_replace($ldap_conn, $target_dn, $modifs);

    // El resultado depende del bindeo admin previo
    if (!$result) {
        $msg = "Error al operar: " . ldap_error($ldap_conn);
        if (ldap_errno($ldap_conn) == 50) {
            $msg = "Error: (A pesar de que $bind_user está validado, Active Directory deniega modificar secretary de $target_dn. ¡Faltan permisos AD en Opers. de Cuentas!)";
        }
    }

echo json_encode(['success' => $result, 'message' => $result ? '' : $msg]);
ldap_unbind($ldap_conn);
?>
