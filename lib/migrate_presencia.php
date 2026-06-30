<?php
/**
 * Script de migración única para convertir presencia.json al nuevo formato indexado.
 * Ejecuta este script desde el servidor o consola.
 */

require_once __DIR__ . '/../private/config.php';
define('PRESENCIA_JSON_PATH', __DIR__ . '/../data/presencia.json');

if (!file_exists(PRESENCIA_JSON_PATH)) {
    die("Error: El archivo presencia.json no existe en " . PRESENCIA_JSON_PATH . "\n");
}

$json_data = file_get_contents(PRESENCIA_JSON_PATH);
$old_data = json_decode($json_data, true);

// Si ya tiene el nuevo formato (es un objeto con 'users'), no hacemos nada
if (isset($old_data['users'])) {
    die("Información: El archivo ya se encuentra en el nuevo formato.\n");
}

$new_data = [
    'last_sync' => time(), // Marcamos como sincronizado ahora para evitar re-sincronización inmediata
    'users' => []
];

// Migramos los datos antiguos
if (is_array($old_data)) {
    foreach ($old_data as $row) {
        if (isset($row['trabajador_id'])) {
            $id = ltrim((string)$row['trabajador_id'], '0');
            $new_data['users'][$id] = [
                'estado' => $row['estado'] ?? 0,
                'fecha'  => $row['fecha'] ?? date('Y-m-d H:i:s')
            ];
        }
    }
}

// Guardamos el nuevo archivo
file_put_contents(PRESENCIA_JSON_PATH, json_encode($new_data, JSON_PRETTY_PRINT));

echo "Configuración: Migración completada con éxito. Usuarios migrados: " . count($new_data['users']) . "\n";
?>
