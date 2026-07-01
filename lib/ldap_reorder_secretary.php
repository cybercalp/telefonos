<?php
/**
 * Backend para reordenar la lista 'secretary' (Pasar llamadas a) mediante Drag & Drop
 * Persiste el orden en el atributo 'comment' (límite 1024 caracteres)
 */

header('Content-Type: application/json');
require_once(__DIR__ . '/../private/config.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('./ldap_permissions.php');
require_once(__DIR__ . '/csrf.php');

use LDAP\Client;

if (empty($_SESSION['is_authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Verificación CSRF
if (!verify_csrf_token(get_token_from_request())) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$user_dn = isset($_POST['target_dn']) ? $_POST['target_dn'] : '';
$new_order_json = isset($_POST['new_order']) ? $_POST['new_order'] : '';

if (!$user_dn || !$new_order_json) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

$new_order = json_decode($new_order_json, true);
if (!is_array($new_order)) {
    echo json_encode(['success' => false, 'message' => 'Formato de orden inválido']);
    exit;
}

// LDAP connection via Client::factory() (admin)
try {
    $client = Client::factory();
    $ldap_conn = $client->getResource();
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de config LDAP']);
    exit;
}

if (!$ldap_conn) {
    echo json_encode(['success' => false, 'message' => 'Fallo de bindeo admin']);
    exit;
}

$session_dn = isset($_SESSION['auth_user_dn']) ? $_SESSION['auth_user_dn'] : (isset($_SESSION['ldap_user_dn']) ? $_SESSION['ldap_user_dn'] : '');
$isSelf = !empty($session_dn) && (strcasecmp(trim(str_replace(['\\', ' '], ['', ''], $session_dn)), trim(str_replace(['\\', ' '], ['', ''], $user_dn))) === 0);

// Determinar si el target es un contacto (su DN contiene objectClass=contact) o un usuario
// y verificar permisos con la función adecuada.
$sr_check = @ldap_read($ldap_conn, $user_dn, '(objectClass=*)', ['objectclass']);
$is_contact = false;
if ($sr_check) {
    $ent_check = ldap_get_entries($ldap_conn, $sr_check);
    if (!empty($ent_check[0]['objectclass'])) {
        $classes = array_map('strtolower', (array)$ent_check[0]['objectclass']);
        $is_contact = in_array('contact', $classes);
    }
}

$has_permission = $is_contact
    ? can_edit_contact($user_dn, $ldap_conn)
    : can_edit_user($ldap_conn, $user_dn);

if (!$has_permission) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    
    exit;
}

// Eliminamos utf8_encode para evitar problemas de doble codificación
$target_dn = $user_dn;

// 1. Persistir orden en comment y secretary
$modifs = [
    'secretary' => $new_order ?: [],
    'comment' => 'SEC-ORDER:' . implode('|', $new_order)
];

if (empty($modifs['secretary'])) {
    $modifs['secretary'] = [];
    $modifs['comment'] = [];
}

$result = @ldap_mod_replace($ldap_conn, $target_dn, $modifs);

    // El resultado depende del bindeo admin previo
    if (!$result) {
        $msg = "Error al operar: " . ldap_error($ldap_conn);
        if (ldap_errno($ldap_conn) == 50) {
            $msg = "Error: Active Directory deniega modificar secretary de $target_dn. ¡Faltan permisos AD en Opers. de Cuentas!";
        }
    }

echo json_encode(['success' => $result, 'message' => $result ? '' : $msg]);

?>
