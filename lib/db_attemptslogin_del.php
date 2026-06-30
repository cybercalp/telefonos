<?php
/**
 * lib/db_attemptslogin_del.php
 * Elimina intentos de login fallidos de SQLite.
 */

require_once __DIR__ . '/db_attemptslogin_select.php';

/**
 * Elimina los intentos de login de la IP actual y, opcionalmente,
 * purga todos los registros con bloqueo ya caducado.
 *
 * @param bool $all Si true, limpia también todos los registros caducados
 */
function delete_attempts_login_fail(bool $all = false): void {
    $ip_client = $_SERVER['REMOTE_ADDR'];

    try {
        $db = get_db();

        // Eliminar la IP actual
        $db->prepare('DELETE FROM attempts_login WHERE ip = ?')
           ->execute([$ip_client]);

        if ($all) {
            // Limpiar todos los registros cuyo bloqueo ya ha caducado
            $db->prepare('DELETE FROM attempts_login WHERE blocked_until > 0 AND blocked_until <= ?')
               ->execute([time()]);
        }

    } catch (\Exception $e) {
        error_log('db_attemptslogin_del error: ' . $e->getMessage());
    }
}
