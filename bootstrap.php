<?php
/**
 * bootstrap.php
 * 
 * Inicialización centralizada de seguridad para todos los entry points.
 * 
 * Este archivo DEBE ser el primer require_once en cada página pública.
 * Carga configuración de sesión segura, headers de seguridad HTTP,
 * y la configuración privada del proyecto.
 */

// 0. Configuración de errores: nunca exponer al usuario, siempre loguear
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 1. Configuración de cookies de sesión (HttpOnly, Secure, SameSite=Lax, strict mode)
require_once __DIR__ . '/lib/session_security.php';

// 2. Generate CSP nonce BEFORE security headers (must exist when CSP header is sent)
$_SESSION['csp_nonce'] = bin2hex(random_bytes(16));
$csp_nonce = $_SESSION['csp_nonce'];

// 3. Headers de seguridad HTTP (CSP, X-Frame-Options, HSTS, etc.) — reads $csp_nonce
require_once __DIR__ . '/lib/security_headers.php';

// 4. Configuración privada (globales, LDAP, IP ranges, DB paths, etc.)
require_once __DIR__ . '/private/config.php';
