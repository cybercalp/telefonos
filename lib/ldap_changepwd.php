<?php
//Funciones para mostrar mensajes
require_once('./lib/ldap_encodepwd.php');
//Funciones para envio de correo
require_once('./lib/sendmail.php');
//Funcion para comprobar el estado de un usuario
require_once('./lib/ldap_checkuser.php');
//Funciones para el control de envíos errores en el formulario
require_once('./lib/db_attemptslogin_add.php');
require_once('./lib/db_attemptslogin_del.php');

require_once(__DIR__ . '/../private/config.php');

if (!defined('DS')) {
   define('DS', '\\\\');
}

//URL hacia UDS cuando cambiamos el password fuera de una IP admitida
$UDS_URL = $config['medley']['UDS_URL'];

// CAMBIA LA CONTRASEÑA DEL USUARIO
function changePassword($user, $oldPassword, $newPassword, $newPasswordCnf) {
   global $ldap_protocol, $ldap_host, $ldap_port, $ldap_domain, $ldap_user, $ldap_pass, $ldap_dn;
   global $ldap_admuser, $ldap_admpwd;
   global $password_min_length, $user_max_attempts_allowed;
   global $ldap_only_user;

   // Suprimir salida de errores al usuario (no hacer leak de información interna)
   // Los errores se siguen registrando en el log del servidor para diagnóstico
   ini_set('display_errors', '0');
   ini_set('log_errors', '1');

   $message = array();
   $message_success = '';

   $sumaTotalGrupo = 0;
	
   $usuario = $user;
   $clave = $oldPassword;

  if (!empty($usuario) && !empty($clave)) {
      //Comprobamos el estado del usuario
       check_user($usuario);
       if ($_SESSION['mensaje_css'] == 'no') {
         return;
       }
   }
   if (!empty($usuario) && !empty($clave) && !empty($newPassword) && !empty($newPasswordCnf)) {
      // Parámetros de conexión LDAP
      $ldap_conn = ldap_connect(get_ldap_uri());

      if (!$ldap_conn) {
         $message[] = 'Error E999 - No se pudo conectar al servidor LDAP.';
      } else {
         // Configurar opciones
         ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
         ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

         if (strpos($usuario, $ldap_domain[0] . DS) === false) {
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

         // Primero comprobamos las credenciales del usuario que se las quiere cambiar
	 if (!ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
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
              goto Exit_Err;
              break;
            case '52e':
              $message[] = 'Usuario o contraseña incorrectos.';
              $message[] = add_attempts_login_fail();
              goto Exit_Err;
              break;
            case '530':
//               $message[] = 'No tienes permitido iniciar sesión en este horario.';
              break;
            case '531':
//              $message[] = 'No tienes permitido iniciar sesión desde esta estación de trabajo.';
              break;
            case '532':
//              $message[] = 'Tu contraseña ha expirado. Debes cambiarla.';
              break;
            case '533':
//              $message[] = 'Tu cuenta está deshabilitada.';
              break;
            case '701':
//              $message[] = 'Tu cuenta ha expirado';
              break;
            case '773':
//              $message[] = 'Debes cambiar tu contraseña antes de iniciar sesión.';
              break;
            case '775':
//              $message[] = 'Tu cuenta está bloqueada. Contacta con soporte.';
              break;
            default:
              $errno = ldap_errno($ldap_conn);
              $error = ldap_error($ldap_conn);
              // Obtener los controles devueltos
              $controls = [];
              ldap_get_option($ldap_conn, LDAP_OPT_SERVER_CONTROLS, $controls);
              // var_dump($controls); // Muestra los controles devueltos por el servidor
              $message[] = "Otros errores, ldap_err_number: '$errno', ldap_error_description: $error";
              $message[] = add_attempts_login_fail();
              goto Exit_Err;
              break;
            }
	 }

         // Autenticación con permisos administrativos para un correcto cambio de cotraseña
         if (ldap_bind($ldap_conn, $ldap_admuser, $ldap_admpwd)) {
            // Creo el filtro para la busqueda
            $ldap_only_user_escaped = ldap_escape($ldap_only_user, "", LDAP_ESCAPE_FILTER);
            $filter_to_search = '(samaccountname=' . $ldap_only_user_escaped . ')';

            // Creo el filtro para la busqueda
            // objectClass=user -  asegura que el objeto sea de tipo usuario.
            // objectCategory=person - excluye objetos como grupos y equipos (esto es importante).
            // userAccountControl - Asegura que el objeto este activo (No tiene el bit de cuenta deshabilitada, es decir, está activo)
            $filter = '(&' . $filter_to_search . '(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';

            // Especifico los parámetros que quiero que me regrese la consulta
            $attrs = array('samaccountname', 'othermailbox', 'mail', 'sn', 'givenname', 'badpwdcount', 'lockouttime');

            // Vincular anónimo y buscar usuario por sAMAccountName
            $user_search = ldap_search($ldap_conn, $ldap_dn, $filter, $attrs);
            if ($user_search) {
               $user_get = ldap_get_entries($ldap_conn, $user_search);
               if ($user_get['count'] > 0) {
                  if (isset($user_get[0]['lockouttime'])) {
                     $lockoutTime = $user_get[0]['lockouttime'][0];
                  } else {
                    $lockoutTime = 0;
                 }
                  if(isset($user_get[0]['badpwdcount'])) {
                    $passwordRetryCount = $user_get[0]['badpwdcount'][0];
                  } else {
                     $passwordRetryCount = 0;
                  }
	       } else {
                  $message[] = 'Error recuperando las entradas de la búsqueda LDAP con el filtro dado.';
                  goto Exit_Err;
              }
            } else {
               $message[] = 'Error en la búsqueda LDAP con el filtro dado.';
               $message[] = 'Filtro: ' . $filter;
	       $message[] = 'Base búsqueda: ' . $ldap_dn;
	       goto Exit_Err;
            }
            $user_entry = ldap_first_entry($ldap_conn, $user_search);
            $user_dn = ldap_get_dn($ldap_conn, $user_entry);

            /* Start the testing */
            /* En nuestro sistema de AD no tenemos programado el bloqueo de usuario por multiples intentos fallidos.
             * Siempre tendremos esta variable a 0 */
            if ($lockoutTime != 0) {
               $message[] = 'Error E101 - Usuario bloqueado temporalmente por demasiados errores de autenticación.';
               // Convertir FILETIME a timestamp
               $windowsEpoch = 11644473600; // Diferencia entre 1601 y 1970 en segundos
               $lockoutUnixTime = (int)($lockoutTime / 10000000) - $windowsEpoch;
               $date_locked = date('Y-m-d H:i:s', $lockoutUnixTime);
               $message[] = 'Bloqueado desde: ' . $date_locked;
               goto Exit_Err;
            }

            /* Para hacer como el servidor UDS, configuro esta opción de aquí.
             * Pero para que funcione la llamada a la función ldap_bind de arriba deberíamos  hacerla con un usuario con permisos administrativos, 
             * ya que si hacemos el bind con el usuario y ponemos bien la contraseña este campo badpwdcount se reestablece a 0 y nunca lazaremos
             * el error de usuario bloqueado  */
            if ($passwordRetryCount >= $user_max_attempts_allowed) {
               $message[] = 'Error E101 - Usuario bloqueado temporalmente por demasiados errores de autenticación.';
               goto Exit_Err;
            }
            if ($newPassword != $newPasswordCnf ) {
               $message[] = 'Error E102 - Las nuevas contraseñas no coinciden.';
               goto Exit_Err;
            }
            if (strlen($newPassword) < $password_min_length ) {
               $message[] = 'Error E103 - Su nueva contraseña es demasiado corta.';
               $message[] = 'Su contraseña debe tener al menos ' . $password_min_length . ' caracteres.';
               goto Exit_Err;
            }
            if (!preg_match('/[0-9]/', $newPassword)) {
               $message[] = 'Error E104 - Su nueva contraseña debe contener al menos un número.';
               goto Exit_Err;
            }else{
               ++$sumaTotalGrupo;
            }
            if (!preg_match('/[a-zA-Z]/', $newPassword)) {
               $message[] = 'Error E105 - Su nueva contraseña debe contener al menos una letra.';
	       goto Exit_Err;
            }else{
               if (!preg_match('/[A-Z]/', $newPassword)) {
                  $message[] = 'Error E106 - Su nueva contraseña debe contener al menos una letra mayúscula.';
                  goto Exit_Err;
               }else{
                  ++$sumaTotalGrupo;
              }
              if (!preg_match('/[a-z]/', $newPassword)) {
                 $message[] = 'Error E107 - Su nueva contraseña debe contener al menos una letra minúscula.';
                 goto Exit_Err;
              }else{
                 ++$sumaTotalGrupo;
              }
            }
            if ((!preg_match('/[^a-zA-Z0-9]/', $newPassword)) && ($sumaTotalGrupo<3)) {
               $message[] = 'Error E108 - Su nueva contraseña debe contener al menos un símbolo.';
               goto Exit_Err;
            }
            // Verificar si el nombre o apellido están contenidos en el password 
            $givenName = $user_get[0]['givenname'][0] ?? '';
            $sn = $user_get[0]['sn'][0] ?? '';

            // Combinar ambos atributos
            $fullName = $givenName . ' ' . $sn;

            // Separar en palabras (por espacios, comas o puntos)
            $particle = preg_split('/[\s,\.]+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);

            foreach ($particle as $word) {
               if (stripos($newPassword, $word) !== false) {
                   $message[] = 'Error E109 - No puede contener datos que aparezcan en el nombre de usuario.';
//                   $message[] = "Coincidencia parcial encontrada con: '$word' del nombre completo: '$fullName'";
//                   break; // Si solo quieres la primera coincidencia, puedes romper aquí
                   goto Exit_Err;
                }
            }
            if ((stripos($newPassword, $givenName) !== false) || (stripos($newPassword, $sn) !== false)) {
               $message[] = 'Error E109 - No puede contener datos que aparezcan en el nombre de usuario.';
               goto Exit_Err;
            }
            if (!$user_get) {
               $message[] = 'Error E200 - No se puede conectar al servidor, no puedes cambiar tu contraseña en este momento, lo sentimos.';
               goto Exit_Err;
            }

            // Obtenemos correos para el posterior aviso 
            $mail = (isset($user_get[0]['mail'])) ? $user_get[0]['mail'][0] : '';
            $recoveryMail = (isset($user_get[0]['othermailbox'])) ? $user_get[0]['othermailbox'][0] : '';

            //Modificaciones
            $modifs = array();
            $modifs['unicodePwd'] = encode_password($newPassword);

            // Ejecutar el reemplazo
            $result = ldap_mod_replace($ldap_conn, $user_dn, $modifs);
            if ($result) {
               $message[] = 'Contraseña cambiada con éxito.';
	       $message_success = 'yes';

               //Enviamos correo de aviso al mail principal y al de recuperación
               $address_name = $sn;
               $function_call = __FUNCTION__;
               if (!empty($mail)) send_mail($function_call, $mail, $address_name);
               if (!empty($recoveryMail) && $recoveryMail !== $mail) send_mail($function_call, $recoveryMail, $address_name);
            } else {
               $message[] = 'Error al cambiar la contraseña:';
               $errno = ldap_errno($ldap_conn);
               $error = ldap_error($ldap_conn);
               if (strpos($error, "Constraint violation") !== false) {
                  $message[] = 'Posiblemente la contraseña ya fue usada anteriormente o no cumple las políticas de seguridad de contraseña.';
	       }else{
                  $message[] = 'ldap_err_number: ' . $errno;
                  $message[] = 'ldap_err_description: ' . $error;
               }
            }
            //Limpiamos tabla attempts_login
            delete_attempts_login_fail(true);
	    $_SESSION['bloqueo_activo'] = false;
            ldap_unbind($ldap_conn);
         }else{
           $message[] = 'Usuario o contraseña (con permisos administrativos) incorrectos.';
           $message[] = add_attempts_login_fail();
         }
      }
   } else {
     $message[] = 'Por favor, completa todos los campos.';
   }
Exit_Err:
   // unset($msg); //La referencia a $value del último elemento del array permanece incluso después del bucle foreach. Se recomienda destruir estas referencias utilizando unset().
   $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
}
?>

