<?php
//Funciones para debug
require_once('./lib/debug_to_console.php');

require_once(__DIR__ . '/../private/config.php');

use LDAP\Client;

if (!defined('DS')) {
   define('DS', '\\\\');
}

// COMPRUEBA SI LA CUENTA DE UN USUARIO ESTA EXPIRADA O BLOQUEADA
function check_user($user, ?Client $ldap = null) {
   global $ldap_protocol, $ldap_host, $ldap_port, $ldap_domain, $ldap_dn;
   global $ldap_only_user;
   global $ldap_admuser, $ldap_admpwd;

   // Suprimir salida de errores al usuario (no hacer leak de información interna)
   // Los errores se siguen registrando en el log del servidor para diagnóstico
   ini_set('display_errors', '0');
   ini_set('log_errors', '1');

   $message = array();
   $message_success = '';

   $usuario = htmlspecialchars($user, ENT_QUOTES, 'UTF-8');

   // Usar Client inyectado o factory por defecto
   $client = $ldap ?? Client::factory();
   $ldap_conn = $client->getResource();

   if (!$ldap_conn) {
     $message[] = 'No se pudo conectar al servidor LDAP.';
   } else {

     if (strpos(addslashes($usuario), $ldap_domain[0] . DS) === false) {
       if (strpos($usuario, '@' . $ldap_domain[1])) {
          $ldap_user = $usuario;
          if (empty($ldap_only_user)) {
             $aux = explode('@', $usuario);
             $ldap_only_user = reset($aux);
          }
       }else{
          $ldap_user = $usuario . '@' . $ldap_domain[1];
          if (empty($ldap_only_user)) $ldap_only_user = $usuario;
       }
     }else{
        $ldap_user = $usuario;
        if (empty($ldap_only_user)) {
           $aux = explode('\\', $usuario);
           $ldap_only_user = end($aux);
        }
     }

     // Autenticación
     if (ldap_bind($ldap_conn, $ldap_admuser, $ldap_admpwd)) {
       // Creo el filtro para la busqueda (escapando el input para prevenir inyección LDAP)
       $filter_to_search = '(samaccountname=' . ldap_escape($ldap_only_user, '', LDAP_ESCAPE_FILTER) . ')';

       // Creo el filtro para la busqueda
       $filter = $filter_to_search;

       // Atributos que queremos recuperar
       $attrs = array('accountExpires', 'userAccountControl', 'msDS-UserPasswordExpiryTimeComputed');

       $result = ldap_search($ldap_conn, $ldap_dn, $filter, $attrs);
       if ($result) {
          $entries = ldap_get_entries($ldap_conn, $result);
	  if ($entries['count'] > 0) {
             $entry = $entries[0];

             // userAccountControl flags
             $uac = $entry["useraccountcontrol"][0];
             if ($uac & 2) {
                $message[] = "La cuenta está deshabilitada";
                $message_success = 'no';
             }

             // accountExpires (FileTime -> Unix timestamp)
             $expires = $entry["accountexpires"][0];
             if ($expires > 0 && $expires < 9223372036854775807) {
                $unix_exp = ($expires / 10000000) - 11644473600;
                if ($unix_exp < time()) {
                   $message[] = "La cuenta está expirada";
                   $message_success = 'no';
		} 
             }

             // Contraseña expirada
             if (isset($entry["msds-userpasswordexpirytimecomputed"][0])) {
                $pwdExp = $entry["msds-userpasswordexpirytimecomputed"][0] ?? null;
		if ($pwdExp) {
                   $unix_pwdexp = ($pwdExp / 10000000) - 11644473600;
                   if ($unix_pwdexp < time()) {
                      $message[] = "La contraseña está expirada";
                      $message_success = 'no';
		   } 
                }
	     }
          } else {
//             $message[] = 'Error recuperando las entradas de la búsqueda LDAP con el filtro dado.';
             $message[] = 'No se han encontrado datos para: ' . $ldap_only_user;
          }
        } else {
          $message[] = 'Error en la búsqueda LDAP con el filtro dado.';
          $message[] = 'Filtro: ' . $filter;
          $message[] = 'Base búsqueda: ' . $ldap_dn;
        }
      }else{
       $message[] = 'Usuario o contraseña incorrectos.';
     }
   }
   foreach ($message as &$msg) {
    
   }
   unset($msg); //La referencia a $value del último elemento del array permanece incluso después del bucle foreach. Se recomienda destruir estas referencias utilizando unset().
   $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
}
?>


