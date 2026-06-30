<?php
/**
 * lib/db_presencia_select.php
 * Consulta y sincronización de estados de presencia desde SQLite.
 */

require_once __DIR__ . '/db_sqlite.php';
require_once __DIR__ . '/../private/config.php';

/**
 * Verifica si es necesaria una sincronización y la dispara en background.
 * Solo se ejecuta una vez por petición HTTP (static flag).
 */
function check_sync_presence(): void {
    global $presence_sync_interval, $app_debug;
    static $already_checked = false;
    if ($already_checked) return;
    $already_checked = true;

    $last_sync = 0;

    try {
        $db   = get_db();
        $stmt = $db->prepare("SELECT valor FROM presencia_meta WHERE clave = 'last_sync'");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            $last_sync = (int)$row['valor'];
        }
    } catch (\Exception $e) {
        error_log('check_sync_presence error: ' . $e->getMessage());
    }

    $interval = (int)($presence_sync_interval ?? 300);

    if ((time() - $last_sync) > $interval) {
        $sync_script = __DIR__ . DIRECTORY_SEPARATOR . 'sync_presence.php';

        if ($app_debug) {
            $motivo = ($last_sync === 0) ? 'Base de datos sin datos' : 'Sincronización caducada';
            file_put_contents(
                __DIR__ . '/../data/sync_debug.log',
                '[' . date('H:i:s') . "] Disparando sincronización... (Motivo: $motivo)\n",
                FILE_APPEND
            );
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $php_path = 'C:\\xampp\\php\\php.exe';
            $cmd = 'start /B "" ' . escapeshellarg($php_path) . ' ' . escapeshellarg($sync_script) . ' > NUL 2>&1';
            shell_exec($cmd);
        } else {
            exec('php ' . escapeshellarg($sync_script) . ' > /dev/null 2>&1 &');
        }
    }
}

/**
 * Consulta el estado de presencia de un trabajador por su ID de empleado.
 *
 * @param  string $employeenumber ID del trabajador
 * @return int|null  1 si está trabajando, 0 si no, null si no se encuentra
 */
function user_in(string $employeenumber): ?int {
    static $presence_cache = null;

    if (empty($employeenumber)) return null;

    // Disparar sincronización si es necesario (solo una vez por petición)
    check_sync_presence();

    if ($presence_cache === null) {
        $presence_cache = [];
        try {
            $db   = get_db();
            $stmt = $db->query('SELECT empleado_id, estado FROM presencia');
            while ($row = $stmt->fetch()) {
                $presence_cache[$row['empleado_id']] = (int)$row['estado'];
            }
        } catch (\Exception $e) {
            error_log('user_in cache error: ' . $e->getMessage());
        }
    }

    $clean_id = ltrim((string)$employeenumber, '0');
    return $presence_cache[$clean_id] ?? null;
}

/**
 * Helper de compatibilidad con código antiguo.
 */
function clone_db_status(mixed $estado): mixed {
    return $estado;
}

// Disparar el chequeo automáticamente al cargar el archivo
check_sync_presence();
