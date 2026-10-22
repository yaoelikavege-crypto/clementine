<?php
/**
 * Génère sql/install.sql à partir de includes/schema.php.
 * Utilisation en ligne de commande uniquement :
 *     php tools/build_sql.php
 *
 * Le fichier généré peut être importé dans phpMyAdmin (InfinityFree)
 * pour une installation manuelle, sans passer par l'assistant.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ce script s\'exécute uniquement en ligne de commande.');
}

$schema = require __DIR__ . '/../includes/schema.php';

function sql_value($value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    $escaped = str_replace(['\\', "'"], ['\\\\', "''"], (string) $value);

    return "'" . $escaped . "'";
}

$lines = [];
$lines[] = '-- =============================================================================';
$lines[] = '--  Clémentine · 21 ans — structure MySQL / MariaDB';
$lines[] = '--  Généré automatiquement depuis includes/schema.php';
$lines[] = '--  Date de génération : ' . date('Y-m-d H:i:s');
$lines[] = '--';
$lines[] = '--  IMPORTANT :';
$lines[] = '--   - Importer dans la base créée sur InfinityFree (phpMyAdmin).';
$lines[] = '--   - Aucun compte administrateur n\'est créé par ce script : utilisez';
$lines[] = '--     /setup/index.php ou créez les comptes via l\'assistant.';
$lines[] = '--   - Ne jamais publier ce fichier après import (SQL/.htaccess le protège).';
$lines[] = '-- =============================================================================';
$lines[] = '';
$lines[] = 'SET NAMES utf8mb4;';
$lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
$lines[] = '';

foreach ($schema['tables'] as $table => $sql) {
    $lines[] = '-- -----------------------------------------------------------------------------';
    $lines[] = '-- Table : ' . $table;
    $lines[] = '-- -----------------------------------------------------------------------------';
    $lines[] = rtrim($sql, ';') . ';';
    $lines[] = '';
}

$lines[] = '-- =============================================================================';
$lines[] = '--  Données initiales';
$lines[] = '-- =============================================================================';
$lines[] = '';

foreach ($schema['seeds'] as $table => $seed) {
    $columns = $seed['columns'];
    $columnSql = '`' . implode('`, `', $columns) . '`';
    $rows = $seed['rows'];

    $lines[] = '-- ' . $table;

    if (!empty($seed['only_if_empty'])) {
        $lines[] = 'SET @seed_' . $table . ' := (SELECT COUNT(*) = 0 FROM `' . $table . '`);';
    }

    foreach ($rows as $row) {
        $values = array_map('sql_value', array_values($row));
        $verb = !empty($seed['unique']) ? 'INSERT IGNORE INTO' : 'INSERT INTO';

        if (!empty($seed['only_if_empty'])) {
            $lines[] = $verb . ' `' . $table . '` (' . $columnSql . ') SELECT ' . implode(', ', $values) . ' WHERE @seed_' . $table . ';';
        } else {
            $lines[] = $verb . ' `' . $table . '` (' . $columnSql . ') VALUES (' . implode(', ', $values) . ');';
        }
    }

    $lines[] = '';
}

$lines[] = '-- Fin du script.';
$lines[] = '';

$target = __DIR__ . '/../sql/install.sql';
$content = implode(PHP_EOL, $lines);

if (file_put_contents($target, $content) === false) {
    fwrite(STDERR, "Impossible d'écrire " . $target . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Fichier généré : ' . realpath($target) . ' (' . strlen($content) . ' octets)' . PHP_EOL);
