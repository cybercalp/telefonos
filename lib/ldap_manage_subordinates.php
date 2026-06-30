<?php
/**
 * Backend para gestionar subordinados (quiénes reportan a este usuario)
 * Atributo AD: 'manager' en los objetos subordinados
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

// Los permisos se verifican por acción más abajo
// list permitida para cualquier usuario autenticado
// add/remove requieren is_admin_user()

$action = isset($_POST['action']) ? $_POST['action'] : '';
$boss_dn = isset($_POST['target_dn']) ? $_POST['target_dn'] : ''; // El DN del "jefe"
$sub_dn = isset($_POST['subordinate_dn']) ? $_POST['subordinate_dn'] : ''; // El DN del "subordinado"

if (!$action || !$boss_dn) {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
    exit;
}

$ldap_conn = ldap_connect(get_ldap_uri());
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

// Robust bind logic for admin
$bind_user = $ldap_admuser;
if (strpos($bind_user, '=') === false && strpos($bind_user, '@') === false) {
    $bind_user .= '@' . ($ldap_domain[1] ?? $ldap_host);
}

if (!@ldap_bind($ldap_conn, $bind_user, $ldap_admpwd)) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión administrativa']);
    ldap_unbind($ldap_conn);
    exit;
}

if ($action === 'list') {
    // 1. Obtener el JEFE del usuario gestionado ($boss_dn)
    $manager_data = null;
    $sr_target = @ldap_read($ldap_conn, $boss_dn, '(objectClass=*)', ['manager']);
    if ($sr_target) {
        $entry_target = ldap_get_entries($ldap_conn, $sr_target);
        if ($entry_target['count'] > 0 && isset($entry_target[0]['manager'][0])) {
            $manager_dn = $entry_target[0]['manager'][0];
            // Buscar perfil del jefe
            $sr_manager = @ldap_read($ldap_conn, $manager_dn, '(objectClass=*)', ['displayname', 'samaccountname', 'thumbnailphoto']);
            if ($sr_manager) {
                $entry_manager = ldap_get_entries($ldap_conn, $sr_manager);
                if ($entry_manager['count'] > 0) {
                    $manager_data = [
                        'name' => $entry_manager[0]['displayname'][0] ?? $entry_manager[0]['samaccountname'][0] ?? 'Sin nombre',
                        'dn' => $manager_dn,
                        'sam' => $entry_manager[0]['samaccountname'][0] ?? '',
                        'photo' => isset($entry_manager[0]['thumbnailphoto'][0]) ? base64_encode($entry_manager[0]['thumbnailphoto'][0]) : null
                    ];
                }
            }
        }
    }

    // 2. Buscar todos los que tengan manager == $boss_dn (Subordinados: Usuarios y Contactos)
    // Escapar el DN para el filtro (Importante)
    $q_escaped = ldap_escape($boss_dn, '', LDAP_ESCAPE_FILTER);
    $filter = "(&(manager=$q_escaped)(|(objectClass=user)(objectClass=contact)))";
    $attrs = ['displayname', 'distinguishedname', 'samaccountname', 'thumbnailphoto', 'objectclass'];
    
    $sr = @ldap_search($ldap_conn, $ldap_dn, $filter, $attrs);
    $results = [];
    if ($sr) {
        $entries = ldap_get_entries($ldap_conn, $sr);
        for ($i = 0; $i < $entries['count']; $i++) {
            $classes = $entries[$i]['objectclass'] ?? [];
            $objType = 'Otro';
            if (in_array('contact', $classes)) $objType = 'Contacto';
            elseif (in_array('user', $classes)) $objType = 'Usuario';

            $results[] = [
                'name' => $entries[$i]['displayname'][0] ?? $entries[$i]['samaccountname'][0] ?? 'Sin nombre',
                'dn' => $entries[$i]['distinguishedname'][0],
                'sam' => $entries[$i]['samaccountname'][0] ?? '',
                'photo' => isset($entries[$i]['thumbnailphoto'][0]) ? base64_encode($entries[$i]['thumbnailphoto'][0]) : null,
                'type' => $objType
            ];
        }
    }
    echo json_encode(['success' => true, 'data' => $results, 'manager' => $manager_data]);

} elseif ($action === 'add') {
    // Solo administradores pueden añadir
    if (!is_admin_user()) {
        echo json_encode(['success' => false, 'message' => 'Sin permisos de administrador para añadir']);
        exit;
    }

    if (!$sub_dn) {
        echo json_encode(['success' => false, 'message' => 'DN del subordinado no especificado']);
        exit;
    }
    
    // Si sub_dn parece un JSON (empieza por [), procesamos como lote
    $dns_to_add = [];
    if (strpos($sub_dn, '[') === 0) {
        $dns_to_add = json_decode($sub_dn, true) ?? [$sub_dn];
    } else {
        $dns_to_add = [$sub_dn];
    }

    $errors = [];
    $count = 0;
    foreach ($dns_to_add as $dn) {
        $modifs = ['manager' => $boss_dn];
        if (@ldap_mod_replace($ldap_conn, $dn, $modifs)) {
            $count++;
        } else {
            $errors[] = "Error en $dn: " . ldap_error($ldap_conn);
        }
    }

    if ($count > 0) {
        $msg = !empty($errors) ? "Se añadieron $count usuarios, pero hubo errores: " . implode(", ", $errors) : '';
        echo json_encode(['success' => true, 'message' => $msg, 'count' => $count]);
    } else {
        echo json_encode(['success' => false, 'message' => "No se pudo añadir ningún usuario. " . implode(", ", $errors)]);
    }

} elseif ($action === 'remove') {
    // Solo administradores pueden quitar
    if (!is_admin_user()) {
        echo json_encode(['success' => false, 'message' => 'Sin permisos de administrador para quitar']);
        exit;
    }

    if (!$sub_dn) {
        echo json_encode(['success' => false, 'message' => 'DN del subordinado no especificado']);
        exit;
    }

    // Si sub_dn parece un JSON (empieza por [), procesamos como lote
    $dns_to_remove = [];
    if (strpos($sub_dn, '[') === 0) {
        $dns_to_remove = json_decode($sub_dn, true) ?? [$sub_dn];
    } else {
        $dns_to_remove = [$sub_dn];
    }

    $errors = [];
    $count = 0;
    foreach ($dns_to_remove as $dn) {
        // Quitar: Limpiar el manager en el objeto subordinado
        $modifs = ['manager' => []];
        $result = @ldap_mod_replace($ldap_conn, $dn, $modifs);
        
        // Si mod_replace falla, intentamos mod_del especificando el valor actual
        if (!$result) {
            $modifs_del = ['manager' => $boss_dn];
            $result = @ldap_mod_del($ldap_conn, $dn, $modifs_del);
        }

        if ($result) {
            $count++;
        } else {
            $errors[] = "Error en $dn: " . ldap_error($ldap_conn);
        }
    }

    if ($count > 0) {
        $msg = !empty($errors) ? "Se quitaron $count usuarios, pero hubo errores: " . implode(", ", $errors) : '';
        echo json_encode(['success' => true, 'message' => $msg, 'count' => $count]);
    } else {
        echo json_encode(['success' => false, 'message' => "No se pudo quitar ningún usuario. " . implode(", ", $errors)]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

ldap_unbind($ldap_conn);
?>
