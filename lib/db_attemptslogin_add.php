<?php
/**
 * lib/db_attemptslogin_add.php
 * Registra un intento de login fallido en SQLite.
 */

require_once __DIR__ . '/db_attemptslogin_select.php';
require_once __DIR__ . '/../private/config.php';

/**
 * Añade o incrementa un intento fallido para la IP actual.
 * Bloquea la IP cuando se supera el límite configurado.
 *
 * @return string Mensaje informativo para el usuario
 */
function add_attempts_login_fail(): string {
    global $limit_attempts, $block_time;

    $now       = time();
    $ip_client = $_SERVER['REMOTE_ADDR'];
    $attempts      = 0;
    $blocked_until = 0;

    select_attempts_login_fail($ip_client, $attempts, $blocked_until);

    $attempts_new      = $attempts + 1;
    $blocked_until_new = ($attempts_new >= $limit_attempts) ? ($now + (int)$block_time) : 0;

    try {
        $db = get_db();
        // UPSERT: inserta o actualiza si la IP ya existe
        $stmt = $db->prepare(
            'INSERT INTO attempts_login (ip, attempts, blocked_until)
             VALUES (?, ?, ?)
             ON CONFLICT(ip) DO UPDATE SET
                 attempts      = excluded.attempts,
                 blocked_until = excluded.blocked_until'
        );
        $stmt->execute([$ip_client, $attempts_new, $blocked_until_new]);
    } catch (\Exception $e) {
        error_log('db_attemptslogin_add error: ' . $e->getMessage());
    }

    if ($blocked_until_new) {
        $minutes = (int)ceil(($blocked_until_new - time()) / 60);
        $_SESSION['bloqueo_activo'] = true;
        return 'Has superado los ' . $limit_attempts . ' intentos. IP bloqueada por ' . $minutes . ' minuto(s)';
    }

    return 'Intentos restantes: ' . ((int)$limit_attempts - $attempts_new);
}
