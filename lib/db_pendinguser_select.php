<?php
/**
 * lib/db_pendinguser_select.php
 * Consulta usuarios pendientes de activación desde SQLite.
 */

require_once __DIR__ . '/db_sqlite.php';

/**
 * Busca en SQLite un token de activación pendiente.
 *
 * @param string $token       Token de búsqueda
 * @param string &$address_to Email/usuario asociado (salida)
 * @param int    &$tstamp     Timestamp de creación (salida)
 * @return bool True si se encontró el token
 */
function select_pending_users(string $token, string &$address_to, int &$tstamp): bool {
    $address_to = '';
    $tstamp     = 0;

    try {
        $db   = get_db();
        $stmt = $db->prepare('SELECT username, tstamp FROM pending_users WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if ($row) {
            $address_to = $row['username'];
            $tstamp     = (int)$row['tstamp'];
            return true;
        }
    } catch (\Exception $e) {
        error_log('db_pendinguser_select error: ' . $e->getMessage());
    }

    return false;
}
