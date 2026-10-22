<?php
/**
 * Paramètres éditables du site (table site_settings).
 * Chargés une seule fois par requête, avec repli sur les valeurs par défaut.
 */

function settings_defaults(): array
{
    static $defaults = null;

    if ($defaults === null) {
        $schema = require __DIR__ . '/schema.php';
        $defaults = [];
        foreach ($schema['seeds']['site_settings']['rows'] as $row) {
            $defaults[$row[0]] = $row[1];
        }
    }

    return $defaults;
}

function settings_all(bool $refresh = false): array
{
    static $cache = null;

    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $cache = settings_defaults();

    try {
        $rows = db_fetch_all('SELECT setting_key, setting_value FROM site_settings');
        foreach ($rows as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable $e) {
        // Base indisponible : on garde les valeurs par défaut (aucune fuite).
        log_event('Lecture des paramètres impossible : ' . $e->getMessage(), 'warning');
    }

    return $cache;
}

function setting(string $key, ?string $default = null): ?string
{
    $all   = settings_all();
    $value = $all[$key] ?? null;

    // Une valeur vide ne doit jamais effacer un texte essentiel : on retombe
    // alors sur la valeur par défaut fournie par l'appelant.
    if (($value === null || $value === '') && $default !== null) {
        return $default;
    }

    return $value === null ? null : (string) $value;
}

function setting_set(string $key, string $value): void
{
    db_query(
        'INSERT INTO site_settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
        [$key, $value, now()->format('Y-m-d H:i:s')]
    );

    settings_all(true);
}
