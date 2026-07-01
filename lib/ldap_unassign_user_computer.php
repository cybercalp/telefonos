<?php
require_once(__DIR__ . '/../private/config.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/ldap_permissions.php');
require_once(__DIR__ . '/csrf.php');

use LDAP\Client;

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['is_authenticated']) || empty($_SESSION['ldap_user'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if (!verify_csrf_token(get_token_from_request())) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$target_sam = $_POST['target_sam'] ?? '';
if (empty($target_sam)) {
    echo json_encode(['success' => false, 'message' => 'Usuario destino no proporcionado']);
    exit;
}

// LDAP connection via Client::factory() (admin)
try {
    $client = Client::factory();
    $ldap_conn = $client->getResource();
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión LDAP']);
    exit;
}

if (!$ldap_conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión LDAP']);
    exit;
}

// Validar permisos
if (!is_admin_user() && strcasecmp($_SESSION['ldap_user'], $target_sam) !== 0) {
    echo json_encode(['success' => false, 'message' => 'Permiso denegado para modificar este usuario']);
    
    exit;
}

// Buscar el DN EXACTO del usuario usando su samaccountname
// Buscar desde la raíz del dominio para no fallar por sub-OUs
$root_dn = preg_replace('/^.*?DC=/i', 'DC=', $ldap_dn);
$filter = "(sAMAccountName=" . ldap_escape($target_sam, "", LDAP_ESCAPE_FILTER) . ")";
$search = @ldap_search($ldap_conn, $root_dn, $filter, ['dn']);
if (!$search || ldap_count_entries($ldap_conn, $search) === 0) {
    echo json_encode(['success' => false, 'message' => "No se encontró tu usuario en AD. root='$root_dn' err='" . ldap_error($ldap_conn) . "'"]);
    
    exit;
}

$entries = ldap_get_entries($ldap_conn, $search);
$my_dn = $entries[0]['dn'];

// Usamos ldap_mod_del para borrar explícitamente el atributo
// En PHP, replace con array vacío suele ignorarse en AD devolviendo Success sin hacer nada.
$success1 = @ldap_mod_del($ldap_conn, $my_dn, ['userWorkstations' => []]);
$err1 = ldap_errno($ldap_conn) . '-' . ldap_error($ldap_conn);

$success2 = @ldap_mod_del($ldap_conn, $my_dn, ['logonWorkstation' => []]);
$err2 = ldap_errno($ldap_conn) . '-' . ldap_error($ldap_conn);

// Consideramos éxito si alguna de las dos funcionó (success) o si devolvió 16 (No such attribute)
$ok1 = ($success1 || ldap_errno($ldap_conn) == 16);
$ok2 = ($success2 || ldap_errno($ldap_conn) == 16);

if ($success1 || $success2) {
    echo json_encode(['success' => true, 'message' => "Borrado correctamente. Err1($err1) Err2($err2)"]);
} else if ($ok1 && $ok2) {
    echo json_encode(['success' => true, 'message' => "Ya estaba borrado. Err1($err1) Err2($err2)"]);
} else {
    echo json_encode(['success' => false, 'message' => "Error LDAP: Err1($err1), Err2($err2)"]);
}


