<?php
// security_headers.php
// Send security-related HTTP headers
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
$csp_nonce = $_SESSION['csp_nonce'] ?? '';
$csp = "default-src 'self'; "
    . "script-src 'self' 'nonce-" . $csp_nonce . "' 'strict-dynamic' 'unsafe-eval' https://cdn.jsdelivr.net; "
    . "style-src 'self' 'nonce-" . $csp_nonce . "' https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
    . "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com; "
    . "img-src 'self' data: https:; "
    . "connect-src 'self'; "
    . "frame-ancestors 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self';";
header("Content-Security-Policy: " . $csp);
// HSTS (only if HTTPS is used)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}
?>
