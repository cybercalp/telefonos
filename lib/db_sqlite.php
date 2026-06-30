<?php
/**
 * lib/db_sqlite.php
 * Capa central de acceso a SQLite. Gestiona la conexión PDO (singleton)
 * y crea el esquema de tablas si no existe.
 *
 * Uso: $db = get_db();
 */

if (!defined('SQLITE_DB_PATH')) {
    define('SQLITE_DB_PATH', __DIR__ . '/../data/telefonos.db');
}

/**
 * Devuelve la instancia PDO compartida de SQLite.
 * Crea la base de datos y el esquema en la primera llamada.
 *
 * @return PDO
 * @throws RuntimeException si no se puede abrir la BD
 */
function get_db(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // Asegurar que el directorio existe
    $dir = dirname(SQLITE_DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    try {
        $pdo = new PDO('sqlite:' . SQLITE_DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Pragmas de rendimiento y seguridad
        $pdo->exec('PRAGMA journal_mode = WAL');   // Escrituras concurrentes sin bloquear lecturas
        $pdo->exec('PRAGMA synchronous  = NORMAL'); // Equilibrio seguridad/velocidad
        $pdo->exec('PRAGMA foreign_keys = ON');

        // Crear esquema si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS attempts_login (
                ip            TEXT    PRIMARY KEY,
                attempts      INTEGER NOT NULL DEFAULT 1,
                blocked_until INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS pending_users (
                token    TEXT    PRIMARY KEY,
                username TEXT    NOT NULL,
                tstamp   INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS remember_me (
                token    TEXT    PRIMARY KEY,
                username TEXT    NOT NULL,
                expires  INTEGER NOT NULL,
                created  INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS presencia (
                empleado_id TEXT PRIMARY KEY,
                estado      INTEGER NOT NULL DEFAULT 0,
                fecha       TEXT    NOT NULL
            );

            CREATE TABLE IF NOT EXISTS presencia_meta (
                clave TEXT PRIMARY KEY,
                valor TEXT NOT NULL
            );
        ");

    } catch (PDOException $e) {
        error_log('SQLite error: ' . $e->getMessage());
        throw new RuntimeException('No se pudo abrir la base de datos local.');
    }

    return $pdo;
}
