<?php
//Funciones para el control de envíos errores en el formulario
require_once('./lib/db_attemptslogin_add.php');
require_once('./lib/db_attemptslogin_del.php');

require_once(__DIR__ . '/../private/config.php');

use LDAP\Client;

if (!defined('DS')) {
   define('DS', '\\\\');
}

// VALIDA MAIL DE RECUPERACIÓN EN LDAP
function validate_mail($usermail, ?Client $ldap = null) {
   global $ldap_domain, $ldap_user, $ldap_pass, $ldap_dn;

   $message = array();
   $message_success = '';

   if (!empty($usermail)) {
      $usuario = $ldap_user;
      $clave = $ldap_pass;

      if (!empty($usuario) && !empty($clave)) {
         // Parámetros de conexión LDAP vía Client (service account)
         if ($ldap) {
            $ldap_conn = $ldap->getResource();
         } else {
            // Normalizar usuario para bind
            $ldap_user = $usuario;
            if (strpos(addslashes($usuario), $ldap_domain[0] . DS) === false) {
               if (strpos($usuario, '@' . $ldap_domain[1])) {
                  $ldap_user = $usuario;
               } else {
                  $ldap_user = $usuario . '@' . $ldap_domain[1];
               }
            }
            $ldap_pass = $clave;

            $uri = get_ldap_uri();
            $parts = parse_url($uri);
            $host = $parts['host'] ?? '';
            $port = $parts['port'] ?? 389;
            $scheme = $parts['scheme'] ?? 'ldap';
            $client = new Client($host, (int)$port, $ldap_user, $ldap_pass, $scheme);
            $ldap_conn = $client->getResource();
         }

         if (!$ldap_conn) {
            $message[] = 'No se pudo conectar al servidor LDAP.';
         } else {

            // Filtro para buscar usuario por nombre de cuenta (samAccountName)
            $filter_to_search = '(othermailbox=' . trim($usermail) . ')';

               // Creo el filtro para la busqueda
               // objectClass=user -  asegura que el objeto sea de tipo usuario.
               // objectCategory=person - excluye objetos como grupos y equipos (esto es importante).
               // userAccountControl - Asegura que el objeto este activo (No tiene el bit de cuenta deshabilitada, es decir, está activo)
               $filter = '(&' . $filter_to_search . '(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';
               // Atributos que queremos recuperar
               $attrs = array('dn', 'samaccountname', 'othermailbox');
	       $result = ldap_search($ldap_conn, $ldap_dn, $filter, $attrs);
               if ($result) {

                  $entries = ldap_get_entries($ldap_conn, $result);

                  if ($entries['count'] > 0) {
                     // Guardamos el nombre de usuario encontrado para usarlo en el envío del correo
                     $_SESSION['rescue_username'] = $entries[0]['samaccountname'][0];
                     $message_success = 'yes';
                    //Limpiamos tabla attempts_login
                     delete_attempts_login_fail(true);
                     $_SESSION['bloqueo_activo'] = false;
		  } else {
                     $_SESSION['rescue_username'] = '';
                     $message[] = 'No se han encontrado datos para: ' . $usermail;
                     $message[] = add_attempts_login_fail();
		  }
               } else {
                  $message[] = 'Error en la búsqueda LDAP con el filtro dado.';
                  $message[] = 'Filtro: ' . $filter;
                  $message[] = 'Base búsqueda: ' . $ldap_dn;
	       }
         }
      } else {
        $message[] = 'Falta usuario o contraseña.';
      }
   } else {
     $message[] = 'Por favor, completa todos los campos.';
   }
   foreach ($message as &$msg) {
    
   }
   unset($msg); //La referencia a $value del último elemento del array permanece incluso después del bucle foreach. Se recomienda destruir estas referencias utilizando unset().
   $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
}

// VALIDA USUARIO Y MAIL DE RECUPERACIÓN EN LDAP
function validate_user_mail($username, $usermail, ?Client $ldap = null) {
   global $ldap_domain, $ldap_user, $ldap_pass, $ldap_dn;

   $message = array();
   $message_success = '';

   if (!empty($username) && !empty($usermail)) {
      $usuario = $ldap_user;
      $clave = $ldap_pass;

      if (!empty($usuario) && !empty($clave)) {
         // Parámetros de conexión LDAP vía Client (service account)
         if ($ldap) {
            $ldap_conn = $ldap->getResource();
         } else {
            // Preparar usuario para bind
            if (strpos($usuario, '@' . $ldap_domain[1]) === false && strpos($usuario, $ldap_domain[0] . '\\') === false) {
               $bind_user = $usuario . '@' . $ldap_domain[1];
            } else {
               $bind_user = $usuario;
            }
            $bind_pass = $clave;

            $uri = get_ldap_uri();
            $parts = parse_url($uri);
            $host = $parts['host'] ?? '';
            $port = $parts['port'] ?? 389;
            $scheme = $parts['scheme'] ?? 'ldap';
            $client = new Client($host, (int)$port, $bind_user, $bind_pass, $scheme);
            $ldap_conn = $client->getResource();
         }

         if (!$ldap_conn) {
            $message[] = 'No se pudo conectar al servidor LDAP.';
         } else {

            // Filtro para buscar por samaccountname Y othermailbox
            $filter = '(&' . 
                      '(samaccountname=' . trim($username) . ')' . 
                      '(othermailbox=' . trim($usermail) . ')' . 
                      '(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';
            
            $attrs = array('dn', 'samaccountname', 'othermailbox');
            $result = ldap_search($ldap_conn, $ldap_dn, $filter, $attrs);
            
            if ($result) {
               $entries = ldap_get_entries($ldap_conn, $result);
               if ($entries['count'] > 0) {
                  $message_success = 'yes';
                  delete_attempts_login_fail(true);
                  $_SESSION['bloqueo_activo'] = false;
               } else {
                  $message[] = 'No se ha encontrado una coincidencia para el usuario y correo proporcionados.';
                  $message[] = add_attempts_login_fail();
               }
            } else {
               $message[] = 'Error en la búsqueda LDAP.';
            }
         }
      } else {
         $message[] = 'Configuración LDAP incompleta.';
      }
   } else {
      $message[] = 'Por favor, completa todos los campos.';
   }

   $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
}
?>

