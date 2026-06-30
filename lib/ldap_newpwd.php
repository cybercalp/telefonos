<?php
//Funciones para mostrar mensajes
require_once('./lib/ldap_encodepwd.php');

require_once(__DIR__ . '/../private/config.php');

if (!defined('DS')) {
   define('DS', '\\\\');
}

//Nombre de Ayuntamiento
$nameAyto = $config['medley']['nameAyto'];

// CREA UN PASSWORD TEMPORAL PARA EL USUARIO DADO
function create_new_pwd_for_user($recoveryUserMail) {
   global $ldap_protocol, $ldap_host, $ldap_port, $ldap_domain, $ldap_dn;
   global $ldap_admuser, $ldap_admpwd;

   $message = array();
   $message_success = '';

   // Parámetros de conexión LDAP
   $ldap_conn = ldap_connect(get_ldap_uri());

   if (!$ldap_conn) {
     $message[] = 'No se pudo conectar al servidor LDAP.';
   } else {
     // Configurar opciones
     ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3) or die ('Imposible asignar el Protocolo LDAP');
     ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

     // Autenticación
     if (ldap_bind($ldap_conn, $ldap_admuser, $ldap_admpwd)) {
       // Filtro para buscar usuario por nombre de cuenta (samAccountName)
       $filter_to_search = '(othermailbox=' . trim($recoveryUserMail) . ')';

       // Creo el filtro para la busqueda
       // objectClass=user -  asegura que el objeto sea de tipo usuario.
       // objectCategory=person - excluye objetos como grupos y equipos (esto es importante).
       // userAccountControl - Asegura que el objeto este activo (No tiene el bit de cuenta deshabilitada, es decir, está activo)
       $filter = '(&' . $filter_to_search . '(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';

       // Atributos que queremos recuperar
       $attrs = array('samaccountName', 'othermailbox');

       $user_search = ldap_search($ldap_conn, $ldap_dn, $filter, $attrs);
       if ($user_search) {
          $user_get = ldap_get_entries($ldap_conn, $user_search);
          if ($user_get['count'] > 0) {
//             $message[] = 'Datos del usuario encontrados para el mail: ' . $recoveryUserMail . ', usuario:' . $user_get[0]['samaccountname'][0];
             $user_entry = ldap_first_entry($ldap_conn, $user_search);
             $user_dn = ldap_get_dn($ldap_conn, $user_entry);

             $newPassword = generarContrasena();
 
             $modifs = array();
             $modifs['unicodePwd'] = encode_password($newPassword);

             // Ejecutar el reemplazo
             $result = ldap_mod_replace($ldap_conn, $user_dn, $modifs);
             if ($result) {
                $message[] = 'Contraseña cambiada con éxito.';
                $message_success = 'yes';
//                $message[] = '<a href="./change_pwd.php" class="submit">Continuar proceso de cambio de constrase&ntilde;a</a>';
                $_SESSION['username'] = $user_get[0]['samaccountname'][0];
                $_SESSION['password_just_reset'] = true;
             } else {
                $message[] = 'Error al cambiar la contraseña:';
                $error = ldap_error($ldap_conn);
                if (strpos($error, "Constraint violation") !== false) {
                   $message[] = 'Posiblemente la contraseña ya fue usada anteriormente o no cumple las políticas de seguridad de contraseña.';
                }else{
                   $message[] = $error;
                }
             }
          } else {
//             $message[] = 'Error recuperando las entradas de la búsqueda LDAP con el filtro dado.';
	     $message[] = 'No se han encontrado datos para: ' . $recoveryUserMail;
          }
       } else {
           $message[] = 'Error en la búsqueda LDAP con el filtro dado.';
           $message[] = 'Filtro: ' . $filter;
           $message[] = 'Base búsqueda: ' . $ldap_dn;
       }
       ldap_unbind($ldap_conn);
     }else{
        $message[] = 'Usuario o contraseña (con permisos administrativos) incorrectos.';
     }
   }
   foreach ($message as &$msg) {
      
   }
   unset($msg);
   $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
}
// FUNCIÓN QUE GENERA UNA CONTRASEÑA DE LONGITUD MÍNIMA DE 12 CARACTERES
function generarContrasena($longitud = 12) {
    $mayusculas = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $minusculas = 'abcdefghijklmnopqrstuvwxyz';
    $numeros    = '0123456789';
    $simbolos   = '!@#$%^&*()-_=+[]{}<>?';

    // Asegurar al menos un carácter de cada tipo
    $contrasena = '';
    $contrasena .= $mayusculas[random_int(0, strlen($mayusculas) - 1)];
    $contrasena .= $minusculas[random_int(0, strlen($minusculas) - 1)];
    $contrasena .= $numeros[random_int(0, strlen($numeros) - 1)];
    $contrasena .= $simbolos[random_int(0, strlen($simbolos) - 1)];

    // Mezclar el resto con todos los caracteres
    $todos = $mayusculas . $minusculas . $numeros . $simbolos;
    for ($i = 4; $i < $longitud; $i++) {
        $contrasena .= $todos[random_int(0, strlen($todos) - 1)];
    }

    // Mezclar los caracteres para que no estén siempre en el mismo orden
    $contrasena = str_shuffle($contrasena);

    return $contrasena;
}
?>

