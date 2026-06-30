<?php
//Funciones para el control de envíos errores en el formulario
require_once('./lib/db_attemptslogin_add.php');
require_once('./lib/db_attemptslogin_del.php');

require_once(__DIR__ . '/../private/config.php');

if (!defined('DS')) {
   define('DS', '\\\\');
}

// VALIDA MAIL DE RECUPERACIÓN EN LDAP
function validate_mail($usermail) {
   global $ldap_protocol, $ldap_host, $ldap_port, $ldap_domain, $ldap_user, $ldap_pass, $ldap_dn;

   $message = array();
   $message_success = '';

   if (!empty($usermail)) {
      $usuario = $ldap_user;
      $clave = $ldap_pass;

      if (!empty($usuario) && !empty($clave)) {
         // Parámetros de conexión LDAP
         $ldap_conn = ldap_connect(get_ldap_uri());

         if (!$ldap_conn) {
           $message[] = 'No se pudo conectar al servidor LDAP.';
         } else {
            // Configurar opciones
            ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3) or die ('Imposible asignar el Protocolo LDAP');
            ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

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
	    $ldap_pass = $clave;

            // Autenticación
            if (ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
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
	       ldap_unbind($ldap_conn);
            }else{
               $message[] = 'Usuario o contraseña incorrectos.';
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
function validate_user_mail($username, $usermail) {
   global $ldap_protocol, $ldap_host, $ldap_port, $ldap_domain, $ldap_user, $ldap_pass, $ldap_dn;

   $message = array();
   $message_success = '';

   if (!empty($username) && !empty($usermail)) {
      $usuario = $ldap_user;
      $clave = $ldap_pass;

      if (!empty($usuario) && !empty($clave)) {
         $ldap_conn = ldap_connect(get_ldap_uri());

         if (!$ldap_conn) {
           $message[] = 'No se pudo conectar al servidor LDAP.';
         } else {
            ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

            // Preparar usuario para bind (admin)
            if (strpos($usuario, '@' . $ldap_domain[1]) === false && strpos($usuario, $ldap_domain[0] . '\\') === false) {
               $bind_user = $usuario . '@' . $ldap_domain[1];
            } else {
               $bind_user = $usuario;
            }

            if (ldap_bind($ldap_conn, $bind_user, $clave)) {
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
               ldap_unbind($ldap_conn);
            } else {
               $message[] = 'Error de autenticación administrativa en LDAP.';
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

