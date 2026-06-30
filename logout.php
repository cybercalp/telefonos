<?php
/**
 * Destruye la sesión actual y redirige al inicio
 */

// Bootstrap: session security + HTTP security headers + config
require_once __DIR__ . '/bootstrap.php';

require_once(__DIR__ . '/lib/remember_me.php');
clear_remember_me();

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la sesión completamente, borre también la cookie de sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();

// Redirigir al inicio
header("Location: ./index.php");
exit;
?>
