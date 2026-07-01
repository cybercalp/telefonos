<?php
/**
 * Endpoint para obtener el HTML de la lista 'secretary' (Pasar llamadas a / Relaciones)
 * para realizar un refresco dinámico mediante AJAX
 */

header('Content-Type: text/html; charset=utf-8');
require_once(__DIR__ . '/../private/config.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/ldap_permissions.php');
require_once(__DIR__ . '/csrf.php');
require_once(__DIR__ . '/db_presencia_select.php');

use LDAP\Client;

if (empty($_SESSION['is_authenticated'])) {
    http_response_code(401);
    echo "No autenticado";
    exit;
}

// Verificación CSRF
if (!verify_csrf_token(get_token_from_request())) {
    http_response_code(403);
    echo "Token CSRF inválido";
    exit;
}

$user_dn = isset($_GET['target_dn']) ? $_GET['target_dn'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'users'; // 'users' o 'contacts'

if (!$user_dn) {
    http_response_code(400);
    echo "Falta target_dn";
    exit;
}

// LDAP connection via Client::factory() (admin)
try {
    $client = Client::factory();
    $ldap_conn = $client->getResource();
} catch (\RuntimeException $e) {
    http_response_code(500);
    echo "Error de conexión LDAP";
    exit;
}

if (!$ldap_conn) {
    http_response_code(500);
    echo "Error de conexión LDAP";
    exit;
}

$isAuthenticated = !empty($_SESSION['is_authenticated']);
$hasPermission = ($type === 'contacts')
    ? can_edit_contact($user_dn, $ldap_conn)
    : can_edit_user($ldap_conn, $user_dn);

// 1. Obtener estado actual
$sr = @ldap_read($ldap_conn, $user_dn, '(objectClass=*)', ['comment', 'secretary']);
if (!$sr) {
    http_response_code(404);
    echo "Objeto no encontrado";
    
    exit;
}
$entries = ldap_get_entries($ldap_conn, $sr);

$ordered_sec_dns = [];
if (isset($entries[0]['comment'][0]) && strpos($entries[0]['comment'][0], 'SEC-ORDER:') === 0) {
    $raw = substr($entries[0]['comment'][0], 10);
    $ordered_sec_dns = array_filter(explode('|', $raw));
} elseif (isset($entries[0]['secretary'])) {
    for ($f=0; $f < $entries[0]['secretary']['count']; $f++) {
        if(!empty($entries[0]['secretary'][$f])) $ordered_sec_dns[] = $entries[0]['secretary'][$f];
    }
}

if (!empty($ordered_sec_dns)) {
    foreach ($ordered_sec_dns as $sec_dn) {
        $sec_attrs = array('employeenumber','wwwhomepage','displayname','telephonenumber','mobile','distinguishedname');
        $res_sec = @ldap_read($ldap_conn, $sec_dn, '(objectClass=*)', $sec_attrs);
        if($res_sec) {
            $managed = ldap_get_entries($ldap_conn, $res_sec);
            if (!empty($managed[0]['displayname'][0])) {
                $managed_dn = $managed[0]['distinguishedname'][0] ?? $sec_dn;
                
                if ($type === 'contacts') {
                    // Estilo de contactos (Empresas Relacionadas)
                    echo '<div class="flex items-center gap-2 min-w-0 secretary-item py-0.5 group/item" data-dn="' . htmlspecialchars($managed_dn) . '">';
                    if ($isAuthenticated && $hasPermission) {
                        echo '  <div class="flex-shrink-0 cursor-grab active:cursor-grabbing text-blue-400/40 hover:text-blue-500 drag-handle"><i class="fas fa-grip-vertical text-[9px]"></i></div>';
                    }
                    echo '  <span class="text-[11px] text-slate-700 dark:text-slate-200 font-semibold truncate flex-1">' . htmlspecialchars($managed[0]['displayname'][0]) . '</span>';
                    if (!empty($managed[0]['telephonenumber'][0])) {
                        echo ' <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap">' . htmlspecialchars($managed[0]['telephonenumber'][0]) . '</span>';
                    }
                    if (!empty($managed[0]['mobile'][0])) {
                        echo ' <span class="text-[10px] font-bold text-blue-500/80 dark:text-blue-400/80 whitespace-nowrap">' . htmlspecialchars($managed[0]['mobile'][0]) . '</span>';
                    }
                    if ($isAuthenticated && $hasPermission) {
                        echo '  <button type="button" onclick="manageSecretary(\'remove\', \'' . addslashes($user_dn) . '\', \'' . addslashes($managed_dn) . '\', \'contacts\')" class="flex-shrink-0 ml-1 text-slate-300 hover:text-rose-500 transition-colors" title="Quitar"><i class="fas fa-times text-[10px]"></i></button>';
                    }
                    echo '</div>';
                } else {
                    // Estilo de usuarios (Pasar llamadas a)
                    $managed[0]['wwwhomepage'][0] = substr((isset($managed[0]['wwwhomepage'][0])?$managed[0]['wwwhomepage'][0]:'').'0000', 0, 4);
                    $smLed = ($managed[0]['wwwhomepage'][0][3]==='1' && isset($managed[0]['employeenumber']) && user_in($managed[0]['employeenumber'][0])) ? 'bg-green-500' : ($managed[0]['wwwhomepage'][0][3]==='1' ? 'bg-rose-500' : 'bg-slate-300');
                    
                    echo '<div class="flex items-center gap-2 min-w-0 secretary-item py-0.5 group/item" data-dn="' . htmlspecialchars($managed_dn) . '">';
                    if ($isAuthenticated && $hasPermission) {
                        echo '  <div class="flex-shrink-0 cursor-grab active:cursor-grabbing text-amber-400/40 hover:text-amber-500 drag-handle"><i class="fas fa-grip-vertical text-[9px]"></i></div>';
                    }
                    echo '  <div class="w-1.5 h-1.5 rounded-full flex-shrink-0 ' . $smLed . '"></div>';
                    echo '  <span class="text-[11px] text-slate-700 dark:text-slate-200 font-semibold truncate flex-1">' . htmlspecialchars($managed[0]['displayname'][0]) . '</span>';
                    if (!empty($managed[0]['telephonenumber'][0])) {
                        echo ' <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap">' . htmlspecialchars($managed[0]['telephonenumber'][0]) . '</span>';
                    }
                    if (!empty($managed[0]['mobile'][0])) {
                        echo ' <span class="text-[10px] font-bold text-blue-500/80 dark:text-blue-400/80 whitespace-nowrap">' . htmlspecialchars($managed[0]['mobile'][0]) . '</span>';
                    }
                    if ($isAuthenticated && $hasPermission) {
                        echo '  <button type="button" onclick="manageSecretary(\'remove\', \'' . addslashes($user_dn) . '\', \'' . addslashes($managed_dn) . '\', \'users\')" class="flex-shrink-0 ml-1 text-slate-300 hover:text-rose-500 transition-colors" title="Quitar"><i class="fas fa-times text-[10px]"></i></button>';
                    }
                    echo '</div>';
                }
            }
        }
    }
} else {
    if ($type === 'contacts') {
        echo '  <p class="text-[10px] text-slate-400 dark:text-slate-500 italic"><i class="fas fa-link-slash mr-1.5 opacity-60"></i>Sin asignar</p>';
    } else {
        echo '  <p class="text-[10px] text-slate-400 dark:text-slate-500 italic"><i class="fas fa-share-square mr-1.5 opacity-60"></i>Sin asignar</p>';
    }
}


