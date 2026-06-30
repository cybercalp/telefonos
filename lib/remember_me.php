<?php
/**
 * lib/remember_me.php
 * Gestión de sesiones persistentes (Remember Me) sobre SQLite.
 */

require_once __DIR__ . '/db_sqlite.php';
require_once __DIR__ . '/../private/config.php';

/**
 * Establece una sesión persistente para el usuario.
 *
 * @param string   $username Nombre de usuario LDAP
 * @param int|null $days     Duración en días (null = valor de config)
 */
function set_remember_me(string $username, ?int $days = null): void {
    global $remember_me_days;

    if (empty($username)) return;

    if ($days === null) {
        $days = (int)$remember_me_days;
    }
    if ($days <= 0) return;

    $token   = bin2hex(random_bytes(32)); // 64 caracteres hex
    $expires = time() + ($days * 24 * 60 * 60);
    $now     = time();

    try {
        $db = get_db();

        // Borrar tokens anteriores del mismo usuario y tokens caducados globalmente
        $db->prepare('DELETE FROM remember_me WHERE username = ? OR expires < ?')
           ->execute([$username, $now]);

        // Insertar nuevo token
        $db->prepare(
            'INSERT INTO remember_me (token, username, expires, created) VALUES (?, ?, ?, ?)'
        )->execute([$token, $username, $expires, $now]);

    } catch (\Exception $e) {
        error_log('remember_me set error: ' . $e->getMessage());
        return;
    }

    // Establecer cookie (HttpOnly, Secure si HTTPS, SameSite=Lax)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || $_SERVER['SERVER_PORT'] == 443;

    setcookie('auth_remember', $token, $expires, '/', '', $secure, true);
}

/**
 * Verifica si existe una sesión persistente válida y restaura la sesión PHP.
 *
 * @return bool True si se restauró la sesión correctamente
 */
function check_remember_me(): bool {
    global $remember_me_days;

    if ((int)$remember_me_days <= 0) return false;
    if (!isset($_COOKIE['auth_remember'])) return false;

    $token = $_COOKIE['auth_remember'];

    try {
        $db   = get_db();
        $stmt = $db->prepare(
            'SELECT username, expires FROM remember_me WHERE token = ? AND expires > ?'
        );
        $stmt->execute([$token, time()]);
        $row = $stmt->fetch();

        if ($row) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['ldap_user']    = $row['username'];
            $_SESSION['2fa_verified'] = true;
            return true;
        }

        // Token no encontrado o expirado: limpiar cookie
        clear_remember_me();

    } catch (\Exception $e) {
        error_log('remember_me check error: ' . $e->getMessage());
    }

    return false;
}

/**
 * Elimina la sesión persistente actual (token de la cookie).
 */
function clear_remember_me(): void {
    if (!isset($_COOKIE['auth_remember'])) return;

    $token = $_COOKIE['auth_remember'];

    try {
        get_db()
            ->prepare('DELETE FROM remember_me WHERE token = ?')
            ->execute([$token]);
    } catch (\Exception $e) {
        error_log('remember_me clear error: ' . $e->getMessage());
    }

    // Invalidar cookie en el navegador
    setcookie('auth_remember', '', time() - 3600, '/');
}
