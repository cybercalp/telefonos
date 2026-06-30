<?php
// session_security.php
// Enforce secure session cookie settings and regenerate ID on login/reset
// Use strict mode
ini_set('session.use_strict_mode', 1);

// Set cookie params
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // only over HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Regenerate session ID after successful authentication (handled in login.php after login)
?>
