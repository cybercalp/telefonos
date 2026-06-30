<?php
// security_headers.php
// Send security-related HTTP headers
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; "
    . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
    . "img-src 'self' data: https:; "
    . "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com; "
    . "connect-src 'self'; "
    . "frame-ancestors 'self';");
// HSTS (only if HTTPS is used)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}
?>
