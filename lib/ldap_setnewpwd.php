<?php
//Funciones para codificar password
require_once(__DIR__ . '/ldap_encodepwd.php');
require_once(__DIR__ . '/../private/config.php');

use LDAP\Client;

if (!defined('DS')) {
   define('DS', '\\\\');
}

// ESTABLECE UNA NUEVA CONTRASEÑA PARA EL USUARIO DADO
function set_user_password($username, $newPassword, ?Client $ldap = null) {
   global $ldap_dn;
   global $password_min_length;

   $message = array();
   $message_success = '';

   if (empty($username) || empty($newPassword)) {
       $_SESSION['mensaje'] = array('Usuario o contraseña no proporcionados.');
       $_SESSION['mensaje_css'] = 'no';
       return;
   }

   // --- VALIDACIONES DE COMPLEJIDAD (Reutilizadas de ldap_changepwd.php) ---
   $sumaTotalGrupo = 0;
   if (strlen($newPassword) < $password_min_length ) {
      $message[] = "Su nueva contraseña es demasiado corta (mínimo $password_min_length caracteres).";
   }
   if (preg_match('/[0-9]/', $newPassword)) { $sumaTotalGrupo++; }
   if (preg_match('/[A-Z]/', $newPassword)) { $sumaTotalGrupo++; }
   if (preg_match('/[a-z]/', $newPassword)) { $sumaTotalGrupo++; }
   if (preg_match('/[^a-zA-Z0-9]/', $newPassword)) { $sumaTotalGrupo++; }

   if ($sumaTotalGrupo < 3) {
      $message[] = 'La contraseña debe cumplir al menos 3 de los siguientes requisitos: números, mayúsculas, minúsculas o símbolos.';
   }

   // Evitar que el nombre aparezca en el password
   if (stripos($newPassword, $username) !== false) {
      $message[] = 'La contraseña no puede contener el nombre de usuario.';
   }

   if (!empty($message)) {
      $_SESSION['mensaje'] = $message;
      $_SESSION['mensaje_css'] = 'no';
      return;
   }

   // --- PROCESO DE CAMBIO EN LDAP ---
   $conn = $ldap ?? Client::factory();
   $ldap_conn = $conn->getResource();

   if (!$ldap_conn) {
      $message[] = 'No se pudo conectar al servidor LDAP.';
   } else {

      // Buscar el DN del usuario
      $filter = '(&(samaccountname=' . trim($username) . ')(objectClass=user)(objectCategory=person))';
      $attrs = array('dn', 'givenname', 'sn', 'mail', 'othermailbox');

         $search = ldap_search($ldap_conn, $ldap_dn, $filter, $attrs);
         if ($search) {
            $entries = ldap_get_entries($ldap_conn, $search);
            if ($entries['count'] > 0) {
               $user_dn = $entries[0]['dn'];
               
               // Validación adicional: no contener nombre o apellidos
               $givenName = $entries[0]['givenname'][0] ?? '';
               $sn = $entries[0]['sn'][0] ?? '';
                if ((!empty($givenName) && stripos($newPassword, $givenName) !== false) || 
                    (!empty($sn) && stripos($newPassword, $sn) !== false)) {
                    $_SESSION['mensaje'] = array('La contraseña no puede contener partes de su nombre real.');
                   $_SESSION['mensaje_css'] = 'no';
                   return;
               }

               // 1. Intentar cambiar la contraseña (unicodePwd)
               $mod_pwd = array('unicodePwd' => encode_password($newPassword));
               if (ldap_mod_replace($ldap_conn, $user_dn, $mod_pwd)) {
                  $message[] = 'Contraseña actualizada en Active Directory.';
                  $message_success = 'yes';

                  // Guardar correos del usuario en sesión para notificación posterior
                  $_SESSION['reset_user_mail'] = $entries[0]['mail'][0] ?? '';
                  $_SESSION['reset_user_recoverymail'] = $entries[0]['othermailbox'][0] ?? '';
                  $_SESSION['reset_user_sn'] = $sn;
                  
                  // 2. Intentar activar cuenta y limpiar bloqueos (opcional)
                  $mod_extra = array(
                      'pwdLastSet' => array("-1"),
                      'lockoutTime' => array("0")
                  );
                  @ldap_mod_replace($ldap_conn, $user_dn, $mod_extra);
                  
               } else {
                  $error = ldap_error($ldap_conn);
                  $extended_error = '';
                  ldap_get_option($ldap_conn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended_error);
                  
                  if (strpos($error, "Constraint violation") !== false || strpos($extended_error, "0000052D") !== false) {
                     $message[] = 'AD indica violación de restricción (posiblemente la contraseña ya fue usada o no cumple políticas de complejidad de AD).';
                  } else {
                     $message[] = 'Error detallado de AD: ' . $error . ' (' . $extended_error . ')';
                  }
               }
            } else {
               $message[] = 'Usuario no encontrado en LDAP.';
            }
         } else {
            $message[] = 'Error en la búsqueda del usuario.';
         }
   }

   $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
}
?>
