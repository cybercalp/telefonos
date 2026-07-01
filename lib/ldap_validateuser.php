<?php
use LDAP\Client;

//Funciones para debug
require_once('./lib/debug_to_console.php');
//Funcion para comprobar el estado de un usuario
require_once('./lib/ldap_checkuser.php');
//Funciones para el control de envíos errores en el formulario
require_once('./lib/db_attemptslogin_add.php');
require_once('./lib/db_attemptslogin_del.php');
//Funcion para la obtención de un GUID en string
require_once('./lib/bintoGUID.php');
//Funcion para la encriptación/desencriptación de datos
require_once('./lib/crypt.php');

require_once(__DIR__ . '/../private/config.php');

if (!defined('DS')) {
   define('DS', '\\\\');
}

// VALIDA USUARIO EN LDAP
function validate_user($user, $pwd, ?LDAP\Client $ldap = null) {
   global $ldap_protocol, $ldap_host, $ldap_port, $ldap_domain, $ldap_dn;
   global $ldap_only_user;

   // Suprimir salida de errores al usuario (no hacer leak de información interna)
   // Los errores se siguen registrando en el log del servidor para diagnóstico
   ini_set('display_errors', '0');
   ini_set('log_errors', '1');

   $message = array();
   $message_success = '';

   $usuario = htmlspecialchars($user, ENT_QUOTES, 'UTF-8');
   $clave = $pwd;

   if (!empty($usuario) && !empty($clave)) {
      //Comprobamos el estado del usuario
        check_user($usuario, $ldap);
       if ($_SESSION['mensaje_css'] == 'no') {
         return;
       }

       // Parámetros de conexión LDAP — via injected Client or factory fallback
       $conn = $ldap ?? Client::factory();
       $ldap_conn = $conn->getResource();

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
	$ldap_pass = $clave;

        // Autenticación
        if (ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
          $message[] = 'Autenticación exitosa. ¡Bienvenido ' . $usuario . '!';
          $message_success = 'yes';

          $_SESSION['ldap_user'] = $ldap_only_user;
          $_SESSION['is_authenticated'] = true;
          $_SESSION['2fa_verified'] = false;

          //Limpiamos tabla attempts_login
          delete_attempts_login_fail(true);
	  $_SESSION['bloqueo_activo'] = false;

	  //Recuperamos el 2FA si lo tiene activo
          // Creo el filtro para la busqueda (escapando el input para prevenir inyección LDAP)
          $filter_to_search = '(samaccountname=' . ldap_escape($ldap_only_user, '', LDAP_ESCAPE_FILTER) . ')';

          // Creo el filtro para la busqueda
          // objectClass=user -  asegura que el objeto sea de tipo usuario.
          // objectCategory=person - excluye objetos como grupos y equipos (esto es importante).
          // userAccountControl - Asegura que el objeto este activo (No tiene el bit de cuenta deshabilitada, es decir, está activo)
          $filter = '(&' . $filter_to_search . '(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';

          // Atributos que queremos recuperar
          $attrs = array('pager', 'objectguid', 'distinguishedname');

          $result = ldap_search($ldap_conn, $ldap_dn, $filter, $attrs);
          if ($result) {
             $entries = ldap_get_entries($ldap_conn, $result);
	     if ($entries['count'] > 0) {
                 $_SESSION['auth_user_dn'] = $entries[0]['distinguishedname'][0];
                 if (isset($entries[0]['objectguid'])) {
                    // Convierte GUID binario a string hexadecimal
                    $strValue = binToGUID($entries[0]['objectguid'][0]);
                 }
		if (isset($entries[0]['pager'])) {
                   //Si tiente secreto 2FA lo desencriptamos
                  $decryptedSecret = decryptSecretGCM($entries[0]['pager'][0], $strValue);
                  $_SESSION['secretkey'] = $decryptedSecret;
		} else {
                   unset($_SESSION['secretkey']);
                }
             } else {
//                $message[] = 'Error recuperando las entradas de la búsqueda LDAP con el filtro dado.';
                $message[] = 'No se han encontrado datos para: ' . $ldap_only_user;
             }
          } else {
             $message[] = 'Error en la búsqueda LDAP con el filtro dado.';
             $message[] = 'Filtro: ' . $filter;
             $message[] = 'Base búsqueda: ' . $ldap_dn;
	  }
	}else{
          $error = '';
          ldap_get_option($ldap_conn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $error);
          // Por ejemplo: "80090308: LdapErr: DSID-0C09044E, comment: AcceptSecurityContext error, data 773, v2580"
          $dataCode = null;
          if (preg_match('/data\s([0-9a-fA-F]+)/', $error, $matches)) {
            $dataCode = strtolower($matches[1]);
          }
          switch ($dataCode) {
          case '525':
            $message[] = 'Usuario no encontrado.';
            $message[] = add_attempts_login_fail();
            break;
          case '52e':
            $message[] = 'Usuario o contraseña incorrectos.';
            $message[] = add_attempts_login_fail();
            break;
          case '530':
             $message[] = 'No tienes permitido iniciar sesión en este horario.';
            //Limpiamos tabla attempts_login
            delete_attempts_login_fail(true);
            $_SESSION['bloqueo_activo'] = false;
            break;
          case '531':
            $message[] = 'No tienes permitido iniciar sesión desde esta estación de trabajo.';
            //Limpiamos tabla attempts_login
            delete_attempts_login_fail(true);
            $_SESSION['bloqueo_activo'] = false;
            break;
          case '532':
            $message[] = 'Tu contraseña ha expirado. Debes cambiarla.';
            //Limpiamos tabla attempts_login
            delete_attempts_login_fail(true);
            $_SESSION['bloqueo_activo'] = false;
            break;
          case '533':
            $message[] = 'Tu cuenta está deshabilitada.';
            //Limpiamos tabla attempts_login
            delete_attempts_login_fail(true);
            $_SESSION['bloqueo_activo'] = false;
            break;
          case '701':
            $message[] = 'Tu cuenta ha expirado';
            //Limpiamos tabla attempts_login
            delete_attempts_login_fail(true);
            $_SESSION['bloqueo_activo'] = false;
            break;
          case '773':
            $message[] = 'Debes cambiar tu contraseña antes de iniciar sesión.';
            //Limpiamos tabla attempts_login
            delete_attempts_login_fail(true);
            $_SESSION['bloqueo_activo'] = false;
            break;
          case '775':
            $message[] = 'Tu cuenta está bloqueada. Contacta con soporte.';
            //Limpiamos tabla attempts_login
            delete_attempts_login_fail(true);
            $_SESSION['bloqueo_activo'] = false;
            break;
          default:
            $errno = ldap_errno($ldap_conn);
            $error = ldap_error($ldap_conn);
            // Obtener los controles devueltos
            //$controls = [];
            //$diag = ldap_get_option($ldap_conn, LDAP_OPT_SERVER_CONTROLS, $controls);
            /* Esta llamada obtiene los controles devueltos por el servidor en la última operación LDAP ejecutada sobre la conexión $ldap_conn
             * El valor de $controls podría contener algo como:
             *                array(1) {
             *                  [0] =>
             *                   array(
             *                         "oid" => "1.3.6.1.4.1.1466.20036",
             *                         "value" => <datos binarios o texto>
             *                   )
             *                }
             * El campo "oid" identifica el tipo de control. Por ejemplo:
             *            1.2.840.113556.1.4.2239 --> control de cambio de contraseña de AD.
             *            1.3.6.1.4.1.1466.20036  --> password policy control (RFC 3062 / RFC 4527).
             */
            //var_dump($controls); // Muestra los controles devueltos por el servidor
            $message[] = "Otros errores, ldap_err_number: '$errno', ldap_error_description: $error";
            $message[] = add_attempts_login_fail();
            break;
          }
	}
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
?>


