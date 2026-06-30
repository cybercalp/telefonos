<?php
//Funciones para la consulta de datos de la tabla attempts_login
require_once('./lib/db_attemptslogin_select.php');

/**
 * Valida el token CSRF en POST.
 * No llama a header() ni exit() — devuelve true/false para ser testeable.
 * En caso de fallo, invalida el token y prepara el mensaje de sesión.
 *
 * @param string $tokenEnviado Token recibido del formulario
 * @param string $tokenSesion  Token almacenado en sesión
 * @return bool True si el token es válido
 */
function validate_csrf_post($tokenEnviado, $tokenSesion) {
    if (!hash_equals($tokenSesion, $tokenEnviado)) {
        unset($_SESSION['csrf_token']);
        $_SESSION['csrf_token_ok'] = false;
        $_SESSION['mensaje'] = array('Token CSRF inválido o formulario reenviado.');
        $_SESSION['mensaje_css'] = '';
        return false;
    }
    return true;
}

$ip_client = $_SERVER['REMOTE_ADDR'];
$attempts = 0;
$blocked_until = 0;
$now = time(); //timestamp actual

$tiempo_restante = 0;

// Prevenir acceso sin POST válido (mantiene comportamiento original con header/exit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEnviado = $_POST['csrf_token'] ?? '';
    $tokenSesion = $_SESSION['csrf_token'] ?? '';

    if (!validate_csrf_post($tokenEnviado, $tokenSesion)) {
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Comprobar si la IP está bloqueada
$row = select_attempts_login_fail($ip_client, $attempts, $blocked_until);
//Hay datos en la tabla
if ($row) {
   //Calculamos si hay bloqueo y el tiempo restante para el mensaje de cuenta regresiva
   $tiempo_restante = ($_SESSION['bloqueo_activo'] === true) ? ($blocked_until - $now) : 0;
   //Esta ip ha sido baneada, pero ya ha expirado el tiempo de bloqueo (reiniciamos)
   if (($blocked_until <= $now) && ($blocked_until != 0)) {
      // El bloqueo expiró --> reiniciar contador
      delete_attempts_login_fail();
      $_SESSION['bloqueo_activo'] = false;
      $tiempo_restante = 0;
   }
 }
?>
