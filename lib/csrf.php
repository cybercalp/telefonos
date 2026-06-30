<?php
/**
 * CSRF Protection Library
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generates a CSRF token if one doesn't exist
 * @return string The token
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies the provided token matches the session token
 * @param string $token The token to verify
 * @return bool True if valid
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Helper to get the CSRF token from the request (POST or Header)
 * @return string|null
 */
function get_token_from_request() {
    if (isset($_POST['csrf_token'])) {
        return $_POST['csrf_token'];
    }
    if (isset($_GET['csrf_token'])) {
        return $_GET['csrf_token'];
    }
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    return null;
}
?>
