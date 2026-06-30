<?php
/**
 * lib/db_pendinguser_del.php
 * Elimina un usuario pendiente de activación de SQLite.
 */

require_once __DIR__ . '/db_pendinguser_select.php';

/**
 * Borra un token de activación. Si se proporcionan $address_to y/o $tstamp,
 * solo borra si coinciden (verificación adicional).
 *
 * @param string      $token      Token a eliminar
 * @param string|null $address_to Email para verificación opcional
 * @param int|null    $tstamp     Timestamp para verificación opcional
 */
function del_pending_users(string $token, ?string $address_to = null, ?int $tstamp = null): void {
    try {
        $db = get_db();

        if ($address_to !== null && $tstamp !== null) {
            $db->prepare(
                'DELETE FROM pending_users WHERE token = ? AND username = ? AND tstamp = ?'
            )->execute([$token, $address_to, $tstamp]);
        } elseif ($address_to !== null) {
            $db->prepare(
                'DELETE FROM pending_users WHERE token = ? AND username = ?'
            )->execute([$token, $address_to]);
        } else {
            $db->prepare(
                'DELETE FROM pending_users WHERE token = ?'
            )->execute([$token]);
        }
    } catch (\Exception $e) {
        error_log('db_pendinguser_del error: ' . $e->getMessage());
    }
}
