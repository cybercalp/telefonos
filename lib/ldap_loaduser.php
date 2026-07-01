<?php
//Funcion de conversión de utf8 a iso

require_once('./lib/bintoGUID.php');
require_once('./lib/ldap_permissions.php');
//Funcion para la encriptación/desencriptación de datos
require_once('./lib/crypt.php');

require_once(__DIR__ . '/../private/config.php');

use LDAP\Client;

if (!defined('DS')) {
   define('DS', '\\\\');
}

//Nombre de Ayuntamiento
$nameAyto = $config['medley']['nameAyto'];

// CONSULTA DATOS DE UN USUARIO DADO EN EL AD
function load_userdata(?Client $ldap = null) {
   global $ldap_protocol, $ldap_host, $ldap_port, $ldap_domain, $ldap_dn, $ldap_user, $ldap_pass;

   $message = array();
   $message_success = '';

   // Si viene un usuario por GET, intentamos cargar ese (si hay permiso)
   $target_user = isset($_GET['user']) ? htmlspecialchars($_GET['user'], ENT_QUOTES, 'UTF-8') : '';

   $session_user = isset($_SESSION['ldap_user']) ? htmlspecialchars($_SESSION['ldap_user'], ENT_QUOTES, 'UTF-8') : '';

   if (!empty($session_user)) {
      // Usar Client inyectado o crear uno con service account
      if ($ldap) {
         $ldap_conn = $ldap->getResource();
      } else {
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

         // Determinar el SAM corto del usuario logueado en la sesión
         $ldap_only_user = $session_user;
         if (strpos($session_user, '@') !== false) {
             $aux = explode('@', $session_user);
             $ldap_only_user = reset($aux);
         } elseif (strpos($session_user, '\\') !== false) {
             $aux = explode('\\', $session_user);
             $ldap_only_user = end($aux);
         }

         // Service account already bound via Client
         // Si hay un target_user especificado, comprobamos si es distinto al logueado
            $effective_user = $ldap_only_user;
            if ($target_user && strcasecmp($target_user, $ldap_only_user) !== 0) {
                // Primero buscamos el DN del target_user para comprobar permisos
                $t_filter = '(samaccountname=' . ldap_escape($target_user, '', LDAP_ESCAPE_FILTER) . ')';
                $t_res = ldap_search($ldap_conn, $ldap_dn, $t_filter, ['distinguishedname']);
                if ($t_res) {
                    $t_ents = ldap_get_entries($ldap_conn, $t_res);
                    if ($t_ents['count'] > 0) {
                        $target_dn = $t_ents[0]['distinguishedname'][0];
                        if (can_edit_user($ldap_conn, $target_dn)) {
                            $effective_user = $target_user;
                        } else {
                            $message[] = 'No tiene permisos para editar a este usuario.';
                            $_SESSION['mensaje'] = $message;
                            return;
                        }
                    }
                }
            }

            // Creo el filtro para la busqueda
            $filter_to_search = '(samaccountname=' . ldap_escape($effective_user, '', LDAP_ESCAPE_FILTER) . ')';

            // Creo el filtro para la busqueda
            // objectClass=user -  asegura que el objeto sea de tipo usuario.
            // objectCategory=person - excluye objetos como grupos y equipos (esto es importante).
            // userAccountControl - Asegura que el objeto este activo (No tiene el bit de cuenta deshabilitada, es decir, está activo)
            $filter = '(&' . $filter_to_search . '(objectClass=user)(objectCategory=person))';

            // Atributos que queremos recuperar
//	    $attrs = array('distinguishedname', 'givenname', 'initials', 'sn', 'displayname', 'company', 'department', 'physicaldeliveryofficename', 'title', 'telephonenumber', 'mobile', 'othermobile', 'homephone', 'otherhomephone', 'facsimiletelephoneNumber', 'mail', 'streetaddress', 'l', 'st', 'postalcode', 'employeenumber', 'samaccountname', 'othermailbox', 'memberOf', 'thumbnailphoto', 'jpegphoto', 'photo', 'pager', 'objectguid');
            $attrs = array('distinguishedname', 'givenname', 'initials', 'sn', 'displayname', 'company', 'department', 'physicaldeliveryofficename', 'title', 'telephonenumber', 'mobile', 'mail', 'othermailbox', 'streetaddress', 'l', 'st', 'postalcode', 'employeenumber', 'samaccountname', 'memberOf', 'thumbnailphoto', 'photo', 'pager', 'objectguid', 'info', 'wwwhomepage');

	    $result = ldap_search($ldap_conn, $ldap_dn, $filter, $attrs);
            if ($result) {
               $entries = ldap_get_entries($ldap_conn, $result);
	       if ($entries['count'] > 0) {
//                  $message[] = 'Datos del usuario ' . $ldap_user . ' recuperados.';
                  $message_success = 'yes';
                  $new_attrs = array_diff($attrs, array('thumbnailphoto', 'jpegphoto', 'photo', 'pager', 'objectguid'));
		  
		  if (isset($entries[0]['objectguid'])) {
                      // Convierte GUID binario a string hexadecimal
                     $strValue = binToGUID($entries[0]['objectguid'][0]);
		     $entries[0]['objectguid'][0] = $strValue;
		  }
                  if (isset($entries[0]['pager'])) {
                    //Si tiente secreto 2FA lo desencriptamos
                    $decryptedSecret = decryptSecretGCM($entries[0]['pager'][0], $entries[0]['objectguid'][0]);
		    $entries[0]['pager'][0] = $decryptedSecret;
		  }
                  if (isset($entries[0]['photo'])) {
                    //Si tiente imagen 2FA la codificamos
                    $encodedImage = 'data:image/jpeg;base64,' . base64_encode($entries[0]['photo'][0]);
                    $entries[0]['photo'][0] = $encodedImage;
                  }
                  if (isset($entries[0]['thumbnailphoto'])) {
                    //Si tiente imagen el usuario la codificamos
                    $encodedImage = 'data:image/jpeg;base64,' . base64_encode($entries[0]['thumbnailphoto'][0]);
                    $entries[0]['thumbnailphoto'][0] = $encodedImage;
                  }

		  $_SESSION['user_data'] = $entries[0];
                  $_SESSION['editing_user_dn'] = $entries[0]['distinguishedname'][0];
	       } else {
//                  $message[] = 'Error recuperando las entradas de la búsqueda LDAP con el filtro dado.';
                  $message[] = 'No se han encontrado datos para: ' . $ldap_only_user;
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
   foreach ($message as &$msg) {
      
   }
   unset($msg); //La referencia a $value del último elemento del array permanece incluso después del bucle foreach. Se recomienda destruir estas referencias utilizando unset().
   if (count($message)>0) $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
}
?>

