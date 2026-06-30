<?php
//Funciones para debug
require_once('./lib/debug_to_console.php');
//Funciones para acceso a BD
require_once('./lib/db_pendinguser_select.php');
require_once('./lib/db_pendinguser_del.php');

require_once(__DIR__ . '/../private/config.php');

// COMPRUEBA QUE EL TOKEN SIGUE SIENDO VÁLIDO
function check_token($token) {
   global $time_assignment;

   $message = array();
   $message_success = '';

   $usermail = '';
   $address_to = '';
   $tstamp = 0;

   if (!empty($token) && preg_match('/^[0-9A-F]{40}$/i', $token)) {
      // Comprobamos el token
      $row = select_pending_users($token, $address_to, $tstamp);
      if ($row) {
        $delta = $time_assignment; // measured in seconds
        // Check to see if link has expired
        if ($_SERVER["REQUEST_TIME"] - $tstamp > $delta) {
           $message[] = 'Ha superado el tiempo de uso de este enlace.';
        }else{
//           $message[] = 'Id Token correcto y dentro del tiempo.';
           // do one-time action here, like activating a user account
           $message_success = 'yes';
           $usermail = $address_to;
	   // El borrado del token se gestiona ahora en login.php
       // del_pending_users($token, $address_to, $tstamp);
        }
      } else {
         $message[] = 'Ningún token válido proporcionado.';
      }
   } else {
     $message[] = 'Ningún token válido proporcionado.';
   }
   foreach ($message as &$msg) {
    
   }
   unset($msg);
   $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
   return $usermail;
}
?>

