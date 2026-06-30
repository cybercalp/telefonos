<?php
/**
 * lib/sync_presence.php
 * Script de sincronización de presencia desde la API de Saviacloud.
 * Se ejecuta en segundo plano cuando el Directorio de Teléfonos lo requiere.
 */

set_time_limit(300);

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/db_sqlite.php';

// Constante de log (puede usar la misma ruta que antes)
if (!defined('SYNC_DEBUG_LOG')) {
    define('SYNC_DEBUG_LOG', __DIR__ . '/../data/sync_debug.log');
}

/**
 * Log centralizado de depuración.
 */
function log_sync(string $msg): void {
    global $app_debug;
    if ($app_debug) {
        file_put_contents(SYNC_DEBUG_LOG, '[' . date('H:i:s') . '] [SYNC_PROCESS] ' . $msg . "\n", FILE_APPEND);
    }
}

log_sync('Iniciando ejecución del script de sincronización...');

if ($app_env !== 'production') {
    log_sync("Entorno actual: $app_env. Sincronización abortada (solo en producción).");
    exit;
}

// ─── 1. Obtener Token OAuth2 ───────────────────────────────────────────────

function getAccessToken(array $presence_config): ?string {
    if (empty($presence_config)) return null;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $presence_config['token_url'],
        CURLOPT_POST           => 1,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $presence_config['client_id'],
            'client_secret' => $presence_config['client_secret'],
            'scope'         => $presence_config['scope'],
        ]),
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) PHP/cURL',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    global $curl_ca_bundle;
    if (!empty($curl_ca_bundle)) {
        curl_setopt($ch, CURLOPT_CAINFO, $curl_ca_bundle);
    }

    $response  = curl_exec($ch);
    $err       = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) { log_sync("Error CURL Token: $err"); return null; }
    if ($http_code !== 200) { log_sync("Error HTTP Token: $http_code — $response"); return null; }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

// ─── 2. Sincronización ────────────────────────────────────────────────────

function syncPresence(array $presence_config): void {
    log_sync('Conectando con la API...');

    $token = getAccessToken($presence_config);
    if (!$token) { log_sync('Error: No se pudo obtener el token de acceso.'); return; }

    $token   = trim($token, " \t\n\r\0\x0B\"'");
    $sub_key = trim($presence_config['subscription_key'] ?? '', " \t\n\r\0\x0B\"'");

    log_sync('Token obtenido (L: ' . strlen($token) . '). SubKey (L: ' . strlen($sub_key) . ').');

    $pageSize = 20;
    $start    = 1;
    $allUsers = [];

    while (true) {
        $url = $presence_config['base_url']
             . "/saviatime/V1/EmpleadosTrabajando?Start=$start&Limit=$pageSize&subscription-key=$sub_key";

        log_sync("Consultando URL: $url");

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $token",
                "subscriptionkey: $sub_key",
                "Ocp-Apim-Subscription-Key: $sub_key",
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Microsoft Windows 10.0.19045; es-ES) PowerShell/5.1',
                'Accept: application/json',
            ],
            CURLINFO_HEADER_OUT    => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        global $curl_ca_bundle;
        if (!empty($curl_ca_bundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $curl_ca_bundle);
        }

        $response    = curl_exec($ch);
        $header_sent = curl_getinfo($ch, CURLINFO_HEADER_OUT);
        $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            log_sync("HEADERS ENVIADOS:\n$header_sent");
            log_sync("HTTP Error ($http_code) — Url: $url — Respuesta: $response");
            break;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_sync('Error JSON: ' . json_last_error_msg());
            break;
        }

        if (empty($data['Data']) || count($data['Data']) === 0) {
            log_sync('Final de datos alcanzado.');
            break;
        }

        log_sync("Procesando página (Start: $start)...");

        foreach ($data['Data'] as $usuario) {
            $code          = $usuario['Code'];
            $trabajador_id = (strlen($code) > 2) ? substr($code, 2) : $code;
            $trabajador_id = ltrim($trabajador_id, '0');

            $allUsers[$trabajador_id] = [
                'estado' => $usuario['IsWorking'] ? 1 : 0,
                'fecha'  => date('Y-m-d H:i:s'),
            ];
        }

        $start += $pageSize;
    }

    if (!empty($allUsers)) {
        try {
            $db = get_db();

            // Transacción atómica: borrar todo e insertar los nuevos datos
            $db->beginTransaction();

            $db->exec('DELETE FROM presencia');

            $stmt = $db->prepare(
                'INSERT INTO presencia (empleado_id, estado, fecha) VALUES (?, ?, ?)'
            );
            foreach ($allUsers as $id => $info) {
                $stmt->execute([$id, $info['estado'], $info['fecha']]);
            }

            // Actualizar marca de sincronización
            $db->prepare(
                "INSERT INTO presencia_meta (clave, valor) VALUES ('last_sync', ?)
                 ON CONFLICT(clave) DO UPDATE SET valor = excluded.valor"
            )->execute([(string)time()]);

            $db->commit();

            log_sync('Sincronización FINALIZADA con éxito. Usuarios total: ' . count($allUsers));

        } catch (\Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            log_sync('Error guardando en SQLite: ' . $e->getMessage());
        }
    } else {
        log_sync('No se han recibido usuarios de la API.');
    }
}

// Ejecutar
syncPresence($presence_config);
