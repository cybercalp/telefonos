<?php
/**
 * Backend for searching and updating computer phone extensions.
 * Actions:
 *   - GET  ?action=search&q=<query>  → search computers by CN
 *   - POST  action=update, computer_dn=<dn>, phone=<number>  → update telephoneNumber
 *
 * Restricted to admin_users only.
 */

header('Content-Type: application/json');
require_once(__DIR__ . '/../private/config.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/ldap_permissions.php');
require_once(__DIR__ . '/csrf.php');

use LDAP\Client;

// Auth check
if (empty($_SESSION['is_authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Admin-only
if (!is_admin_user()) {
    echo json_encode(['success' => false, 'message' => 'Acceso restringido a administradores']);
    exit;
}

// CSRF check
if (!verify_csrf_token(get_token_from_request())) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// LDAP connection via Client::factory() (admin)
try {
    $client = Client::factory();
    $ldap_conn = $client->getResource();
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de config LDAP: ' . $e->getMessage()]);
    exit;
}

if (!$ldap_conn) {
    echo json_encode(['success' => false, 'message' => 'No se pudo conectar al servidor LDAP']);
    exit;
}

// ─── SEARCH ────────────────────────────────────────────────────────────
if ($action === 'search') {
    $query = trim($_GET['q'] ?? '');
    if (strlen($query) < 2) {
        echo json_encode([]);
        ldap_unbind($ldap_conn);
        exit;
    }

    if (empty($ldap_computers_dn)) {
        echo json_encode(['success' => false, 'message' => 'ldap_computers_dn no configurado']);
        ldap_unbind($ldap_conn);
        exit;
    }

    $q_escaped = ldap_escape($query, '', LDAP_ESCAPE_FILTER);
    $filter = "(&(objectClass=computer)(|(cn=*$q_escaped*)(location=*$q_escaped*)(description=*$q_escaped*)))";
    $attrs = ['cn', 'distinguishedname', 'telephonenumber', 'description', 'operatingsystem', 'location'];

    $sr = @ldap_search($ldap_conn, $ldap_computers_dn, $filter, $attrs, 0, 30);
    $results = [];

    if ($sr) {
        $entries = ldap_get_entries($ldap_conn, $sr);
        for ($i = 0; $i < $entries['count']; $i++) {
            $results[] = [
                'cn'    => $entries[$i]['cn'][0] ?? '',
                'dn'    => $entries[$i]['distinguishedname'][0] ?? '',
                'phone' => $entries[$i]['telephonenumber'][0] ?? '',
                'description' => $entries[$i]['description'][0] ?? '',
                'os'    => $entries[$i]['operatingsystem'][0] ?? '',
                'location' => $entries[$i]['location'][0] ?? '',
            ];
        }
        // Sort by CN alphabetically
        usort($results, function ($a, $b) {
            return strnatcasecmp($a['cn'], $b['cn']);
        });
    }

    echo json_encode($results);
    ldap_unbind($ldap_conn);
    exit;
}

// ─── UPDATE ────────────────────────────────────────────────────────────
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $computer_dn = $_POST['computer_dn'] ?? '';
    $phone = trim($_POST['phone'] ?? '');

    if (empty($computer_dn)) {
        echo json_encode(['success' => false, 'message' => 'DN del equipo no proporcionado']);
        ldap_unbind($ldap_conn);
        exit;
    }

    // Validate that the DN belongs to the computers OU (security check)
    if (stripos($computer_dn, $ldap_computers_dn) === false) {
        echo json_encode(['success' => false, 'message' => 'DN no pertenece a la OU de equipos']);
        ldap_unbind($ldap_conn);
        exit;
    }

    // If phone is empty, remove the attribute; otherwise set it
    if (empty($phone)) {
        $result = @ldap_mod_del($ldap_conn, $computer_dn, ['telephonenumber' => []]);
        // If attribute doesn't exist, that's OK
        if (!$result && ldap_errno($ldap_conn) == 16) {
            $result = true; // "No such attribute" is fine when deleting
        }
    } else {
        $result = @ldap_mod_replace($ldap_conn, $computer_dn, ['telephonenumber' => [$phone]]);
    }

    if (!$result) {
        $errno = ldap_errno($ldap_conn);
        $msg = 'Error al actualizar: ' . ldap_error($ldap_conn);
        if ($errno == 50) {
            $msg = 'Active Directory deniega la modificación. Faltan permisos AD para el usuario de servicio.';
        }
        echo json_encode(['success' => false, 'message' => $msg]);
    } else {
        echo json_encode(['success' => true, 'message' => 'Extensión actualizada correctamente']);
    }

    ldap_unbind($ldap_conn);
    exit;
}

// ─── CLEAR DESCRIPTION ────────────────────────────────────────────────────────────
if ($action === 'clear_description' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $computer_dn = $_POST['computer_dn'] ?? '';

    if (empty($computer_dn)) {
        echo json_encode(['success' => false, 'message' => 'DN del equipo no proporcionado']);
        ldap_unbind($ldap_conn);
        exit;
    }

    if (stripos($computer_dn, $ldap_computers_dn) === false) {
        echo json_encode(['success' => false, 'message' => 'DN no pertenece a la OU de equipos']);
        ldap_unbind($ldap_conn);
        exit;
    }

    $result = @ldap_mod_del($ldap_conn, $computer_dn, ['description' => []]);
    if (!$result && ldap_errno($ldap_conn) == 16) {
        $result = true; // "No such attribute"
    }

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . ldap_error($ldap_conn)]);
    } else {
        echo json_encode(['success' => true, 'message' => 'Descripción eliminada']);
    }

    ldap_unbind($ldap_conn);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
