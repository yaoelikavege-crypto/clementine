<?php
/**
 * Connexion PDO unique à MySQL / MariaDB.
 * Toutes les requêtes de l'application passent par des requêtes préparées.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = (array) config('db', []);

    $host    = (string) ($cfg['host'] ?? '127.0.0.1');
    $port    = (int) ($cfg['port'] ?? 3306);
    $name    = (string) ($cfg['name'] ?? '');
    $user    = (string) ($cfg['user'] ?? '');
    $pass    = (string) ($cfg['pass'] ?? '');
    $charset = (string) ($cfg['charset'] ?? 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}

/**
 * Exécute une requête préparée.
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

function db_fetch(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();

    return $row === false ? null : $row;
}

function db_fetch_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function db_value(string $sql, array $params = [])
{
    $value = db_query($sql, $params)->fetchColumn();

    return $value === false ? null : $value;
}

function db_count(string $table, string $where = '', array $params = []): int
{
    $allowed = ['admins', 'media', 'memories', 'playlists', 'playlist_tracks', 'love_letter', 'wishes', 'countdown_messages', 'login_attempts', 'audit_log', 'site_settings', 'visitor_credentials'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Table non autorisée.');
    }

    $sql = 'SELECT COUNT(*) FROM `' . $table . '`';
    if ($where !== '') {
        $sql .= ' WHERE ' . $where;
    }

    return (int) db_value($sql, $params);
}

/**
 * Vérifie que la base est joignable sans faire échouer l'application.
 */
function db_ping(): bool
{
    try {
        db()->query('SELECT 1');

        return true;
    } catch (Throwable $e) {
        return false;
    }
}
