<?php
/**
 * lib/db_pendinguser_add.php
 * Inserta un usuario pendiente de activación en SQLite.
 */

require_once __DIR__ . '/db_pendinguser_select.php';

/**
 * Inserta o reemplaza un token de activación en la tabla pending_users.
 *
 * @param string $token      Token único de activación
 * @param string $address_to Email o nombre de usuario
 * @param int    $tstamp     Timestamp de creación
 */
function insert_pending_users(string $token, string $address_to, int $tstamp): void {
    try {
        $db = get_db();
        $db->prepare(
            'INSERT INTO pending_users (token, username, tstamp)
             VALUES (?, ?, ?)
             ON CONFLICT(token) DO UPDATE SET
                 username = excluded.username,
                 tstamp   = excluded.tstamp'
        )->execute([$token, $address_to, $tstamp]);
    } catch (\Exception $e) {
        error_log('db_pendinguser_add error: ' . $e->getMessage());
    }
}
