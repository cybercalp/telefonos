<?php
/**
 * lib/db_attemptslogin_select.php
 * Consulta los intentos de login fallidos desde SQLite.
 */

require_once __DIR__ . '/db_sqlite.php';

/**
 * Busca en SQLite los intentos de login fallidos para una IP.
 *
 * @param string $ip_client       IP del cliente
 * @param int    &$attempts       Número de intentos (salida)
 * @param int    &$blocked_until  Timestamp de bloqueo (salida)
 * @return bool True si se encontró la IP
 */
function select_attempts_login_fail(string $ip_client, int &$attempts, int &$blocked_until): bool {
    $attempts      = 0;
    $blocked_until = 0;

    try {
        $db   = get_db();
        $stmt = $db->prepare('SELECT attempts, blocked_until FROM attempts_login WHERE ip = ?');
        $stmt->execute([$ip_client]);
        $row = $stmt->fetch();

        if ($row) {
            $attempts      = (int)$row['attempts'];
            $blocked_until = (int)$row['blocked_until'];
            return true;
        }
    } catch (\Exception $e) {
        error_log('db_attemptslogin_select error: ' . $e->getMessage());
    }

    return false;
}
